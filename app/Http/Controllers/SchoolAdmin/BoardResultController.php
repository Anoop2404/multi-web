<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\AcademicYearRecord;
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
    public function index(Request $request)
    {
        $class = $request->filled('class') ? $request->integer('class') : null;
        abort_if($class !== null && ! in_array($class, [10, 12], true), 404);

        $results = BoardResult::where('tenant_id', $this->school->id)
            ->when($class, fn ($q) => $q->where('class', $class))
            ->with(['toppers.subjectMarks', 'toppers.examStream', 'uploads' => fn ($q) => $q->orderByDesc('version')->limit(5)])
            ->orderByDesc('academic_year')
            ->orderByDesc('class')
            ->get();

        $auditHistory = DataChangeLog::query()
            ->where('school_id', $this->school->id)
            ->whereIn('log_name', ['board_result', 'topper', 'achievement'])
            ->latest()
            ->limit(75)
            ->get(['id', 'action', 'description', 'log_name', 'subject_type', 'subject_id', 'changes', 'created_at', 'causer_user_id']);

        // Class + Academic Year "search" — looks up the matching result from what's already
        // loaded above (no extra query) so the aggregate form + topper entry can live inline.
        $academicYear = $request->string('academic_year')->toString() ?: null;
        $activeResult = ($class && $academicYear)
            ? $results->first(fn (BoardResult $r) => $r->academic_year === $academicYear)
            : null;

        return $this->inertia('School/BoardResults/Index', array_merge([
            'results' => $results,
            'academicYearOptions' => AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label', 'status']),
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
            'toppers'      => $result->toppers,
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
        if ($request->boolean('submit_for_review')) {
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
            'toppers.*.photo' => 'nullable|image|max:4096',
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
                'toppers' => "Duplicate CBSE Roll No(s) in the form: {$dupes}. Each roll number must be unique.",
            ]);
        }

        return $rows->all();
    }

    /**
     * Find the existing topper (from a collection) that a form row matches, using
     * admission_no → roll_no → name as priority order. Returns null when no match.
     *
     * @param  \Illuminate\Support\Collection<int, Topper>|list<Topper>  $toppers
     * @param  array<string, mixed>  $row
     */
    private function matchTopper($toppers, array $row): ?Topper
    {
        return (new \Illuminate\Support\Collection($toppers))->first(
            fn (Topper $t) => (filled($row['admission_no'] ?? null) && $t->admission_no === $row['admission_no'])
                || (blank($row['admission_no'] ?? null) && filled($row['roll_no'] ?? null) && $t->roll_no === $row['roll_no'])
                || (blank($row['admission_no'] ?? null) && blank($row['roll_no'] ?? null) && strtolower($t->name) === strtolower($row['name']))
        );
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
            // and update in place — prevents duplicate toppers on every save.
            $existingToppers = Topper::where('board_result_id', $boardResult->id)
                ->get(['id', 'name', 'roll_no', 'admission_no', 'gender', 'stream', 'stream_id',
                       'total_marks', 'marks_obtained', 'percentage', 'rank', 'photo']);

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
                $conflict = Topper::query()
                    ->where('board_result_id', $boardResult->id)
                    ->where('roll_no', $row['roll_no'])
                    ->when($matched, fn ($q) => $q->where('id', '!=', $matched->id))
                    ->exists();
                if ($conflict) {
                    throw ValidationException::withMessages([
                        "toppers.{$i}.roll_no" => "CBSE Roll No '{$row['roll_no']}' is already assigned to another topper in this result.",
                    ]);
                }
            }

            $nextRank = (int) ($existingToppers->max('rank') ?? 0) + 1;
            $created = 0;
            $updated = 0;

            foreach ($rows as $i => $row) {
                $marksObtained = (float) $row['marks_obtained'];
                $totalMarks = $resolved[$i]['total_marks'];
                $percentage = $totalMarks > 0 ? round(($marksObtained / $totalMarks) * 100, 2) : 0;

                $photoPath = null;
                if ($request->hasFile("toppers.{$i}.photo")) {
                    $photoPath = $request->file("toppers.{$i}.photo")->store(
                        'board-results/'.$this->school->id.'/'.$boardResult->id,
                        TenantStorage::uploadDisk()
                    );
                }

                // Try to match this row to an existing topper by admission_no, then roll_no,
                // then name — prevents duplicate creation on every save.
                $matched = $this->matchTopper($existingToppers, $row);

                if ($matched) {
                    $matched->update([
                        'name' => $row['name'],
                        'roll_no' => $row['roll_no'] ?? $matched->roll_no,
                        'admission_no' => $row['admission_no'] ?? $matched->admission_no,
                        'gender' => $row['gender'] ?? $matched->gender,
                        'stream' => $resolved[$i]['stream_label'] ?? $row['stream'] ?? $matched->stream,
                        'stream_id' => $resolved[$i]['stream_id'] ?? $matched->stream_id,
                        'total_marks' => $totalMarks,
                        'marks_obtained' => $marksObtained,
                        'percentage' => $percentage,
                        'photo' => $photoPath ?? $matched->photo,
                    ]);
                    $updated++;
                } else {
                    Topper::create([
                        'board_result_id' => $boardResult->id,
                        'tenant_id' => $this->school->id,
                        'name' => $row['name'],
                        'roll_no' => $row['roll_no'] ?? null,
                        'admission_no' => $row['admission_no'] ?? null,
                        'gender' => $row['gender'] ?? null,
                        'stream' => $resolved[$i]['stream_label'] ?? $row['stream'] ?? null,
                        'stream_id' => $resolved[$i]['stream_id'],
                        'total_marks' => $totalMarks,
                        'marks_obtained' => $marksObtained,
                        'percentage' => $percentage,
                        'rank' => $nextRank++,
                        'photo' => $photoPath,
                    ]);
                    $created++;
                }
            }

            app(SubjectStatsNormalizer::class)->rebuild($boardResult->fresh(['toppers']));

            return $created;
        });
    }

    public function submit(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'This result cannot be submitted in its current state.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

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
        if ($academicYear === '') {
            $active = AcademicYearRecord::query()->where('status', 'active')->first();
            $academicYear = $active?->label ?? ((date('Y') - 1).'-'.substr((string) date('Y'), 2));
        }

        $yearService = app(BoardResultAcademicYearService::class);
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

        return $this->inertia('School/BoardResults/SubjectToppers', array_merge([
            'boardResult' => $boardResult,
            'academicYear' => $academicYear,
            'academicYearOptions' => AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label', 'status']),
        ], $this->topperContext($boardResult)));
    }

    public function toppers(string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        $boardResult->load(['toppers.subjectMarks', 'toppers.examStream', 'uploads']);

        return $this->inertia('School/BoardResults/Toppers', array_merge(
            ['boardResult' => $boardResult],
            $this->topperContext($boardResult),
        ));
    }

    /** Shared topper-entry context used by both the standalone Toppers page and the inline Index search/entry flow. */
    private function topperContext(BoardResult $boardResult): array
    {
        $isClass12 = (int) $boardResult->class === 12;
        $sahodayaId = (string) $this->school->parent_id;
        $streamOptions = $isClass12 ? BoardExamSubjects::class12StreamLabels($sahodayaId) : [];

        return [
            'isClass12' => $isClass12,
            'streamOptions' => $streamOptions,
            'standardSubjects' => BoardExamSubjects::standardBoardSubjects($sahodayaId),
            'subjectsByStream' => $isClass12 ? collect($streamOptions)
                ->mapWithKeys(fn ($label, $key) => [$key => BoardExamSubjects::subjectsForStream($key, $sahodayaId)])
                ->all() : [],
            'subjectWiseLeaders' => $isClass12 ? BoardExamSubjects::subjectWiseLeaders($boardResult->toppers) : [],
            'canEdit' => $boardResult->isEditable(),
            'editLockReason' => $boardResult->isEditable() ? null : $boardResult->editLockReason(),
            'topperCap' => app(TopperCountService::class)->resolveCap($sahodayaId, (int) $boardResult->class),
            'topperCount' => $boardResult->toppers->count(),
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
        app(SubjectStatsNormalizer::class)->rebuild($boardResult->fresh(['toppers']));

        app(DataChangeLogger::class)->updated(
            $topper,
            'Topper updated',
            DataChangeLogger::diff($before, $topper->only(array_keys($before))),
            $this->school->id,
            'topper',
        );

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
        app(SubjectStatsNormalizer::class)->rebuild($boardResult->fresh(['toppers']));

        app(DataChangeLogger::class)->created(
            $topper,
            'Topper added',
            $this->school->id,
            'topper',
            ['name' => $topper->name, 'percentage' => $topper->percentage],
        );

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
            'rows.*.name' => 'required|string|max:255',
            'rows.*.gender' => 'required|string|in:male,female,other',
            'rows.*.roll_no' => 'nullable|string|max:64',
            'rows.*.marks' => 'required|numeric|min:0',
        ]);

        $subject = $data['subject'];
        $sahodayaId = (string) $this->school->parent_id;
        $boardResult->load(['toppers.subjectMarks']);

        return DB::transaction(function () use ($data, $boardResult, $sahodayaId, $subject) {
            BoardResult::query()->whereKey($boardResult->id)->lockForUpdate()->first();

            $nextRank = (int) (Topper::where('board_result_id', $boardResult->id)->max('rank') ?? 0) + 1;
            $created = 0;
            $updated = 0;
            $workingToppers = $boardResult->toppers;

            // Validate roll_no uniqueness across all rows submitted AND existing toppers.
            $submittedRollNos = [];
            foreach ($data['rows'] as $i => $row) {
                $rollNo = $row['roll_no'] ?? null;
                if (blank($rollNo)) {
                    continue;
                }
                // Check for duplicate within the submitted rows themselves.
                if (isset($submittedRollNos[$rollNo])) {
                    throw ValidationException::withMessages([
                        "rows.{$i}.roll_no" => "Duplicate CBSE Roll No '{$rollNo}' within the same submission. Each roll number must be unique.",
                    ]);
                }
                $submittedRollNos[$rollNo] = true;

            }

            foreach ($data['rows'] as $row) {
                $name = trim($row['name']);
                // Match by admission_no first (most reliable), then roll_no, then name
                // as last resort — prevents duplicate-name collisions.
                $topper = $workingToppers->first(
                    fn (Topper $t) => (filled($row['admission_no'] ?? null) && $t->admission_no === $row['admission_no'])
                        || (filled($row['roll_no'] ?? null) && $t->roll_no === $row['roll_no'])
                        || strtolower($t->name) === strtolower($name)
                );

                if ($topper) {
                    $subjectMarks = $topper->subject_marks;
                    $subjectMarks[$subject] = $row['marks'];

                    $topper->update([
                        'name' => $name,
                        'gender' => $row['gender'],
                        'roll_no' => filled($row['roll_no'] ?? null) ? $row['roll_no'] : $topper->roll_no,
                    ]);
                    app(TopperSubjectMarkService::class)->sync($topper, $subjectMarks);
                    $updated++;
                } else {
                    $totalMarks = 100;

                    $topper = Topper::create([
                        'board_result_id' => $boardResult->id,
                        'tenant_id' => $this->school->id,
                        'name' => $name,
                        'gender' => $row['gender'],
                        'roll_no' => $row['roll_no'] ?? null,
                        'percentage' => round((float) $row['marks'], 2),
                        'marks_obtained' => $row['marks'],
                        'total_marks' => $totalMarks,
                        'rank' => $nextRank++,
                    ]);
                    app(TopperSubjectMarkService::class)->sync($topper, [$subject => $row['marks']]);
                    $workingToppers->push($topper);
                    $created++;
                }
            }

            app(SubjectStatsNormalizer::class)->rebuild($boardResult->fresh(['toppers']));

            app(DataChangeLogger::class)->event(
                'created',
                "{$subject}: {$created} added, {$updated} updated",
                $this->school->id,
                'topper',
                $boardResult,
                ['subject' => $subject, 'created' => $created, 'updated' => $updated],
            );

            $parts = array_filter([
                $created ? "{$created} added" : null,
                $updated ? "{$updated} updated" : null,
            ]);

            return back()->with('success', implode(', ', $parts)." for {$subject}.");
        });
    }

    /** @return array<string, mixed> */
    private function validateTopper(
        Request $request,
        BoardResult $boardResult,
        bool $isClass12,
        ?Topper $exclude = null,
    ): array {
        $rules = [
            'name' => 'required|string|max:255',
            'admission_no' => 'nullable|string|max:64',
            'roll_no' => 'nullable|string|max:64',
            'gender' => 'required|string|in:male,female,other',
            'percentage' => 'required|numeric|min:0|max:100',
            'total_marks' => 'nullable|integer|min:0',
            'marks_obtained' => 'nullable|numeric|min:0',
            'stream' => 'nullable|string|max:100',
            'stream_id' => 'nullable|integer',
            'rank' => 'nullable|integer|min:1',
            'is_perfect_scorer' => 'boolean',
            'photo' => 'nullable|image|max:4096',
        ];

        if ($isClass12) {
            $rules['stream_key'] = 'nullable|string|max:50';
            $rules['subject_marks'] = 'nullable|array';
            $rules['subject_marks.*'] = 'nullable|numeric|min:0';
        }

        $data = $request->validate($rules);
        $data = PersistDefaults::coalesce($data, ['rank' => 1]);

        $rank = (int) ($data['rank'] ?? 1);
        $duplicate = Topper::query()
            ->where('board_result_id', $boardResult->id)
            ->where('rank', $rank)
            ->when($exclude, fn ($q) => $q->where('id', '!=', $exclude->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'rank' => "Rank {$rank} is already assigned to another topper for this result.",
            ]);
        }

        // Validate roll_no uniqueness within this board result.
        if (filled($data['roll_no'] ?? null)) {
            $rollNoTaken = Topper::query()
                ->where('board_result_id', $boardResult->id)
                ->where('roll_no', $data['roll_no'])
                ->when($exclude, fn ($q) => $q->where('id', '!=', $exclude->id))
                ->exists();
            if ($rollNoTaken) {
                throw ValidationException::withMessages([
                    'roll_no' => "CBSE Roll No '{$data['roll_no']}' is already assigned to another topper in this result.",
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

    public function destroyTopper(string $tenantId, BoardResult $boardResult, Topper $topper)
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

        $topper->delete();
        app(SubjectStatsNormalizer::class)->rebuild($boardResult->fresh(['toppers']));

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
}
