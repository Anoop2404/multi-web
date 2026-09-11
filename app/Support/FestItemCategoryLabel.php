<?php

namespace App\Support;

use App\Models\FestEventItem;

/**
 * Centralizes the "category" shown next to a Kalotsav item's name across mark entry
 * sheets, exports, public visibility, and reports. "Category" in Kalotsav context
 * usually means the item's class/age bracket (e.g. "Category 1 — Classes 3 & 4"), not
 * the internal arts_category genre tag — so class_group takes priority, then the
 * sports-only age_group, and only then the arts genre as a last resort. Callers pass
 * in already-resolved label maps (FestClassGroupScheme::labels(), the arts_category
 * taxonomy) so this stays a cheap, DB-free lookup safe to call in a loop.
 */
class FestItemCategoryLabel
{
    /**
     * @param  array<string, string>  $classGroupLabels
     * @param  array<string, string>  $artsCategoryLabels
     */
    public static function resolve(?FestEventItem $item, array $classGroupLabels, array $artsCategoryLabels = []): ?string
    {
        if (! $item) {
            return null;
        }

        if ($item->class_group && $item->class_group !== 'open') {
            return FestClassGroupScheme::resolveItemLabel($classGroupLabels, $item->class_group);
        }

        if ($item->age_group) {
            $tenantId = $item->event?->tenant_id;
            $labels = FestSportsAgeGroup::labels($tenantId);

            return $labels[$item->age_group] ?? $item->age_group;
        }

        if ($item->category && $item->category !== 'general') {
            return $artsCategoryLabels[$item->category] ?? ucwords(str_replace(['_', '-'], ' ', $item->category));
        }

        return null;
    }

    /** Short form for contexts (like certificates) that want just the leading part of a "Long — elaboration" label. */
    public static function shortLabel(?FestEventItem $item, array $classGroupLabels, array $artsCategoryLabels = []): ?string
    {
        $label = self::resolve($item, $classGroupLabels, $artsCategoryLabels);

        return $label ? trim(explode(' — ', $label)[0]) : null;
    }

    /**
     * "Boys"/"Girls"/"Mixed" for an item's own gender restriction, null for 'open' (no
     * restriction — not worth stating). Shared by the certificate item picker, the
     * grouped-by-item admin views, and grouped ZIP exports so all three describe the
     * same item the same way.
     */
    public static function genderLabel(?string $gender): ?string
    {
        return match (strtolower((string) $gender)) {
            'male', 'm', 'boy', 'boys' => 'Boys',
            'female', 'f', 'girl', 'girls' => 'Girls',
            'mixed', 'common' => 'Mixed',
            default => null,
        };
    }

    /** Same 5-value mapping as FestCertificateService::itemTaxonomyLabels()'s $type. */
    public static function typeLabel(?string $participantType): string
    {
        return match (strtolower((string) $participantType)) {
            'group' => 'Group',
            'team' => 'Team',
            'pair' => 'Pair',
            'trio' => 'Trio',
            default => 'Individual',
        };
    }
}
