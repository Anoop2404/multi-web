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
                'status'       => $registration->status,
                'submitted_at' => $registration->submitted_at?->toIso8601String(),
            ];
        }

        if (! $exam->results_published) {
            return [
                'status'       => 'submitted',
                'submitted_at' => $registration->submitted_at?->toIso8601String(),
                'pending'      => true,
            ];
        }

        $mark ??= $registration->mark;

        return [
            'status'       => 'submitted',
            'submitted_at' => $registration->submitted_at?->toIso8601String(),
            'score'        => $mark?->score,
            'percentage'   => $mark?->percentage,
            'grade'        => $mark?->grade,
            'correct_count'=> $mark?->correct_count,
            'wrong_count'  => $mark?->wrong_count,
            'unanswered_count' => $mark?->unanswered_count,
        ];
    }

    /** @return array<string, mixed> */
    public static function forExamList(McqExam $exam, McqRegistration $registration): array
    {
        return [
            'registration_id' => $registration->id,
            'exam_id'         => $exam->id,
            'title'           => $exam->title,
            'status'          => $registration->status,
            'scheduled_at'    => $exam->scheduled_at?->toIso8601String(),
            'results_published' => (bool) $exam->results_published,
            'mark'            => $exam->results_published && $registration->status === 'submitted'
                ? self::forRegistration($registration)
                : null,
        ];
    }
}
