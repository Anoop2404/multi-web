<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\SiteSection;
use App\Models\Tenant;

class FestCmsAutoPush
{
    /** Push published fest scoreboard into homepage kalotsav section config. */
    public function pushScoreboard(FestEvent $event): void
    {
        $tenant = Tenant::find($event->tenant_id);
        if (! $tenant) {
            return;
        }

        $scoreboard = EventContext::for($event)->scoreboardBySchool();

        SiteSection::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('section_type', 'kalotsav')
                    ->orWhere('section_type', 'sports_meet');
            })
            ->each(function (SiteSection $section) use ($event, $scoreboard) {
                $config = $section->config ?? [];
                $config['fest_event_id'] = $event->id;
                $config['kalotsav_event_id'] = $event->id;
                $config['event_title'] = $event->title;
                // LIFE-08 fix (functional audit, 2026-08-11/12): this used to hardcode
                // `true` regardless of the event's actual state, so a homepage section
                // pushed once at publish-time could never be told results were later
                // unpublished — FestResultsController::unpublish() now calls
                // pushScoreboard() too, and needs this to reflect the live flag instead
                // of re-asserting "published" on every call.
                $config['results_published'] = (bool) $event->results_published;
                $config['scoreboard'] = $scoreboard;
                $section->update(['config' => $config]);
            });

        $tenant->invalidateCache();
    }
}
