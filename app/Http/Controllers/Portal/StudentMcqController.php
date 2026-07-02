<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Services\Mcq\McqExamSessionService;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\Mcq\McqResultPresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentMcqController extends Controller
{
    public function hallTicket(Request $request, string $tenantId, McqRegistration $registration)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($registration->school_id !== $tenantId || $registration->student_id !== $student->id, 403);

        $registration->load(['exam', 'student', 'school']);

        return view('mcq.hall-ticket', [
            'registration' => $registration,
            'school'       => Tenant::findOrFail($tenantId),
        ]);
    }

    public function showExam(Request $request, string $tenantId, McqRegistration $registration, McqExamSessionService $sessions)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($registration->school_id !== $tenantId || $registration->student_id !== $student->id, 403);

        $registration->load(['exam', 'mark']);

        if ($registration->exam?->isOfflineDelivery() && $registration->status !== 'submitted') {
            return redirect()
                ->route('portal.student.dashboard', ['tenantId' => $tenantId])
                ->withErrors(['exam' => 'This is an offline exam. Use your hall ticket at the scheduled venue.']);
        }

        if ($registration->status === 'submitted') {
            return inertia('Portal/Student/McqResult', [
                'school'       => Tenant::findOrFail($tenantId)->only('id', 'name'),
                'student'      => $student->only('id', 'name', 'reg_no'),
                'registration' => $registration->only('id', 'status', 'submitted_at'),
                'mark'         => McqResultPresenter::forRegistration($registration, $registration->mark),
                'showResults'  => (bool) $registration->exam?->results_published,
            ]);
        }

        try {
            $sessions->assertCanStart($registration);
        } catch (ValidationException $e) {
            return redirect()
                ->route('portal.student.dashboard', ['tenantId' => $tenantId])
                ->withErrors($e->errors());
        }

        if ($registration->started_at && $sessions->isExpired($registration)) {
            return redirect()
                ->route('portal.student.dashboard', ['tenantId' => $tenantId])
                ->withErrors(['exam' => 'Exam time has expired.']);
        }

        return inertia('Portal/Student/McqExam', [
            'school'       => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'student'      => $student->only('id', 'name', 'reg_no'),
            'registration' => $registration->only('id', 'status', 'started_at'),
            'exam'         => $registration->exam->only('id', 'title', 'duration_minutes', 'scheduled_at'),
            'questions'    => $sessions->paperForStudent($registration),
            'expiresAt'    => $sessions->expiresAt($registration)?->toIso8601String(),
            'started'      => (bool) $registration->started_at,
        ]);
    }

    public function startExam(Request $request, string $tenantId, McqRegistration $registration, McqExamSessionService $sessions)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($registration->school_id !== $tenantId || $registration->student_id !== $student->id, 403);

        $sessions->start($registration);

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration->fresh(['exam']),
            'mcq.exam.started',
            "Student started online MCQ exam #{$registration->exam_id}",
        );

        return redirect()->route('portal.student.mcq.exam', [
            'tenantId'     => $tenantId,
            'registration' => $registration->id,
        ]);
    }

    public function submitExam(Request $request, string $tenantId, McqRegistration $registration, McqExamSessionService $sessions)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($registration->school_id !== $tenantId || $registration->student_id !== $student->id, 403);

        $data = $request->validate([
            'answers'   => 'required|array',
            'answers.*' => 'nullable|string|max:10',
        ]);

        $mark = $sessions->submit($registration, $data['answers']);

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration->fresh(['exam']),
            'mcq.exam.submitted',
            "Student submitted online MCQ exam with score {$mark->score}",
        );

        return redirect()->route('portal.student.mcq.exam', [
            'tenantId'     => $tenantId,
            'registration' => $registration->id,
        ])->with('success', "Exam submitted. Score: {$mark->score}");
    }
}
