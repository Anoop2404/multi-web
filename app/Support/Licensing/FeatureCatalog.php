<?php

namespace App\Support\Licensing;

/**
 * Static registry of licensable feature/module keys (FRD-13 §9) so the plan-editing UI
 * has a fixed checklist instead of freeform key entry. 'limit' type features store their
 * cap in limit_value; 'module' type features are plain on/off.
 */
class FeatureCatalog
{
    /** @return array<string, array{label: string, type: 'module'|'limit'}> */
    public static function all(): array
    {
        return [
            'module.kalotsavam' => ['label' => 'Kalotsavam / cultural fest', 'type' => 'module'],
            'module.sports' => ['label' => 'Sports meet', 'type' => 'module'],
            'module.mcq' => ['label' => 'Talent Search (MCQ) exams', 'type' => 'module'],
            'module.training' => ['label' => 'Teacher training', 'type' => 'module'],
            'module.board_results' => ['label' => 'Board results', 'type' => 'module'],
            'module.website' => ['label' => 'Public website / site builder', 'type' => 'module'],
            'module.website_premium' => ['label' => 'Premium website design tier', 'type' => 'module'],
            'api_access' => ['label' => 'API access', 'type' => 'module'],
            'limit.schools' => ['label' => 'Max member schools', 'type' => 'limit'],
            'limit.students' => ['label' => 'Max students', 'type' => 'limit'],
            'limit.storage_gb' => ['label' => 'Storage limit (GB)', 'type' => 'limit'],
        ];
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'] ?? $key;
    }

    public static function isLimit(string $key): bool
    {
        return (self::all()[$key]['type'] ?? 'module') === 'limit';
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
