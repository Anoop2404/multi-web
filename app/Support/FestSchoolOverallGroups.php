<?php

namespace App\Support;

use App\Models\FestEvent;

/** Overlapping school championship totals; category points remain unchanged. */
class FestSchoolOverallGroups
{
    public static function groups(FestEvent $event): array
    {
        return collect($event->rootEvent()->aggregation_config['school_overall_groups'] ?? [])
            ->map(fn ($group) => array_merge($group, ['school_tier' => $group['key']]))->all();
    }

    public static function find(FestEvent $event, ?string $key): ?array
    {
        if (! $key || ! str_starts_with($key, 'overall:')) return null;
        return collect(self::groups($event))->firstWhere('key', substr($key, 8));
    }
}
