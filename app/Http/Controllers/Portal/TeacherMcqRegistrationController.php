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
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $hallTicketsPublished = (bool) $reg->exam?->hall_tickets_published;

            return [
                'id' => $reg->id,
                'status' => $reg->status,
                'approval_status' => $reg->approval_status,
                'approval_status_label' => $reg->approvalStatusLabel(),
                'show_hall_ticket' => $hallTicketsPublished && (bool) $reg->hall_ticket_no,
                'hall_ticket_no' => $hallTicketsPublished ? $reg->hall_ticket_no : null,
                'hall_room' => $hallTicketsPublished ? $reg->hall_room : null,
                'seat_no' => $hallTicketsPublished ? $reg->seat_no : null,
                'score' => $reg->mark?->score,
                'grade' => $reg->mark?->grade,
                'rank' => $reg->mark?->rank,
                'has_certificate' => (bool) $reg->certificate,
                'exam' => $reg->exam?->only('id', 'title', 'scheduled_at', 'venue', 'status', 'results_published', 'hall_tickets_published'),
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

        $approvalStatus = app(McqRegistrationApprovalService::class)->initialApprovalStatus($exam);

        // Same WF-04 fix as McqRegistrationController::store() (student self-registration) —
        // this was a plain check-then-create with no transaction or lock, so a double-click
        // or duplicate tab could both pass the "already registered" check before either had
        // written its row. The unique(exam_id, teacher_id) DB constraint already existed
        // (migration 2026_10_01_000001_mcq_registration_teacher_exam_unique) and would have
        // caught the duplicate at the DB level either way, but with no transaction/lock and
        // no catch here, a lost race surfaced as a raw 500 instead of the graceful "already
        // registered" message students get.
        try {
            [$registration, $alreadyRegistered] = DB::transaction(function () use ($exam, $teacher, $school, $approvalStatus) {
                $existing = McqRegistration::where('exam_id', $exam->id)
                    ->where('teacher_id', $teacher->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing && ! $existing->isCancelled()) {
                    return [$existing, true];
                }

                if ($existing) {
                    $existing->update([
                        'school_id' => $school->id,
                        'student_id' => null,
                        'status' => 'registered',
                        'approval_status' => $approvalStatus,
                        'cancelled_at' => null,
                        'cancelled_by_user_id' => null,
                    ]);

                    return [$existing->fresh(), false];
                }

                $created = McqRegistration::create([
                    'exam_id' => $exam->id,
                    'teacher_id' => $teacher->id,
                    'student_id' => null,
                    'school_id' => $school->id,
                    'status' => 'registered',
                    'approval_status' => $approvalStatus,
                ]);

                return [$created, false];
            });
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                return back()->with('success', 'You are already registered for this exam.');
            }

            throw $e;
        }

        if ($alreadyRegistered) {
            return back()->with('success', 'You are already registered for this exam.');
        }

        app(McqSchoolFeeService::class)->syncForSchool($exam, $school);
        app(\App\Services\Mcq\McqExamNotifier::class)->registrationConfirmed($registration);

        return back()->with('success', $exam->hasFee()
            ? 'Registered successfully. Your school will pay the batch fee if applicable.'
            : 'Registered successfully.');
    }
}
