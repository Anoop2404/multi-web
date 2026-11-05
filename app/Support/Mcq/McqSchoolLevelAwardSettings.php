<?php

namespace App\Support\Mcq;

use App\Models\McqExam;

/**
 * Per-exam school-level award / mark-entry policy (STSE KG Setting 1).
 *
 * Stored under settings_json.school_level_awards.
 *
 * KG (LKG/UKG): school-only evaluation, no Level-2 exam. Schools enter marks,
 * then Promote/Submit the exact top 15% for district recognition — with no ties
 * in ranks 1–3 and no ties that would expand the 15% cutoff border.
 */
final class McqSchoolLevelAwardSettings
{
    public const PRESET_STSE_KG_SETTING_1 = 'stse_kg_setting_1';

    /** @deprecated Use PRESET_STSE_KG_SETTING_1. Kept so older saved exams still resolve. */
    public const PRESET_MCS_MALAPPURAM = 'mcs_malappuram';

    public const PRESETS = [
        self::PRESET_STSE_KG_SETTING_1,
        self::PRESET_MCS_MALAPPURAM,
    ];

    public const TIE_ERROR = 'Rank 1, 2, 3 or the top 15% quota contains ties. Please resolve ties via re-valuation or tie-breaker to proceed.';

    public static function normalizePreset(?string $preset): ?string
    {
        if ($preset === null || $preset === '') {
            return null;
        }

        if ($preset === self::PRESET_MCS_MALAPPURAM) {
            return self::PRESET_STSE_KG_SETTING_1;
        }

        return in_array($preset, self::PRESETS, true) ? $preset : null;
    }

    public static function presetLabel(string $preset): string
    {
        return match (self::normalizePreset($preset) ?? $preset) {
            self::PRESET_STSE_KG_SETTING_1 => 'STSE KG Setting 1',
            default => 'STSE KG Setting 1',
        };
    }

    /** @return array{enabled: bool, preset: ?string, kg: array<string, mixed>} */
    public static function forExam(McqExam $exam): array
    {
        $block = $exam->settings_json['school_level_awards'] ?? [];
        if (! is_array($block)) {
            $block = [];
        }

        $enabled = (bool) ($block['enabled'] ?? false);
        $preset = self::normalizePreset($block['preset'] ?? null);

        $defaults = self::presetKg(self::PRESET_STSE_KG_SETTING_1);
        $kg = is_array($block['kg'] ?? null) ? array_merge($defaults, $block['kg']) : $defaults;
        $kg['classes'] = self::normalizeClasses($kg['classes'] ?? $defaults['classes']);
        $kg['cutoff_percent'] = max(0.0, min(100.0, (float) ($kg['cutoff_percent'] ?? 15)));
        $kg['unique_top_ranks'] = array_values(array_map(
            'intval',
            array_filter((array) ($kg['unique_top_ranks'] ?? [1, 2, 3]), fn ($r) => (int) $r >= 1)
        ));
        $kg['block_cutoff_ties'] = (bool) ($kg['block_cutoff_ties'] ?? true);
        $kg['school_level_only'] = (bool) ($kg['school_level_only'] ?? true);

        return [
            'enabled' => $enabled,
            'preset' => $preset,
            'kg' => $kg,
        ];
    }

    /** @return array{enabled: bool, preset: ?string, kg: array<string, mixed>, classes: list<string>, cutoff_percent: float, preset_label: ?string} */
    public static function forInertia(McqExam $exam): array
    {
        $cfg = self::forExam($exam);

        return [
            'enabled' => $cfg['enabled'],
            'preset' => $cfg['preset'],
            'preset_label' => $cfg['preset'] ? self::presetLabel($cfg['preset']) : null,
            'kg' => $cfg['kg'],
            'classes' => $cfg['kg']['classes'],
            'cutoff_percent' => (float) $cfg['kg']['cutoff_percent'],
            'tie_error' => self::TIE_ERROR,
        ];
    }

