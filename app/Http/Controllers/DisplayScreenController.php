<?php

namespace App\Http\Controllers;

use App\Models\FestEvent;
use App\Models\ScreenSetting;
use App\Services\Events\EventContext;
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
        $event = $eventId ? FestEvent::find($eventId) : null;
        $scoreboard = $event ? EventContext::for($event)->scoreboardBySchool() : [];

        return inertia('Display/Scoreboard', [
            'screen'     => $screen->only('slug', 'title', 'config_json'),
            'event'      => $event?->only('id', 'title', 'event_type'),
            'scoreboard' => $scoreboard,
        ]);
    }
}
