<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestRegionChangeRequest;
use App\Models\FestSchoolPhaseRegionSelection;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestSchoolPhaseRegionService;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;

class FestPhaseRegionMatrixController extends SahodayaAdminController
{
    public function pickEvent(Request $request)
    {
        $events = FestEvent::forTenant($this->sahodaya->id)
            ->whereNull('parent_event_id')
            ->where('workflow_mode', FestPhasedWorkflowService::MODE)
            ->whereHas('phases', fn ($q) => $q->where(function ($w) {
                $w->where('is_regional', true)->orWhereNotNull('region_partition_group');
            }))
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_start', 'status']);

        return $this->inertia('Sahodaya/Events/PhaseRegionMatrixPicker', [
            'events' => $events,
        ]);
    }

    public function index(string $tenantId, FestEvent $event)
    {
        $this->assertEvent($event);

        $phases = FestEventPhase::where('event_id', $event->id)
            ->where(function ($q) {
                $q->where('is_regional', true)->orWhereNotNull('region_partition_group');
            })
            ->orderBy('sort_order')
            ->with(['allowedRegions' => fn ($q) => $q->where('enabled', true)->with('region:id,name')])
            ->get(['id', 'event_id', 'name', 'code', 'sort_order']);

        $phaseIds = $phases->pluck('id');

        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name', 'school_prefix']);

        $selections = FestSchoolPhaseRegionSelection::where('event_id', $event->id)
            ->whereIn('phase_id', $phaseIds)
            ->with('region:id,name')
            ->get()
            ->keyBy(fn (FestSchoolPhaseRegionSelection $s) => $s->school_id.':'.$s->phase_id);

        $pendingRequests = FestRegionChangeRequest::where('event_id', $event->id)
            ->whereIn('phase_id', $phaseIds)
            ->where('status', 'pending')
            ->with(['school:id,name', 'phase:id,name', 'requestedRegion:id,name', 'currentRegion:id,name'])
            ->latest()
            ->get();

        return $this->inertia('Sahodaya/Events/PhaseRegionMatrix', [
            'event' => $event->only('id', 'title'),
            'phases' => $phases->map(fn (FestEventPhase $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'regions' => $p->allowedRegions->map(fn ($ar) => [
                    'region_id' => $ar->region_id,
                    'name' => $ar->region?->name,
                    'capacity' => $ar->capacity,
                ])->values(),
            ])->values(),
            'schools' => $schools->map(fn (Tenant $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'school_prefix' => $s->school_prefix,
            ])->values(),
            'selections' => $selections->map(fn (FestSchoolPhaseRegionSelection $sel) => [
                'region_id' => $sel->region_id,
                'region_name' => $sel->region?->name,
                'locked' => $sel->isLocked(),
                'changed_at' => $sel->changed_at,
                'change_reason' => $sel->change_reason,
            ]),
            'pendingRequests' => $pendingRequests->map(fn (FestRegionChangeRequest $r) => [
                'id' => $r->id,
                'school_id' => $r->school_id,
                'school_name' => $r->school?->name,
                'phase_id' => $r->phase_id,
                'phase_name' => $r->phase?->name,
                'current_region_name' => $r->currentRegion?->name,
                'requested_region_name' => $r->requestedRegion?->name,
                'requested_region_id' => $r->requested_region_id,
                'reason' => $r->reason,
                'created_at' => $r->created_at,
            ])->values(),
        ]);
    }

    public function approve(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestRegionChangeRequest $regionChangeRequest,
        FestSchoolPhaseRegionService $selections,
        PlatformAuditLogger $audit,
    ) {
        $this->assertEvent($event);
        abort_unless((int) $regionChangeRequest->event_id === $event->id, 403);
        abort_unless($regionChangeRequest->status === 'pending', 422, 'Only pending requests can be reviewed.');

        $data = $request->validate([
            'resolution_note' => 'nullable|string|max:2000',
            'acknowledge_paid_invoice' => 'nullable|boolean',
        ]);

        $phase = FestEventPhase::findOrFail($regionChangeRequest->phase_id);

        $selection = $selections->select(
            $event,
            $phase,
            $regionChangeRequest->school_id,
            (int) $regionChangeRequest->requested_region_id,
            $request->user()?->id,
            true,
            $data['resolution_note'] ?? $regionChangeRequest->reason,
            (bool) ($data['acknowledge_paid_invoice'] ?? false),
        );

        $regionChangeRequest->update([
            'status' => 'approved',
            'resolution_note' => $data['resolution_note'] ?? null,
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        $audit->festEvent($event, FestPageActivity::REGISTRATIONS, 'fest.phase_region.request_approved', 'Approved school region change request', [
            'phase_id' => $phase->id,
            'school_id' => $regionChangeRequest->school_id,
            'region_id' => $selection->region_id,
            'request_id' => $regionChangeRequest->id,
        ]);

        return back()->with('success', 'Region change approved and applied.');
    }

    public function reject(Request $request, string $tenantId, FestEvent $event, FestRegionChangeRequest $regionChangeRequest)
    {
        $this->assertEvent($event);
        abort_unless((int) $regionChangeRequest->event_id === $event->id, 403);
        abort_unless($regionChangeRequest->status === 'pending', 422, 'Only pending requests can be reviewed.');

        $data = $request->validate([
            'resolution_note' => 'nullable|string|max:2000',
        ]);

        $regionChangeRequest->update([
            'status' => 'rejected',
            'resolution_note' => $data['resolution_note'] ?? null,
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Region change request rejected.');
    }

    private function assertEvent(FestEvent $event): void
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->parent_event_id, 422, 'Configure the root event.');
    }
}
