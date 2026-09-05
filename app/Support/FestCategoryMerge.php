<?php

namespace App\Support;

use App\Models\FestEvent;

/**
 * Reads the admin-configured "merge these categories into one scoring bucket" setting
 * (aggregation_config.championship_category_map on the ROOT event) — shared by every
 * place that needs to fold one category's marks/points into another's: the individual
 * championship (FestChampionshipController), the school/team cumulative scoreboard
 * (FestCumulativeChampionshipService, which originated this map), and the plain/
 * non-phased public scoreboard (PublicFestScoreboardService).
 */
class FestCategoryMerge
{
    /**
     * The flat, event-wide "source => target" entries only — a numeric-string key
     * holding its own sub-array is a per-phase override consumed directly by
     * FestCumulativeChampionshipService, not something this shared flat-map reader
     * (used by the simpler, non-phased paths) should surface.
     *
     * @return array<string, string>
     */
    public static function map(FestEvent $root): array
    {
        $map = ($root->aggregation_config ?? [])['championship_category_map'] ?? [];

        return collect($map)->filter(fn ($target) => is_string($target))->all();
    }

    public static function resolve(FestEvent $root, string $rawCategory): string
    {
        return self::map($root)[$rawCategory] ?? $rawCategory;
    }

    /**
     * Every raw category key that ends up counting toward $target once merges are
     * applied — the target itself (in case items are tagged with it directly) plus any
     * source keys mapped onto it. Use this to gather marks/items for a "category"
     * filter value that might now be a merge target, not just its own raw key.
     *
     * @return list<string>
     */
    public static function sourceKeysFor(FestEvent $root, string $target): array
    {
        $sources = collect(self::map($root))->filter(fn ($t) => $t === $target)->keys();

        return $sources->push($target)->unique()->values()->all();
    }

    /**
     * Collapse a list of raw category keys down to their merged targets, deduplicated —
     * for building a "which categories can I filter by" list that shows one merged
     * bucket once instead of every raw key that feeds it.
     *
     * @param  list<string>  $rawKeys
     * @return list<string>
     */
    public static function collapse(FestEvent $root, array $rawKeys): array
    {
        $map = self::map($root);

        return collect($rawKeys)->map(fn ($key) => $map[$key] ?? $key)->unique()->values()->all();
    }
}
