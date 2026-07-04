<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\Student;
use App\Services\Mcq\McqEligibilityService;
use App\Services\Mcq\McqReportService;
use App\Services\Mcq\McqSchoolFeeService;
use App\Support\Mcq\McqExamEligibilityConfig;
use App\Support\Mcq\McqExamLevelLabels;
use App\Support\Mcq\McqResultPresenter;
use Illuminate\Http\Request;

class McqController extends SchoolAdminController
{
    public function hub(McqRegistrationController $registrations)
    {
        $sahodayaId = $this->school->parent_id;
        $exams = McqExam::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->orderByDesc('scheduled_at')
            ->get();

        $registeredCount = McqRegistration::where('school_id', $this->school->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->count();

        $hubStats = [
            'available'   => $exams->whereIn('status', ['published', 'ongoing'])->count(),
            'registered'  => $exams->filter(fn (McqExam $e) => McqRegistration::where('exam_id', $e->id)->where('school_id', $this->school->id)->exists())->count(),
            'completed'   => $exams->where('status', 'completed')->where('results_published', true)->count(),
            'total_regs'  => $registeredCount,
        ];

        return $registrations->index($hubStats);
    }

    public function exam(string $tenantId, McqExam $exam, string $tab = 'register')
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        $exam->load(['series:id,title', 'parentExam:id,title']);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->with(['student.user:id,username', 'mark', 'feeReceipt'])
            ->orderBy('hall_ticket_no')
            ->get()
            ->map(function (McqRegistration $reg) use ($exam) {
                $row = $reg->toArray();
                $row['student'] = $reg->student?->only('id', 'name', 'reg_no');
                $row['portal_username'] = $reg->student?->user?->username;
                $row['approval_status_label'] = $reg->approvalStatusLabel();
                if ($exam->results_published && $reg->mark) {
                    $row['mark'] = McqResultPresenter::forRegistration($reg, $reg->mark);
                }

                return $row;
            });

        $schoolFee = McqSchoolFee::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->with('feeReceipt')
            ->first();

        $allStudents = Student::where('tenant_id', $this->school->id)
            ->active()
            ->with('schoolClass:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'reg_no', 'school_class_id', 'gender', 'user_id']);

        $eligibleStudents = app(McqEligibilityService::class)->eligibleStudents($exam, $allStudents);

        $registeredIds = $registrations->pluck('student_id')->filter()->all();
        $registeredIdSet = array_flip($registeredIds);
        $ticketsIssuedCount = $registrations->filter(fn ($r) => ! empty($r['hall_ticket_no']))->count();

        $students = $eligibleStudents->map(fn (Student $s) => [
            'id'               => $s->id,
            'name'             => $s->name,
            'reg_no'           => $s->reg_no,
            'gender'           => $s->gender,
            'class_name'       => $s->schoolClass?->name,
            'school_class_id'  => $s->school_class_id,
            'has_portal_login' => (bool) $s->user_id,
            'registered'       => isset($registeredIdSet[$s->id]),
        ])->values();

        $classOptions = $this->schoolClasses()->map(function ($class) use ($students) {
            $eligibleInClass = $students->where('school_class_id', $class->id)->where('registered', false);

            return [
                'id'             => $class->id,
                'name'           => $class->name,
                'eligible_count' => $eligibleInClass->count(),
            ];
        })->values();

        $canRegister = in_array($exam->status, ['published', 'ongoing'], true);

        $reportService = app(McqReportService::class);
        $reportRows = in_array($tab, ['reports'], true) ? $reportService->registrationRows($exam, $this->school->id) : [];
        $toppers = in_array($tab, ['toppers', 'results'], true) && $exam->results_published
            ? $reportService->schoolToppers($exam, $this->school->id)
            : [];

        return $this->inertia('School/Mcq/ExamDetail', [
            'exam' => array_merge($exam->toArray(), [
                'fee_label'              => McqExamLevelLabels::feeLabel($exam->fee_type, $exam->fee_amount),
                'scheduled_at_label'     => $exam->scheduled_at?->format('j M Y, g:i A'),
                'has_fee'                => $exam->hasFee(),
                'level_label'            => McqExamLevelLabels::levelLabel((int) ($exam->exam_level ?? 1)),
                'exam_type_label'        => McqExamLevelLabels::examTypeLabel($exam->exam_type),
                'eligibility_summary'    => McqExamEligibilityConfig::summaryLabel($exam->eligibility_config, $exam->tenant_id),
                'delivery_mode_label'    => $exam->isOnlineDelivery() ? 'Online' : 'Offline',
                'registration_open'      => in_array($exam->status, ['published', 'ongoing'], true),
                'status_label'           => McqExamLevelLabels::statusLabel($exam->status),
                'series_title'           => $exam->series?->title,
                'parent_exam_title'      => $exam->parentExam?->title,
            ]),
            'tab'                    => $tab,
            'registrations'          => $registrations,
            'schoolFee'              => $schoolFee,
            'students'               => $students,
            'classOptions'           => $classOptions,
            'registeredStudentIds'   => $registeredIds,
            'ticketsIssuedCount'     => $ticketsIssuedCount,
            'studentPortalCredentials' => session('studentPortalCredentials'),
            'registerStats'          => [
                'eligible'    => $students->count(),
                'available'   => $students->where('registered', false)->count(),
                'registered'  => $registrations->count(),
                'batch_due'   => (float) ($schoolFee?->total_due ?? 0),
                'can_register'=> $canRegister,
            ],
            'portalLoginUrl'         => url('/portal/login'),
            'reportRows'             => $reportRows,
            'toppers'                => $toppers,
            'reportExports'          => [
                'registration' => "/school-admin/{$this->school->id}/mcq/{$exam->id}/reports/registration/export",
                'attendance'   => "/school-admin/{$this->school->id}/mcq/{$exam->id}/reports/attendance/export",
            ],
        ]);
    }

    public function register(Request $request, string $tenantId, McqExam $exam)
    {
        $request->merge(['exam_id' => $exam->id]);

        return app(McqRegistrationController::class)->store($request);
    }

    public function bulkRegister(Request $request, string $tenantId, McqExam $exam)
    {
        $request->merge(['exam_id' => $exam->id]);

        return app(McqRegistrationController::class)->bulkStore($request);
    }

    public function uploadFee(Request $request, string $tenantId, McqExam $exam)
    {
        return app(McqRegistrationController::class)->uploadSchoolPayment($request, $tenantId, $exam);
    }

    public function hallTicketsPdf(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->whereNotNull('hall_ticket_no')
            ->with(['student'])
            ->orderBy('hall_ticket_no')
            ->get();

        return view('mcq.hall-tickets-bulk', compact('exam', 'registrations'));
    }
}
