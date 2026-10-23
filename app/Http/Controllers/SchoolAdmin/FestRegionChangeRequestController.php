<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestPhaseRegion;
use App\Models\FestRegionChangeRequest;
use App\Services\Events\FestSchoolEventRegionService;
use App\Services\Events\FestSchoolPhaseRegionService;
use App\Support\ProgramRouteMap;
use App\Support\SchoolFestProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FestRegionChangeRequestController extends SchoolAdminController
{
    public function index(string $tenantId, FestEvent $event, string $program, FestSchoolPhaseRegionService $selections)
    {
        $meta = SchoolFestProgram::meta($program);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $root = $event->rootEvent();
        if (! $root->usesPhasedRegionalBilling()) {
            $service = app(FestSchoolEventRegionService::class);
            $current = $service->current($root, $this->school->id);

            return $this->inertia('School/Events/RegionChangeRequests', [
                'event' => $root->only('id', 'title', 'status'), 'program' => $meta['slug'], 'programMeta' => $meta,
                'phases' => [['id' => 0, 'name' => $root->title,
                    'regions' => $service->regions($root)->map(fn ($child) => ['region_id' => $child->region_id, 'name' => $child->region?->name ?? $child->cluster_label])->values(),
                    'current_region_id' => $current?->region_id, 'current_region_name' => $current?->region?->name ?? $current?->cluster_label, 'locked' => $current !== null,
                ]],
                'requests' => FestRegionChangeRequest::where('event_id', $root->id)->where('school_id', $this->school->id)->whereNull('phase_id')->with(['currentRegion:id,name', 'requestedRegion:id,name'])->latest()->get(),
            ]);
        }

        $phases = FestEventPhase::where('event_id', $root->id)
            ->where(function ($q) {
                $q->where('is_regional', true)->orWhereNotNull('region_partition_group');
            })
            ->orderBy('sort_order')
            ->with(['allowedRegions' => fn ($q) => $q->where('enabled', true)->with('region:id,name')])
            ->get();

        $requests = FestRegionChangeRequest::where('event_id', $root->id)
            ->where('school_id', $this->school->id)
            ->with(['phase:id,name', 'currentRegion:id,name', 'requestedRegion:id,name'])
            ->latest()
            ->get();

        return $this->inertia('School/Events/RegionChangeRequests', [
            'event' => $event->only('id', 'title', 'status'),
            'program' => $meta['slug'],
            'programMeta' => $meta,
            'phases' => $phases->map(function (FestEventPhase $phase) use ($root, $selections) {
                $selection = $selections->resolve($root, $phase, $this->school->id);

                return [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'regions' => $phase->allowedRegions->map(fn ($ar) => [
                        'region_id' => $ar->region_id,
                        'name' => $ar->region?->name,
                    ])->values(),
                    'current_region_id' => $selection?->region_id,
                    'current_region_name' => $selection?->region?->name,
                    'locked' => (bool) $selection?->isLocked(),
                ];
            })->values(),
            'requests' => $requests,
        ]);
    }

    public function store(Request $request, string $tenantId, FestEvent $event, string $program, FestSchoolPhaseRegionService $selections)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $root = $event->rootEvent();
        if (! $root->usesPhasedRegionalBilling()) {
            $data = $request->validate(['requested_region_id' => 'required|integer', 'reason' => 'required|string|max:2000']);
            $service = app(FestSchoolEventRegionService::class);
            $current = $service->current($root, $this->school->id);
            abort_unless($current, 422, 'Select your initial event region first.');
            abort_unless($service->regions($root)->contains('region_id', (int) $data['requested_region_id']), 422, 'That region is not configured for this event.');
            abort_if((int) $current->region_id === (int) $data['requested_region_id'], 422, 'That is already your region.');
            DB::transaction(function () use ($root, $current, $data, $request) {
                FestEvent::whereKey($root->id)->lockForUpdate()->firstOrFail();
                abort_if(FestRegionChangeRequest::where('event_id', $root->id)->whereNull('phase_id')->where('school_id', $this->school->id)->where('status', 'pending')->exists(), 422, 'A region change request is already pending review.');
                FestRegionChangeRequest::create(['event_id' => $root->id, 'school_id' => $this->school->id, 'phase_id' => null,
                    'current_region_id' => $current->region_id, 'requested_region_id' => $data['requested_region_id'], 'reason' => $data['reason'],
                    'status' => 'pending', 'requested_by_user_id' => $request->user()?->id]);
            });

            return back()->with('success', 'Event region change submitted for Sahodaya approval.');
        }

        $data = $request->validate([
            'phase_id' => ['required', 'integer', Rule::exists('fest_event_phases', 'id')->where('event_id', $root->id)],
            'requested_region_id' => 'required|integer|exists:regions,id',
            'reason' => 'required|string|max:2000',
        ]);

        $phase = FestEventPhase::findOrFail($data['phase_id']);
        abort_unless($phase->isRegional(), 422, 'This phase is not regional.');

        $allowed = FestPhaseRegion::where('phase_id', $phase->id)
            ->where('region_id', $data['requested_region_id'])
            ->where('enabled', true)
            ->exists();
        abort_unless($allowed, 422, 'That region is not enabled for this phase.');

        $current = $selections->resolve($root, $phase, $this->school->id);
        abort_if(! $current, 422, 'Select an initial region for this phase before requesting a change.');
        abort_if((int) $current->region_id === (int) $data['requested_region_id'], 422, 'That is already your selected region.');

        $duplicate = FestRegionChangeRequest::where('event_id', $root->id)
            ->where('phase_id', $phase->id)
            ->where('school_id', $this->school->id)
            ->where('status', 'pending')
            ->exists();
        abort_if($duplicate, 422, 'A region change request for this phase is already pending review.');

        FestRegionChangeRequest::create([
            'event_id' => $root->id,
            'phase_id' => $phase->id,
            'school_id' => $this->school->id,
            'current_region_id' => $current->region_id,
            'requested_region_id' => $data['requested_region_id'],
            'reason' => $data['reason'],
            'status' => 'pending',
            'requested_by_user_id' => $request->user()?->id,
        ]);

        $meta = SchoolFestProgram::meta($program);

        return redirect('/school-admin/'.$this->school->id.'/'.ProgramRouteMap::prefixFromSlug($meta['slug'])."/events/{$event->id}/region-change-requests")
            ->with('success', 'Region change request submitted for admin review.');
    }
}
