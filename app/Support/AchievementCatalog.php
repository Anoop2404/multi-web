<?php

namespace App\Support;

/**
 * Canonical achievement category / level master data (#156).
 */
class AchievementCatalog
{
    public const CATEGORIES = [
        'academic' => 'Academic',
        'sports' => 'Sports',
        'cultural' => 'Cultural',
        'science' => 'Science',
        'arts' => 'Arts',
        'social' => 'Social',
        'national' => 'National',
        'other' => 'Other',
    ];

    public const LEVELS = [
        'school' => 'School Level',
        'district' => 'District Level',
        'state' => 'State Level',
        'national' => 'National Level',
        'international' => 'International Level',
    ];

    /** @return list<string> */
    public static function categoryKeys(): array
    {
        return array_keys(self::CATEGORIES);
    }

    /** @return list<string> */
    public static function levelKeys(): array
    {
        return array_keys(self::LEVELS);
    }

    public static function normalizeCategory(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $key = strtolower(trim($value));
        if (isset(self::CATEGORIES[$key])) {
            return $key;
        }

        // Legacy UI labels → keys
        $byLabel = array_change_key_case(array_flip(self::CATEGORIES), CASE_LOWER);
        $labelKey = strtolower(trim($value));

        return $byLabel[$labelKey] ?? 'other';
    }

    public static function normalizeLevel(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $key = strtolower(str_replace([' ', '-'], '_', trim($value)));
        $key = preg_replace('/_level$/', '', $key) ?? $key;
        if (isset(self::LEVELS[$key])) {
            return $key;
        }

        $byLabel = [];
        foreach (self::LEVELS as $k => $label) {
            $byLabel[strtolower($label)] = $k;
            $byLabel[strtolower(str_replace(' Level', '', $label))] = $k;
        }

        return $byLabel[strtolower(trim($value))] ?? 'school';
    }

    public static function categoryLabel(?string $key): string
    {
        $normalized = self::normalizeCategory($key);

        return $normalized ? (self::CATEGORIES[$normalized] ?? $key ?? '—') : '—';
    }

    public static function levelLabel(?string $key): string
    {
        $normalized = self::normalizeLevel($key);

        return $normalized ? (self::LEVELS[$normalized] ?? $key ?? '—') : '—';
    }
}
