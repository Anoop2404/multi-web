<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestRegistrationBatch;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestPartitionService;
use App\Services\Events\FestPhasedWorkflowService;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD for FestRegistrationBatch (a "payment level" — e.g. MCS Kalotsav's Level 1 /
 * Level 2 — that one or more FestEventPhase rows bill through). Previously the only way
 * any batch ever got created was the hardcoded FestPhasedWorkflowService::configureMcs2026()
 * one-click bootstrap, which always produced exactly two batches named LEVEL_1/LEVEL_2 —
 * fine for that one specific Sahodaya's specific event, but not something any other
 * Sahodaya could use for their own differently-shaped phased/regional event. This
 * controller makes batch definition itself a normal admin action, mirroring the existing
 * generic FestEventPhaseController CRUD for phases.
 */
class FestRegistrationBatchController extends SahodayaAdminController
{
    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit, FestPartitionService $partitions)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->parent_event_id, 422, 'Payment batches belong on the root event.');

        if ($event->workflow_mode !== FestPhasedWorkflowService::MODE) {
            $partitions->assertSafeToActivatePhasedWorkflow($event);
        }

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:64', 'regex:/^[A-Z0-9_]+$/',
                Rule::unique('fest_registration_batches', 'code')->where('event_id', $event->id),
            ],
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
            'school_base_fee' => 'nullable|numeric|min:0',
            'student_registration_fee' => 'nullable|numeric|min:0',
            'invoice_prefix' => 'nullable|string|max:32',
            'registration_open' => 'nullable|date',
            'registration_close' => 'nullable|date|after_or_equal:registration_open',
            'payment_due_at' => 'nullable|date',
            'status' => 'nullable|in:draft,published,registration_open,ongoing,completed,cancelled',
        ]);

        $maxOrder = FestRegistrationBatch::where('event_id', $event->id)->max('sort_order') ?? 0;

        $batch = FestRegistrationBatch::create([
            'event_id' => $event->id,
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
            'school_base_fee' => $data['school_base_fee'] ?? 0,
            'student_registration_fee' => $data['student_registration_fee'] ?? null,
            'invoice_prefix' => $data['invoice_prefix'] ?? null,
            'registration_open' => $data['registration_open'] ?? null,
            'registration_close' => $data['registration_close'] ?? null,
            'payment_due_at' => $data['payment_due_at'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ]);

        // Creating a payment batch is what actually opts an event into the full phased/
        // regional engine — topology leaf sync, batch invoicing, and phase-region
        // registration routing (FestPhaseTopologyService, FestRegistrationBatchFeeService,
        // FestRegistrationRouterService, FestSchoolPhaseRegionService) are all gated on
        // workflow_mode === FestPhasedWorkflowService::MODE, and per-item lifecycle gating
        // (EventLifecycleGate) on phase_mode_enabled. Previously both were only ever set
        // inside the hardcoded MCS-2026 bootstrap; setting them here generically means any
        // Sahodaya creating their first batch enables the same engine for their own event.
        if ($event->workflow_mode !== FestPhasedWorkflowService::MODE || ! $event->phase_mode_enabled) {
            $event->update([
                'workflow_mode' => FestPhasedWorkflowService::MODE,
                'phase_mode_enabled' => true,
                // Matches what fest:configure-phased-structure already sets for the CLI
                // path. Behaviorally a no-op for FestPartitionService::conductMode()'s own
                // derivation (it already infers 'partitioned' from phase-leaf children
                // carrying partition_key/cluster_key) -- but without it here,
                // FestAuditEventTopology::auditStandardWithChildren() reads the raw column
                // directly and flags a false standard_event_with_children anomaly on every
                // event configured purely through this web UI.
                'conduct_mode' => 'partitioned',
            ]);
        }

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.registration_batch.created', "Created payment batch {$batch->name}", [
            'batch_id' => $batch->id,
            'code' => $batch->code,
        ]);

        return back()->with('success', "Payment batch '{$batch->name}' created.");
    }

    public function update(Request $request, string $tenantId, FestEvent $event, FestRegistrationBatch $batch, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($batch->event_id !== $event->id, 403);

        $data = $request->validate([
            'code' => [
                'sometimes', 'required', 'string', 'max:64', 'regex:/^[A-Z0-9_]+$/',
                Rule::unique('fest_registration_batches', 'code')->where('event_id', $event->id)->ignore($batch->id),
            ],
            'name' => 'sometimes|required|string|max:255',
            'sort_order' => 'nullable|integer',
            'school_base_fee' => 'nullable|numeric|min:0',
            'student_registration_fee' => 'nullable|numeric|min:0',
            'invoice_prefix' => 'nullable|string|max:32',
            'registration_open' => 'nullable|date',
            'registration_close' => 'nullable|date|after_or_equal:registration_open',
            'payment_due_at' => 'nullable|date',
            'status' => 'nullable|in:draft,published,registration_open,ongoing,completed,cancelled',
            'registration_locked' => 'nullable|boolean',
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $batch->update($data);

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.registration_batch.updated', "Updated payment batch {$batch->name}", [
            'batch_id' => $batch->id,
        ]);

        return back()->with('success', "Payment batch '{$batch->name}' updated.");
    }

    public function destroy(Request $request, string $tenantId, FestEvent $event, FestRegistrationBatch $batch, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($batch->event_id !== $event->id, 403);

        abort_if(
            $batch->phases()->exists() || $batch->operationalEvents()->exists() || $batch->fees()->exists(),
            422,
            'This payment batch has phases, operational events, or fees attached to it. Reassign or remove those first.'
        );

        $name = $batch->name;
        $batch->delete();

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.registration_batch.deleted', "Deleted payment batch {$name}");

        return back()->with('success', "Payment batch '{$name}' deleted.");
    }
}
