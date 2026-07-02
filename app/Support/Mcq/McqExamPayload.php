<?php

namespace App\Support\Mcq;

use App\Support\PersistDefaults;

class McqExamPayload
{
    public const DEFAULT_DURATION_MINUTES = 60;

    public static function durationMinutes(mixed $value): int
    {
        if (! filled($value) || ! is_numeric($value)) {
            return self::DEFAULT_DURATION_MINUTES;
        }

        $minutes = (int) $value;

        if ($minutes < 5 || $minutes > 480) {
            return self::DEFAULT_DURATION_MINUTES;
        }

        return $minutes;
    }

    public static function totalQuestions(mixed $value): int
    {
        return PersistDefaults::integer($value, 0, 0, 9999);
    }

    public static function nextHallTicketNo(mixed $value): int
    {
        return PersistDefaults::integer($value, 100, 1, 99_999_999);
    }

    /** @param  array<string, mixed>  $data */
    public static function applyDefaults(array $data): array
    {
        $data['duration_minutes'] = self::durationMinutes($data['duration_minutes'] ?? null);
        $data['total_questions'] = self::totalQuestions($data['total_questions'] ?? null);
        $data['eligibility_config'] = McqExamEligibilityConfig::normalize($data['eligibility_config'] ?? null);

        $fee = (float) ($data['fee_amount'] ?? 0);

        return PersistDefaults::coalesce($data, [
            'exam_type'           => 'assessment',
            'delivery_mode'       => 'offline',
            'fee_type'            => $fee > 0 ? 'flat' : 'none',
            'next_hall_ticket_no' => 100,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function eligibilityError(array $data): ?string
    {
        return McqExamEligibilityConfig::validationError($data['eligibility_config'] ?? null);
    }
}
