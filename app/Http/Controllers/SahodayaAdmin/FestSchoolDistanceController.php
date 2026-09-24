<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestSchoolDistance;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FestSchoolDistanceController extends SahodayaAdminController
{
    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $schoolIds = FestRegistration::where('event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->distinct()
            ->pluck('school_id');

        $schools = Tenant::whereIn('id', $schoolIds)->orderBy('name')->get(['id', 'name']);

        $distances = FestSchoolDistance::where('event_id', $event->id)
            ->whereIn('school_id', $schoolIds)
            ->pluck('distance_km', 'school_id');

        $rows = $schools->map(fn (Tenant $school) => [
            'id'          => $school->id,
            'name'        => $school->name,
            'distance_km' => isset($distances[$school->id]) ? (float) $distances[$school->id] : null,
        ])->values();

        return $this->inertia('Sahodaya/Events/SchoolDistances', $this->withEventActivity($event, FestPageActivity::REPORTING_BATCHES, [
            'event'   => $event,
            'schools' => $rows,
        ]));
    }

    public function save(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $validated = $request->validate([
            'distances'               => 'required|array',
            'distances.*.school_id'   => 'required|string',
            'distances.*.distance_km' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $event) {
            foreach ($validated['distances'] as $row) {
                FestSchoolDistance::updateOrCreate(
                    ['event_id' => $event->id, 'school_id' => $row['school_id']],
                    ['distance_km' => $row['distance_km'] !== null && $row['distance_km'] !== '' ? $row['distance_km'] : null],
                );
            }
        });

        $audit->festEvent($event, FestPageActivity::REPORTING_BATCHES, 'fest.school_distances.saved', 'Updated school distances', [
            'count' => count($validated['distances']),
        ]);

        return back()->with('success', 'School distances saved.');
    }
}
