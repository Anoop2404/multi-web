<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestRegistrationBatch;
use App\Models\Region;
use App\Services\Events\FestEventPhaseService;
use App\Services\Events\FestPartitionService;
use App\Support\FestPageActivity;

use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class FestEventPhaseController extends SahodayaAdminController
{
    public function index(Request $request, string $tenantId, FestEvent $event, FestEventPhaseService $service, FestPartitionService $partitions)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $phases = $service->getPhases($event)->load(['registrationBatch', 'allowedRegions.region']);
        $registrationBatches = FestRegistrationBatch::where('event_id', $event->id)->orderBy('sort_order')->get();
        $items = $event->items()->with('phase')->get()->map(fn ($item) => [
            'id' => $item->id,
            'title' => $item->title,
            'item_code' => $item->item_code,
            'category' => $item->category,
            'stage_type' => $item->stage_type,
            'gender' => $item->gender,
            'participant_type' => $item->participant_type,
            'phase_id' => $item->phase_id,
            'phase_name' => $item->phase?->name,
        ]);

        return $this->inertia('Sahodaya/Events/Phases', $this->withEventActivity($event, FestPageActivity::ITEMS, [
            'event' => $event,
            'phases' => $phases,
            'items' => $items,
            'registrationBatches' => $registrationBatches,
            'regions' => Region::forTenant($event->tenant_id)->active()->orderBy('sort_order')->get(),
            // Informational only on this page -- only batch creation actually flips
            // routing (FestRegistrationBatchController::store()), so phase/item setup
            // stays usable while old-system partitions exist without registrations; that
            // pivot point is already covered by FestPartitionService::
            // assertSafeToActivatePhasedWorkflow(). Never used to lock phases/items here.
            // Same threshold as FestEventController::levels()'s conductSystemLocked --
            // matches FestPartitionService::assertLegacyPartitioningAllowed() exactly
            // (phases/batches existing, not just workflow_mode already flipped).
            'conductSystemLocked' => ($event->usesPhasedRegionalBilling() || $phases->isNotEmpty() || $registrationBatches->isNotEmpty())
                ? 'phased'
                : ($partitions->hasLegacyPartitions($event) ? 'partitioned' : null),
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, FestEventPhaseService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:64',
            'sort_order' => 'nullable|integer',
            'is_default' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'registration_open' => 'nullable|date',
            'registration_close' => 'nullable|date|after_or_equal:registration_open',
            'status' => 'nullable|in:draft,published,registration_open,ongoing,completed,cancelled',
            'school_registration_fee_share' => 'nullable|numeric|min:0',
            'student_registration_fee' => 'nullable|numeric|min:0',
            'registration_batch_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('fest_registration_batches', 'id')->where('event_id', $event->id)],
            'is_regional' => 'nullable|boolean',
            'result_publish_mode' => 'nullable|in:all_regions,per_region',
        ]);

        $phase = $service->createPhase($event, $data);

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.phase.created', "Created phase {$phase->name}", [
            'phase_id' => $phase->id,
            'name' => $phase->name,
        ]);

        return back()->with('success', "Phase '{$phase->name}' created.");
    }

    public function update(Request $request, string $tenantId, FestEvent $event, FestEventPhase $phase, FestEventPhaseService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($phase->event_id !== $event->id, 403);

        // LIFE-05 fix (functional audit, 2026-08-11/12): these lifecycle
        // fields previously had no request/service write path at all — see
        // FestEventPhaseService::updatePhase(). 'status' is deliberately
        // NOT accepted here; it goes through quickStatus() below so it's
        // always guarded by StatusTransitionGuard.
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:64',
            'sort_order' => 'nullable|integer',
            'is_default' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'registration_open' => 'nullable|date',
            'registration_close' => 'nullable|date|after_or_equal:registration_open',
            'registration_locked' => 'nullable|boolean',
            'food_cutoff_at' => 'nullable|date',
            'scoring_locked' => 'nullable|boolean',
            'schedule_published' => 'nullable|boolean',
            'results_published' => 'nullable|boolean',
            'appeals_open' => 'nullable|boolean',
            'appeal_deadline_at' => 'nullable|date|after_or_equal:ends_at',
            // How much of the event's flat school registration fee this phase collects — see
            // FestEventPhaseService::updatePhase() and
            // docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §3 item 4.
            'status' => 'nullable|in:draft,published,registration_open,ongoing,completed,cancelled',
            'school_registration_fee_share' => 'nullable|numeric|min:0',
            'student_registration_fee' => 'nullable|numeric|min:0',
            'registration_batch_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('fest_registration_batches', 'id')->where('event_id', $event->id)],
            'is_regional' => 'nullable|boolean',
            'result_publish_mode' => 'nullable|in:all_regions,per_region',
            'payment_instructions' => 'nullable|string|max:5000',
            'payment_qr_code' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3072',
            'remove_payment_qr_code' => 'nullable|boolean',
        ]);

        if ($request->hasFile('payment_qr_code')) {
            $data['payment_qr_code'] = \App\Support\TenantStorage::storeUploadedFile($request->file('payment_qr_code'), 'payment_qr_codes');
        } elseif ($request->boolean('remove_payment_qr_code')) {
            $data['payment_qr_code'] = null;
        }
        unset($data['remove_payment_qr_code']);

        if (! empty($data['status']) && $data['status'] !== $phase->status) {
            $service->transitionStatus($phase, $data['status']);
        }

        $updated = $service->updatePhase($phase, $data);

        // Push the phase's lifecycle fields (dates, status, lock flags) onto its already-
        // materialized leaf events immediately — without this, a leaf's own stale snapshot
        // from the last sync silently blocks/allows registration regardless of what the
        // phase now says, since FestItemRegistrationGate::assertOpen() checks the leaf's own
        // fields before the phase-aware EventLifecycleGate is ever consulted. Previously
        // this only happened via a separate, easy-to-miss "Sync operational events" button.
        if ($event->usesPhasedRegionalBilling()) {
            app(\App\Services\Events\FestPhaseTopologyService::class)->sync($event->fresh());
        }

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.phase.updated', "Updated phase {$updated->name}", [
            'phase_id' => $updated->id,
        ]);

        return back()->with('success', "Phase '{$updated->name}' updated.");
    }

    /**
     * LIFE-05 fix (functional audit, 2026-08-11/12): dedicated status
     * transition endpoint for phases, mirroring
     * FestEventController::quickStatus() — previously no endpoint could ever
     * change a phase's status at all.
     */
    public function quickStatus(Request $request, string $tenantId, FestEvent $event, FestEventPhase $phase, FestEventPhaseService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($phase->event_id !== $event->id, 403);

        $data = $request->validate([
            'status' => 'required|in:draft,published,registration_open,ongoing,completed,cancelled',
        ]);

        $updated = $service->transitionStatus($phase, $data['status']);

        if ($event->usesPhasedRegionalBilling()) {
            app(\App\Services\Events\FestPhaseTopologyService::class)->sync($event->fresh());
        }

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.phase.status_changed', "Phase {$updated->name} status → {$data['status']}", [
            'phase_id' => $updated->id,
            'status' => $data['status'],
        ]);

        return back()->with('success', "Phase status updated to \"{$data['status']}\".");
    }

    public function destroy(Request $request, string $tenantId, FestEvent $event, FestEventPhase $phase, FestEventPhaseService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($phase->event_id !== $event->id, 403);

        $name = $phase->name;
        $service->deletePhase($phase, $request->boolean('force'));

        if ($event->usesPhasedRegionalBilling()) {
            app(\App\Services\Events\FestPhaseTopologyService::class)->sync($event->fresh());
        }

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.phase.deleted', "Deleted phase {$name}", [
            'phase_id' => $phase->id,
        ]);

        return back()->with('success', "Phase '{$name}' deleted.");
    }

    public function assignItems(Request $request, string $tenantId, FestEvent $event, FestEventPhaseService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            // Scoped to THIS event — previously any phase id anywhere (including another
            // event's) passed the bare exists() check; FestEventPhaseService::
            // assignItemsToPhase() re-checks this too (defense in depth), but the request
            // should fail validation before it even reaches the service. Phase 5 audit item 2.
            'phase_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('fest_event_phases', 'id')->where('event_id', $event->id)],
            'item_ids' => 'required|array',
            'item_ids.*' => ['integer', \Illuminate\Validation\Rule::exists('fest_event_items', 'id')->where('event_id', $event->id)],
        ]);

        $count = $service->assignItemsToPhase($event, $data['phase_id'] ?? null, $data['item_ids']);

        if ($event->usesPhasedRegionalBilling()) {
            app(\App\Services\Events\FestPhaseTopologyService::class)->sync($event->fresh());
        }

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.phase.items_assigned', "Assigned {$count} item(s) to phase", [
            'phase_id' => $data['phase_id'] ?? null,
            'count' => $count,
        ]);

        // Phase assignment has no effect on registration routing until a payment batch
        // exists (that's what flips workflow_mode -- see FestRegistrationBatchController::
        // store()); without this, an admin who sets up phases before batches gets a
        // silent "success" that doesn't actually do anything yet.
        if ($event->phases()->exists() && $event->registrationBatches()->doesntExist()) {
            return back()->with('warning', "Assigned {$count} item(s) to phase, but phase-based routing isn't active yet — create a payment batch first, or these items keep routing through the event's current topology.");
        }

        return back()->with('success', "Assigned {$count} item(s) to phase.");
    }
}
