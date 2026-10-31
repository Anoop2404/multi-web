<?php

namespace App\Support;

use App\Models\FestEvent;
use App\Models\FestEventItem;

final class FestStandbyLimit
{
    public static function forItem(FestEvent $event, ?FestEventItem $item): int
    {
        if ($event->event_type !== 'sports') {
            return 2;
        }
        $criteria = $item?->criteria_json ?? [];
        return max(0, (int) ($criteria['standbys'] ?? 1));
    }
}
