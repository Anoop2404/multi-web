<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqQuestion;
use App\Models\McqRegistration;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class McqExamSessionService
{
    /** @return Collection<int, McqQuestion> */
    public function questionsForExam(McqExam $exam, bool $shuffle = false): Collection
    {
        $exam->loadMissing('questionBanks.questions');

        $questions = $exam->questionBanks
            ->flatMap(fn ($bank) => $bank->questions)
            ->sortBy('display_order')
            ->values();

        if ($shuffle) {
            $questions = $questions->shuffle()->values();
        }

        $limit = (int) ($exam->total_questions ?: 0);
        if ($limit > 0 && $questions->count() > $limit) {
            $questions = $questions->take($limit);
        }

        return $questions;
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

        if ($registration->status === 'submitted') {
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

        if ($exam->scheduled_at && now()->lt($exam->scheduled_at)) {
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

        $gradable = $this->questionsForExam($exam)
            ->filter(fn (McqQuestion $q) => $this->isGradable($q));

        if ($gradable->isEmpty()) {
            throw ValidationException::withMessages([
                'exam' => 'No gradable Talent Search questions are attached to this exam.',
            ]);
        }
    }

    public function start(McqRegistration $registration): McqRegistration
    {
        $this->assertCanStart($registration);

        if (! $registration->started_at) {
            $registration->update([
                'status'     => 'started',
                'started_at' => now(),
            ]);
        }

        return $registration->fresh(['exam']);
    }

    /** @return list<array<string, mixed>> */
    public function paperForStudent(McqRegistration $registration): array
    {
        $registration->loadMissing('exam');
        $shuffle = (bool) ($registration->exam->settings_json['shuffle_questions'] ?? false);

        return $this->questionsForExam($registration->exam, $shuffle)
            ->map(fn (McqQuestion $q, int $index) => [
                'id'            => $q->id,
                'number'        => $index + 1,
                'title'         => $q->title,
                'body_text'     => $q->body_text,
                'document_path' => $q->document_path,
                'options'       => collect($q->options_json ?? [])
                    ->map(fn ($opt) => ['key' => $opt['key'], 'label' => $opt['label']])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function expiresAt(McqRegistration $registration): ?\Carbon\Carbon
    {
        $registration->loadMissing('exam');
        if (! $registration->started_at || ! $registration->exam?->duration_minutes) {
            return null;
        }

        return $registration->started_at->copy()->addMinutes((int) $registration->exam->duration_minutes);
    }

    public function isExpired(McqRegistration $registration): bool
    {
        $expires = $this->expiresAt($registration);

        return $expires !== null && now()->greaterThan($expires);
    }

    /** @param  array<int|string, string|null>  $answers  question_id => option_key */
    public function saveDraftAnswers(McqRegistration $registration, array $answers): void
    {
        $this->assertActiveSession($registration, allowExpired: false);

        $normalized = [];
        foreach ($answers as $questionId => $optionKey) {
            if ($optionKey !== null && $optionKey !== '') {
                $normalized[(string) $questionId] = (string) $optionKey;
            }
        }

        $registration->update(['draft_answers' => $normalized]);
    }

    /** @param  array<int|string, string>  $answers  question_id => option_key */
    public function submit(McqRegistration $registration, array $answers): McqMark
    {
        $registration->loadMissing('exam', 'mark');

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

        $autoSubmitted = $expired;

        $questions = $this->questionsForExam($registration->exam);
        $gradable = $questions->filter(fn (McqQuestion $q) => $this->isGradable($q));

        $normalizedAnswers = [];
        foreach ($answers as $questionId => $optionKey) {
            $normalizedAnswers[(string) $questionId] = (string) $optionKey;
        }

        $correct = 0;
        $wrong = 0;
        $storedAnswers = [];

        foreach ($gradable as $question) {
            $chosen = $normalizedAnswers[(string) $question->id] ?? null;
            $isCorrect = $chosen !== null && $chosen === (string) $question->correct_option_key;

            if ($isCorrect) {
                $correct++;
            } elseif ($chosen !== null) {
                $wrong++;
            }

            $storedAnswers[] = [
                'question_id' => $question->id,
                'chosen'      => $chosen,
                'correct'     => $question->correct_option_key,
                'is_correct'  => $isCorrect,
            ];
        }

        $totalGradable = $gradable->count();
        $unanswered = max(0, $totalGradable - $correct - $wrong);
        $score = $correct;
        $percentage = $totalGradable > 0 ? round(($correct / $totalGradable) * 100, 2) : 0;
        $passMark = (int) ($registration->exam->pass_mark ?? 0);
        $gradeService = app(\App\Services\Mcq\McqGradeService::class);
        $grade = $gradeService->gradeForPercentage($registration->exam, $percentage);
        if ($passMark > 0 && $percentage < $passMark && $grade !== 'F') {
            $bands = $gradeService->bandsForExam($registration->exam);
            $failBand = collect($bands)->first(fn ($b) => ! $b['is_pass']);
            $grade = $failBand['label'] ?? 'F';
        }

        $mark = McqMark::updateOrCreate(
            ['registration_id' => $registration->id],
            [
                'correct_count'    => $correct,
                'wrong_count'      => $wrong,
                'unanswered_count' => $unanswered,
                'score'            => $score,
                'percentage'       => $percentage,
                'grade'            => $grade,
                'answers_json'     => $storedAnswers,
                'locked_at'        => now(),
            ],
        );

        $registration->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        return $mark;
    }

    private function assertActiveSession(McqRegistration $registration, bool $allowExpired = false): void
    {
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

    private function isGradable(McqQuestion $question): bool
    {
        return filled($question->correct_option_key)
            && is_array($question->options_json)
            && count($question->options_json) >= 2;
    }
}
