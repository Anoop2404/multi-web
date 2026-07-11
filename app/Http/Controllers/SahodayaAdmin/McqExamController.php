<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\AcademicYear;
use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\Tenant;
use App\Services\Fees\OfflineProgramFeeOrchestrator;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Services\Mcq\McqExamNotifier;
use App\Services\Mcq\McqRankingService;
use App\Services\Mcq\McqRegistrationApprovalService;
use App\Services\Mcq\McqSchoolFeeService;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Students\StudentVerificationGate;
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
            'school_discount_amount' => 'nullable|numeric|min:0',
            'payment_deadline'  => 'nullable|date',
            'late_fee_amount'   => 'nullable|numeric|min:0',
            'penalty_amount'    => 'nullable|numeric|min:0',
            'eligibility_config'=> 'nullable|array',
            'delivery_mode'     => 'nullable|in:offline,online',
        ]);

        $fee = (float) ($data['fee_amount'] ?? 0);
        $discount = (float) ($data['school_discount_amount'] ?? 0);
        if ($discount > $fee && $fee > 0) {
            return back()->withErrors(['school_discount_amount' => 'School discount cannot exceed the student fee.']);
        }

        $data['tenant_id'] = $this->sahodaya->id;
        $data['conductor_level'] = 'sahodaya';
        $data['status'] = 'draft';
        $data['exam_type'] = $data['exam_type'] ?? 'assessment';
        $data['delivery_mode'] = $data['delivery_mode'] ?? 'offline';
        $data['academic_year_id'] = AcademicYear::activeId();
        $data['fee_type'] = $fee > 0 ? 'flat' : 'none';
        $data['fee_amount'] = $fee > 0 ? $fee : null;
        $data['school_discount_amount'] = $fee > 0 && $discount > 0 ? $discount : null;
        $data['next_hall_ticket_no'] = 100;
        $data = McqExamPayload::applyDefaults($data);

        if ($error = McqExamPayload::eligibilityError($data)) {
            return back()->withErrors(['eligibility_config' => $error]);
        }

        $exam = McqExam::create($data);

        app(PlatformAuditLogger::class)->mcq($exam, 'mcq.exam.created', "Talent Search exam created: {$exam->title}");

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

        $ledgerAccount = app(\App\Services\Ledger\LedgerAccountSetupService::class)
            ->mcqLedgerMeta($exam, $this->sahodaya->id);

        return $this->inertia('Sahodaya/Mcq/Show', [
            'exam'              => $examPayload,
            'registrations'     => $registrations,
            'schoolFees'        => $schoolFees,
            'pendingPaymentApprovals' => $pendingPaymentApprovals,
            'ledgerAccount'     => [
                'code'       => $ledgerAccount['code'],
                'name'       => $ledgerAccount['name'],
                'head_id'    => $ledgerAccount['head_id'],
                'ledger_url' => $ledgerAccount['ledger_url'],
            ],
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
            'gradeMasters' => \App\Models\McqGradeMaster::where('tenant_id', $this->sahodaya->id)->where('is_active', true)->orderBy('title')->get(['id', 'title', 'is_default']),
            'hallTicketTemplates' => \App\Models\McqHallTicketTemplate::where('tenant_id', $this->sahodaya->id)->where('is_active', true)->orderBy('title')->get(['id', 'title', 'is_default']),
            'certificateTemplates' => \App\Models\McqCertificateTemplate::where('tenant_id', $this->sahodaya->id)->where('is_active', true)->orderBy('title')->get(['id', 'title', 'is_default']),
            'gradeBands' => app(\App\Services\Mcq\McqGradeService::class)->bandsForExam($exam),
            'clusterRequireStudentVerification' => app(StudentVerificationGate::class)
                ->requiredGlobally($this->sahodaya->id),
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
            'school_discount_amount' => 'nullable|numeric|min:0',
            'payment_deadline' => 'nullable|date',
            'late_fee_amount'  => 'nullable|numeric|min:0',
            'penalty_amount'   => 'nullable|numeric|min:0',
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
            'grade_master_id' => 'nullable|integer|exists:mcq_grade_masters,id',
            'hall_ticket_template_id' => 'nullable|integer|exists:mcq_hall_ticket_templates,id',
            'certificate_template_id' => 'nullable|integer|exists:mcq_certificate_templates,id',
            'student_verification_mode' => 'nullable|in:inherit,required,optional',
        ]);

        $fee = (float) ($data['fee_amount'] ?? 0);
        $discount = (float) ($data['school_discount_amount'] ?? 0);
        if ($discount > $fee && $fee > 0) {
            return back()->withErrors(['school_discount_amount' => 'School discount cannot exceed the student fee.']);
        }
        $data['fee_type'] = $fee > 0 ? 'flat' : 'none';
        $data['fee_amount'] = $fee > 0 ? $fee : null;
        $data['school_discount_amount'] = $fee > 0 && $discount > 0 ? $discount : null;

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

        $verificationMode = $data['student_verification_mode'] ?? null;
        unset($data['student_verification_mode']);
        if ($verificationMode !== null) {
            $settings = $data['settings_json'] ?? ($exam->settings_json ?? []);
            if ($verificationMode === 'inherit') {
                unset($settings['require_verified_students']);
            } elseif ($verificationMode === 'required') {
                $settings['require_verified_students'] = true;
            } elseif ($verificationMode === 'optional') {
                $settings['require_verified_students'] = false;
            }
            $data['settings_json'] = $settings;
        }

        $exam->update($data);

        if (array_key_exists('fee_amount', $data) || array_key_exists('fee_type', $data) || array_key_exists('school_discount_amount', $data)) {
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

        app(PlatformAuditLogger::class)->mcq($exam, 'mcq.exam.updated', "Talent Search exam updated: {$exam->title}", [
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
            "Talent Search results published for {$exam->title}",
            ['ranked' => $ranked],
        );

        return back()->with('success', "Results published. {$ranked} student(s) ranked.");
    }

    public function unpublishResults(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $exam->update(['results_published' => false]);

        app(PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.results.unpublished',
            "Talent Search results unpublished (reopened for correction) for {$exam->title} by {$request->user()?->name}",
        );

        return back()->with('success', 'Results hidden. Marks can now be edited; publish again when corrections are complete.');
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
            'grade'             => 'nullable|string|max:20',
        ]);

        app(\App\Services\Mcq\McqMarkSaveService::class)->save($exam, $registration, $data, $request->user()->id);

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

        app(OfflineProgramFeeOrchestrator::class)->notifyApproved(
            $registration->school,
            $issued,
            'Talent Search exam fee',
            ($registration->exam?->title ?? 'Talent Search Exam').' — '.($registration->student?->name ?? 'Student'),
            adminPath: 'payments',
        );

        app(McqExamNotifier::class)->feeApproved($registration);

        return back()->with('success', 'Talent Search fee approved. Registration confirmed and hall ticket issued.');
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

        return back()->with('success', 'Talent Search fee rejected. School can re-upload.');
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

        return back()->with('success', "School Talent Search fee approved. {$approvedCount} registration(s) confirmed with hall tickets.");
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

    public function ledger(string $tenantId, McqExam $exam, \App\Services\Ledger\LedgerReportingService $reporting)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        app(\App\Services\Ledger\LedgerAccountSetupService::class)->ensureMcqExamHead($exam);

        $ledger = $reporting->mcqExamPaymentLedger($exam);

        return $this->inertia('Sahodaya/Mcq/FeeLedger', [
            'exam'           => $exam->only('id', 'title', 'status', 'fee_type', 'fee_amount'),
            'accountCode'    => $ledger['account_code'],
            'accountName'    => $ledger['account_name'],
            'transactions'   => $ledger['transactions'],
            'schoolPayments' => $ledger['school_payments'],
            'summary'        => $ledger['summary'],
        ]);
    }

    public function updateLedgerAccount(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $setup = app(\App\Services\Ledger\LedgerAccountSetupService::class);
        $head = $setup->ensureMcqExamHead($exam);
        $setup->updateHeadName($head, $data['name']);

        return back()->with('success', 'Ledger account name saved.');
    }
}
