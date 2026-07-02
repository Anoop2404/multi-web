<?php

namespace App\Http\Controllers;

use App\Models\FestEvent;
use App\Models\FestSchedule;
use App\Models\ScreenSetting;
use App\Services\Events\EventContext;
use App\Services\Events\FestPublicVisibilityService;
use App\Support\TenantBranding;
use Illuminate\Http\Request;

class DisplayScreenController extends Controller
{
    public function show(Request $request, string $tenantId, string $slug)
    {
        $screen = ScreenSetting::where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $eventId = $screen->config_json['event_id'] ?? null;
        $event = $eventId ? FestEvent::where('tenant_id', $tenantId)->find($eventId) : null;
        $scoreboard = $event ? EventContext::for($event)->scoreboardBySchool() : [];
        $nowPerforming = null;
        $nextUp = [];

        if ($event) {
            $visibility = app(FestPublicVisibilityService::class);

            $nowSlot = FestSchedule::where('event_id', $event->id)
                ->whereNotNull('called_at')
                ->orderByDesc('called_at')
                ->with(['item', 'participant.student', 'participant.registration.event', 'participant.registration.item'])
                ->first();

            if ($nowSlot?->participant) {
                $nowPerforming = $visibility->formatPublicParticipant($event, $nowSlot->participant, $nowSlot);
                $nowPerforming['item_title'] = $nowSlot->item?->title;
            }

            $nextUp = FestSchedule::where('event_id', $event->id)
                ->whereNull('called_at')
                ->orderBy('sort_order')
                ->orderBy('scheduled_at')
                ->with(['item', 'participant.student', 'participant.registration.event', 'participant.registration.item'])
                ->limit(3)
                ->get()
                ->map(function (FestSchedule $s) use ($event, $visibility) {
                    if (! $s->participant) {
                        return ['item_title' => $s->item?->title, 'reference' => null];
                    }

                    $row = $visibility->formatPublicParticipant($event, $s->participant, $s);

                    return [
                        'item_title' => $s->item?->title,
                        'reference'  => $row['reference'],
                        'order'      => $s->sort_order,
                    ];
                })
                ->all();
        }

        return inertia('Display/Scoreboard', [
            'screen'        => $screen->only('slug', 'title', 'config_json'),
            'event'         => $event?->only('id', 'title', 'event_type'),
            'scoreboard'    => $scoreboard,
            'nowPerforming' => $nowPerforming,
            'nextUp'        => $nextUp,
            'logoUrl'       => $event ? TenantBranding::logoUrl(\App\Models\Tenant::find($event->tenant_id)) : null,
        ]);
    }
}
