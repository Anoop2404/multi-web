<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\McqQuestion;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Services\Mcq\McqExamSessionService;
use App\Services\Mcq\McqPaperService;
use App\Support\TenantStorage;
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

        $registration->load(['exam', 'student.schoolClass', 'school']);
        abort_unless($registration->exam?->hall_tickets_published, 403, 'Hall tickets have not been released by Sahodaya yet.');

        return view('mcq.hall-ticket', [
            'registration' => $registration,
            'school'       => Tenant::findOrFail($tenantId),
        ]);
    }

    public function certificate(Request $request, string $tenantId, McqRegistration $registration)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($registration->school_id !== $tenantId || $registration->student_id !== $student->id, 403);

        $registration->load(['exam', 'student', 'school', 'mark']);
        $sahodaya = Tenant::findOrFail($registration->exam->tenant_id);
        $certificate = app(\App\Services\Mcq\McqCertificateService::class)->issue($registration);

        return view('mcq.certificate', [
            'registration' => $registration,
            'certificate'  => $certificate,
            'sahodaya'     => $sahodaya,
            'fields'       => app(\App\Services\Mcq\McqCertificateService::class)->fieldValues($registration, $sahodaya),
            'design'       => $certificate->design_snapshot_json ?? [],
        ]);
    }

    public function invoice(Request $request, string $tenantId, McqRegistration $registration, \App\Services\Mcq\McqRegistrationInvoiceService $invoices)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($registration->school_id !== $tenantId || $registration->student_id !== $student->id, 403);

        $registration->loadMissing('exam');
        abort_unless($registration->exam?->hasFee(), 404, 'This exam has no fee.');

        return $invoices->download($registration);
    }

    public function showExam(Request $request, string $tenantId, McqRegistration $registration, McqExamSessionService $sessions)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($registration->school_id !== $tenantId || $registration->student_id !== $student->id, 403);

        $registration->load(['exam', 'mark']);

        if ($registration->exam && \App\Services\ExamEngine\ExamEngineRun::usesEngine($registration->exam) && $registration->status !== 'submitted') {
            return $this->enterExamEngine($tenantId, $registration);
        }

        if ($registration->exam?->isOfflineDelivery() && $registration->status !== 'submitted') {
            return redirect()
                ->route('portal.student.dashboard', ['tenantId' => $tenantId])
                ->withErrors(['exam' => 'This is an offline exam. Use your hall ticket at the scheduled venue.']);
        }

        if ($registration->status === 'submitted') {
            $certs = app(\App\Services\Mcq\McqCertificateService::class);
            $certAvailable = $certs->isAvailableForStudent($registration);

            return inertia('Portal/Student/McqResult', [
                'school'       => Tenant::findOrFail($tenantId)->only('id', 'name'),
                'student'      => $student->only('id', 'name', 'admission_number', 'reg_no'),
                'registration' => array_merge(
                    $registration->only('id', 'status', 'submitted_at', 'attendance_status'),
                    ['exam' => $registration->exam?->only('id', 'title', 'results_published')],
                ),
                'mark'         => McqResultPresenter::forRegistration($registration, $registration->mark),
                'showResults'  => (bool) $registration->exam?->results_published,
                'resultVisibility' => $registration->exam
                    ? \App\Support\Mcq\McqResultReleaseSettings::visibility($registration->exam)
                    : 'score_grade',
                'certificateUrl' => $certAvailable
                    ? route('portal.student.mcq.certificate', ['tenantId' => $tenantId, 'registration' => $registration->id])
                    : null,
                'certificateUnavailableReason' => $certAvailable ? null : $certs->unavailableReason($registration),
                'certificatesEnabled' => $registration->exam
                    ? \App\Support\Mcq\McqResultReleaseSettings::certificatesEnabled($registration->exam)
                    : false,
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
            $sessions->submit($registration, $registration->draft_answers ?? []);
            app(PlatformAuditLogger::class)->mcqRegistration(
                $registration->fresh(['exam']), 'mcq.exam.auto_submitted',
                'Expired online exam submitted from its saved answers on resume.',
            );

            return redirect()->route('portal.student.mcq.exam', ['tenantId' => $tenantId, 'registration' => $registration->id]);
        }

        $schedule = $registration->paper_snapshot['schedule'] ?? $registration->exam->scheduleForClass($registration->student?->schoolClass?->name);
        $exam = $registration->exam;
        $questions = $sessions->paperForStudent($registration);
        $hasNegative = collect($questions)->contains(fn ($q) => (float) ($q['negative_mark'] ?? 0) > 0);

        return inertia('Portal/Student/McqExam', [
            'school'       => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'student'      => $student->only('id', 'name', 'admission_number', 'reg_no'),
            'registration' => $registration->only('id', 'status', 'started_at'),
            'exam'         => array_merge($exam->only('id', 'title', 'pass_mark'), $schedule, [
                'product_mode' => $exam->productMode(),
                'is_model_exam' => $exam->isModelExam(),
                'requires_hall_ticket' => $exam->requiresHallTicket(),
                'no_pin_required' => $exam->isModelExam() || ! $exam->requiresHallTicket(),
                'has_negative_marking' => $hasNegative,
            ]),
            'questions'    => $questions,
            'savedAnswers' => $registration->draft_answers ?? [],
            'expiresAt'    => $sessions->expiresAt($registration)?->toIso8601String(),
            'started'      => (bool) $registration->started_at,
        ]);
    }

    /**
     * Engine exams are written on the separate exam site. A signed, single-use launch token
     * signs the student straight in (no second password check); without one configured, the
     * student is sent to the exam site's login page.
     */
    private function enterExamEngine(string $tenantId, McqRegistration $registration)
    {
        $exam = $registration->exam;
        $dashboard = redirect()->route('portal.student.dashboard', ['tenantId' => $tenantId]);

        if (\App\Services\ExamEngine\ExamEngineRun::state($exam) !== 'armed') {
            $opens = $exam->scheduled_at?->copy()->subHours((int) \App\Services\ExamEngine\ExamEngineRun::settings($exam)['checkin_hours']);

            return $dashboard->withErrors(['exam' => $opens && now()->lt($opens)
                ? 'Check-in for this exam opens on '.$opens->timezone(config('app.timezone'))->format('d M Y, h:i A').'.'
                : 'The exam site is not open for this exam yet. Please try again later or contact your school.']);
        }

        if ($registration->blocksScoring() || $registration->isCancelled() || ! $registration->isApproved()) {
            return $dashboard->withErrors(['exam' => 'You are not cleared to write this exam. Please contact your school.']);
        }

        $tokens = app(\App\Services\ExamEngine\ExamEngineLaunchToken::class);

        return \Inertia\Inertia::location($tokens->isConfigured()
            ? $tokens->launchUrl($registration)
            : \App\Services\ExamEngine\ExamEngineBridge::examSiteUrl($exam));
    }

    /** A question or option image on the student's own paper, once they have started writing. */
    public function questionImage(Request $request, string $tenantId, McqRegistration $registration, string $question, McqPaperService $papers)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($registration->school_id !== $tenantId || $registration->student_id !== $student->id, 403);
        abort_unless($registration->started_at, 403);
        $question = $papers->questionsForRegistration($registration)->firstWhere('id', (int) $question);
        abort_unless($question, 404);

        $option = $request->query('option');
        $path = is_string($option) && $option !== ''
            ? collect($question->options_json ?? [])->first(fn ($o) => strtolower((string) ($o['key'] ?? '')) === strtolower($option))['image_path'] ?? null
            : $question->document_path;
        abort_unless($path, 404);

        return TenantStorage::downloadPrivate($path, inline: true);
    }

    public function startExam(Request $request, string $tenantId, McqRegistration $registration, McqExamSessionService $sessions)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($registration->school_id !== $tenantId || $registration->student_id !== $student->id, 403);

        $sessions->start($registration);

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration->fresh(['exam']),
            'mcq.exam.started',
            "Student started online Talent Search exam #{$registration->exam_id}",
        );

        return redirect()->route('portal.student.mcq.exam', [
            'tenantId'     => $tenantId,
            'registration' => $registration->id,
        ]);
    }

    public function saveAnswer(Request $request, string $tenantId, McqRegistration $registration, McqExamSessionService $sessions)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($registration->school_id !== $tenantId || $registration->student_id !== $student->id, 403);

        $data = $request->validate([
            'answers'   => 'required|array',
            'answers.*' => 'nullable',
        ]);

        $sessions->saveDraftAnswers($registration, $data['answers']);

        return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
    }

    /**
     * Record a client-reported proctoring event (tab hidden / window blur / fullscreen exit)
     * for the student's own active session. Detect-and-log only -- never auto-submits or
     * penalizes; the recorded events are surfaced to Sahodaya staff for later review.
     */
    public function recordProctorEvent(Request $request, string $tenantId, McqRegistration $registration, McqExamSessionService $sessions)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($registration->school_id !== $tenantId || $registration->student_id !== $student->id, 403);

        $data = $request->validate([
            'type' => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Models\McqProctorEvent::TYPES)],
        ]);

        $sessions->recordProctorEvent($registration, $data['type']);

        return response()->json(['recorded' => true]);
    }

    public function submitExam(Request $request, string $tenantId, McqRegistration $registration, McqExamSessionService $sessions)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($registration->school_id !== $tenantId || $registration->student_id !== $student->id, 403);

        $data = $request->validate([
            'answers'   => 'present|array',
            'answers.*' => 'nullable',
        ]);

        $registration->loadMissing('exam');
        $autoSubmitted = $registration->started_at && $sessions->isExpired($registration);

        $mark = $sessions->submit($registration, $data['answers']);

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration->fresh(['exam']),
            $autoSubmitted ? 'mcq.exam.auto_submitted' : 'mcq.exam.submitted',
            $autoSubmitted
                ? "Student auto-submitted online Talent Search exam (time expired) with score {$mark->score}"
                : "Student submitted online Talent Search exam with score {$mark->score}",
        );

        $message = $autoSubmitted
            ? 'Time expired — your answers were auto-submitted.'
            : 'Exam submitted.';
        if ($registration->exam->results_published) {
            $message .= " Score: {$mark->score}";
        }

        return redirect()->route('portal.student.mcq.exam', [
            'tenantId'     => $tenantId,
            'registration' => $registration->id,
        ])->with('success', $message);
    }
}
