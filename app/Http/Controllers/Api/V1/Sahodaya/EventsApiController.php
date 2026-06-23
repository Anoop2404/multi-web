<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Models\FestEvent;
use App\Services\Events\EventContext;
use Illuminate\Http\Request;

class EventsApiController extends SahodayaApiController
{
    public function index(Request $request)
    {
        $events = FestEvent::forTenant($this->sahodaya->id)
            ->withCount(['items', 'registrations'])
            ->orderByDesc('event_start')
            ->get();

        return response()->json(['data' => $events]);
    }

    public function show(FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return response()->json([
            'data' => $event->load('items'),
            'scoreboard' => EventContext::for($event)->scoreboardBySchool(),
        ]);
    }
}
