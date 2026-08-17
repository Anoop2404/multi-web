<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Student;
use App\Services\Mcq\McqRegistrationApprovalService;
use App\Services\Mcq\McqRegistrationGateService;
use App\Support\Mcq\McqExamEligibilityConfig;
use App\Support\Mcq\McqResultPresenter;
use Illuminate\Http\Request;

class McqApiController extends SchoolApiController
{
    public function index()
    {
        $exams = McqExam::where('tenant_id', $this->school->parent_id)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->orderByDesc('scheduled_at')
            ->get();

        $registrations = McqRegistration::where('school_id', $this->school->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->with(['exam', 'mark', 'student'])
            ->get()
            ->map(function (McqRegistration $reg) {
                return array_merge(
                    McqResultPresenter::forExamList($reg->exam, $reg),
                    [
                        'student' => $reg->student?->only('id', 'name', 'admission_number', 'reg_no'),
                    ]
                );
            });

        return response()->json(['data' => ['exams' => $exams, 'registrations' => $registrations]]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'exam_id'    => 'required|exists:mcq_exams,id',
            'student_id' => 'required|exists:students,id',
        ]);

        $exam = McqExam::findOrFail($data['exam_id']);
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        $student = Student::findOrFail($data['student_id']);
        abort_if($student->tenant_id !== $this->school->id, 403);

        // Mobile API bypass fix: this used to create a registration with only the
        // tenant/school ownership checks above — no eligibility, fee-payment, or
        // registration-window validation, unlike the web registration flow. Reuse
        // the exact same gate the web controller uses (SchoolAdmin\McqRegistrationController::store())
        // so both paths enforce identical rules and can't drift apart again.
        abort_unless(
            McqExamEligibilityConfig::allowsStudents($exam->eligibility_config),
            422,
            'This exam is not open to students.',
        );

        app(McqRegistrationGateService::class)->assertCanRegister($exam, $this->school, $student);

        $approvalStatus = app(McqRegistrationApprovalService::class)->initialApprovalStatus($exam);

        $registration = McqRegistration::firstOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $student->id],
            ['school_id' => $this->school->id, 'status' => 'registered', 'approval_status' => $approvalStatus]
        );

        return response()->json(['data' => $registration->load('exam')], 201);
    }
}
