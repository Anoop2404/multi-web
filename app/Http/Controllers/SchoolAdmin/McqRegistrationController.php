<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FeeReceipt;
use App\Models\McqExam;
use App\Models\McqExamSeries;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Mcq\McqEligibilityService;
use App\Services\Mcq\McqRegistrationApprovalService;
use App\Services\Mcq\McqRegistrationPortalService;
use App\Services\Mcq\McqSchoolFeeService;
use App\Support\Mcq\McqExamEligibilityConfig;
use App\Support\Mcq\McqExamLevelLabels;
use App\Support\Mcq\McqResultPresenter;
use App\Support\TenantStorage;
use App\Services\Notifications\SahodayaAdminNotifier;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class McqRegistrationController extends SchoolAdminController
{
    public function index(array $hubStats = [])
    {
        $sahodayaId = $this->school->parent_id;

        $examModels = McqExam::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->with(['series:id,title', 'parentExam:id,title,exam_level'])
            ->orderByDesc('scheduled_at')
            ->get();

        $students = Student::where('tenant_id', $this->school->id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'reg_no', 'school_class_id', 'gender']);

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
            'seriesGroups', 'standaloneExams', 'registrations', 'hubStats'
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
        abort_if(! in_array($exam->status, ['published', 'ongoing'], true), 422, 'Registration is closed for this exam.');

        $student = Student::findOrFail($data['student_id']);
        abort_if($student->tenant_id !== $this->school->id, 403);
        $this->assertEligible($exam, $student);

        $approvalStatus = app(McqRegistrationApprovalService::class)->initialApprovalStatus($exam);

        $registration = McqRegistration::firstOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $student->id],
            [
                'school_id'       => $this->school->id,
                'status'          => 'registered',
                'approval_status' => $approvalStatus,
            ]
        );

        if (! $registration->wasRecentlyCreated) {
            return back()->with('success', 'Student is already registered for this exam.');
        }

        $portalCredentials = app(McqRegistrationPortalService::class)->provisionOne($student);

        app(McqSchoolFeeService::class)->syncForSchool($exam, $this->school);

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration,
            'mcq.registration.created',
            "School registered {$student->name} for {$exam->title}",
        );
        app(\App\Services\Mcq\McqExamNotifier::class)->registrationConfirmed($registration);

        return $this->registrationResponse(
            'Student registered for exam. Upload batch fee proof after Sahodaya sets the fee (or once calculated on the Fee tab).',
            $portalCredentials,
        );
    }

    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'exam_id'         => 'required|exists:mcq_exams,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'student_ids'     => 'nullable|array',
            'student_ids.*'   => 'integer|exists:students,id',
        ]);

        $exam = McqExam::findOrFail($data['exam_id']);
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        abort_if(! in_array($exam->status, ['published', 'ongoing'], true), 422, 'Registration is closed for this exam.');

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

        $registered = 0;
        $approvalStatus = app(McqRegistrationApprovalService::class)->initialApprovalStatus($exam);
        $newlyRegisteredStudents = collect();

        foreach ($studentIds as $studentId) {
            $student = Student::find($studentId);
            if (! $student || $student->tenant_id !== $this->school->id) {
                continue;
            }

            if (! $this->studentEligible($exam, $student)) {
                continue;
            }

            $registration = McqRegistration::firstOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $student->id],
                [
                    'school_id'       => $this->school->id,
                    'status'          => 'registered',
                    'approval_status' => $approvalStatus,
                ]
            );

            if ($registration->wasRecentlyCreated) {
                $registered++;
                $newlyRegisteredStudents->push($student);
            }
        }

        app(McqSchoolFeeService::class)->syncForSchool($exam, $this->school);

        $portalCredentials = app(McqRegistrationPortalService::class)->provisionForStudents($newlyRegisteredStudents);

        app(PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.registration.bulk',
            "School bulk-registered {$registered} student(s) for {$exam->title}",
            ['school_id' => $this->school->id, 'count' => $registered],
        );

        return $this->registrationResponse(
            "{$registered} student(s) registered. Upload batch fee proof on the Fee tab once Sahodaya sets the per-student amount.",
            $portalCredentials,
        );
    }

    /** @param  list<array<string, mixed>>  $portalCredentials */
    private function registrationResponse(string $message, array $portalCredentials)
    {
        $payload = ['success' => $message];
        if ($portalCredentials !== []) {
            $payload['studentPortalCredentials'] = $portalCredentials;
        }

        return back()->with($payload);
    }

    public function uploadSchoolPayment(Request $request, string $tenantId, McqExam $exam, McqSchoolFeeService $feeService)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        abort_unless($exam->hasFee(), 422, 'This exam requires a per-student fee.');

        $schoolFee = $feeService->syncForSchool($exam, $this->school);
        abort_if($schoolFee->total_due <= 0, 422, 'No fee due for this exam.');

        $data = $request->validate([
            'payment_proof'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'transaction_ref' => 'nullable|string|max:100',
        ]);

        $path = TenantStorage::storeUploadedFile(
            $request->file('payment_proof'),
            "mcq-payments/{$this->school->id}"
        );

        $receipt = FeeReceipt::create([
            'feeable_type'        => McqSchoolFee::class,
            'feeable_id'          => $schoolFee->id,
            'file_path'           => $path,
            'transaction_ref'     => $data['transaction_ref'] ?? null,
            'payment_date'        => now()->toDateString(),
            'amount'              => $schoolFee->total_due,
            'status'              => 'uploaded',
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        $schoolFee->update(['fee_receipt_id' => $receipt->id, 'status' => 'proof_uploaded']);

        app(SahodayaAdminNotifier::class)->notifyAdmins(
            $this->school->parent_id,
            'payment.proof.uploaded',
            ['school_name' => $this->school->name, 'context_label' => $exam->title.' MCQ batch fee'],
            "/sahodaya-admin/{$this->school->parent_id}/mcq-exams/{$exam->id}"
        );

        app(PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.fee.proof_uploaded',
            "School {$this->school->name} uploaded MCQ batch fee proof for {$exam->title}",
            ['school_id' => $this->school->id],
        );

        return back()->with('success', 'Batch MCQ fee proof uploaded.');
    }

    private function assertEligible(McqExam $exam, Student $student): void
    {
        $service = app(McqEligibilityService::class);
        if ($service->isEligible($exam, $student)) {
            return;
        }

        $level = (int) ($exam->exam_level ?? 1);
        if ($level > 1) {
            $modeLabel = McqExamLevelLabels::eligibilityModeLabel(
                $exam->eligibility_mode,
                $exam->cutoff_score !== null ? (float) $exam->cutoff_score : null,
                $exam->top_rank_count,
            );
            abort(422, "{$student->name} did not qualify for Level {$level} ({$modeLabel}).");
        }

        abort(422, "{$student->name} is not eligible for this exam (class/gender rules).");
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
                'context_label' => $exam->title.' MCQ fee',
            ],
            "/sahodaya-admin/{$this->school->parent_id}/mcq-exams/{$exam->id}"
        );

        return back()->with('success', 'MCQ fee proof uploaded.');
    }
}
