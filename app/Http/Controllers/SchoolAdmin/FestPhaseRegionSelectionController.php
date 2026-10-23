<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Services\Events\FestSchoolEventRegionService;
use App\Services\Events\FestSchoolPhaseRegionService;
use Illuminate\Http\Request;

class FestPhaseRegionSelectionController extends SchoolAdminController
{
    public function store(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestSchoolPhaseRegionService $selections,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        if (! $event->rootEvent()->usesPhasedRegionalBilling()) {
            $data = $request->validate(['region_id' => 'required|integer']);
            app(FestSchoolEventRegionService::class)->select($event, $this->school->id, (int) $data['region_id'], $request->user()?->id);

            return back()->with('success', 'Event region selected and locked. Future changes require Sahodaya approval.');
        }
        $data = $request->validate([
            'phase_id' => 'required|integer|exists:fest_event_phases,id',
            'region_id' => 'required|integer|exists:regions,id',
        ]);
        $phase = FestEventPhase::findOrFail($data['phase_id']);

        $selection = $selections->select(
            $event,
            $phase,
            $this->school->id,
            (int) $data['region_id'],
            $request->user()?->id,
        );

        return back()->with('success', "{$selection->region->name} selected for {$selection->phase->name}.");
    }
}
