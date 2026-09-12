<?php

namespace App\Support;

use App\Models\FestEvent;
use App\Models\FestEventItem;

/**
 * Reads the admin-configured "exclude this category's points from the overall/combined
 * school scoreboard" setting (aggregation_config.excluded_overall_categories on the ROOT
 * event) — same storage convention as FestCategoryMerge's championship_category_map.
 * A category's own individual scoreboard tab is unaffected; only the "All Categories"
 * combined total skips it.
 */
class FestOverallCategoryExclusion
{
    /** @return list<string> */
    public static function excluded(FestEvent $root): array
    {
        $raw = ($root->aggregation_config ?? [])['excluded_overall_categories'] ?? [];

        return collect($raw)->filter(fn ($v) => is_string($v) && $v !== '')->values()->all();
    }

    public static function isExcluded(FestEvent $root, ?string $rawCategory): bool
    {
        if (! $rawCategory) {
            return false;
        }

        return in_array($rawCategory, self::excluded($root), true);
    }

    /** class_group for a Kalotsav-style event, age_group for a sports event — same convention as PublicFestScoreboardService::categories()/EventContext::scoreboardByCategory(). */
    public static function categoryKeyForItem(FestEvent $event, ?FestEventItem $item): ?string
    {
        if (! $item) {
            return null;
        }

        return $event->event_type === 'sports' ? $item->age_group : $item->class_group;
    }
}
