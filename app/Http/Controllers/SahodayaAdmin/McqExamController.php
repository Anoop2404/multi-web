<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\AcademicYear;
use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\Tenant;
use App\Services\Fees\ProgramFeeReceiptMailer;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Services\Mcq\McqExamNotifier;
use App\Services\Mcq\McqRankingService;
use App\Services\Mcq\McqRegistrationApprovalService;
use App\Services\Mcq\McqSchoolFeeService;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\ExcelExport;
use App\Support\FestClassGroupScheme;
use App\Support\Mcq\McqExamEligibilityConfig;
use App\Support\Mcq\McqExamPayload;
use App\Support\Mcq\McqExamLevelLabels;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class McqExamController extends SahodayaAdminController
{
    public function index()
    {
        $exams = McqExam::where('tenant_id', $this->sahodaya->id)
            ->with(['series:id,title', 'parentExam:id,title,exam_level'])
            ->withCount('registrations')
            ->orderByDesc('scheduled_at')
            ->get()
            ->map(fn (McqExam $exam) => array_merge($exam->toArray(), [
                'level_label'            => McqExamLevelLabels::levelLabel((int) ($exam->exam_level ?? 1)),
                'exam_type_label'        => McqExamLevelLabels::examTypeLabel($exam->exam_type),
                'eligibility_mode_label' => McqExamLevelLabels::eligibilityModeLabel(
                    $exam->eligibility_mode,
                    $exam->cutoff_score !== null ? (float) $exam->cutoff_score : null,
                    $exam->top_rank_count,
                ),
                'series_title' => $exam->series?->title,
            ]));

        $activeStatuses = ['published', 'registration_open', 'ongoing'];

        $masterData = app(EffectiveMasterDataResolver::class);

        $exams = $exams->map(fn (array $row) => array_merge($row, [
            'eligibility_summary' => McqExamEligibilityConfig::summaryLabel($row['eligibility_config'] ?? null, $this->sahodaya->id),
        ]));

        return $this->inertia('Sahodaya/Mcq/Index', [
            'exams' => $exams,
            'stats' => [
                'exams'         => $exams->count(),
                'active'        => $exams->whereIn('status', $activeStatuses)->count(),
                'registrations' => (int) $exams->sum('registrations_count'),
                'published'     => $exams->where('results_published', true)->count(),
            ],
            'classCategories' => $masterData->classCategories($this->sahodaya->id)->values(),
            'masterClasses'   => $masterData->masterClasses($this->sahodaya->id)->map(fn ($c) => [
                'id'                  => $c->id,
                'name'                => $c->name,
                'class_category_id'   => $c->class_category_id,
                'class_category_label'=> $c->classCategory?->label,
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'             => 'required|string|max:255',
            'exam_type'         => 'nullable|in:practice,assessment,competitive',
            'scheduled_at'      => 'nullable|date',
            'venue'             => 'nullable|string|max:255',
            'duration_minutes'  => 'nullable|integer|min:5|max:480',
            'total_questions'   => 'nullable|integer|min:1',
            'pass_mark'         => 'nullable|integer|min:0',
            'fee_amount'        => 'nullable|numeric|min:0',
            'eligibility_config'=> 'nullable|array',
            'delivery_mode'     => 'nullable|in:offline,online',
        ]);

        $fee = (float) ($data['fee_amount'] ?? 0);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['conductor_level'] = 'sahodaya';
        $data['status'] = 'draft';
        $data['exam_type'] = $data['exam_type'] ?? 'assessment';
        $data['delivery_mode'] = $data['delivery_mode'] ?? 'offline';
        $data['academic_year_id'] = AcademicYear::activeId();
        $data['fee_type'] = $fee > 0 ? 'flat' : 'none';
        $data['fee_amount'] = $fee > 0 ? $fee : null;
        $data['next_hall_ticket_no'] = 100;
        $data = McqExamPayload::applyDefaults($data);

        if ($error = McqExamPayload::eligibilityError($data)) {
            return back()->withErrors(['eligibility_config' => $error]);
        }

        $exam = McqExam::create($data);

        app(PlatformAuditLogger::class)->mcq($exam, 'mcq.exam.created', "MCQ exam created: {$exam->title}");

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$exam->id}")
            ->with('success', 'Exam created.');
    }

    public function show(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $exam->load(['series:id,title', 'parentExam:id,title,exam_level']);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->with(['mark', 'student', 'feeReceipt'])
            ->get();

        $schoolFees = McqSchoolFee::where('exam_id', $exam->id)
            ->with(['school', 'feeReceipt'])
            ->get();

        $masterData = app(EffectiveMasterDataResolver::class);

        $examPayload = array_merge($exam->toArray(), [
            'level_label'            => McqExamLevelLabels::levelLabel((int) ($exam->exam_level ?? 1)),
            'exam_type_label'        => McqExamLevelLabels::examTypeLabel($exam->exam_type),
            'eligibility_mode_label' => McqExamLevelLabels::eligibilityModeLabel(
                $exam->eligibility_mode,
                $exam->cutoff_score !== null ? (float) $exam->cutoff_score : null,
                $exam->top_rank_count,
            ),
            'eligibility_summary'  => McqExamEligibilityConfig::summaryLabel($exam->eligibility_config, $this->sahodaya->id),
            'eligibility_config'   => McqExamEligibilityConfig::normalize($exam->eligibility_config),
            'series_title'     => $exam->series?->title,
            'parent_exam_title'=> $exam->parentExam?->title,
            'tickets_issued'   => McqRegistration::where('exam_id', $exam->id)->whereNotNull('hall_ticket_no')->exists(),
            'has_fee'          => $exam->hasFee(),
            'tickets_issued_count' => McqRegistration::where('exam_id', $exam->id)->whereNotNull('hall_ticket_no')->count(),
        ]);

        $pendingPaymentApprovals = $schoolFees->filter(
            fn ($sf) => $sf->feeReceipt?->status === 'uploaded' || $sf->status === 'proof_uploaded'
        )->count();

        return $this->inertia('Sahodaya/Mcq/Show', [
            'exam'              => $examPayload,
            'registrations'     => $registrations,
            'schoolFees'        => $schoolFees,
            'pendingPaymentApprovals' => $pendingPaymentApprovals,
            'classCategories'   => $masterData->classCategories($this->sahodaya->id)->values(),
            'masterClasses'     => $masterData->masterClasses($this->sahodaya->id)->map(fn ($c) => [
                'id'                  => $c->id,
                'name'                => $c->name,
                'class_category_id'   => $c->class_category_id,
                'class_category_label'=> $c->classCategory?->label,
            ])->values(),
            'classGroupOptions' => collect(FestClassGroupScheme::labelsForSahodaya($this->sahodaya->id))
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
                ->values(),
        ]);
    }

    public function update(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'exam_type'        => 'nullable|in:practice,assessment,competitive',
            'scheduled_at'     => 'nullable|date',
            'venue'            => 'nullable|string|max:255',
            'hall_instructions'=> 'nullable|string|max:2000',
            'next_hall_ticket_no' => 'nullable|integer|min:1|max:99999999',
            'hall_ticket_settings' => 'nullable|array',
            'hall_ticket_settings.show_reg_no' => 'nullable|boolean',
            'hall_ticket_settings.show_school' => 'nullable|boolean',
            'hall_ticket_settings.header_title' => 'nullable|string|max:120',
            'hall_ticket_settings.footer_note' => 'nullable|string|max:500',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'total_questions'  => 'nullable|integer|min:1',
            'pass_mark'        => 'nullable|integer|min:0',
            'fee_amount'       => 'nullable|numeric|min:0',
            'eligibility_config'=> 'nullable|array',
            'eligibility_config.assignment_type' => 'nullable|in:all,category,class',
            'eligibility_config.scope' => 'nullable|in:all,filtered',
            'eligibility_config.class_category_ids' => 'nullable|array',
            'eligibility_config.class_category_ids.*' => 'integer',
            'eligibility_config.master_class_ids' => 'nullable|array',
            'eligibility_config.master_class_ids.*' => 'integer',
            'eligibility_config.class_groups' => 'nullable|array',
            'eligibility_config.class_groups.*' => 'string|max:20',
            'eligibility_config.gender' => 'nullable|in:open,male,female',
            'delivery_mode'    => 'nullable|in:offline,online',
            'status'           => 'required|in:draft,published,ongoing,completed,cancelled',
            'requires_hall_ticket' => 'nullable|boolean',
        ]);

        $fee = (float) ($data['fee_amount'] ?? 0);
        $data['fee_type'] = $fee > 0 ? 'flat' : 'none';
        $data['fee_amount'] = $fee > 0 ? $fee : null;

        if (array_key_exists('next_hall_ticket_no', $data) && $data['next_hall_ticket_no'] === null) {
            unset($data['next_hall_ticket_no']);
        }

        $data = McqExamPayload::applyDefaults($data);

        if ($error = McqExamPayload::eligibilityError($data)) {
            return back()->withErrors(['eligibility_config' => $error]);
        }

        if (in_array($data['status'], ['published', 'ongoing'], true) && $fee <= 0
            && ($data['exam_type'] ?? $exam->exam_type ?? 'assessment') !== 'practice') {
            return back()->withErrors(['fee_amount' => 'Set a per-student fee before opening registration.']);
        }

        if (array_key_exists('next_hall_ticket_no', $data) && $data['next_hall_ticket_no'] !== null) {
            $data['next_hall_ticket_no'] = McqExamPayload::nextHallTicketNo($data['next_hall_ticket_no']);
            $hasTickets = McqRegistration::where('exam_id', $exam->id)->whereNotNull('hall_ticket_no')->exists();
            abort_if($hasTickets, 422, 'Starting reg. no. cannot be changed after hall tickets are issued.');
        }

        if (isset($data['hall_ticket_settings'])) {
            $settings = $exam->settings_json ?? [];
            $settings['hall_ticket'] = array_merge($settings['hall_ticket'] ?? [], $data['hall_ticket_settings']);
            $data['settings_json'] = $settings;
            unset($data['hall_ticket_settings']);
        }

        if (array_key_exists('requires_hall_ticket', $data)) {
            $settings = $data['settings_json'] ?? ($exam->settings_json ?? []);
            $settings['requires_hall_ticket'] = (bool) $data['requires_hall_ticket'];
            $data['settings_json'] = $settings;
            unset($data['requires_hall_ticket']);
        }

        $exam->update($data);

        if (array_key_exists('fee_amount', $data) || array_key_exists('fee_type', $data)) {
            $exam->refresh();
            $feeService = app(McqSchoolFeeService::class);
            McqRegistration::where('exam_id', $exam->id)
                ->distinct()
                ->pluck('school_id')
                ->each(function ($schoolId) use ($exam, $feeService) {
                    $school = Tenant::find($schoolId);
                    if ($school) {
                        $feeService->syncForSchool($exam, $school);
                    }
                });
        }

        app(PlatformAuditLogger::class)->mcq($exam, 'mcq.exam.updated', "MCQ exam updated: {$exam->title}", [
            'status' => $data['status'] ?? $exam->status,
        ]);

        return back()->with('success', 'Exam updated.');
    }

    public function publishResults(string $tenantId, McqExam $exam, McqRankingService $ranking)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $ranked = $ranking->rankExam($exam);

        $exam->update([
            'results_published'    => true,
            'results_published_at' => now(),
        ]);

        app(McqExamNotifier::class)->resultsPublished($exam);

        app(PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.results.published',
            "MCQ results published for {$exam->title}",
            ['ranked' => $ranked],
        );

        return back()->with('success', "Results published. {$ranked} student(s) ranked.");
    }

    public function unpublishResults(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $exam->update(['results_published' => false]);

        return back()->with('success', 'Results hidden.');
    }

    public function storeMark(Request $request, string $tenantId, McqExam $exam, McqRegistration $registration)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 403);

        $data = $request->validate([
            'correct_count'     => 'required|integer|min:0',
            'wrong_count'       => 'required|integer|min:0',
            'unanswered_count'  => 'required|integer|min:0',
            'score'             => 'required|numeric|min:0',
            'grade'             => 'nullable|in:A,B,C,D,F',
        ]);

        $total = $data['correct_count'] + $data['wrong_count'] + $data['unanswered_count'];
        $data['percentage'] = $total > 0 ? round(($data['score'] / max($exam->total_questions, 1)) * 100, 2) : 0;
        $data['locked_by'] = $request->user()->id;
        $data['locked_at'] = now();

        McqMark::updateOrCreate(['registration_id' => $registration->id], $data);
        $registration->update(['status' => 'submitted', 'submitted_at' => now()]);

        app(\App\Services\Audit\PlatformAuditLogger::class)->mcqRegistration(
            $registration->fresh(['exam']),
            'mcq.mark.entered',
            "Mark entered for registration #{$registration->id}",
        );

        $presentCount = McqRegistration::where('exam_id', $exam->id)->where('attendance_status', 'present')->count();
        $markedCount = McqMark::whereHas('registration', fn ($q) => $q->where('exam_id', $exam->id))->count();
        if ($presentCount > 0 && $markedCount >= $presentCount) {
            app(\App\Services\Mcq\McqRankingService::class)->rankExam($exam);
        }

        return back()->with('success', 'Marks saved.');
    }

    public function approveFee(Request $request, string $tenantId, McqExam $exam, McqRegistration $registration)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 403);

        $registration->loadMissing(['exam', 'student', 'school']);

        $receipt = $registration->feeReceipt;
        abort_unless($receipt && $receipt->status === 'uploaded', 422, 'No uploaded proof to approve.');

        $receipt->update([
            'status'      => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $issued = app(ProgramFeeReceiptService::class)->issueMcqRegistration(
            $registration->fresh(['exam', 'student', 'school']),
            $receipt->fresh(),
        );

        app(McqRegistrationApprovalService::class)->approve($registration->fresh(['exam', 'student', 'feeReceipt']), $request->user()->id);

        app(ProgramFeeReceiptMailer::class)->sendApproved(
            $registration->school,
            $issued,
            'MCQ exam fee',
            ($registration->exam?->title ?? 'MCQ Exam').' — '.($registration->student?->name ?? 'Student'),
            adminPath: 'payments',
        );

        app(McqExamNotifier::class)->feeApproved($registration);

        return back()->with('success', 'MCQ fee approved. Registration confirmed and hall ticket issued.');
    }

    public function rejectFee(Request $request, string $tenantId, McqExam $exam, McqRegistration $registration)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 403);

        $data = $request->validate(['rejection_reason' => 'nullable|string|max:500']);

        $receipt = $registration->feeReceipt;
        if ($receipt) {
            $receipt->update([
                'status'           => 'rejected',
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'reviewed_by'      => $request->user()->id,
                'reviewed_at'      => now(),
            ]);
        }

        $registration->update(['fee_receipt_id' => null]);

        app(\App\Services\Mcq\McqExamNotifier::class)->feeRejected($registration, $data['rejection_reason'] ?? null);

        return back()->with('success', 'MCQ fee rejected. School can re-upload.');
    }

    public function approveRegistration(Request $request, string $tenantId, McqExam $exam, McqRegistration $registration)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 403);

        app(McqRegistrationApprovalService::class)->approve(
            $registration->fresh(['exam', 'student', 'feeReceipt']),
            $request->user()->id,
        );

        return back()->with('success', 'Registration approved and hall ticket issued.');
    }

    public function rejectRegistration(Request $request, string $tenantId, McqExam $exam, McqRegistration $registration)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 403);

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        app(McqRegistrationApprovalService::class)->reject(
            $registration->fresh(['exam', 'student']),
            $request->user()->id,
            $data['reason'] ?? null,
        );

        return back()->with('success', 'Registration rejected.');
    }

    public function feeProof(string $tenantId, McqExam $exam, McqRegistration $registration)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 403);

        $path = $registration->feeReceipt?->file_path;
        abort_unless($path, 404);

        return TenantStorage::downloadResponse($this->sahodaya, $path);
    }

    public function leaderboard(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($exam->results_published, 403, 'Publish results first.');

        $rows = McqMark::query()
            ->whereHas('registration', fn ($q) => $q->where('exam_id', $exam->id))
            ->with(['registration.student', 'registration.school'])
            ->orderBy('rank')
            ->limit(100)
            ->get()
            ->map(fn (McqMark $m) => [
                'rank'        => $m->rank,
                'student'     => $m->registration?->student?->name,
                'school'      => $m->registration?->school?->name,
                'score'       => $m->score,
                'grade'       => $m->grade,
            ]);

        return $this->inertia('Sahodaya/Mcq/Leaderboard', compact('exam', 'rows'));
    }

    public function exportLeaderboard(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($exam->results_published, 403);

        $rows = McqMark::query()
            ->whereHas('registration', fn ($q) => $q->where('exam_id', $exam->id))
            ->with(['registration.student', 'registration.school'])
            ->orderBy('rank')
            ->get()
            ->map(fn (McqMark $m) => [
                $m->rank,
                $m->registration?->student?->name,
                $m->registration?->school?->name,
                $m->score,
                $m->grade,
            ]);

        return ExcelExport::download('mcq-leaderboard-'.$exam->id, ['Rank', 'Student', 'School', 'Score', 'Grade'], $rows);
    }

    public function approveSchoolFee(Request $request, string $tenantId, McqExam $exam, McqSchoolFee $schoolFee)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolFee->exam_id !== $exam->id, 403);

        $approvedCount = app(McqSchoolFeeService::class)->approve($schoolFee, $request->user()->id);

        return back()->with('success', "School MCQ fee approved. {$approvedCount} registration(s) confirmed with hall tickets.");
    }

    public function uploadQuestionPaper(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'question_paper'      => 'required|file|mimes:pdf|max:10240',
            'question_paper_label'=> 'nullable|string|max:255',
        ]);

        if ($exam->question_paper_path) {
            \Illuminate\Support\Facades\Storage::disk(TenantStorage::uploadDisk())->delete($exam->question_paper_path);
        }

        $path = TenantStorage::storeUploadedFile(
            $request->file('question_paper'),
            "mcq/question-papers/{$exam->id}"
        );

        $exam->update([
            'question_paper_path'  => $path,
            'question_paper_label' => $data['question_paper_label'] ?? $exam->title,
        ]);

        app(PlatformAuditLogger::class)->mcq($exam, 'mcq.exam.question_paper_uploaded', "Question paper uploaded: {$exam->title}");

        return back()->with('success', 'Question paper uploaded and published to the public archive.');
    }

    public function destroyQuestionPaper(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        if ($exam->question_paper_path) {
            \Illuminate\Support\Facades\Storage::disk(TenantStorage::uploadDisk())->delete($exam->question_paper_path);
        }

        $exam->update([
            'question_paper_path'  => null,
            'question_paper_label' => null,
        ]);

        return back()->with('success', 'Question paper removed from archive.');
    }
}
