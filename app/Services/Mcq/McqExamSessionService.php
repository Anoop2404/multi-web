<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqProctorEvent;
use App\Models\McqQuestion;
use App\Models\McqRegistration;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class McqExamSessionService
{
    /** @return Collection<int, McqQuestion> */
    public function questionsForExam(McqExam $exam): Collection
    {
        return app(McqPaperService::class)->questionsForExam($exam);
    }

    public function canTakeOnline(McqRegistration $registration): bool
    {
        try {
            $this->assertCanStart($registration);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function assertCanStart(McqRegistration $registration): void
    {
        $registration->loadMissing('exam', 'feeReceipt');
        $this->assertCandidateEligible($registration);

        if ($registration->status === 'submitted') {
            if ($registration->canReattempt()) {
                throw ValidationException::withMessages([
                    'exam' => 'This attempt is complete. Start a reattempt from your exam list when ready.',
                ]);
            }
            throw ValidationException::withMessages(['exam' => 'Exam already submitted.']);
        }

        $exam = $registration->exam;
        if (! $exam || ! in_array($exam->status, ['published', 'ongoing'], true)) {
            throw ValidationException::withMessages([
                'exam' => 'This exam is not available for online delivery.',
            ]);
        }

        if ($exam->isOfflineDelivery()) {
            throw ValidationException::withMessages([
                'exam' => 'This is an offline exam. Take the exam at the scheduled venue with your hall ticket.',
            ]);
        }

        if (\App\Services\ExamEngine\ExamEngineRun::usesEngine($exam)) {
            throw ValidationException::withMessages([
                'exam' => 'This exam is taken on the online exam site. Use "Enter exam" from your portal, or sign in there with your hall ticket and passcode.',
            ]);
        }

        $scheduledAt = $exam->scheduleForClass($registration->student?->schoolClass?->name)['scheduled_at'];
        if ($scheduledAt && now()->lt($scheduledAt)) {
            throw ValidationException::withMessages(['exam' => 'The exam has not started yet.']);
        }

        if (($exam->fee_type ?? 'none') !== 'none') {
            $feeVerified = app(McqRegistrationApprovalService::class)->feeVerified($registration);
            if (! $feeVerified && $registration->approval_status !== 'approved') {
                throw ValidationException::withMessages(['exam' => 'Exam fee must be approved before starting.']);
            }
        }

        if ($registration->attendance_status === 'absent') {
            throw ValidationException::withMessages([
                'exam' => 'You were marked absent for this exam.',
            ]);
        }

        if ($exam->requiresHallTicket()) {
            if (! filled($registration->hall_ticket_no)) {
                throw ValidationException::withMessages([
                    'exam' => 'A hall ticket must be issued before starting this exam.',
                ]);
            }

            if ($registration->attendance_status === 'pending') {
                throw ValidationException::withMessages([
                    'exam' => 'Attendance must be marked present before starting.',
                ]);
            }
        } elseif ($registration->attendance_status === 'pending' && filled($registration->hall_ticket_no)) {
            throw ValidationException::withMessages(['exam' => 'Attendance must be marked present before starting.']);
        }

        $paper = app(McqPaperService::class)->questionsForRegistration($registration);

        // Multi-correct / numeric are supported on the in-app portal take-exam path
        // (and on the exam engine). Do not block start for those types.

        $gradable = $paper->filter(fn (McqQuestion $q) => $q->isGradable());

        if ($gradable->isEmpty()) {
            throw ValidationException::withMessages([
                'exam' => 'No gradable questions are attached to this exam.',
            ]);
        }

        app(McqConcurrencyLimitService::class)->assertCapacityAvailable($registration);
    }

    public function start(McqRegistration $registration): McqRegistration
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($registration) {
            // Same lock as class assignment saves: a start cannot race a paper change.
            McqExam::whereKey($registration->exam_id)->lockForUpdate()->firstOrFail();
            $registration = McqRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            $this->assertCanStart($registration);
            app(McqPaperService::class)->freezeForRegistration($registration);
            if (! $registration->started_at) $registration->update(['status' => 'started', 'started_at' => now()]);
            return $registration->fresh(['exam']);
        });
    }

    /**
     * The paper as sent to the student's browser: no answer keys, image paths swapped for
     * the student image route, order fixed per registration.
     *
     * @return list<array<string, mixed>>
     */
    public function paperForStudent(McqRegistration $registration): array
    {
        $imageBase = "/portal/student/{$registration->school_id}/mcq/{$registration->id}/questions";

        return app(McqPaperService::class)->questionsForRegistration($registration)
            ->map(fn (McqQuestion $q, int $index) => [
                'id'            => $q->id,
                'number'        => $index + 1,
                'section'       => $q->section_title ?: ($q->bank?->subject ?: ($q->bank?->title ?: 'General')),
                'type'          => $q->typeValue(),
                'marks'         => $q->marksValue(),
                'negative_mark' => $q->negativeMarkValue(),
                'choose_count'  => $q->showsCorrectCount() ? count($q->correctKeys()) : null,
                'title'         => $q->title,
                'body_text'     => $q->body_text,
                'image_url'     => $q->document_path ? "{$imageBase}/{$q->id}/image" : null,
                'options'       => collect($q->options_json ?? [])
                    ->map(fn ($opt, $optionIndex) => [
                        'key'       => $opt['key'],
                        'display_key' => chr(97 + $optionIndex),
                        'label'     => $opt['label'] ?? '',
                        'image_url' => filled($opt['image_path'] ?? null) ? "{$imageBase}/{$q->id}/image?option={$opt['key']}" : null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function expiresAt(McqRegistration $registration): ?\Carbon\Carbon
    {
        $registration->loadMissing('exam');
        if (! $registration->started_at || ! $registration->exam) {
            return null;
        }

        $schedule = $registration->paper_snapshot['schedule'] ?? $registration->exam->scheduleForClass($registration->student?->schoolClass?->name);

        return $registration->started_at->copy()->addMinutes($schedule['duration_minutes']);
    }

    public function isExpired(McqRegistration $registration): bool
    {
        $expires = $this->expiresAt($registration);

        return $expires !== null && now()->greaterThanOrEqualTo($expires);
    }

    /**
     * Persist draft answers. Values may be a single option key, a numeric string,
     * or a list of option keys for multiple-correct questions.
     *
     * @param  array<int|string, mixed>  $answers
     */
    public function saveDraftAnswers(McqRegistration $registration, array $answers): void
    {
        $this->assertActiveSession($registration, allowExpired: false);

        $normalized = [];
        foreach ($answers as $questionId => $value) {
            if ($questionId === '_marked_for_review') {
                if (is_array($value)) {
                    $normalized['_marked_for_review'] = array_values(array_filter(array_map('strval', $value)));
                }
                continue;
            }
            $clean = $this->normalizeAnswerValue($value);
            if ($clean !== null) {
                $normalized[(string) $questionId] = $clean;
            }
        }

        $registration->update(['draft_answers' => $normalized]);
    }

    /** @param  array<int|string, mixed>  $answers */
    public function submit(McqRegistration $registration, array $answers): McqMark
    {
        $registration->loadMissing('exam', 'mark');
        $this->assertCandidateEligible($registration);

        if ($registration->status === 'submitted') {
            throw ValidationException::withMessages(['exam' => 'Exam already submitted.']);
        }

        $expired = $registration->started_at && $this->isExpired($registration);

        if ($registration->started_at) {
            if (! $expired) {
                $this->assertCanStart($registration);
            }
        } else {
            $this->start($registration);
            $registration->refresh();
        }

        // Once time is over, only answers received before the deadline may score.
        if ($expired) {
            $answers = $registration->draft_answers ?? [];
        }

        $result = app(McqGrader::class)->grade(
            app(McqPaperService::class)->questionsForRegistration($registration),
            $answers,
            $registration->paper_snapshot['multi_marking'] ?? $registration->exam->multiMarkingMode(),
        );

        $mark = $this->storeGradedResult($registration, $result);

        $registration->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
            'submit_mode'  => $registration->submit_mode ?: 'manual',
        ]);

        return $mark;
    }

    /**
     * Archive the completed attempt and clear session fields so the student can start again.
     * McqMark is updated on the next submit (latest) or kept when scoring is "best" until beaten.
     */
    public function beginReattempt(McqRegistration $registration): McqRegistration
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($registration) {
            $registration = McqRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            $registration->loadMissing(['exam', 'mark']);

            if (! $registration->canReattempt()) {
                throw ValidationException::withMessages([
                    'exam' => 'No reattempts remain for this exam.',
                ]);
            }

            $exam = $registration->exam;
            $mark = $registration->mark;
            $history = $registration->attempt_history ?? [];
            $history[] = [
                'attempt'      => $registration->attemptNumber(),
                'started_at'   => $registration->started_at?->toIso8601String(),
                'submitted_at' => $registration->submitted_at?->toIso8601String(),
                'submit_mode'  => $registration->submit_mode,
                'score'        => $mark?->score,
                'percentage'   => $mark?->percentage,
                'grade'        => $mark?->grade,
                'correct'      => $mark?->correct_count,
                'wrong'        => $mark?->wrong_count,
                'unanswered'   => $mark?->unanswered_count,
            ];

            $keepMark = $exam->reattemptScoring() === 'best' && $mark;

            $registration->forceFill([
                'status'          => 'registered',
                'started_at'      => null,
                'submitted_at'    => null,
                'submit_mode'     => null,
                'draft_answers'   => null,
                'paper_snapshot'  => null,
                'attempt_number'  => $registration->attemptNumber() + 1,
                'attempt_history' => $history,
            ])->save();

            if (! $keepMark && $mark) {
                $mark->delete();
            }

            McqProctorEvent::where('registration_id', $registration->id)->delete();

            return $registration->fresh(['exam', 'mark']);
        });
    }

    /**
     * Saves a McqGrader result as the registration's mark, applying the exam's grade bands and
     * pass mark. Shared by the in-app submit and the exam engine's result consumer.
     *
     * @param  array{correct: int, wrong: int, unanswered: int, score: float, percentage: float, answers: list<array<string, mixed>>}  $result
     */
    public function storeGradedResult(McqRegistration $registration, array $result): McqMark
    {
        $registration->loadMissing('exam');
        $percentage = $result['percentage'];
        $passMark = (float) ($registration->paper_snapshot['pass_mark'] ?? $registration->exam->pass_mark ?? 0);
        $gradeService = app(\App\Services\Mcq\McqGradeService::class);
        $grade = $gradeService->gradeForPercentage($registration->exam, $percentage);
        if ($passMark > 0 && $percentage < $passMark && $grade !== 'F') {
            $bands = $gradeService->bandsForExam($registration->exam);
            $failBand = collect($bands)->first(fn ($b) => ! $b['is_pass']);
            $grade = $failBand['label'] ?? 'F';
        }

        $payload = [
            'correct_count'    => $result['correct'],
            'wrong_count'      => $result['wrong'],
            'unanswered_count' => $result['unanswered'],
            'score'            => $result['score'],
            'percentage'       => $percentage,
            'grade'            => $grade,
            'answers_json'     => $result['answers'],
            'locked_at'        => now(),
        ];

        $existing = McqMark::where('registration_id', $registration->id)->first();
        if (
            $existing
            && $registration->exam->reattemptScoring() === 'best'
            && (float) $existing->percentage >= (float) $percentage
        ) {
            // Keep the stronger attempt's official mark; history already archived the prior run.
            return $existing;
        }

        $mark = McqMark::updateOrCreate(
            ['registration_id' => $registration->id],
            $payload,
        );

        if ($registration->exam->results_published) {
            app(McqRankingService::class)->rankExam($registration->exam);
            $mark->refresh();
        }

        return $mark;
    }

    /**
     * Record a client-reported proctoring event (tab switch / window blur / fullscreen exit).
     * Default mode is log-only. With proctor_mode=strict_tab, tab_hidden events past the
     * configured limit auto-submit the exam from draft answers (server-authoritative).
     *
     * @return array{event: ?McqProctorEvent, auto_submitted: bool, mark: ?McqMark}
     */
    public function recordProctorEvent(McqRegistration $registration, string $eventType, ?\Carbon\Carbon $occurredAt = null): array
    {
        $empty = ['event' => null, 'auto_submitted' => false, 'mark' => null];

        if (! in_array($eventType, McqProctorEvent::TYPES, true)) {
            return $empty;
        }

        if (! $registration->started_at || $registration->status === 'submitted') {
            return $empty;
        }

        $existingCount = McqProctorEvent::where('registration_id', $registration->id)->count();
        if ($existingCount >= McqProctorEvent::MAX_PER_REGISTRATION) {
            return $empty;
        }

        $event = McqProctorEvent::create([
            'registration_id' => $registration->id,
            'event_type'      => $eventType,
            'occurred_at'     => $occurredAt ?? now(),
        ]);

        $registration->loadMissing('exam');
        $exam = $registration->exam;
        if (! $exam || $exam->proctorMode() !== 'strict_tab' || $eventType !== 'tab_hidden') {
            return ['event' => $event, 'auto_submitted' => false, 'mark' => null];
        }

        $tabCount = McqProctorEvent::where('registration_id', $registration->id)
            ->where('event_type', 'tab_hidden')
            ->count();

        if ($tabCount < $exam->proctorMaxTabViolations()) {
            return ['event' => $event, 'auto_submitted' => false, 'mark' => null];
        }

        $registration->refresh();
        if ($registration->status === 'submitted') {
            return ['event' => $event, 'auto_submitted' => false, 'mark' => null];
        }

        $registration->forceFill(['submit_mode' => 'auto_tab'])->save();
        $mark = $this->submit($registration, $registration->draft_answers ?? []);

        return ['event' => $event, 'auto_submitted' => true, 'mark' => $mark];
    }

    /**
     * @return string|list<string>|null
     */
    private function normalizeAnswerValue(mixed $value): string|array|null
    {
        if (is_array($value)) {
            $keys = McqGrader::normalizeKeyList($value);

            return $keys === [] ? null : $keys;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        // Comma-separated multi keys from older clients → list.
        if (str_contains($trimmed, ',')) {
            $keys = McqGrader::normalizeKeyList($trimmed);

            return $keys === [] ? null : $keys;
        }

        return $trimmed;
    }

    private function assertCandidateEligible(McqRegistration $registration): void
    {
        if ($registration->isCancelled() || $registration->approval_status === 'rejected' || $registration->blocksScoring()) {
            throw ValidationException::withMessages(['exam' => 'This registration is not cleared to take the exam. Contact your school.']);
        }
    }

    private function assertActiveSession(McqRegistration $registration, bool $allowExpired = false): void
    {
        $this->assertCandidateEligible($registration);
        if ($registration->status === 'submitted') {
            throw ValidationException::withMessages(['exam' => 'Exam already submitted.']);
        }

        if (! $registration->started_at) {
            throw ValidationException::withMessages(['exam' => 'Exam has not started.']);
        }

        if (! $allowExpired && $this->isExpired($registration)) {
            throw ValidationException::withMessages(['exam' => 'Exam time has expired.']);
        }

        if (! $allowExpired) {
            $this->assertCanStart($registration);
        }
    }
}
