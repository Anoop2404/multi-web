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
            $payload['answers_json'] = self::sanitizeAnswersForStudent($mark->answers_json, $registration);
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
     * Student-safe answer review rows (hydrated with question statement, options, and explanation).
     *
     * @param  list<array<string, mixed>>  $answers
     * @return list<array<string, mixed>>
     */
    private static function sanitizeAnswersForStudent(array $answers, ?McqRegistration $registration = null): array
    {
        $questionsById = collect();
        $imageBase = null;

        if ($registration) {
            try {
                $questionsById = app(\App\Services\Mcq\McqPaperService::class)
                    ->questionsForRegistration($registration)
                    ->keyBy('id');
                $imageBase = "/portal/student/{$registration->school_id}/mcq/{$registration->id}/questions";
            } catch (\Throwable) {
                $questionsById = collect();
            }
        }

        $out = [];
        foreach ($answers as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $qid = $row['question_id'] ?? null;
            $q = $qid ? $questionsById->get($qid) : null;

            $options = [];
            if ($q && is_array($q->options_json)) {
                foreach ($q->options_json as $opt) {
                    $options[] = [
                        'key' => $opt['key'] ?? '',
                        'label' => $opt['label'] ?? '',
                        'image_url' => filled($opt['image_path'] ?? null) && $imageBase
                            ? "{$imageBase}/{$q->id}/image?option={$opt['key']}"
                            : null,
                    ];
                }
            }

            $out[] = [
                'index' => $index + 1,
                'question_id' => $qid,
                'type' => $row['type'] ?? $q?->typeValue() ?? 'single',
                'question_body' => $q?->body_text ?? null,
                'question_image_url' => $q?->document_path && $imageBase ? "{$imageBase}/{$q->id}/image" : null,
                'options' => $options,
                'explanation' => $q?->explanation ?? null,
                'chosen' => $row['chosen'] ?? null,
                'correct' => $row['correct'] ?? null,
                'is_correct' => (bool) ($row['is_correct'] ?? false),
                'awarded' => $row['awarded'] ?? null,
                'marks' => $row['marks'] ?? $q?->marksValue() ?? 1,
                'negative_mark' => $row['negative_mark'] ?? (float) ($q?->negative_mark ?? 0),
            ];
        }

        return $out;
    }
}
