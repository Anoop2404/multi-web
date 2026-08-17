<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\BoardResult;
use App\Models\BoardResultUpload;
use App\Models\DataChangeLog;
use App\Models\Topper;
use App\Services\Audit\DataChangeLogger;
use App\Services\BoardResults\BoardResultAcademicYearService;
use App\Services\BoardResults\BoardResultMarksConfigService;
use App\Services\BoardResults\BoardResultNotifier;
use App\Services\BoardResults\SubjectStatsNormalizer;
use App\Services\BoardResults\TopperCountService;
use App\Services\BoardResults\TopperSubjectMarkService;
use App\Support\BoardExamSubjects;
use App\Support\PersistDefaults;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BoardResultController extends SchoolAdminController
{
    public function reports(Request $request)
    {
        $class = $request->filled('class') ? $request->integer('class') : null;
        $academicYear = $request->string('academic_year')->toString() ?: null;
        $yearService = app(BoardResultAcademicYearService::class);
        $academicYearOptions = $yearService->activeOrPopulatedYearOptions((string) $this->school->parent_id);

        if (!$academicYear) {
            $configuredOpenYear = collect($academicYearOptions)
                ->first(fn (array $year) => $year['entry_configured'] && $year['entry_status'] === 'open');
            $openYear = $configuredOpenYear ?? collect($academicYearOptions)->firstWhere('entry_status', 'open');
            $academicYear = $openYear['label'] ?? ((date('Y') - 1).'-'.substr((string) date('Y'), 2));
        }

        $results = BoardResult::where('tenant_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->with(['toppers.subjectMarks', 'toppers.examStream'])
            ->get();

        $activeResult = $class ? $results->firstWhere('class', $class) : $results->first();

        return $this->inertia('School/BoardResults/Reports', [
            'results' => $results,
            'academicYearOptions' => $academicYearOptions,
            'selectedClass' => $class ?? ($activeResult ? $activeResult->class : 10),
            'selectedAcademicYear' => $academicYear,
            'activeResult' => $activeResult,
            'school' => $this->school,
        ]);
    }

    public function summaryPdf(Request $request)
    {
        $class = $request->integer('class') ?: 10;
        $academicYear = $request->string('academic_year')->toString() ?: date('Y').'-'.substr(date('Y') + 1, 2);

        $result = BoardResult::where('tenant_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->where('class', $class)
            ->firstOrFail();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('board-results.pdf.school-summary', [
            'result'        => $result,
            'academicYear'  => $academicYear,
            'selectedClass' => $class,
            'school'        => $this->school,
            'logoSrc'       => \App\Support\TenantBranding::logoEmbedSrc($this->school),
            'generatedAt'   => now()->format('d M Y · h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("school-summary-class-{$class}-{$academicYear}.pdf");
    }

    public function toppersPdf(Request $request)
    {
        $class = $request->integer('class') ?: 10;
        $academicYear = $request->string('academic_year')->toString() ?: date('Y').'-'.substr(date('Y') + 1, 2);

        $result = BoardResult::where('tenant_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->where('class', $class)
            ->with(['toppers.subjectMarks', 'toppers.examStream'])
            ->firstOrFail();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('board-results.pdf.school-toppers', [
            'result'        => $result,
            'academicYear'  => $academicYear,
            'selectedClass' => $class,
            'school'        => $this->school,
            'logoSrc'       => \App\Support\TenantBranding::logoEmbedSrc($this->school),
            'generatedAt'   => now()->format('d M Y · h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("school-toppers-class-{$class}-{$academicYear}.pdf");
    }

    public function index(Request $request)
    {
        $class = $request->filled('class') ? $request->integer('class') : null;
        abort_if($class !== null && ! in_array($class, [10, 12], true), 404);
        $yearService = app(BoardResultAcademicYearService::class);

        $results = BoardResult::where('tenant_id', $this->school->id)
            ->when($class, fn ($q) => $q->where('class', $class))
            ->with([
                'toppers' => fn ($q) => $q->overallEntries()->with(['subjectMarks', 'examStream']),
                'uploads' => fn ($q) => $q->orderByDesc('version')->limit(5),
            ])
            ->orderByDesc('academic_year')
            ->orderByDesc('class')
            ->get();

        $auditHistory = DataChangeLog::query()
            ->where('school_id', $this->school->id)
            ->whereIn('log_name', ['board_result', 'topper', 'achievement'])
            ->latest()
            ->limit(75)
            ->get(['id', 'action', 'description', 'log_name', 'subject_type', 'subject_id', 'changes', 'created_at', 'causer_user_id']);

        $academicYearOptions = $yearService->activeOrPopulatedYearOptions((string) ($this->school->parent_id ?: $this->school->id));
        $academicYear = $request->string('academic_year')->toString() ?: null;

        if (! $academicYear) {
            $configuredOpenYear = collect($academicYearOptions)
                ->first(fn (array $year) => ($year['entry_configured'] ?? false) && ($year['entry_status'] ?? '') === 'open');
            $openYear = $configuredOpenYear ?? collect($academicYearOptions)->firstWhere('entry_status', 'open');
            $academicYear = $openYear['label'] ?? ($results->first()?->academic_year ?? ((date('Y') - 1).'-'.substr((string) date('Y'), 2)));
        }

        $activeResult = $class
            ? $results->first(fn (BoardResult $r) => $r->academic_year === $academicYear)
            : null;

        return $this->inertia('School/BoardResults/Workspace', array_merge([
            'activeTab' => 'overview',
            'results' => $results,
            'academicYearOptions' => $academicYearOptions,
            'statuses' => [
                BoardResult::STATUS_DRAFT,
                BoardResult::STATUS_SUBMITTED,
                BoardResult::STATUS_VERIFIED,
                BoardResult::STATUS_APPROVED,
                BoardResult::STATUS_REJECTED,
                BoardResult::STATUS_PUBLISHED,
            ],
            'auditHistory' => $auditHistory,
            // Was hardcoded to class 10 regardless of which class the admin is viewing/editing,
            // so Class XII entry silently inherited Class X's (often lower) topper quota.
            // The per-result activeResultContext.topperCap below is authoritative once a
            // result is selected; this top-level value is just the page-load fallback.
            'topperCap' => app(TopperCountService::class)->resolveCap(
                (string) $this->school->parent_id,
                $class ?? 10
            ),
            'selectedClass' => $class,
            'selectedAcademicYear' => $academicYear,
            'streamOptions' => BoardExamSubjects::class12StreamLabels((string) $this->school->parent_id),
            'marksConfig' => $this->marksConfigFor(
                $class ?? 10,
                BoardExamSubjects::class12StreamLabels((string) $this->school->parent_id),
                (string) $this->school->parent_id
            ),
            'activeResult' => $activeResult,
        ], $activeResult ? ['activeResultContext' => $this->topperContext($activeResult)] : []));
    }

    public function rankReport(Request $request)
    {
        $class = $request->filled('class') ? $request->integer('class') : 12;
        abort_if(! in_array($class, [10, 12], true), 404);

        $academicYear = $request->string('academic_year')->trim()->toString();
        abort_if($academicYear === '', 422, 'academic_year is required.');

        $result = BoardResult::with(['toppers' => function ($q) {
            $q->with(['subjectMarks', 'examStream'])->orderBy('rank');
        }])
            ->where('tenant_id', $this->school->id)
            ->where('class', $class)
            ->where('academic_year', $academicYear)
            ->first();

        abort_if($result === null, 404, 'No board result found for the given class and academic year.');

        return view('school.board-results.rank-report', [
            'school'       => $this->school,
            'result'       => $result,
            'toppers'      => $result->toppers
                ->where('entry_type', Topper::ENTRY_OVERALL)
                ->values(),
            'academicYear' => $academicYear,
            'class'        => $class,
            'isClass12'    => $class === 12,
            'subjectWiseLeaders' => BoardExamSubjects::subjectWiseLeaders($result->toppers),
        ]);
    }

    public function store(Request $request)
    {
        $yearService = app(BoardResultAcademicYearService::class);
        $data = $yearService->attachToPayload($this->validateBoardResult($request));
        $topperRows = $this->validateTopperRows($request);

        // Total marks is admin-locked now (BoardResultMarksConfigService), not typed by the
        // school. Class X has one Sahodaya-wide value; Class XII varies per-topper by stream,
        // so the aggregate-level column is left null and each Topper carries its own total.
        $data['total_marks'] = (int) $data['class'] === 10
            ? app(BoardResultMarksConfigService::class)->resolve((string) $this->school->parent_id, 10, null)
            : null;

        $data['tenant_id'] = $this->school->id;
        $data['examination_type'] = $data['examination_type']
            ?? BoardResult::examinationTypeForClass((int) $data['class']);
        $data = PersistDefaults::coalesce($data, [
            'total_appeared' => 0,
            'pass_count' => 0,
            'pass_percent' => 0,
            'distinctions' => 0,
            'first_class' => 0,
            'status' => BoardResult::STATUS_DRAFT,
        ]);

        $keys = [
            'tenant_id' => $this->school->id,
            'class' => $data['class'],
            'examination_type' => $data['examination_type'],
            'academic_year' => $data['academic_year'],
        ];

        // Lock the result row to prevent race conditions on status check and creation.
        $result = DB::transaction(function () use ($keys, $data, $request, $yearService, $topperRows) {
            $existing = BoardResult::query()->where($keys)->lockForUpdate()->first();
            if ($existing && ! $existing->isEditable()) {
                throw ValidationException::withMessages([
                    'academic_year' => 'This result is locked ('.$existing->status.'). Wait for rejection before editing.',
                ]);
            }
            if ($existing) {
                $yearService->assertResultEditable($existing);
            }

            $payload = collect($data)->except(['result_pdf', 'attachments', 'toppers'])->all();
            if ($existing?->status === BoardResult::STATUS_REJECTED) {
                $payload['status'] = BoardResult::STATUS_DRAFT;
                $payload['rejection_reason'] = null;
            }

            $result = BoardResult::updateOrCreate($keys, $payload);

            $this->storeUploads($request, $result);

            $addedCount = $this->createToppersFromRows($request, $result, $topperRows);

            app(DataChangeLogger::class)->event(
                $existing ? 'updated' : 'created',
                $existing ? 'Board result updated' : 'Board result created',
                $this->school->id,
                'board_result',
                $result,
                ['class' => $result->class, 'academic_year' => $result->academic_year],
            );

            return ['result' => $result, 'addedCount' => $addedCount];
        });

        $fresh = $result['result']->fresh();
        if ($request->boolean('submit_for_review')) {
            if (! $fresh->hasResultPdf()) {
                throw ValidationException::withMessages([
                    'result_pdf' => 'Upload the proof document before submitting for verification.',
                ]);
            }
            $this->performSubmit($request, $fresh);
            $msg = 'Board result saved and submitted for Sahodaya verification.';
        } else {
            $msg = 'Board result saved.'.($result['addedCount'] ? " {$result['addedCount']} topper(s) added." : '');
        }

        return redirect("/school-admin/{$this->school->id}/board-results?class={$result['result']->class}&academic_year=".urlencode($result['result']->academic_year))
            ->with('success', $msg);
    }

    public function update(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'This result cannot be edited in its current state.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        $before = $boardResult->only([
            'class', 'examination_type', 'academic_year', 'total_appeared', 'pass_count',
            'pass_percent', 'distinctions', 'first_class', 'highest_mark', 'average_mark', 'remarks',
        ]);

        $data = app(BoardResultAcademicYearService::class)->attachToPayload(
            $this->validateBoardResult($request, $boardResult)
        );
        $topperRows = $this->validateTopperRows($request);

        // See store() — total marks is admin-locked, not school-editable.
        $data['total_marks'] = (int) $data['class'] === 10
            ? app(BoardResultMarksConfigService::class)->resolve((string) $this->school->parent_id, 10, null)
            : null;

        $data['examination_type'] = $data['examination_type']
            ?? BoardResult::examinationTypeForClass((int) $data['class']);
        $data = PersistDefaults::coalesce($data, [
            'total_appeared' => 0,
            'pass_count' => 0,
            'pass_percent' => 0,
        ]);

        if ($boardResult->status === BoardResult::STATUS_REJECTED) {
            $data['status'] = BoardResult::STATUS_DRAFT;
            $data['rejection_reason'] = null;
        }

        DB::transaction(function () use ($boardResult, $data, $request, $topperRows, $before) {
            BoardResult::query()->whereKey($boardResult->id)->lockForUpdate()->first();
            $boardResult->update(collect($data)->except(['result_pdf', 'attachments', 'toppers'])->all());
            $this->storeUploads($request, $boardResult->fresh());
            $addedCount = $this->createToppersFromRows($request, $boardResult->fresh(), $topperRows);

            app(DataChangeLogger::class)->updated(
                $boardResult,
                'Board result updated',
                DataChangeLogger::diff($before, $boardResult->only(array_keys($before))),
                $this->school->id,
                'board_result',
            );
        });

        $fresh = $boardResult->fresh();

        try {
            app(\App\Services\BoardResults\BoardResultCertificationService::class)
                ->invalidateForDataChange($fresh, $request->user(), 'Result summary or toppers updated by the school.');
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['result' => [$e->getMessage()]]);
        }

        if ($request->boolean('submit_for_review')) {
            $activePackage = $fresh->activeCertificationPackage();
            if ($activePackage && $activePackage->status !== \App\Models\BoardResultCertificationPackage::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'result' => 'This result is already going through Principal Verification. Use that workflow to certify and submit it.',
                ]);
            }
            if (app(BoardResultAcademicYearService::class)->isCertificationRequired($fresh)) {
                throw ValidationException::withMessages([
                    'result' => 'Your Sahodaya requires Principal Verification for this academic year. Use "Send for Leadership Review" instead of direct submission.',
                ]);
            }
            if (! $fresh->hasResultPdf()) {
                throw ValidationException::withMessages([
                    'result_pdf' => 'Upload the proof document before submitting for verification.',
                ]);
            }
            $this->performSubmit($request, $fresh);
            $msg = 'Board result updated and submitted for Sahodaya verification.';
        } else {
            $msg = 'Board result updated.';
        }

        return back()->with('success', $msg);
    }

    /**
     * Optional topper rows submitted alongside the aggregate form. Fully blank rows are
     * skipped; a row must have both a name and marks to count.
     *
     * @return list<array<string, mixed>> keyed by original request index (needed to match file uploads)
     */
    private function validateTopperRows(Request $request): array
    {
        $data = $request->validate([
            'toppers' => 'nullable|array',
            'toppers.*.name' => 'nullable|string|max:255',
            'toppers.*.stream' => 'nullable|string|max:100',
            'toppers.*.stream_key' => 'nullable|string|max:50',
            'toppers.*.roll_no' => 'nullable|string|max:64',
            // Nullable here so a blank placeholder row (the default empty row every form
            // starts with) doesn't fail validation on its own — gender is only required
            // below, for rows the user actually filled in (name + marks present).
            'toppers.*.gender' => 'nullable|string|in:male,female,other',
            'toppers.*.admission_no' => 'nullable|string|max:64',
            'toppers.*.marks_obtained' => 'nullable|numeric|min:0',
            'toppers.*.marksheet' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);

        $rows = collect($data['toppers'] ?? [])
            ->filter(fn ($row) => filled($row['name'] ?? null) || filled($row['marks_obtained'] ?? null));

        foreach ($rows as $i => $row) {
            if (blank($row['name'] ?? null) || blank($row['marks_obtained'] ?? null)) {
                throw ValidationException::withMessages([
                    "toppers.{$i}" => 'Each topper row needs both a name and marks scored (or leave the row fully blank to skip it).',
                ]);
            }
            if (blank($row['gender'] ?? null)) {
                throw ValidationException::withMessages([
                    "toppers.{$i}.gender" => 'Select a gender for each topper row.',
                ]);
            }
        }

        // Check for duplicate roll_no WITHIN the submitted rows.
        $rollNos = $rows->pluck('roll_no')->filter(fn ($v) => filled($v))->values();
        if ($rollNos->count() !== $rollNos->unique()->count()) {
            $dupes = $rollNos->duplicates()->unique()->values()->implode(', ');
            throw ValidationException::withMessages([
                'toppers' => "Duplicate CBSE Roll No(s) in the form for {$this->school->name}: {$dupes}. Each roll number must be unique.",
            ]);
        }

        return $rows->all();
    }

    /**
     * Find the existing topper (from a collection) that a form row matches, using
     * topper_id → roll_no → name as priority order. Returns null when no match.
     *
     * @param  \Illuminate\Support\Collection<int, Topper>|list<Topper>  $toppers
     * @param  array<string, mixed>  $row
     */
    private function matchTopper($toppers, array $row): ?Topper
    {
        $topperId = filled($row['topper_id'] ?? null) ? (int) $row['topper_id'] : null;
        $rollNo = filled($row['roll_no'] ?? null) ? trim((string) $row['roll_no']) : null;
        $name = filled($row['name'] ?? null) ? trim((string) $row['name']) : null;

        $collection = $toppers instanceof \Illuminate\Support\Collection ? $toppers : new \Illuminate\Support\Collection($toppers);

        if ($topperId) {
            $matched = $collection->firstWhere('id', $topperId);
            if ($matched) {
                return $matched;
            }
        }

        if ($rollNo) {
            $matched = $collection->first(fn (Topper $t) => filled($t->roll_no) && trim((string) $t->roll_no) === $rollNo);
            if ($matched) {
                return $matched;
            }
        }

        if ($name) {
            $matched = $collection->first(fn (Topper $t) => filled($t->name) && trim(strtolower((string) $t->name)) === strtolower($name));
            if ($matched) {
                return $matched;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows  keyed by original request index
     * @return int number of toppers created (updated rows are not counted)
     */
    private function createToppersFromRows(Request $request, BoardResult $boardResult, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $sahodayaId = (string) $this->school->parent_id;
        $isClass12 = (int) $boardResult->class === 12;
        $marksConfig = app(BoardResultMarksConfigService::class);

        // Lock the result row to serialize topper creation and prevent race conditions
        // on the Top-N cap check and rank auto-increment.
        return DB::transaction(function () use ($request, $boardResult, $rows, $sahodayaId, $isClass12, $marksConfig) {
            BoardResult::query()->whereKey($boardResult->id)->lockForUpdate()->first();

            // Pre-load existing toppers for this result so we can match rows to them
            // and update in place — prevents duplicate toppers on every save. Scoped to
            // entry_type=overall only: a student can legitimately also have a
            // subject-wise or Full A1 row with the same roll_no/name in this same
            // board_result, and matching against those would silently reclassify that
            // other row to 'overall' and overwrite it (#161 follow-up).
            $existingToppers = Topper::where('board_result_id', $boardResult->id)
                ->overallEntries()
                ->get(['id', 'name', 'roll_no', 'admission_no', 'gender', 'stream', 'stream_id',
                       'entry_type', 'total_marks', 'marks_obtained', 'percentage', 'rank', 'photo']);

            // Schools can enter as many student toppers/achievers as needed.
            // Sahodaya Top-N config filters reports at the Sahodaya level.

            // Resolve each row's stream (Class XII) and admin-locked "out of" total up front,
            // so marks_obtained can be validated against the correct per-stream total before
            // anything is created. Class X has no stream — one Sahodaya-wide total applies.
            $resolved = [];
            foreach ($rows as $i => $row) {
                $streamKey = $row['stream_key'] ?? $row['stream'] ?? null;
                $streamLabel = null;
                $streamId = null;

                if ($isClass12) {
                    if (blank($streamKey)) {
                        throw ValidationException::withMessages([
                            "toppers.{$i}.stream_key" => 'Select a stream for each Class XII topper — it determines the locked total marks.',
                        ]);
                    }
                    $normalizedKey = BoardExamSubjects::normalizeStream($streamKey, $sahodayaId);
                    if ($normalizedKey === null) {
                        throw ValidationException::withMessages([
                            "toppers.{$i}.stream_key" => "The selected stream '{$streamKey}' is not available for this Sahodaya.",
                        ]);
                    }
                    $labels = BoardExamSubjects::class12StreamLabels($sahodayaId);
                    $streamLabel = $labels[$normalizedKey] ?? $streamKey;
                    $streamId = BoardExamSubjects::resolveStreamId($normalizedKey, $sahodayaId);
                }

                $totalMarks = $marksConfig->resolve($sahodayaId, (int) $boardResult->class, $streamId);

                if ((float) $row['marks_obtained'] > $totalMarks) {
                    throw ValidationException::withMessages([
                        "toppers.{$i}.marks_obtained" => "Marks scored cannot exceed the total marks ({$totalMarks}).",
                    ]);
                }

                $resolved[$i] = ['stream_label' => $streamLabel, 'stream_id' => $streamId, 'total_marks' => $totalMarks];
            }

            // Validate that each filled roll_no isn't already taken — queries the DB directly
            // rather than relying on the potentially-stale pre-loaded collection.
            foreach ($rows as $i => $row) {
                if (blank($row['roll_no'] ?? null)) {
                    continue;
                }
                $matched = $this->matchTopper($existingToppers, $row);
                // Scoped to overall entries — the same roll_no is expected to also exist
                // on that student's subject-wise/Full A1 rows, which isn't a conflict.
                $conflict = Topper::query()
                    ->where('board_result_id', $boardResult->id)
                    ->overallEntries()
                    ->where('roll_no', $row['roll_no'])
                    ->when($matched, fn ($q) => $q->where('id', '!=', $matched->id))
                    ->exists();
                if ($conflict) {
                    throw ValidationException::withMessages([
                        "toppers.{$i}.roll_no" => "CBSE Roll No '{$row['roll_no']}' is already assigned to another Overall topper in this result.",
                    ]);
                }
            }

            $created = 0;
            $updated = 0;

            foreach ($rows as $i => $row) {
                $marksObtained = (float) $row['marks_obtained'];
                $totalMarks = $resolved[$i]['total_marks'];
                $percentage = $totalMarks > 0 ? round(($marksObtained / $totalMarks) * 100, 2) : 0;

                $marksheetPath = null;
                $marksheetDisk = null;
                if ($request->hasFile("toppers.{$i}.marksheet")) {
                    $marksheetDisk = TenantStorage::uploadDisk();
                    $marksheetPath = $request->file("toppers.{$i}.marksheet")->store(
                        'board-results/'.$this->school->id.'/'.$boardResult->id,
                        $marksheetDisk
                    );
                }

                // Try to match this row to an existing topper by admission_no, then roll_no,
                // then name — prevents duplicate creation on every save.
                $matched = $this->matchTopper($existingToppers, $row);

                if ($matched) {
                    $matched->update([
                        'entry_type' => Topper::ENTRY_OVERALL,
                        'name' => $row['name'],
                        'roll_no' => $row['roll_no'] ?? $matched->roll_no,
                        'admission_no' => $row['admission_no'] ?? $matched->admission_no,
                        'gender' => $row['gender'] ?? $matched->gender,
                        'stream' => $resolved[$i]['stream_label'] ?? $row['stream'] ?? $matched->stream,
                        'stream_id' => $resolved[$i]['stream_id'] ?? $matched->stream_id,
                        'total_marks' => $totalMarks,
                        'marks_obtained' => $marksObtained,
                        'percentage' => $percentage,
                        'marksheet_path' => $marksheetPath ?? $matched->marksheet_path,
                        'marksheet_disk' => $marksheetDisk ?? $matched->marksheet_disk,
                    ]);
                    $updated++;
                } else {
                    Topper::create([
                        'board_result_id' => $boardResult->id,
                        'tenant_id' => $this->school->id,
                        'entry_type' => Topper::ENTRY_OVERALL,
                        'name' => $row['name'],
                        'roll_no' => $row['roll_no'] ?? null,
                        'admission_no' => $row['admission_no'] ?? null,
                        'gender' => $row['gender'] ?? null,
                        'stream' => $resolved[$i]['stream_label'] ?? $row['stream'] ?? null,
                        'stream_id' => $resolved[$i]['stream_id'],
                        'total_marks' => $totalMarks,
                        'marks_obtained' => $marksObtained,
                        'percentage' => $percentage,
                        'marksheet_path' => $marksheetPath,
                        'marksheet_disk' => $marksheetDisk,
                    ]);
                    $created++;
                }
            }

            $this->recomputeOverallRanks($boardResult);
            app(SubjectStatsNormalizer::class)->rebuild($boardResult->fresh(['toppers']));

            return $created;
        });
    }

    /**
     * Rank is never hand-typed or set once at creation anymore — it's always
     * recomputed here from percentage (competition ranking: ties share a rank,
     * the next distinct score skips ahead), so a school can never end up with a
     * lower-scoring student ranked ahead of a higher-scoring one (#161).
     */
    private function recomputeOverallRanks(BoardResult $boardResult): void
    {
        $toppers = Topper::query()
            ->where('board_result_id', $boardResult->id)
            ->where('entry_type', Topper::ENTRY_OVERALL)
            ->orderByDesc('percentage')
            ->orderByDesc('marks_obtained')
            ->orderBy('id')
            ->get(['id', 'percentage', 'marks_obtained', 'rank']);

        if ($toppers->isEmpty()) {
            return;
        }

        // Temporarily null out ranks for overall toppers of this board_result to prevent
        // intermediate rank collision exceptions when row-by-row updating.
        Topper::query()
            ->whereIn('id', $toppers->pluck('id'))
            ->update(['rank' => null]);

        $lastScore = null;
        $lastRank = 0;

        foreach ($toppers as $index => $topper) {
            $score = (float) $topper->percentage;
            $position = $index + 1;

            $rank = ($lastScore === null || abs($score - $lastScore) > 0.0001)
                ? $position
                : $lastRank;

            Topper::whereKey($topper->id)->update(['rank' => $rank]);

            $lastScore = $score;
            $lastRank = $rank;
        }
    }

    public function submit(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'This result cannot be submitted in its current state.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        // Principal Verification (docs/BOARD_RESULTS_PRINCIPAL_VERIFICATION_PLAN.md) is the
        // required path once a school has started it for this result — direct submission is
        // only still allowed for schools/years that haven't begun using the new workflow yet,
        // so this doesn't retroactively break results already mid-flight under the old process.
        $activePackage = $boardResult->activeCertificationPackage();
        if ($activePackage && $activePackage->status !== \App\Models\BoardResultCertificationPackage::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'result' => 'This result is already going through Principal Verification. Use that workflow to certify and submit it — direct submission is disabled once verification has started.',
            ]);
        }

        if (app(BoardResultAcademicYearService::class)->isCertificationRequired($boardResult)) {
            throw ValidationException::withMessages([
                'result' => 'Your Sahodaya requires Principal Verification for this academic year. Use "Send for Leadership Review" instead of direct submission.',
            ]);
        }

        if (! $boardResult->hasResultPdf()) {
            throw ValidationException::withMessages([
                'result_pdf' => 'Upload the proof document before submitting for verification.',
            ]);
        }

        $this->performSubmit($request, $boardResult);

        return back()->with('success', 'Board result submitted for Sahodaya verification.');
    }

    private function performSubmit(Request $request, BoardResult $boardResult): void
    {
        $history = $boardResult->correction_history ?? [];
        $history[] = [
            'at' => now()->toIso8601String(),
            'by' => $request->user()->id,
            'action' => 'resubmitted',
            'submission_count' => (int) ($boardResult->submission_count ?? 0) + 1,
            'pdf_path' => $boardResult->result_pdf_path,
        ];

        $boardResult->update([
            'status' => BoardResult::STATUS_SUBMITTED,
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
            'submission_count' => (int) ($boardResult->submission_count ?? 0) + 1,
            'correction_history' => $history,
            'rejection_reason' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'verified_by' => null,
            'verified_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'published_at' => null,
        ]);

        app(DataChangeLogger::class)->event(
            'submitted',
            'Board result submitted for verification',
            $this->school->id,
            'board_result',
            $boardResult,
            ['class' => $boardResult->class, 'academic_year' => $boardResult->academic_year],
        );

        app(BoardResultNotifier::class)->notifySubmitted($boardResult->fresh(), $request->user());
    }

    public function uploadPdf(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'Uploads are locked for this result.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        $request->validate([
            'result_pdf' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp|max:20480',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp|max:20480',
        ]);

        $this->storeUploads($request, $boardResult);

        app(DataChangeLogger::class)->event(
            'uploaded',
            'Board result PDF uploaded',
            $this->school->id,
            'board_result',
            $boardResult,
        );

        return back()->with('success', 'Proof document uploaded.');
    }

    public function destroy(string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'Published or in-review results cannot be deleted.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        app(DataChangeLogger::class)->deleted(
            $boardResult,
            'Board result deleted',
            $this->school->id,
            'board_result',
            $boardResult->only(['class', 'academic_year', 'examination_type', 'pass_percent']),
        );

        $boardResult->delete();

        return back()->with('success', 'Board result removed.');
    }

    public function subjectToppers(Request $request, string $tenantId)
    {
        $class = $request->filled('class') ? (int) $request->input('class') : 12;
        $academicYear = $request->string('academic_year')->trim()->toString();
        $yearService = app(BoardResultAcademicYearService::class);
        $academicYearOptions = $yearService->activeOrPopulatedYearOptions((string) $this->school->parent_id);
        if ($academicYear === '') {
            $configuredOpenYear = collect($academicYearOptions)
                ->first(fn (array $year) => $year['entry_configured'] && $year['entry_status'] === 'open');
            $openYear = $configuredOpenYear
                ?? collect($academicYearOptions)->firstWhere('entry_status', 'open');
            $academicYear = $openYear['label'] ?? ((date('Y') - 1).'-'.substr((string) date('Y'), 2));
        }

        $yearService->assertEditableYear($yearService->resolveId($academicYear), $academicYear);

        $sahodayaId = (string) $this->school->parent_id;
        $marksConfigService = app(BoardResultMarksConfigService::class);
        $totalMarks = (int) $class === 10
            ? $marksConfigService->resolve($sahodayaId, 10, null)
            : null;

        $boardResult = BoardResult::firstOrCreate(
            [
                'tenant_id' => $this->school->id,
                'class' => $class,
                'academic_year' => $academicYear,
            ],
            [
                'examination_type' => BoardResult::examinationTypeForClass($class),
                'status' => BoardResult::STATUS_DRAFT,
                'total_marks' => $totalMarks,
            ]
        );

        $boardResult->load(['toppers.subjectMarks', 'toppers.examStream', 'uploads']);

        return $this->inertia('School/BoardResults/Workspace', array_merge([
            'activeTab' => 'subject-toppers',
            'boardResult' => $boardResult,
            'academicYear' => $academicYear,
            'academicYearOptions' => $academicYearOptions,
        ], $this->topperContext($boardResult)));
    }

    /**
     * Full A1 Achievers (#161): a dedicated page where a school enters one
     * student's marks for every subject in a single form. Based on CBSE A1 rules
     * (top 1/8th percentile of all candidates), any mark entered by the school for
     * a Full A1 student is accepted as A1. Separate from Subject-Wise Toppers so
     * this list is always exactly "students confirmed Full A1", nothing else.
     */
    public function fullA1Achievers(Request $request, string $tenantId)
    {
        $class = $request->filled('class') ? (int) $request->input('class') : 10;
        abort_unless(in_array($class, [10, 12], true), 404);

        $academicYear = $request->string('academic_year')->trim()->toString();
        $yearService = app(BoardResultAcademicYearService::class);
        $academicYearOptions = $yearService->activeOrPopulatedYearOptions((string) $this->school->parent_id);
        if ($academicYear === '') {
            $populatedResult = BoardResult::where('tenant_id', $this->school->id)
                ->where('class', $class)
                ->whereHas('toppers', fn ($q) => $q->where('entry_type', Topper::ENTRY_FULL_A1))
                ->orderByDesc('academic_year')
                ->first();

            if ($populatedResult) {
                $academicYear = $populatedResult->academic_year;
            } else {
                $configuredOpenYear = collect($academicYearOptions)
                    ->first(fn (array $year) => ($year['entry_configured'] ?? false) && ($year['entry_status'] ?? '') === 'open');
                $openYear = $configuredOpenYear
                    ?? collect($academicYearOptions)->firstWhere('entry_status', 'open');
                $academicYear = $openYear['label'] ?? ((date('Y') - 1).'-'.substr((string) date('Y'), 2));
            }
        }

        $yearService->assertEditableYear($yearService->resolveId($academicYear), $academicYear);

        $sahodayaId = (string) $this->school->parent_id;
        $marksConfigService = app(BoardResultMarksConfigService::class);
        $totalMarks = $class === 10 ? $marksConfigService->resolve($sahodayaId, 10, null) : null;

        $boardResult = BoardResult::firstOrCreate(
            [
                'tenant_id' => $this->school->id,
                'class' => $class,
                'academic_year' => $academicYear,
            ],
            [
                'examination_type' => BoardResult::examinationTypeForClass($class),
                'status' => BoardResult::STATUS_DRAFT,
                'total_marks' => $totalMarks,
            ]
        );

        $boardResult->load(['toppers' => function ($q) {
            $q->fullA1Entries()->with('subjectMarks');
        }, 'uploads']);

        $standardSubjects = $class === 10
            ? (BoardExamSubjects::subjectsForClass10($sahodayaId) ?: BoardExamSubjects::standardBoardSubjects($sahodayaId))
            : BoardExamSubjects::standardBoardSubjects($sahodayaId);

        // Official CBSE code per subject, purely for display next to each option —
        // not written anywhere, so there's no risk of the Class X/XII code collision
        // described in CbseSubjectCodes (e.g. Sanskrit: 122 at Class X, 322 at XII).
        $subjectCodes = collect($standardSubjects)
            ->mapWithKeys(fn ($label) => [
                $label => $class === 10
                    ? \App\Support\CbseSubjectCodes::forClass10Label($label)
                    : \App\Support\CbseSubjectCodes::forClass12Label($label),
            ])
            ->filter()
            ->all();

        return $this->inertia('School/BoardResults/Workspace', [
            'activeTab' => 'full-a1',
            'boardResult' => $boardResult,
            'academicYear' => $academicYear,
            'academicYearOptions' => $academicYearOptions,
            'standardSubjects' => $standardSubjects,
            'subjectCodes' => $subjectCodes,
            'streamOptions' => $class === 12 ? BoardExamSubjects::class12StreamLabels($sahodayaId) : [],
            'canEdit' => $boardResult->isEditable(),
            'editLockReason' => $boardResult->isEditable() ? null : $boardResult->editLockReason(),
        ]);
    }

    /**
     * Save one or more students' full subject-mark sets from the Full A1
     * Achievers form. Validates that required student details and duplicate subjects are handled.
     */
    public function storeFullA1AchieversBatch(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'Achievers are locked for this result.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        $isClass12 = (int) $boardResult->class === 12;

        // subject_marks travels as an ordered list of {subject, marks} pairs (not a
        // label => marks map) specifically so a student picking the same subject
        // twice can be detected server-side — a plain JSON object would have already
        // silently collapsed the duplicate down to one key before it got here.
        $data = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.topper_id' => 'nullable|integer',
            'rows.*.name' => 'required|string|max:255',
            'rows.*.gender' => 'required|string|in:male,female,other',
            'rows.*.roll_no' => 'required|string|max:64',
            'rows.*.stream' => 'nullable|string|max:100',
            'rows.*.subject_marks' => 'required|array|min:1',
            'rows.*.subject_marks.*.subject' => 'required|string|max:120',
            'rows.*.subject_marks.*.marks' => 'required|numeric|min:0|max:100',
        ]);

        // Enforce the A1 rule and duplicate-subject rule here, server-side — the
        // frontend already blocks both, but the server is the real gate. Reject the
        // whole batch (not a partial save) so a school can't end up half-saved.
        $failures = [];
        $normalizedSubjectMarks = [];
        foreach ($data['rows'] as $i => $row) {
            if ($isClass12 && blank($row['stream'] ?? null)) {
                $failures[] = 'Row '.($i + 1).": select a stream for {$row['name']}.";

                continue;
            }

            $seenSubjects = [];
            $subjectMap = [];
            foreach ($row['subject_marks'] as $entry) {
                $label = trim((string) $entry['subject']);
                $key = mb_strtolower($label);

                if (isset($seenSubjects[$key])) {
                    $failures[] = 'Row '.($i + 1).": {$row['name']} has \"{$label}\" entered more than once — remove the duplicate before saving.";

                    continue;
                }
                $seenSubjects[$key] = true;

                $marks = (float) $entry['marks'];
                $subjectMap[$label] = $marks;
            }

            $normalizedSubjectMarks[$i] = $subjectMap;
        }
        if ($failures !== []) {
            throw ValidationException::withMessages(['rows' => $failures]);
        }

        // Roll_no is required for Full A1 Achievers (unlike the other topper entry
        // forms, where it's optional) and must be unique within this submission.
        $submittedRollNos = [];
        foreach ($data['rows'] as $i => $row) {
            $rollNo = trim((string) $row['roll_no']);
            if (isset($submittedRollNos[$rollNo])) {
                throw ValidationException::withMessages([
                    "rows.{$i}.roll_no" => "Duplicate CBSE Roll No '{$rollNo}' for school '{$this->school->name}' within the same submission.",
                ]);
            }
            $submittedRollNos[$rollNo] = true;
        }

        try {
            $response = DB::transaction(function () use ($data, $boardResult, $isClass12, $normalizedSubjectMarks) {
                BoardResult::query()->whereKey($boardResult->id)->lockForUpdate()->first();

                $existing = Topper::where('board_result_id', $boardResult->id)
                    ->fullA1Entries()
                    ->get(['id', 'name', 'roll_no', 'admission_no']);

                $created = 0;
                $updated = 0;

                foreach ($data['rows'] as $i => $row) {
                    $name = trim($row['name']);
                    $topper = $this->matchTopper($existing, $row);

                    $attrs = [
                        'name' => $name,
                        'gender' => $row['gender'],
                        'roll_no' => trim($row['roll_no']),
                        'admission_no' => filled($row['admission_no'] ?? null) ? trim((string) $row['admission_no']) : null,
                        'stream' => $isClass12 ? ($row['stream'] ?? null) : null,
                        'rank' => null,
                    ];

                    if ($topper) {
                        $topper->update($attrs);
                        $updated++;
                    } else {
                        $topper = Topper::create(array_merge($attrs, [
                            'board_result_id' => $boardResult->id,
                            'tenant_id' => $this->school->id,
                            'entry_type' => Topper::ENTRY_FULL_A1,
                        ]));
                        $existing->push($topper);
                        $created++;
                    }

                    app(TopperSubjectMarkService::class)->sync($topper, $normalizedSubjectMarks[$i]);
                }

            app(DataChangeLogger::class)->event(
                'created',
                "{$created} added, {$updated} updated (Full A1 Achievers)",
                $this->school->id,
                'topper',
                $boardResult,
                ['created' => $created, 'updated' => $updated],
            );

            $parts = array_filter([
                $created ? "{$created} added" : null,
                $updated ? "{$updated} updated" : null,
            ]);

            return back()->with('success', implode(', ', $parts).' as Full A1 Achievers.');
        });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'rows' => 'A Full A1 Achiever record with the same Roll Number already exists in this result.',
            ]);
        }

        $this->invalidateCertificationIfNeeded($boardResult, $request, 'Full A1 Achievers updated by the school.');

        return $response;
    }

    public function toppers(string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        $boardResult->load(['toppers.subjectMarks', 'toppers.examStream', 'uploads']);

        return $this->inertia('School/BoardResults/Workspace', array_merge(
            ['activeTab' => 'toppers', 'boardResult' => $boardResult],
            $this->topperContext($boardResult),
        ));
    }

    /** Shared topper-entry context used by both the standalone Toppers page and the inline Index search/entry flow. */
    private function topperContext(BoardResult $boardResult): array
    {
        $isClass12 = (int) $boardResult->class === 12;
        $sahodayaId = (string) $this->school->parent_id;
        $streamOptions = $isClass12 ? BoardExamSubjects::class12StreamLabels($sahodayaId) : [];

        // Class X has its own admin-editable subject list now (a global "class_10"
        // pseudo-stream row) — prefer it over the generic 23-subject fallback when
        // it's been populated; fall back to the flat list otherwise (e.g. fresh
        // Sahodaya that hasn't customized it yet).
        $standardSubjects = ! $isClass12
            ? (BoardExamSubjects::subjectsForClass10($sahodayaId) ?: BoardExamSubjects::standardBoardSubjects($sahodayaId))
            : BoardExamSubjects::standardBoardSubjects($sahodayaId);

        return [
            'isClass12' => $isClass12,
            'streamOptions' => $streamOptions,
            'standardSubjects' => $standardSubjects,
            'subjectsByStream' => $isClass12 ? collect($streamOptions)
                ->mapWithKeys(fn ($label, $key) => [$key => BoardExamSubjects::subjectsForStream($key, $sahodayaId)])
                ->all() : [],
            'subjectWiseLeaders' => $isClass12 ? BoardExamSubjects::subjectWiseLeaders($boardResult->toppers) : [],
            'canEdit' => $boardResult->isEditable(),
            'editLockReason' => $boardResult->isEditable() ? null : $boardResult->editLockReason(),
            'topperCap' => app(TopperCountService::class)->resolveCap($sahodayaId, (int) $boardResult->class),
            'topperCount' => $boardResult->toppers
                ->where('entry_type', Topper::ENTRY_OVERALL)
                ->count(),
            'marksConfig' => $this->marksConfigFor((int) $boardResult->class, $streamOptions, $sahodayaId),
        ];
    }

    /**
     * Admin-locked "out of" marks for the frontend: a flat Class X value, and (for Class XII)
     * a per-stream-key map so the entry table can show/lock each row's total once a stream is
     * picked. Schools can no longer type this value — see BoardResultMarksConfigService.
     *
     * @param  array<string, string>  $streamOptions  stream_key => label
     * @return array{classX: int, byStream: array<string, int>}
     */
    /**
     * Corrections to a result's toppers must invalidate any in-progress certification
     * package (superseding signed reports and bumping the package version) — see
     * BoardResultCertificationService::invalidateForDataChange() and plan §7. This is a
     * no-op for the common case where no certification package exists yet.
     */
    private function invalidateCertificationIfNeeded(BoardResult $boardResult, Request $request, string $reason): void
    {
        try {
            app(\App\Services\BoardResults\BoardResultCertificationService::class)
                ->invalidateForDataChange($boardResult->fresh(), $request->user(), $reason);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['result' => [$e->getMessage()]]);
        }
    }

    private function marksConfigFor(int $class, array $streamOptions, string $sahodayaId): array
    {
        $marksConfig = app(BoardResultMarksConfigService::class);

        return [
            'classX' => $marksConfig->resolve($sahodayaId, 10, null),
            'byStream' => collect($streamOptions)->mapWithKeys(function ($label, $key) use ($marksConfig, $sahodayaId) {
                $streamId = BoardExamSubjects::resolveStreamId($key, $sahodayaId);

                return [$key => $marksConfig->resolve($sahodayaId, 12, $streamId)];
            })->all(),
        ];
    }

    public function updateTopper(Request $request, string $tenantId, BoardResult $boardResult, Topper $topper)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_if($topper->board_result_id !== $boardResult->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'Toppers are locked for this result.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        // Removing the final subject mark from a subject-only row removes that wrapper
        // topper too. Overall toppers remain in place when one of their subject marks is
        // removed because they still carry a genuine aggregate result.
        if (
            $topper->isSubjectOnly()
            && $request->has('subject_marks')
            && empty(array_filter((array) $request->input('subject_marks'), fn ($mark) => $mark !== null && $mark !== ''))
        ) {
            $topper->delete();
            app(SubjectStatsNormalizer::class)->rebuild($boardResult->fresh(['toppers']));
            $this->invalidateCertificationIfNeeded($boardResult, $request, 'Subject topper entry removed by the school.');

            return back()->with('success', 'Subject topper entry removed.');
        }

        $before = $topper->only(['name', 'percentage', 'rank', 'stream', 'stream_id', 'admission_no', 'roll_no']);
        $data = $this->validateTopper($request, $boardResult, (int) $boardResult->class === 12, $topper);
        $subjectMarks = $data['subject_marks'] ?? null;
        unset($data['subject_marks']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store(
                'board-results/'.$this->school->id.'/'.$boardResult->id,
                TenantStorage::uploadDisk()
            );
        }

        $topper->update($data);
        if (is_array($subjectMarks)) {
            app(TopperSubjectMarkService::class)->sync($topper, $subjectMarks);
        }
        if ($topper->entry_type === Topper::ENTRY_OVERALL) {
            $this->recomputeOverallRanks($boardResult);
        }
        app(SubjectStatsNormalizer::class)->rebuild($boardResult->fresh(['toppers']));

        app(DataChangeLogger::class)->updated(
            $topper,
            'Topper updated',
            DataChangeLogger::diff($before, $topper->only(array_keys($before))),
            $this->school->id,
            'topper',
        );

        $this->invalidateCertificationIfNeeded($boardResult, $request, 'Topper updated by the school.');

        return back()->with('success', 'Topper updated.');
    }

    /**
     * Add several toppers at once: name / roll no / admission no / marks scored (+ optional
     * photo proof) per row. Total ("out of") marks is admin-locked — resolved server-side per
     * row via BoardResultMarksConfigService, not submitted by the school. Percentage is derived
     * automatically. Rank and subject-wise marks aren't set here — use the single Edit form
     * afterward for those (mainly relevant to Class XII).
     */
    public function storeToppersBatch(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'Toppers are locked for this result.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        $rows = $this->validateTopperRows($request);
        if ($rows === []) {
            throw ValidationException::withMessages([
                'toppers' => 'Add at least one topper (name + marks scored).',
            ]);
        }

        $created = $this->createToppersFromRows($request, $boardResult, $rows);

        app(DataChangeLogger::class)->event(
            'created',
            $created.' topper(s) added (bulk)',
            $this->school->id,
            'topper',
            $boardResult,
            ['count' => $created],
        );

        $this->invalidateCertificationIfNeeded($boardResult, $request, 'Toppers added in bulk by the school.');

        return back()->with('success', $created.' topper(s) added.');
    }

    public function storeTopper(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'Toppers are locked for this result.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        $sahodayaId = (string) $this->school->parent_id;

        $isClass12 = (int) $boardResult->class === 12;
        $data = $this->validateTopper($request, $boardResult, $isClass12);
        $subjectMarks = $data['subject_marks'] ?? null;
        unset($data['subject_marks']);

        $data['board_result_id'] = $boardResult->id;
        $data['tenant_id'] = $this->school->id;
        $data['entry_type'] = Topper::ENTRY_OVERALL;

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store(
                'board-results/'.$this->school->id.'/'.$boardResult->id,
                TenantStorage::uploadDisk()
            );
        }

        $topper = Topper::create($data);
        if (is_array($subjectMarks)) {
            app(TopperSubjectMarkService::class)->sync($topper, $subjectMarks);
        }
        $this->recomputeOverallRanks($boardResult);
        app(SubjectStatsNormalizer::class)->rebuild($boardResult->fresh(['toppers']));

        app(DataChangeLogger::class)->created(
            $topper,
            'Topper added',
            $this->school->id,
            'topper',
            ['name' => $topper->name, 'percentage' => $topper->percentage],
        );

        $this->invalidateCertificationIfNeeded($boardResult, $request, 'Topper added by the school.');

        return back()->with('success', 'Topper added.');
    }

    /**
     * Add/update several students' marks for a single subject in one request. Replaces
     * looping one router.post/put per row on the Subject-Wise Toppers page, which was
     * firing a separate "success" flash per row and never refreshing the on-screen rows.
     */
    public function storeSubjectToppersBatch(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'Toppers are locked for this result.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        $data = $request->validate([
            'subject' => 'required|string|max:100',
            'rows' => 'required|array|min:1',
            'rows.*.topper_id' => 'nullable|integer',
            'rows.*.original_subject' => 'nullable|string|max:100',
            'rows.*.name' => 'required|string|max:255',
            'rows.*.gender' => 'required|string|in:male,female,other',
            'rows.*.roll_no' => 'nullable|string|max:64',
            'rows.*.admission_no' => 'nullable|string|max:64',
            'rows.*.marks' => 'required|numeric|min:0',
        ]);

        $subject = $data['subject'];
        $sahodayaId = (string) $this->school->parent_id;

        try {
            $response = DB::transaction(function () use ($data, $boardResult, $sahodayaId, $subject) {
                BoardResult::query()->whereKey($boardResult->id)->lockForUpdate()->first();

                $nextRank = (int) (Topper::where('board_result_id', $boardResult->id)->max('rank') ?? 0) + 1;
                $created = 0;
                $updated = 0;
                $workingToppers = Topper::where('board_result_id', $boardResult->id)
                    ->subjectEntries()
                    ->with('subjectMarks')
                    ->get();

                // Validate roll_no uniqueness across all rows submitted within the payload.
                $submittedRollNos = [];
                foreach ($data['rows'] as $i => $row) {
                    $rollNo = filled($row['roll_no'] ?? null) ? trim((string) $row['roll_no']) : null;
                    if (blank($rollNo)) {
                        continue;
                    }
                    if (isset($submittedRollNos[$rollNo])) {
                        throw ValidationException::withMessages([
                            "rows.{$i}.roll_no" => "Duplicate CBSE Roll No '{$rollNo}' for school '{$this->school->name}' within the same submission. Each roll number must be unique.",
                        ]);
                    }
                    $submittedRollNos[$rollNo] = true;
                }

                $processedTopperIds = [];

                foreach ($data['rows'] as $i => $row) {
                    $name = trim($row['name']);
                    $rollNo = filled($row['roll_no'] ?? null) ? trim((string) $row['roll_no']) : null;

                    $topper = $this->matchTopper($workingToppers, $row);

                    if ($topper) {
                        if ($rollNo && trim((string) $topper->roll_no) !== $rollNo) {
                            $conflict = Topper::query()
                                ->where('board_result_id', $boardResult->id)
                                ->subjectEntries()
                                ->where('roll_no', $rollNo)
                                ->where('id', '!=', $topper->id)
                                ->exists();
                            if ($conflict) {
                                throw ValidationException::withMessages([
                                    "rows.{$i}.roll_no" => "CBSE Roll No '{$rollNo}' is already assigned to another Subject topper in this result.",
                                ]);
                            }
                        }

                        $subjectMarks = $topper->subject_marks;
                        $originalSubject = trim((string) ($row['original_subject'] ?? ''));
                        if ($originalSubject !== '' && strcasecmp($originalSubject, $subject) !== 0) {
                            unset($subjectMarks[$originalSubject]);
                        }
                        $subjectMarks[$subject] = $row['marks'];

                        $topper->update([
                            'name' => $name,
                            'gender' => $row['gender'],
                            'roll_no' => $rollNo ?? $topper->roll_no,
                            'admission_no' => filled($row['admission_no'] ?? null) ? trim((string) $row['admission_no']) : $topper->admission_no,
                        ]);
                        app(TopperSubjectMarkService::class)->sync($topper, $subjectMarks);
                        $processedTopperIds[] = (int) $topper->id;
                        $updated++;
                    } else {
                        if ($rollNo) {
                            $conflict = Topper::query()
                                ->where('board_result_id', $boardResult->id)
                                ->subjectEntries()
                                ->where('roll_no', $rollNo)
                                ->exists();
                            if ($conflict) {
                                throw ValidationException::withMessages([
                                    "rows.{$i}.roll_no" => "CBSE Roll No '{$rollNo}' is already assigned to another Subject topper in this result.",
                                ]);
                            }
                        }

                        $topper = Topper::create([
                            'board_result_id' => $boardResult->id,
                            'tenant_id' => $this->school->id,
                            'entry_type' => Topper::ENTRY_SUBJECT,
                            'name' => $name,
                            'gender' => $row['gender'],
                            'roll_no' => $rollNo,
                            'admission_no' => filled($row['admission_no'] ?? null) ? trim((string) $row['admission_no']) : null,
                            'percentage' => null,
                            'marks_obtained' => null,
                            'total_marks' => null,
                            'rank' => $nextRank++,
                        ]);
                        app(TopperSubjectMarkService::class)->sync($topper, [$subject => $row['marks']]);
                        $workingToppers->push($topper);
                        $processedTopperIds[] = (int) $topper->id;
                        $created++;
                    }
                }

                // Remove subject mark for any existing subject topper that had a mark for this subject
                // but was omitted from the submitted rows (i.e. student removed from form for this subject).
                $removedCount = 0;
                foreach ($workingToppers as $workingTopper) {
                    if (in_array((int) $workingTopper->id, $processedTopperIds, true)) {
                        continue;
                    }

                    $marks = $workingTopper->subject_marks;
                    $matchedKey = null;
                    foreach (array_keys($marks) as $k) {
                        if (strcasecmp($k, $subject) === 0) {
                            $matchedKey = $k;
                            break;
                        }
                    }

                    if ($matchedKey !== null) {
                        unset($marks[$matchedKey]);
                        if (empty($marks)) {
                            $workingTopper->delete();
                        } else {
                            app(TopperSubjectMarkService::class)->sync($workingTopper, $marks);
                        }
                        $removedCount++;
                    }
                }

                app(SubjectStatsNormalizer::class)->rebuild($boardResult->fresh(['toppers']));

                app(DataChangeLogger::class)->event(
                    'created',
                    "{$subject}: {$created} added, {$updated} updated, {$removedCount} removed",
                    $this->school->id,
                    'topper',
                    $boardResult,
                    ['subject' => $subject, 'created' => $created, 'updated' => $updated, 'removed' => $removedCount],
                );

                $msg = "Saved {$subject} toppers.";
                if ($created > 0 && $updated > 0) {
                    $msg = "{$subject}: {$created} student(s) added, {$updated} updated.";
                } elseif ($created > 0) {
                    $msg = "{$subject}: {$created} student(s) added.";
                } elseif ($updated > 0) {
                    $msg = "{$subject}: {$updated} student(s) updated.";
                } elseif ($removedCount > 0) {
                    $msg = "{$subject}: {$removedCount} student(s) removed.";
                }

                return back()->with('success', $msg);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
            if ($e instanceof \Illuminate\Database\QueryException && (string) $e->getCode() !== '23505' && ! str_contains($e->getMessage(), '23505')) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'rows' => 'One of the CBSE Roll Numbers in the form is already assigned to another Subject topper in this result.',
            ]);
        }

        $this->invalidateCertificationIfNeeded($boardResult, $request, "Subject-wise toppers for {$subject} updated by the school.");

        return $response;
    }

    /** @return array<string, mixed> */
    private function validateTopper(
        Request $request,
        BoardResult $boardResult,
        bool $isClass12,
        ?Topper $exclude = null,
    ): array {
        $isSubjectOnly = $exclude?->isSubjectOnly() || $request->input('entry_type') === Topper::ENTRY_SUBJECT;

        $rules = [
            'name' => 'required|string|max:255',
            'admission_no' => 'nullable|string|max:64',
            'roll_no' => 'nullable|string|max:64',
            'gender' => 'required|string|in:male,female,other',
            'percentage' => $isSubjectOnly ? 'nullable|numeric|min:0|max:100' : 'required|numeric|min:0|max:100',
            'total_marks' => 'nullable|integer|min:0',
            'marks_obtained' => 'nullable|numeric|min:0',
            'stream' => 'nullable|string|max:100',
            'stream_id' => 'nullable|integer',
            'is_perfect_scorer' => 'boolean',
            'photo' => 'nullable|image|max:4096',
        ];

        if ($isClass12) {
            $rules['stream_key'] = 'nullable|string|max:50';
            $rules['subject_marks'] = 'nullable|array';
            $rules['subject_marks.*'] = 'nullable|numeric|min:0';
        }

        $data = $request->validate($rules);

        // Rank is never accepted from the client — recomputeOverallRanks() derives it
        // from percentage right after this topper is saved (#161).

        // Validate roll_no uniqueness within this board result — scoped to Overall
        // entries only (this method is only ever used for that entry_type). The same
        // roll_no legitimately also appearing on that student's subject-wise/Full A1
        // rows isn't a conflict, so an unscoped check here would wrongly block adding
        // a genuine Overall topper who already has one of those other entries.
        if (filled($data['roll_no'] ?? null)) {
            $rollNoTaken = Topper::query()
                ->where('board_result_id', $boardResult->id)
                ->overallEntries()
                ->where('roll_no', $data['roll_no'])
                ->when($exclude, fn ($q) => $q->where('id', '!=', $exclude->id))
                ->exists();
            if ($rollNoTaken) {
                throw ValidationException::withMessages([
                    'roll_no' => "CBSE Roll No '{$data['roll_no']}' is already assigned to another Overall topper in this result.",
                ]);
            }
        }

        $sahodayaId = (string) $this->school->parent_id;

        if ($isClass12) {
            $hasSubjectMarks = is_array($data['subject_marks'] ?? null) && $data['subject_marks'] !== [];
            $rawStream = $data['stream_key'] ?? $data['stream'] ?? null;
            if (blank($rawStream) && ! $hasSubjectMarks) {
                throw ValidationException::withMessages([
                    'stream_key' => 'Select a stream for Class XII overall toppers.',
                ]);
            }

            $streamKey = BoardExamSubjects::normalizeStream($rawStream, $sahodayaId);
            if ($rawStream !== null && $rawStream !== '' && $streamKey === null) {
                throw ValidationException::withMessages([
                    'stream_key' => "The selected stream '{$rawStream}' is not available for this Sahodaya.",
                ]);
            }
            if ($streamKey) {
                $labels = BoardExamSubjects::class12StreamLabels($sahodayaId);
                $data['stream'] = $labels[$streamKey] ?? $data['stream'] ?? null;
                $data['stream_id'] = BoardExamSubjects::resolveStreamId($streamKey, $sahodayaId);
                if ($data['stream_id'] === null) {
                    throw ValidationException::withMessages([
                        'stream_key' => "The selected stream '{$rawStream}' is not configured in the stream master.",
                    ]);
                }
            }

            $data['subject_marks'] = BoardExamSubjects::normalizeSubjectMarks($data['subject_marks'] ?? []);
            unset($data['stream_key']);
        } else {
            unset($data['subject_marks'], $data['stream_id']);
        }

        // Validate marks_obtained against the configured total_marks when available.
        $sahodayaId = (string) $this->school->parent_id;
        $marksConfig = app(BoardResultMarksConfigService::class);
        $streamId = $data['stream_id'] ?? null;
        $configuredTotal = $marksConfig->resolve($sahodayaId, (int) $boardResult->class, $streamId);
        if ($configuredTotal > 0 && isset($data['marks_obtained']) && (float) $data['marks_obtained'] > $configuredTotal) {
            throw ValidationException::withMessages([
                'marks_obtained' => "Marks obtained ({$data['marks_obtained']}) cannot exceed the configured total marks ({$configuredTotal}) for this class/stream.",
            ]);
        }

        return $data;
    }

    public function destroyTopper(Request $request, string $tenantId, BoardResult $boardResult, Topper $topper)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_if($topper->board_result_id !== $boardResult->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'Toppers are locked for this result.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        app(DataChangeLogger::class)->deleted(
            $topper,
            'Topper removed',
            $this->school->id,
            'topper',
            $topper->only(['name', 'percentage', 'rank']),
        );

        $wasOverall = $topper->entry_type === Topper::ENTRY_OVERALL;
        $topper->delete();
        if ($wasOverall) {
            $this->recomputeOverallRanks($boardResult);
        }
        app(SubjectStatsNormalizer::class)->rebuild($boardResult->fresh(['toppers']));

        $this->invalidateCertificationIfNeeded($boardResult, $request, 'Topper removed by the school.');

        return back()->with('success', 'Topper removed.');
    }

    /** @return array<string, mixed> */
    private function validateBoardResult(Request $request, ?BoardResult $existing = null): array
    {
        $data = $request->validate([
            'class' => 'required|integer|in:10,12',
            'examination_type' => ['nullable', 'string', Rule::in(BoardResult::examinationTypes())],
            'academic_year' => 'required|string|max:20',
            'total_appeared' => 'nullable|integer|min:0',
            'pass_count' => 'nullable|integer|min:0',
            'pass_percent' => 'nullable|numeric|min:0|max:100',
            'distinctions' => 'nullable|integer|min:0',
            'first_class' => 'nullable|integer|min:0',
            'highest_mark' => 'nullable|numeric|min:0',
            'average_mark' => 'nullable|numeric|min:0',
            'total_marks' => 'nullable|integer|min:1',
            'remarks' => 'nullable|string|max:5000',
            'result_pdf' => ($existing?->hasResultPdf() ? 'nullable' : 'nullable').'|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp|max:20480',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp|max:20480',
        ]);

        if ((int) ($data['pass_count'] ?? 0) > (int) ($data['total_appeared'] ?? 0)) {
            throw ValidationException::withMessages([
                'pass_count' => 'Pass count cannot exceed total appeared.',
            ]);
        }

        // Validate that distinctions + first_class does not exceed pass_count.
        if (isset($data['distinctions']) && isset($data['first_class']) && isset($data['pass_count'])) {
            $distFirst = (int) $data['distinctions'] + (int) $data['first_class'];
            if ($distFirst > (int) $data['pass_count']) {
                throw ValidationException::withMessages([
                    'distinctions' => 'Distinctions + First Class ('.$distFirst.') cannot exceed pass count ('.$data['pass_count'].').',
                ]);
            }
        }

        $examType = $data['examination_type'] ?? BoardResult::examinationTypeForClass((int) $data['class']);
        $expected = BoardResult::examinationTypeForClass((int) $data['class']);
        if ($examType !== $expected) {
            throw ValidationException::withMessages([
                'examination_type' => "Class {$data['class']} must use examination type {$expected}.",
            ]);
        }
        $data['examination_type'] = $examType;

        return $data;
    }

    private function storeUploads(Request $request, BoardResult $result): void
    {
        $disk = TenantStorage::uploadDisk();
        $dir = 'board-results/'.$this->school->id.'/'.$result->id;

        if ($request->hasFile('result_pdf')) {
            $file = $request->file('result_pdf');
            $path = TenantStorage::storeUploadedFile($file, $dir, $disk);
            $ext = strtolower($file->getClientOriginalExtension());
            $fileType = match (true) {
                in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) => 'image',
                $ext === 'pdf' => 'pdf',
                in_array($ext, ['doc', 'docx', 'xls', 'xlsx'], true) => 'document',
                default => 'document',
            };

            DB::transaction(function () use ($result, $path, $disk, $file, $fileType, $request) {
                BoardResult::query()->whereKey($result->id)->lockForUpdate()->first();

                $nextVersion = (int) BoardResultUpload::query()
                    ->where('board_result_id', $result->id)
                    ->max('version') + 1;

                BoardResultUpload::create([
                    'board_result_id' => $result->id,
                    'tenant_id' => $this->school->id,
                    'version' => max(1, $nextVersion),
                    'file_path' => $path,
                    'storage_disk' => $disk,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $fileType,
                    'uploaded_by' => $request->user()?->id,
                ]);

                $result->update([
                    'result_pdf_path' => $path,
                    'result_pdf_disk' => $disk,
                ]);
            });
        }

        if ($request->hasFile('attachments')) {
            DB::transaction(function () use ($request, $result, $dir, $disk) {
                BoardResult::query()->whereKey($result->id)->lockForUpdate()->first();
                $freshPaths = $result->fresh()->attachment_paths ?? [];

                foreach ($request->file('attachments') as $file) {
                    $path = TenantStorage::storeUploadedFile($file, $dir.'/attachments', $disk);
                    $freshPaths[] = [
                        'path' => $path,
                        'disk' => $disk,
                        'name' => $file->getClientOriginalName(),
                    ];

                    $nextVersion = (int) BoardResultUpload::query()
                        ->where('board_result_id', $result->id)
                        ->where('file_type', 'attachment')
                        ->max('version') + 1;

                    BoardResultUpload::create([
                        'board_result_id' => $result->id,
                        'tenant_id' => $this->school->id,
                        'version' => max(1, $nextVersion),
                        'file_path' => $path,
                        'storage_disk' => $disk,
                        'file_name' => $file->getClientOriginalName(),
                        'file_type' => 'attachment',
                        'uploaded_by' => $request->user()?->id,
                    ]);
                }

                $result->update(['attachment_paths' => $freshPaths]);
            });
        }
    }

    public function uploadTopperMarksheet(Request $request, string $tenantId, BoardResult $boardResult, Topper $topper)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_if($topper->board_result_id !== $boardResult->id, 403);

        $data = $request->validate([
            'marksheet' => 'required|file|mimes:jpeg,jpg,png,pdf,webp|max:10240',
        ]);

        $baseDir = 'sahodaya/'.$this->school->parent_id.'/board-results/marksheets';
        $disk = TenantStorage::uploadDisk();
        $path = $request->file('marksheet')->store($baseDir, $disk);

        $topper->update([
            'marksheet_path'      => $path,
            'marksheet_disk'      => $disk,
            'verification_status' => 'pending',
            'rejection_reason'    => null,
        ]);

        return back()->with('success', "Marksheet uploaded for {$topper->name}.");
    }

    public function deleteTopperMarksheet(string $tenantId, BoardResult $boardResult, Topper $topper)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_if($topper->board_result_id !== $boardResult->id, 403);

        $topper->update([
            'marksheet_path'      => null,
            'marksheet_disk'      => null,
            'verification_status' => 'pending',
            'rejection_reason'    => null,
        ]);

        return back()->with('success', 'Marksheet removed.');
    }
}
