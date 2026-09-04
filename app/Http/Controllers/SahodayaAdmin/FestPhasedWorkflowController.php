<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\Region;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestPhaseTopologyService;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestSchoolFeeSlabSelectionService;
use App\Services\Events\FestSchoolPhaseRegionService;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FestPhasedWorkflowController extends SahodayaAdminController
{
    public function syncTopology(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestPhaseTopologyService $topology,
        PlatformAuditLogger $audit,
    ) {
        $this->assertEvent($event);
        $leaves = $topology->sync($event);

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.phased_workflow.topology_synced', 'Synchronized phased operational events', [
            'operational_leaf_ids' => $leaves->pluck('id')->all(),
        ]);

        return back()->with('success', "Synchronized {$leaves->count()} operational event(s).");
    }

    public function syncPhaseRegions(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestEventPhase $phase,
        FestPhasedWorkflowService $workflow,
        FestPhaseTopologyService $topology,
    ) {
        $this->assertEvent($event);
        abort_if($phase->event_id !== $event->id, 403);
        $data = $request->validate([
            'region_ids' => 'required|array|min:1',
            'region_ids.*' => [
                'integer',
                Rule::exists('regions', 'id')->where(fn ($q) => $q->where('tenant_id', $this->sahodaya->id)
                    ->where(fn ($q2) => $q2->whereNull('fest_event_id')->orWhere('fest_event_id', $event->id))),
            ],
            'venues' => 'nullable|array',
            'venues.*' => 'nullable|string|max:255',
        ]);

        $phase->update(['is_regional' => true]);
        $workflow->syncAllowedRegions($phase, $data['region_ids'], $data['venues'] ?? []);
        $topology->sync($event->fresh());

        return back()->with('success', "Regions updated for {$phase->name}.");
    }

    public function overrideSchoolRegion(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestEventPhase $phase,
        string $schoolId,
        FestSchoolPhaseRegionService $selections,
        PlatformAuditLogger $audit,
    ) {
        $this->assertEvent($event);
        abort_if($phase->event_id !== $event->id, 403);
        $data = $request->validate([
            'region_id' => ['required', 'integer', Rule::exists('regions', 'id')->where(fn ($q) => $q->where('tenant_id', $this->sahodaya->id)
                ->where(fn ($q2) => $q2->whereNull('fest_event_id')->orWhere('fest_event_id', $event->id)))],
            'reason' => 'required|string|max:1000',
            'acknowledge_paid_invoice' => 'nullable|boolean',
        ]);

        $selection = $selections->select(
            $event,
            $phase,
            $schoolId,
            (int) $data['region_id'],
            $request->user()?->id,
            true,
            $data['reason'],
            (bool) ($data['acknowledge_paid_invoice'] ?? false),
        );
        $audit->festEvent($event, FestPageActivity::REGISTRATIONS, 'fest.phase_region.overridden', 'Overrode school phase region', [
            'phase_id' => $phase->id,
            'school_id' => $schoolId,
            'region_id' => $selection->region_id,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'School region updated and eligible registrations migrated.');
    }

    public function overrideSchoolFeeSlab(
        Request $request,
        string $tenantId,
        FestEvent $event,
        string $schoolId,
        FestSchoolFeeSlabSelectionService $selections,
        PlatformAuditLogger $audit,
    ) {
        $this->assertEvent($event);
        $data = $request->validate([
            'min_count' => 'required|integer|min:0',
            'max_count' => 'nullable|integer|min:0',
            'reason' => 'required|string|max:1000',
        ]);

        $selection = $selections->select(
            $event,
            $schoolId,
            (int) $data['min_count'],
            isset($data['max_count']) ? (int) $data['max_count'] : null,
            $request->user()?->id,
            true,
            $data['reason'],
        );
        $audit->festEvent($event, FestPageActivity::REGISTRATIONS, 'fest.fee_slab.overridden', 'Overrode school fee slab', [
            'school_id' => $schoolId,
            'min_count' => $selection->min_count,
            'max_count' => $selection->max_count,
            'amount' => $selection->amount,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'School fee band updated.');
    }

    private function assertEvent(FestEvent $event): void
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->parent_event_id, 422, 'Configure the root event.');
    }
}
