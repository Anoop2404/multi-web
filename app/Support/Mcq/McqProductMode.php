<?php

namespace App\Support\Mcq;

use App\Models\McqExam;

/**
 * Product surface for MCQ exams. Talent Search keeps fees/hall tickets/series chrome;
 * model-exam modes hide that ceremony while reusing the same Mcq* delivery stack.
 */
final class McqProductMode
{
    public const TALENT_SEARCH = 'talent_search';

    public const SAHODAYA_MODEL = 'sahodaya_model_exam';

    public const SCHOOL_MODEL = 'school_model_exam';

    public const ALL = [
        self::TALENT_SEARCH,
        self::SAHODAYA_MODEL,
        self::SCHOOL_MODEL,
    ];

    public static function of(McqExam $exam): string
    {
        $mode = $exam->settings_json['product_mode'] ?? null;
        if (in_array($mode, self::ALL, true)) {
            return $mode;
        }

        if (($exam->conductor_level ?? 'sahodaya') === 'school') {
            return self::SCHOOL_MODEL;
        }

        return self::TALENT_SEARCH;
    }

    public static function isModelExam(McqExam $exam): bool
    {
        return in_array(self::of($exam), [self::SAHODAYA_MODEL, self::SCHOOL_MODEL], true);
    }

    public static function isTalentSearch(McqExam $exam): bool
    {
        return self::of($exam) === self::TALENT_SEARCH;
    }

    public static function label(string $mode): string
    {
        return match ($mode) {
            self::SAHODAYA_MODEL => 'Sahodaya model exam',
            self::SCHOOL_MODEL => 'School model exam',
            default => 'Talent Search',
        };
    }

    /**
     * Default settings merged when creating / switching to a product mode.
     *
     * @return array<string, mixed>
     */
    public static function presetSettings(string $mode): array
    {
        return match ($mode) {
            self::SAHODAYA_MODEL => [
                'product_mode' => self::SAHODAYA_MODEL,
                'requires_hall_ticket' => false,
                'require_verified_students' => true,
                'multi_marking' => 'partial',
                'shuffle_questions' => true,
                'shuffle_options' => true,
                'allow_member_banks' => false,
            ],
            self::SCHOOL_MODEL => [
                'product_mode' => self::SCHOOL_MODEL,
                'requires_hall_ticket' => false,
                'require_verified_students' => false,
                'multi_marking' => 'partial',
                'shuffle_questions' => true,
                'shuffle_options' => true,
            ],
            default => [
                'product_mode' => self::TALENT_SEARCH,
            ],
        };
    }

    /** Whether fee / payment / ledger UI should show. */
    public static function showsFees(McqExam $exam): bool
    {
        return self::isTalentSearch($exam);
    }

    /** Whether hall ticket / centre-code ceremony UI should show. */
    public static function showsHallTickets(McqExam $exam): bool
    {
        return self::isTalentSearch($exam) || $exam->requiresHallTicket();
    }
}
