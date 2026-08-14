<?php

namespace App\Services\Website;

use App\Models\KalotsavEvent;
use App\Models\WebsiteSite;

class SahodayaHomepageModeResolver
{
    public function resolve(WebsiteSite $site): string
    {
        if ($site->homepage_mode_override_until?->isFuture()) {
            return $site->homepage_mode ?: 'evergreen';
        }

        $event = KalotsavEvent::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('is_active', true)
            ->orderByDesc('event_date')
            ->first();

        if (! $event) return 'evergreen';
        if ($event->results_published) return 'results_published';
        if ($event->event_date?->isBetween(now()->subDay()->startOfDay(), now()->addDay()->endOfDay())) return 'event_live';
        if ($event->event_date?->isFuture()) return 'registration_open';

        return 'evergreen';
    }
}
