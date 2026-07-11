<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Services\Mcq\McqEligibilityService;
use App\Services\Mcq\McqRegistrationApprovalService;
use App\Services\Mcq\McqRegistrationGateService;
use App\Services\Mcq\McqSchoolFeeService;
use App\Services\Membership\SchoolMembershipGate;
use App\Support\Mcq\McqExamEligibilityConfig;
use Illuminate\Http\Request;

class TeacherMcqRegistrationController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $teacher = $request->attributes->get('portalTeacher');
        $school = Tenant::findOrFail($tenantId);
        $eligibility = app(McqEligibilityService::class);

        $exams = McqExam::where('tenant_id', $school->parent_id)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->orderByDesc('scheduled_at')
            ->get()
            ->filter(fn (McqExam $exam) => McqExamEligibilityConfig::allowsTeachers($exam->eligibility_config))
            ->values();

        $myRegs = McqRegistration::where('teacher_id', $teacher->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->with(['exam:id,title,scheduled_at,venue,status,results_published', 'mark', 'certificate'])
            ->get()
            ->keyBy('exam_id');

        $openExams = $exams
            ->filter(fn (McqExam $exam) => in_array($exam->status, ['published', 'ongoing'], true))
            ->map(function (McqExam $exam) use ($teacher, $eligibility, $myRegs) {
                $registered = $myRegs->has($exam->id) && ! $myRegs->get($exam->id)->isCancelled();
                $eligible = $eligibility->isTeacherEligible($exam, $teacher);
                $selfReg = McqExamEligibilityConfig::allowTeacherSelfRegistration($exam->eligibility_config);

                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'venue' => $exam->venue,
                    'scheduled_at' => $exam->scheduled_at?->toIso8601String(),
                    'scheduled_at_label' => $exam->scheduled_at?->format('j M Y, g:i A'),
                    'has_fee' => $exam->hasFee(),
                    'fee_amount' => $exam->fee_amount,
                    'registered' => $registered,
                    'can_register' => $selfReg && $eligible && ! $registered,
                    'ineligibility_reason' => $registered
                        ? null
                        : (! $selfReg
                            ? 'Self-registration is disabled — ask your school admin to nominate you.'
                            : ($eligible ? null : $eligibility->teacherIneligibilityReason($exam, $teacher))),
                ];
            })
            ->values();

        $registrations = $myRegs->values()->map(function (McqRegistration $reg) {
            return [
                'id' => $reg->id,
                'status' => $reg->status,
                'approval_status' => $reg->approval_status,
                'approval_status_label' => $reg->approvalStatusLabel(),
                'hall_ticket_no' => $reg->hall_ticket_no,
                'hall_room' => $reg->hall_room,
                'seat_no' => $reg->seat_no,
                'score' => $reg->mark?->score,
                'grade' => $reg->mark?->grade,
                'rank' => $reg->mark?->rank,
                'has_certificate' => (bool) $reg->certificate,
                'exam' => $reg->exam?->only('id', 'title', 'scheduled_at', 'venue', 'status', 'results_published'),
            ];
        });

        return inertia('Portal/Teacher/McqExams', [
            'school' => $school->only('id', 'name'),
            'teacher' => $teacher->only('id', 'name'),
            'openExams' => $openExams,
            'registrations' => $registrations,
        ]);
    }

    public function register(Request $request, string $tenantId, McqExam $exam)
    {
        $teacher = $request->attributes->get('portalTeacher');
        $school = Tenant::findOrFail($tenantId);

        app(SchoolMembershipGate::class)->assertPaid($school);

        abort_if($exam->tenant_id !== $school->parent_id, 403);
        abort_unless(
            McqExamEligibilityConfig::allowsTeachers($exam->eligibility_config),
            422,
            'This exam is not open to teachers.',
        );
        abort_unless(
            McqExamEligibilityConfig::allowTeacherSelfRegistration($exam->eligibility_config),
            422,
            'Self-registration is not enabled for this exam.',
        );

        app(McqRegistrationGateService::class)->assertCanRegisterTeacher($exam, $school, $teacher);

        $existing = McqRegistration::where('exam_id', $exam->id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if ($existing && ! $existing->isCancelled()) {
            return back()->with('success', 'You are already registered for this exam.');
        }

        $approvalStatus = app(McqRegistrationApprovalService::class)->initialApprovalStatus($exam);

        if ($existing) {
            $existing->update([
                'school_id' => $school->id,
                'student_id' => null,
                'status' => 'registered',
                'approval_status' => $approvalStatus,
                'cancelled_at' => null,
                'cancelled_by_user_id' => null,
            ]);
            $registration = $existing->fresh();
        } else {
            $registration = McqRegistration::create([
                'exam_id' => $exam->id,
                'teacher_id' => $teacher->id,
                'student_id' => null,
                'school_id' => $school->id,
                'status' => 'registered',
                'approval_status' => $approvalStatus,
            ]);
        }

        app(McqSchoolFeeService::class)->syncForSchool($exam, $school);
        app(\App\Services\Mcq\McqExamNotifier::class)->registrationConfirmed($registration);

        return back()->with('success', $exam->hasFee()
            ? 'Registered successfully. Your school will pay the batch fee if applicable.'
            : 'Registered successfully.');
    }
}
