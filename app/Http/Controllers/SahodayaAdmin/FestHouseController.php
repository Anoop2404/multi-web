<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestHouse;
use App\Models\FestHouseSchool;
use App\Models\Tenant;
use App\Services\Events\EventContext;
use Illuminate\Http\Request;

class FestHouseController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $houses = FestHouse::where('event_id', $event->id)
            ->with('schoolAssignments')
            ->orderBy('sort_order')
            ->get();

        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name']);

        $assignedSchoolIds = FestHouseSchool::where('event_id', $event->id)->pluck('school_id');

        return $this->inertia('Sahodaya/Events/Houses', [
            'event'           => $event,
            'houses'          => $houses,
            'schools'         => $schools,
            'houseScoreboard' => EventContext::for($event)->scoreboardByHouse(),
            'assignedSchoolIds' => $assignedSchoolIds,
        ]);
    }

    public function storeHouse(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
            'motto' => 'nullable|string|max:255',
        ]);

        FestHouse::create(array_merge($data, [
            'event_id'   => $event->id,
            'sort_order' => (FestHouse::where('event_id', $event->id)->max('sort_order') ?? 0) + 1,
        ]));

        return back()->with('success', 'House created.');
    }

    public function assignSchool(Request $request, string $tenantId, FestEvent $event, FestHouse $house)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($house->event_id !== $event->id, 403);

        $data = $request->validate([
            'school_id' => 'required|string',
        ]);

        FestHouseSchool::updateOrCreate(
            ['event_id' => $event->id, 'school_id' => $data['school_id']],
            ['house_id' => $house->id]
        );

        return back()->with('success', 'School assigned to house.');
    }

    public function destroyHouse(string $tenantId, FestEvent $event, FestHouse $house)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($house->event_id !== $event->id, 403);
        $house->delete();

        return back()->with('success', 'House removed.');
    }
}
