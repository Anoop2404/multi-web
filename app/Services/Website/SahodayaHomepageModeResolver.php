<?php

namespace App\Services\Website;

use App\Models\FestEvent;
use App\Models\WebsiteSite;

class SahodayaHomepageModeResolver
{
    public function resolve(WebsiteSite $site): string
    {
        if ($site->homepage_mode_override_until?->isFuture()) {
            return $site->homepage_mode ?: 'evergreen';
        }

        $event = FestEvent::forTenant($site->tenant_id)
            ->whereNull('parent_event_id') // top-level events only — cascaded regional/cluster children share this parent
            ->visibleInNav()
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->orderByDesc('event_start')
            ->first();

        if (! $event) return 'evergreen';
        if ($event->results_published) return 'results_published';
        if ($event->event_start?->isBetween(now()->subDay()->startOfDay(), now()->addDay()->endOfDay())) return 'event_live';
        if ($event->event_start?->isFuture()) return 'registration_open';

        return 'evergreen';
    }
}
