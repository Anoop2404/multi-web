<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\BoardResult;
use App\Models\BoardResultUpload;
use App\Models\DataChangeLog;
use App\Models\Topper;
use App\Services\Audit\DataChangeLogger;
use App\Services\BoardResults\BoardResultAcademicYearService;
use App\Services\BoardResults\BoardResultNotifier;
use App\Services\BoardResults\SubjectStatsNormalizer;
use App\Services\BoardResults\TopperCountService;
use App\Services\BoardResults\TopperSubjectMarkService;
use App\Support\BoardExamSubjects;
use App\Support\PersistDefaults;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BoardResultController extends SchoolAdminController
{
    public function index()
    {
        $results = BoardResult::where('tenant_id', $this->school->id)
            ->with(['toppers', 'uploads' => fn ($q) => $q->orderByDesc('version')->limit(5)])
            ->orderByDesc('academic_year')
            ->orderByDesc('class')
            ->get();

        $auditHistory = DataChangeLog::query()
            ->where('school_id', $this->school->id)
            ->whereIn('log_name', ['board_result', 'topper', 'achievement'])
            ->latest()
            ->limit(50)
            ->get(['id', 'action', 'description', 'log_name', 'subject_type', 'subject_id', 'changes', 'created_at', 'causer_user_id']);

        return $this->inertia('School/BoardResults/Index', [
            'results' => $results,
            'examinationTypes' => BoardResult::examinationTypes(),
            'statuses' => [
                BoardResult::STATUS_DRAFT,
                BoardResult::STATUS_SUBMITTED,
                BoardResult::STATUS_VERIFIED,
                BoardResult::STATUS_APPROVED,
                BoardResult::STATUS_REJECTED,
                BoardResult::STATUS_PUBLISHED,
            ],
            'auditHistory' => $auditHistory,
            'topperCap' => app(TopperCountService::class)->resolveCap(
                (string) $this->school->parent_id,
                10
            ),
        ]);
    }

    public function store(Request $request)
    {
        $yearService = app(BoardResultAcademicYearService::class);
        $data = $yearService->attachToPayload($this->validateBoardResult($request));

        $data['tenant_id'] = $this->school->id;
        $data['examination_type'] = $data['examination_type']
            ?? BoardResult::examinationTypeForClass((int) $data['class']);
        $data = PersistDefaults::coalesce($data, [
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

        $existing = BoardResult::where($keys)->first();
        if ($existing && ! $existing->isEditable()) {
            throw ValidationException::withMessages([
                'academic_year' => 'This result is locked ('.$existing->status.'). Wait for rejection before editing.',
            ]);
        }
        if ($existing) {
            $yearService->assertResultEditable($existing);
        }

        $payload = collect($data)->except(['result_pdf', 'attachments'])->all();
        if ($existing?->status === BoardResult::STATUS_REJECTED) {
            $payload['status'] = BoardResult::STATUS_DRAFT;
            $payload['rejection_reason'] = null;
        }

        $result = BoardResult::updateOrCreate($keys, $payload);
        $this->storeUploads($request, $result);

        app(DataChangeLogger::class)->event(
            $existing ? 'updated' : 'created',
            $existing ? 'Board result updated' : 'Board result created',
            $this->school->id,
            'board_result',
            $result,
            ['class' => $result->class, 'academic_year' => $result->academic_year],
        );

        return redirect("/school-admin/{$this->school->id}/board-results/{$result->id}/toppers")
            ->with('success', 'Board result saved. Now add toppers.');
    }

    public function update(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'Only draft or rejected results can be edited.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        $before = $boardResult->only([
            'class', 'examination_type', 'academic_year', 'total_appeared', 'pass_count',
            'pass_percent', 'distinctions', 'first_class', 'highest_mark', 'average_mark', 'remarks',
        ]);

        $data = app(BoardResultAcademicYearService::class)->attachToPayload(
            $this->validateBoardResult($request, $boardResult)
        );
        $data['examination_type'] = $data['examination_type']
            ?? BoardResult::examinationTypeForClass((int) $data['class']);

        if ($boardResult->status === BoardResult::STATUS_REJECTED) {
            $data['status'] = BoardResult::STATUS_DRAFT;
            $data['rejection_reason'] = null;
        }

        $boardResult->update(collect($data)->except(['result_pdf', 'attachments'])->all());
        $this->storeUploads($request, $boardResult->fresh());

        app(DataChangeLogger::class)->updated(
            $boardResult,
            'Board result updated',
            DataChangeLogger::diff($before, $boardResult->only(array_keys($before))),
            $this->school->id,
            'board_result',
        );

        return back()->with('success', 'Board result updated.');
    }

    public function submit(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'Only draft or rejected results can be submitted.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        if (! $boardResult->hasResultPdf()) {
            throw ValidationException::withMessages([
                'result_pdf' => 'Upload the CBSE result PDF before submitting for verification.',
            ]);
        }

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
        ]);

        app(DataChangeLogger::class)->event(
            'submitted',
            'Board result submitted for verification',
            $this->school->id,
            'board_result',
            $boardResult,
        );

        app(BoardResultNotifier::class)->submissionConfirmation($boardResult);

        return back()->with('success', 'Board result submitted for Sahodaya verification.');
    }

    public function uploadPdf(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'Uploads are locked for this result.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        $request->validate([
            'result_pdf' => 'required|file|mimes:pdf|max:20480',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx|max:20480',
        ]);

        $this->storeUploads($request, $boardResult);

        app(DataChangeLogger::class)->event(
            'uploaded',
            'Board result PDF uploaded',
            $this->school->id,
            'board_result',
            $boardResult,
        );

        return back()->with('success', 'Result PDF uploaded.');
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

    public function toppers(string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        $boardResult->load(['toppers.subjectMarks', 'toppers.examStream', 'uploads']);

        $isClass12 = (int) $boardResult->class === 12;
        $sahodayaId = (string) $this->school->parent_id;
        $streamOptions = $isClass12 ? BoardExamSubjects::class12StreamLabels($sahodayaId) : [];

        return $this->inertia('School/BoardResults/Toppers', [
            'boardResult' => $boardResult,
            'isClass12' => $isClass12,
            'streamOptions' => $streamOptions,
            'subjectsByStream' => $isClass12 ? collect($streamOptions)
                ->mapWithKeys(fn ($label, $key) => [$key => BoardExamSubjects::subjectsForStream($key, $sahodayaId)])
                ->all() : [],
            'subjectWiseLeaders' => $isClass12
                ? BoardExamSubjects::subjectWiseLeaders($boardResult->toppers)
                : [],
            'canEdit' => $boardResult->isEditable(),
            'topperCap' => app(TopperCountService::class)->resolveCap($sahodayaId, (int) $boardResult->class),
            'topperCount' => $boardResult->toppers->count(),
        ]);
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

    public function storeTopper(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($boardResult->isEditable(), 422, 'Toppers are locked for this result.');
        app(BoardResultAcademicYearService::class)->assertResultEditable($boardResult);

        $sahodayaId = (string) $this->school->parent_id;
        app(TopperCountService::class)->assertCanAdd($boardResult, $sahodayaId);

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
            'percentage' => 'required|numeric|min:0|max:100',
            'total_marks' => 'nullable|integer|min:0',
            'marks_obtained' => 'nullable|integer|min:0',
            'stream' => 'nullable|string|max:100',
            'stream_id' => 'nullable|integer',
            'rank' => 'nullable|integer|min:1',
            'is_perfect_scorer' => 'boolean',
            'photo' => 'nullable|image|max:4096',
        ];

        if ($isClass12) {
            $rules['stream_key'] = 'nullable|string|max:50';
            $rules['subject_marks'] = 'nullable|array';
            $rules['subject_marks.*'] = 'nullable|numeric|min:0|max:100';
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

        $sahodayaId = (string) $this->school->parent_id;

        if ($isClass12) {
            $streamKey = BoardExamSubjects::normalizeStream($data['stream_key'] ?? $data['stream'] ?? null, $sahodayaId);
            if ($streamKey) {
                $labels = BoardExamSubjects::class12StreamLabels($sahodayaId);
                $data['stream'] = $labels[$streamKey] ?? $data['stream'] ?? null;
                $data['stream_id'] = BoardExamSubjects::resolveStreamId($streamKey, $sahodayaId);
            }

            $data['subject_marks'] = BoardExamSubjects::normalizeSubjectMarks($data['subject_marks'] ?? []);
            unset($data['stream_key']);
        } else {
            unset($data['subject_marks'], $data['stream_id']);
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
            'total_appeared' => 'required|integer|min:0',
            'pass_count' => 'required|integer|min:0',
            'pass_percent' => 'required|numeric|min:0|max:100',
            'distinctions' => 'nullable|integer|min:0',
            'first_class' => 'nullable|integer|min:0',
            'highest_mark' => 'nullable|numeric|min:0|max:100',
            'average_mark' => 'nullable|numeric|min:0|max:100',
            'remarks' => 'nullable|string|max:5000',
            'result_pdf' => ($existing?->hasResultPdf() ? 'nullable' : 'nullable').'|file|mimes:pdf|max:20480',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx|max:20480',
        ]);

        if ((int) $data['pass_count'] > (int) $data['total_appeared']) {
            throw ValidationException::withMessages([
                'pass_count' => 'Pass count cannot exceed total appeared.',
            ]);
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
            $nextVersion = (int) $result->uploads()->where('file_type', 'pdf')->max('version') + 1;

            BoardResultUpload::create([
                'board_result_id' => $result->id,
                'tenant_id' => $this->school->id,
                'version' => max(1, $nextVersion),
                'file_path' => $path,
                'storage_disk' => $disk,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => 'pdf',
                'uploaded_by' => $request->user()?->id,
            ]);

            $result->update([
                'result_pdf_path' => $path,
                'result_pdf_disk' => $disk,
            ]);
        }

        if ($request->hasFile('attachments')) {
            $paths = $result->attachment_paths ?? [];
            foreach ($request->file('attachments') as $file) {
                $path = TenantStorage::storeUploadedFile($file, $dir.'/attachments', $disk);
                $paths[] = [
                    'path' => $path,
                    'disk' => $disk,
                    'name' => $file->getClientOriginalName(),
                ];
                $nextVersion = (int) $result->uploads()->where('file_type', 'attachment')->max('version') + 1;
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
            $result->update(['attachment_paths' => $paths]);
        }
    }
}
