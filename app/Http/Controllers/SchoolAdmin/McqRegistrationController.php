<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\ManagesStudentPortalCredentials;
use App\Models\FeeReceipt;
use App\Models\McqExam;
use App\Models\McqExamSeries;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Mcq\McqEligibilityService;
use App\Services\Mcq\McqExamNotifier;
use App\Services\Mcq\McqRegistrationApprovalService;
use App\Services\Mcq\McqRegistrationGateService;
use App\Services\Mcq\McqRegistrationPortalService;
use App\Services\Mcq\McqSchoolFeeService;
use App\Services\School\SchoolDocumentDownloadGateService;
use App\Support\Mcq\McqExamEligibilityConfig;
use App\Support\Mcq\McqExamLevelLabels;
use App\Support\Mcq\McqResultPresenter;
use App\Support\TenantStorage;
use App\Services\Notifications\SahodayaAdminNotifier;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class McqRegistrationController extends SchoolAdminController
{
    use ManagesStudentPortalCredentials;

    public function index(array $hubStats = [])
    {
        $registrationGate = app(McqRegistrationGateService::class)->schoolGatePayload($this->school);
        $sahodayaId = $this->school->parent_id;

        $examModels = McqExam::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->with(['series:id,title', 'parentExam:id,title,exam_level'])
            ->orderByDesc('scheduled_at')
            ->get();

        $students = Student::where('tenant_id', $this->school->id)
            ->active()
            ->with('schoolClass:id,name,class_category_id')
            ->orderBy('name')
            ->get(['id', 'name', 'reg_no', 'school_class_id', 'gender', 'verified_at']);

        $eligibility = app(McqEligibilityService::class);

        $exams = $examModels->map(function (McqExam $exam) use ($students, $eligibility, $sahodayaId) {
            $myCount = McqRegistration::where('exam_id', $exam->id)
                ->where('school_id', $this->school->id)
                ->count();

            $eligibleIds = $eligibility->eligibleStudents($exam, $students)->pluck('id');
            $registeredIds = McqRegistration::where('exam_id', $exam->id)
                ->where('school_id', $this->school->id)
                ->pluck('student_id');

            $pendingCount = $eligibleIds->diff($registeredIds)->count();

            $qualifierCount = null;
            if ((int) ($exam->exam_level ?? 1) === 1 && $exam->results_published) {
                $childExam = McqExam::where('parent_exam_id', $exam->id)->first();
                if ($childExam) {
                    $qualifierCount = $eligibility->eligibleStudents($childExam, $students)->count();
                }
            }

            $bucket = $myCount > 0 ? 'registered' : (($exam->status === 'completed') ? 'past' : 'available');

            return array_merge($exam->toArray(), [
                'my_registration_count'   => $myCount,
                'eligible_count'          => $eligibleIds->count(),
                'pending_registration_count' => $pendingCount,
                'qualifier_count'         => $qualifierCount,
                'level_label'             => McqExamLevelLabels::levelLabel((int) ($exam->exam_level ?? 1)),
                'exam_type_label'         => McqExamLevelLabels::examTypeLabel($exam->exam_type),
                'eligibility_mode_label'  => McqExamLevelLabels::eligibilityModeLabel(
                    $exam->eligibility_mode,
                    $exam->cutoff_score !== null ? (float) $exam->cutoff_score : null,
                    $exam->top_rank_count,
                ),
                'series_title'            => $exam->series?->title,
                'parent_exam_title'       => $exam->parentExam?->title,
                'eligibility_summary'     => McqExamEligibilityConfig::summaryLabel($exam->eligibility_config, $sahodayaId),
                'bucket'                  => $bucket,
                'has_fee'                 => $exam->hasFee(),
                'fee_label'               => McqExamLevelLabels::feeLabel($exam->fee_type, $exam->fee_amount),
                'status_label'            => McqExamLevelLabels::statusLabel($exam->status),
                'scheduled_at_label'      => $exam->scheduled_at?->format('j M Y, g:i A') ?? null,
                'registration_open'       => in_array($exam->status, ['published', 'ongoing'], true),
                'delivery_mode_label'     => ($exam->delivery_mode ?? 'offline') === 'online' ? 'Online' : 'Offline',
            ]);
        })
            ->filter(fn (array $e) => $e['bucket'] !== 'past' || $e['my_registration_count'] > 0)
            ->values();

        $seriesGroups = McqExamSeries::where('tenant_id', $sahodayaId)
            ->orderByDesc('id')
            ->get(['id', 'title', 'status'])
            ->map(function (McqExamSeries $series) use ($exams) {
                $seriesExams = $exams->where('series_id', $series->id)->sortBy('exam_level')->values();

                return [
                    'id'    => $series->id,
                    'title' => $series->title,
                    'exams' => $seriesExams,
                ];
            })
            ->filter(fn (array $g) => $g['exams']->isNotEmpty())
            ->values();

        $standaloneExams = $exams->whereNull('series_id')->values();

        $registrations = McqRegistration::where('school_id', $this->school->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->with(['exam', 'mark', 'feeReceipt', 'student'])
            ->get()
            ->groupBy('exam_id')
            ->map(fn ($rows) => $rows->map(function (McqRegistration $reg) {
                $row = [
                    'id'         => $reg->id,
                    'status'     => $reg->status,
                    'approval_status' => $reg->approval_status,
                    'approval_status_label' => $reg->approvalStatusLabel(),
                    'hall_ticket_no' => $reg->hall_ticket_no,
                    'student_id' => $reg->student_id,
                    'student'    => $reg->student?->only('id', 'name', 'reg_no'),
                    'fee_receipt'=> $reg->feeReceipt?->only('id', 'status'),
                ];

                if ($reg->exam?->results_published && $reg->status === 'submitted') {
                    $row['mark'] = McqResultPresenter::forRegistration($reg, $reg->mark);
                }

                return $row;
            })->values());

        return $this->inertia('School/Mcq/Index', compact(
            'seriesGroups', 'standaloneExams', 'registrations', 'hubStats', 'registrationGate'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'exam_id'    => 'required|exists:mcq_exams,id',
            'student_id' => 'required|exists:students,id',
        ]);

        $exam = McqExam::findOrFail($data['exam_id']);
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        abort_unless(
            \App\Support\Mcq\McqExamEligibilityConfig::allowsStudents($exam->eligibility_config),
            422,
            'This exam is not open to students.',
        );

        $student = Student::findOrFail($data['student_id']);
        abort_if($student->tenant_id !== $this->school->id, 403);

        app(McqRegistrationGateService::class)->assertCanRegister($exam, $this->school, $student);

        $approvalStatus = app(McqRegistrationApprovalService::class)->initialApprovalStatus($exam);

        $existing = McqRegistration::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing && ! $existing->isCancelled()) {
            return back()->with('success', 'Student is already registered for this exam.');
        }

        if ($existing) {
            // Re-register a previously cancelled student, reusing the same registration id.
            $existing->update([
                'school_id'            => $this->school->id,
                'status'               => 'registered',
                'approval_status'      => $approvalStatus,
                'cancelled_at'         => null,
                'cancelled_by_user_id' => null,
            ]);
            $registration = $existing->fresh();
        } else {
            $registration = McqRegistration::create([
                'exam_id'         => $exam->id,
                'student_id'      => $student->id,
                'school_id'       => $this->school->id,
                'status'          => 'registered',
                'approval_status' => $approvalStatus,
            ]);
        }

        $credential = app(McqRegistrationPortalService::class)->provisionOne($student);

        app(McqSchoolFeeService::class)->syncForSchool($exam, $this->school);

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration,
            'mcq.registration.created',
            "School registered {$student->name} for {$exam->title}",
        );
        app(\App\Services\Mcq\McqExamNotifier::class)->registrationConfirmed($registration);

        return $this->registrationResponse(
            'Student registered for exam. Upload batch fee proof after Sahodaya sets the fee (or once calculated on the Fee tab).',
            $credential ? [$credential] : [],
        );
    }

    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'exam_id'         => 'required|exists:mcq_exams,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'student_ids'     => 'nullable|array|max:2000',
            'student_ids.*'   => 'integer|exists:students,id',
        ]);

        $exam = McqExam::findOrFail($data['exam_id']);
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        abort_unless(
            \App\Support\Mcq\McqExamEligibilityConfig::allowsStudents($exam->eligibility_config),
            422,
            'This exam is not open to students.',
        );
        app(McqRegistrationGateService::class)->assertSchoolCanAccess($this->school);

        $studentIds = collect($data['student_ids'] ?? []);

        if (! empty($data['school_class_id'])) {
            $class = SchoolClass::where('tenant_id', $this->school->id)
                ->where('id', $data['school_class_id'])
                ->firstOrFail();

            $classStudentIds = Student::where('tenant_id', $this->school->id)
                ->where('school_class_id', $class->id)
                ->active()
                ->pluck('id');

            $studentIds = $studentIds->merge($classStudentIds)->unique()->values();
        }

        abort_if($studentIds->isEmpty(), 422, 'Select at least one student or a class.');

        $studentsById = Student::where('tenant_id', $this->school->id)
            ->whereIn('id', $studentIds)
            ->active()
            ->get()
            ->keyBy('id');

        $existingByStudent = McqRegistration::where('exam_id', $exam->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $registered = 0;
        $approvalStatus = app(McqRegistrationApprovalService::class)->initialApprovalStatus($exam);
        $newlyRegisteredStudents = collect();

        foreach ($studentIds as $studentId) {
            $student = $studentsById->get($studentId);
            if (! $student) {
                continue;
            }

            if (! $this->studentEligible($exam, $student)) {
                continue;
            }

            $existing = $existingByStudent->get($student->id);

            if ($existing && ! $existing->isCancelled()) {
                continue;
            }

            if ($existing) {
                $existing->update([
                    'school_id'            => $this->school->id,
                    'status'               => 'registered',
                    'approval_status'      => $approvalStatus,
                    'cancelled_at'         => null,
                    'cancelled_by_user_id' => null,
                ]);
            } else {
                McqRegistration::create([
                    'exam_id'         => $exam->id,
                    'student_id'      => $student->id,
                    'school_id'       => $this->school->id,
                    'status'          => 'registered',
                    'approval_status' => $approvalStatus,
                ]);
            }

            $registered++;
            $newlyRegisteredStudents->push($student);
        }

        app(McqSchoolFeeService::class)->syncForSchool($exam, $this->school);

        $newCredentials = app(McqRegistrationPortalService::class)->provisionForStudents($newlyRegisteredStudents);

        app(PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.registration.bulk',
            "School bulk-registered {$registered} student(s) for {$exam->title}",
            ['school_id' => $this->school->id, 'count' => $registered],
        );

        return $this->registrationResponse(
            "{$registered} student(s) registered. Upload batch fee proof on the Fee tab once Sahodaya sets the per-student amount.",
            $newCredentials,
        );
    }

    public function storeTeacher(Request $request)
    {
        $data = $request->validate([
            'exam_id'    => 'required|exists:mcq_exams,id',
            'teacher_id' => 'required|exists:teachers,id',
        ]);

        $exam = McqExam::findOrFail($data['exam_id']);
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        $teacher = \App\Models\Teacher::findOrFail($data['teacher_id']);
        abort_if($teacher->tenant_id !== $this->school->id, 403);

        app(McqRegistrationGateService::class)->assertCanRegisterTeacher($exam, $this->school, $teacher);

        $approvalStatus = app(McqRegistrationApprovalService::class)->initialApprovalStatus($exam);

        $existing = McqRegistration::where('exam_id', $exam->id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if ($existing && ! $existing->isCancelled()) {
            return back()->with('success', 'Teacher is already registered for this exam.');
        }

        if ($existing) {
            $existing->update([
                'school_id'            => $this->school->id,
                'student_id'           => null,
                'status'               => 'registered',
                'approval_status'      => $approvalStatus,
                'cancelled_at'         => null,
                'cancelled_by_user_id' => null,
            ]);
            $registration = $existing->fresh();
        } else {
            $registration = McqRegistration::create([
                'exam_id'         => $exam->id,
                'teacher_id'      => $teacher->id,
                'student_id'      => null,
                'school_id'       => $this->school->id,
                'status'          => 'registered',
                'approval_status' => $approvalStatus,
            ]);
        }

        app(McqSchoolFeeService::class)->syncForSchool($exam, $this->school);

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration,
            'mcq.registration.created',
            "School registered teacher {$teacher->name} for {$exam->title}",
        );
        app(\App\Services\Mcq\McqExamNotifier::class)->registrationConfirmed($registration);

        return back()->with('success', 'Teacher registered for exam.');
    }

    public function bulkStoreTeachers(Request $request)
    {
        $data = $request->validate([
            'exam_id'     => 'required|exists:mcq_exams,id',
            'teacher_ids' => 'required|array|min:1|max:2000',
            'teacher_ids.*' => 'integer|exists:teachers,id',
        ]);

        $exam = McqExam::findOrFail($data['exam_id']);
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        abort_unless(
            \App\Support\Mcq\McqExamEligibilityConfig::allowsTeachers($exam->eligibility_config),
            422,
            'This exam is not open to teachers.',
        );
        app(McqRegistrationGateService::class)->assertSchoolCanAccess($this->school);

        $teacherIds = collect($data['teacher_ids'])->unique()->values();
        $teachersById = \App\Models\Teacher::where('tenant_id', $this->school->id)
            ->whereIn('id', $teacherIds)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        $existingByTeacher = McqRegistration::where('exam_id', $exam->id)
            ->whereIn('teacher_id', $teacherIds)
            ->get()
            ->keyBy('teacher_id');

        $registered = 0;
        $approvalStatus = app(McqRegistrationApprovalService::class)->initialApprovalStatus($exam);
        $eligibility = app(McqEligibilityService::class);

        foreach ($teacherIds as $teacherId) {
            $teacher = $teachersById->get($teacherId);
            if (! $teacher || ! $eligibility->isTeacherEligible($exam, $teacher)) {
                continue;
            }

            $existing = $existingByTeacher->get($teacher->id);
            if ($existing && ! $existing->isCancelled()) {
                continue;
            }

            if ($existing) {
                $existing->update([
                    'school_id'            => $this->school->id,
                    'student_id'           => null,
                    'status'               => 'registered',
                    'approval_status'      => $approvalStatus,
                    'cancelled_at'         => null,
                    'cancelled_by_user_id' => null,
                ]);
            } else {
                McqRegistration::create([
                    'exam_id'         => $exam->id,
                    'teacher_id'      => $teacher->id,
                    'student_id'      => null,
                    'school_id'       => $this->school->id,
                    'status'          => 'registered',
                    'approval_status' => $approvalStatus,
                ]);
            }

            $registered++;
        }

        app(McqSchoolFeeService::class)->syncForSchool($exam, $this->school);

        app(PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.registration.bulk',
            "School bulk-registered {$registered} teacher(s) for {$exam->title}",
            ['school_id' => $this->school->id, 'count' => $registered, 'audience' => 'teachers'],
        );

        return back()->with('success', "{$registered} teacher(s) registered.");
    }

    public function cancel(Request $request, string $tenantId, McqExam $exam)
    {
        $data = $request->validate([
            'student_id' => 'nullable|integer|exists:students,id',
            'teacher_id' => 'nullable|integer|exists:teachers,id',
        ]);

        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        abort_if(empty($data['student_id']) && empty($data['teacher_id']), 422, 'Select a student or teacher to cancel.');

        $query = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id);

        if (! empty($data['teacher_id'])) {
            $query->where('teacher_id', $data['teacher_id']);
        } else {
            $query->where('student_id', $data['student_id']);
        }

        $registration = $query->first();

        abort_unless($registration, 422, 'Registration not found for this exam.');

        if ($registration->isCancelled()) {
            return back()->with('success', 'Registration is already cancelled.');
        }

        abort_unless(
            $registration->canBeCancelledBySchool(),
            422,
            'This registration can no longer be cancelled. Contact your Sahodaya to cancel it from the Exam Registrations page.',
        );

        $registration->update([
            'status'               => 'cancelled',
            'cancelled_at'         => now(),
            'cancelled_by_user_id' => $request->user()->id,
        ]);

        $name = $registration->participantName();
        $cancelReason = "MCQ registration cancelled by school for {$name} in {$exam->title}";

        app(McqSchoolFeeService::class)->syncForSchool($exam, $this->school, $cancelReason, $request->user()->id, $registration->id);
        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration->fresh(['exam', 'student', 'teacher']),
            'mcq.registration.cancelled',
            "School cancelled registration for {$name} in {$exam->title}",
        );

        try {
            app(McqExamNotifier::class)->registrationCancelledBySchool($registration->fresh(['exam', 'student', 'teacher']));
        } catch (\Throwable) {
            // non-blocking — sahodaya notification must not prevent school cancel response
        }

        return back()->with('success', 'Registration cancelled. You can re-register later if needed.');
    }

    public function resetPortalPassword(Request $request, string $tenantId, McqExam $exam, Student $student)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        abort_if($student->tenant_id !== $this->school->id, 403);

        abort_unless(
            McqRegistration::where('exam_id', $exam->id)->where('student_id', $student->id)->where('school_id', $this->school->id)->exists(),
            422,
            'Student is not registered for this exam.',
        );

        return $this->resetStudentPortalPassword($student, $request->user()->id);
    }

    public function exportCredentials(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        app(SchoolDocumentDownloadGateService::class)->assertMcqExamFeeForDownloads($exam, $this->school);

        $rows = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->with(['student.user:id,username'])
            ->orderBy('hall_ticket_no')
            ->get()
            ->map(fn (McqRegistration $reg) => [
                $reg->student?->name,
                $reg->student?->reg_no,
                $reg->student?->user?->username ?? $reg->student?->reg_no,
                $reg->hall_ticket_no,
            ]);

        return \App\Support\ExcelExport::download(
            'mcq-portal-logins-'.$exam->id,
            ['Student', 'Reg. no', 'Portal username', 'Hall ticket'],
            $rows,
        );
    }

    /** @param  list<array{student_id: int, student_name: string, username: string, password: string}>  $newCredentials */
    private function registrationResponse(string $message, array $newCredentials = [])
    {
        $flash = ['success' => $message];
        if ($newCredentials !== []) {
            $flash['mcqNewCredentials'] = $newCredentials;
        }

        return back()->with($flash);
    }

    public function uploadSchoolPayment(Request $request, string $tenantId, McqExam $exam, McqSchoolFeeService $feeService)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        abort_unless($exam->hasFee(), 422, 'This exam requires a per-student fee.');

        $schoolFee = $feeService->syncForSchool($exam, $this->school);
        abort_if($schoolFee->total_due <= 0, 422, 'No fee due for this exam.');

        $outstanding = $schoolFee->outstandingBalance();
        abort_if($outstanding <= 0, 422, 'This batch fee is already fully paid.');

        $data = $request->validate([
            'payment_proof'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'transaction_ref' => 'nullable|string|max:100',
            'amount'          => 'nullable|numeric|min:1|max:'.$outstanding,
        ]);

        $amount = round((float) ($data['amount'] ?? $outstanding), 2);

        $path = TenantStorage::storeUploadedFile(
            $request->file('payment_proof'),
            "mcq-payments/{$this->school->id}"
        );

        FeeReceipt::supersedePriorForFeeable($schoolFee);

        $receipt = FeeReceipt::create([
            'feeable_type'        => McqSchoolFee::class,
            'feeable_id'          => $schoolFee->id,
            'file_path'           => $path,
            'transaction_ref'     => $data['transaction_ref'] ?? null,
            'payment_date'        => now()->toDateString(),
            'amount'              => $amount,
            'status'              => 'uploaded',
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        $schoolFee->update(['fee_receipt_id' => $receipt->id, 'status' => 'proof_uploaded']);

        app(SahodayaAdminNotifier::class)->notifyAdmins(
            $this->school->parent_id,
            'payment.proof.uploaded',
            ['school_name' => $this->school->name, 'context_label' => $exam->title.' Talent Search batch fee'],
            "/sahodaya-admin/{$this->school->parent_id}/mcq-exams/{$exam->id}"
        );

        app(PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.fee.proof_uploaded',
            "School {$this->school->name} uploaded Talent Search batch fee proof for {$exam->title}",
            ['school_id' => $this->school->id],
        );

        return back()->with('success', 'Batch Talent Search fee proof uploaded.');
    }

    private function assertEligible(McqExam $exam, Student $student): void
    {
        $service = app(McqEligibilityService::class);
        if ($service->isEligible($exam, $student)) {
            return;
        }

        abort(422, $service->ineligibilityReason($exam, $student) ?? 'Student is not eligible for this exam.');
    }

    private function studentEligible(McqExam $exam, Student $student): bool
    {
        return app(McqEligibilityService::class)->isEligible($exam, $student);
    }

    public function uploadPayment(Request $request, string $tenantId, McqRegistration $registration)
    {
        abort_if($registration->school_id !== $this->school->id, 403);

        $exam = $registration->exam;
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        abort_unless($exam->hasFee(), 422, 'This exam requires a per-student fee.');

        $data = $request->validate([
            'payment_proof'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'transaction_ref' => 'nullable|string|max:100',
        ]);

        $path = TenantStorage::storeUploadedFile(
            $request->file('payment_proof'),
            "mcq-payments/{$this->school->id}"
        );

        FeeReceipt::supersedePriorForFeeable($registration);

        $receipt = FeeReceipt::create([
            'feeable_type'        => McqRegistration::class,
            'feeable_id'          => $registration->id,
            'file_path'           => $path,
            'transaction_ref'     => $data['transaction_ref'] ?? null,
            'payment_date'        => now()->toDateString(),
            'amount'              => $exam->fee_amount,
            'status'              => 'uploaded',
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        $registration->update(['fee_receipt_id' => $receipt->id]);

        app(SahodayaAdminNotifier::class)->notifyAdmins(
            $this->school->parent_id,
            'payment.proof.uploaded',
            [
                'school_name'   => $this->school->name,
                'context_label' => $exam->title.' Talent Search fee',
            ],
            "/sahodaya-admin/{$this->school->parent_id}/mcq-exams/{$exam->id}"
        );

        return back()->with('success', 'Talent Search fee proof uploaded.');
    }
}