    public static function enabled(McqExam $exam): bool
    {
        return self::forExam($exam)['enabled'];
    }

    public static function isKgClass(McqExam $exam, ?string $className): bool
    {
        if (! self::enabled($exam) || $className === null || trim($className) === '') {
            return false;
        }

        $needle = McqClassKey::normalize($className);
        foreach (self::forExam($exam)['kg']['classes'] as $allowed) {
            if (McqClassKey::normalize($allowed) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *   classes: list<string>,
     *   cutoff_percent: float,
     *   unique_top_ranks: list<int>,
     *   block_cutoff_ties: bool,
     *   school_level_only: bool
     * }
     */
    public static function presetKg(string $preset): array
    {
        $key = self::normalizePreset($preset) ?? self::PRESET_STSE_KG_SETTING_1;

        return match ($key) {
            self::PRESET_STSE_KG_SETTING_1 => [
                'classes' => ['LKG', 'UKG'],
                'cutoff_percent' => 15.0,
                'unique_top_ranks' => [1, 2, 3],
                'block_cutoff_ties' => true,
                'school_level_only' => true,
            ],
            default => [
                'classes' => ['LKG', 'UKG'],
                'cutoff_percent' => 15.0,
                'unique_top_ranks' => [1, 2, 3],
                'block_cutoff_ties' => true,
                'school_level_only' => true,
            ],
        };
    }

    /**
     * Normalize admin form input into a settings_json.school_level_awards patch.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeInput(array $input, McqExam $exam): array
    {
        $existing = $exam->settings_json['school_level_awards'] ?? [];
        if (! is_array($existing)) {
            $existing = [];
        }

        $enabled = array_key_exists('school_level_awards_enabled', $input)
            ? (bool) $input['school_level_awards_enabled']
            : (bool) ($existing['enabled'] ?? false);

        $preset = self::normalizePreset(
            $input['school_level_awards_preset'] ?? ($existing['preset'] ?? null)
        );
        if ($enabled && $preset === null) {
            $preset = self::PRESET_STSE_KG_SETTING_1;
        }
        if (! $enabled) {
            $preset = null;
        }

        $kg = self::presetKg($preset ?? self::PRESET_STSE_KG_SETTING_1);
        if (array_key_exists('school_level_awards_cutoff_percent', $input) && $input['school_level_awards_cutoff_percent'] !== null) {
            $kg['cutoff_percent'] = max(0.0, min(100.0, (float) $input['school_level_awards_cutoff_percent']));
        } elseif (isset($existing['kg']['cutoff_percent'])) {
            $kg['cutoff_percent'] = (float) $existing['kg']['cutoff_percent'];
        }

        return [
            'enabled' => $enabled,
            'preset' => $enabled ? ($preset ?? self::PRESET_STSE_KG_SETTING_1) : null,
            'kg' => $kg,
            // Preserve per-school submission locks when toggling policy.
            'submissions' => is_array($existing['submissions'] ?? null) ? $existing['submissions'] : [],
        ];
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    public static function submissions(McqExam $exam): array
    {
        $subs = $exam->settings_json['school_level_awards']['submissions'] ?? [];

        return is_array($subs) ? $subs : [];
    }

    public static function isClassSubmitted(McqExam $exam, string $schoolId, ?string $className): bool
    {
        if ($className === null) {
            return false;
        }

        $key = McqClassKey::normalize($className);
        $entry = self::submissions($exam)[$schoolId][$key] ?? null;

        return is_array($entry) && ($entry['status'] ?? null) === 'submitted';
    }

    /**
     * @param  list<string>|mixed  $classes
     * @return list<string>
     */
    private static function normalizeClasses(mixed $classes): array
    {
        if (! is_array($classes) || $classes === []) {
            return ['LKG', 'UKG'];
        }

        $out = [];
        foreach ($classes as $class) {
            $n = McqClassKey::normalize((string) $class);
            if ($n !== '' && ! in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out !== [] ? $out : ['LKG', 'UKG'];
    }
}
