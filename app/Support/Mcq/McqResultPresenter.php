<?php

namespace App\Support\Mcq;

use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;

class McqResultPresenter
{
    /** @return array<string, mixed>|null */
    public static function forRegistration(McqRegistration $registration, ?McqMark $mark = null): ?array
    {
        $registration->loadMissing('exam');
        $exam = $registration->exam;
        if (! $exam) {
            return null;
        }

        if ($registration->status !== 'submitted') {
            return [
                'status' => $registration->status,
                'submitted_at' => $registration->submitted_at?->toIso8601String(),
                'result_state' => 'not_submitted',
                'result_message' => 'Not submitted yet.',
            ];
        }

        if (! McqResultReleaseSettings::isResultVisible($exam, $registration)) {
            return [
                'status' => 'submitted',
                'submitted_at' => $registration->submitted_at?->toIso8601String(),
                'pending' => true,
                'result_state' => 'held',
                'result_message' => 'Results not released yet.',
            ];
        }

        $mark ??= $registration->mark;
        $visibility = McqResultReleaseSettings::visibility($exam);
        $showsGrade = McqResultReleaseSettings::showsGrade($exam);
        $showsReview = McqResultReleaseSettings::showsReview($exam);

        $payload = [
            'status' => 'submitted',
            'submitted_at' => $registration->submitted_at?->toIso8601String(),
            'score' => $mark?->score,
            'percentage' => $mark?->percentage,
            'correct_count' => $mark?->correct_count,
            'wrong_count' => $mark?->wrong_count,
            'unanswered_count' => $mark?->unanswered_count,
            'visibility' => $visibility,
            'result_state' => 'released',
            'result_message' => null,
        ];

        if ($showsGrade) {
            $payload['grade'] = $mark?->grade;
            $payload['rank'] = $mark?->rank;
        }

        if ($showsReview && is_array($mark?->answers_json)) {
            $payload['answers_json'] = self::sanitizeAnswersForStudent($mark->answers_json);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public static function forExamList(McqExam $exam, McqRegistration $registration): array
    {
        $visible = McqResultReleaseSettings::isResultVisible($exam, $registration);
        $submitted = $registration->status === 'submitted';

        $resultState = 'not_submitted';
        $resultMessage = 'Not submitted yet.';
        if ($submitted && ! $visible) {
            $resultState = 'held';
            $resultMessage = 'Results not released yet.';
        } elseif ($submitted && $visible) {
            $resultState = 'released';
            $resultMessage = null;
        }

        return [
            'registration_id' => $registration->id,
            'exam_id' => $exam->id,
            'title' => $exam->title,
            'status' => $registration->status,
            'scheduled_at' => $exam->scheduled_at?->toIso8601String(),
            'results_published' => (bool) $exam->results_published,
            'result_state' => $resultState,
            'result_message' => $resultMessage,
            'result_visibility' => McqResultReleaseSettings::visibility($exam),
            'product_mode' => $exam->productMode(),
            'product_label' => McqProductMode::label($exam->productMode()),
            'mark' => $visible && $submitted
                ? self::forRegistration($registration)
                : null,
        ];
    }

    /**
     * Student-safe answer review rows (correct key only when review visibility is on).
     *
     * @param  list<array<string, mixed>>  $answers
     * @return list<array<string, mixed>>
     */
    private static function sanitizeAnswersForStudent(array $answers): array
    {
        $out = [];
        foreach ($answers as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'index' => $index + 1,
                'question_id' => $row['question_id'] ?? null,
                'chosen' => $row['chosen'] ?? null,
                'correct' => $row['correct'] ?? null,
                'is_correct' => (bool) ($row['is_correct'] ?? false),
                'awarded' => $row['awarded'] ?? null,
                'marks' => $row['marks'] ?? null,
            ];
        }

        return $out;
    }
}
