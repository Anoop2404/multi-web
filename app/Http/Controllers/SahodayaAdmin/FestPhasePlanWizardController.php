<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestRegistrationBatch;
use App\Models\Region;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestPartitionService;
use App\Services\Events\FestPhasedStructureConfigurator;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestPhaseTopologyService;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Self-service wizard for the phased/regional structure setup that previously required
 * running `fest:configure-phased-structure` from the CLI. Drives
 * FestPhasedStructureConfigurator directly — the same validated, idempotent,
 * dry-run-first service the CLI command already uses — so there is no second, divergent
 * write path: batches, phases, region enablement, item assignment, and topology sync all
 * go through the exact same services either way. This controller only adds the ergonomics
 * (bulk form, dry-run preview, unmapped-items gate) the CLI already has but the old
 * one-phase-at-a-time admin UI didn't.
 */
class FestPhasePlanWizardController extends SahodayaAdminController
{
    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->parent_event_id, 422, 'Configure the root event, not an operational leaf.');

        return $this->inertia('Sahodaya/Events/PhasePlanWizard', $this->withEventActivity($event, FestPageActivity::ITEMS, [
            'event' => $event,
            'items' => $event->items()->where('is_enabled', true)->orderBy('display_order')->orderBy('title')
                ->get(['id', 'title', 'item_code']),
            'regions' => Region::forTenant($event->tenant_id)->active()->visibleToEvent($event->id)->orderBy('sort_order')->get(['id', 'name', 'code']),
            'existingBatches' => FestRegistrationBatch::where('event_id', $event->id)->orderBy('sort_order')
                ->get(['id', 'code', 'name', 'school_base_fee']),
            'existingPhases' => FestEventPhase::where('event_id', $event->id)->with('registrationBatch:id,code')
                ->orderBy('sort_order')->get()->map(fn (FestEventPhase $p) => [
                    'id' => $p->id, 'code' => $p->code, 'name' => $p->name,
                    'batch_code' => $p->registrationBatch?->code, 'is_regional' => $p->is_regional,
                ]),
        ]));
    }

    public function preview(Request $request, string $tenantId, FestEvent $event, FestPhasedStructureConfigurator $configurator)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->parent_event_id, 422, 'Configure the root event, not an operational leaf.');

        $result = $configurator->configure($event, $this->configFromRequest($request, $event), commit: false);

        return response()->json($this->serializePreview($result));
    }

    public function commit(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestPhasedStructureConfigurator $configurator,
        FestPartitionService $partitions,
        FestPhaseTopologyService $topology,
        PlatformAuditLogger $audit,
    ) {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->parent_event_id, 422, 'Configure the root event, not an operational leaf.');

        $config = $this->configFromRequest($request, $event);

        // Re-run the dry-run just before committing — the request body is the source of
        // truth for what to build, but the unmapped-items block must be re-verified
        // server-side rather than trusting a client that already showed an empty list.
        $preview = $configurator->configure($event, $config, commit: false);
        abort_if($preview['unmapped_items']->isNotEmpty(), 422, 'Every enabled item must be assigned to a phase before committing.');

        // Mirrors FestRegistrationBatchController::store() — creating the phased structure
        // is what actually opts the event into phased_regional_billing routing, so it needs
        // the same "no orphaned legacy registrations" guard that batch creation already has.
        // The CLI command this wizard replaces skips this check entirely (operator
        // convention only); the wizard does not repeat that gap.
        if ($event->workflow_mode !== FestPhasedWorkflowService::MODE) {
            $partitions->assertSafeToActivatePhasedWorkflow($event);
        }

        DB::transaction(function () use ($event, $config, $configurator, $topology) {
            $configurator->configure($event, $config, commit: true);

            if ($event->workflow_mode !== FestPhasedWorkflowService::MODE || ! $event->phase_mode_enabled) {
                $event->update([
                    'workflow_mode' => FestPhasedWorkflowService::MODE,
                    'phase_mode_enabled' => true,
                    'conduct_mode' => 'partitioned',
                ]);
            }

            $topology->sync($event->fresh());
        });

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.phase_plan.committed', 'Phase plan wizard committed', [
            'batch_count' => count($config['batches']),
            'phase_count' => count($config['phases']),
            'mapped_item_count' => count($config['item_phase_map']),
        ]);

        return back()->with('success', 'Phase plan committed — batches, phases, and item assignments are live.');
    }

    /** @return array{tenant_id: string, batches: array, phases: array, item_phase_map: array} */
    private function configFromRequest(Request $request, FestEvent $event): array
    {
        $data = $request->validate([
            'batches' => 'required|array|min:1',
            'batches.*.code' => 'required|string|max:64',
            'batches.*.name' => 'required|string|max:255',
            'batches.*.sort_order' => 'nullable|integer',
            'batches.*.school_base_fee' => 'nullable|numeric|min:0',
            'phases' => 'required|array|min:1',
            'phases.*.code' => 'required|string|max:64',
            'phases.*.name' => 'required|string|max:255',
            'phases.*.batch_code' => 'required|string|max:64',
            'phases.*.sort_order' => 'nullable|integer',
            'phases.*.is_regional' => 'nullable|boolean',
            'phases.*.region_codes' => 'nullable|array',
            'phases.*.region_codes.*' => 'string',
            'item_phase_map' => 'nullable|array',
            'item_phase_map.*' => 'string',
        ]);

        return [
            'tenant_id' => $event->tenant_id,
            'batches' => $data['batches'],
            'phases' => $data['phases'],
            'item_phase_map' => $data['item_phase_map'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function serializePreview(array $result): array
    {
        return [
            'batches' => $result['batches']->map(fn (array $b) => [
                'code' => $b['code'], 'action' => $b['action'], 'name' => $b['model']->name,
            ])->values(),
            'phases' => $result['phases']->map(fn (array $p) => [
                'code' => $p['code'], 'action' => $p['action'], 'name' => $p['model']->name,
                'is_regional' => $p['model']->is_regional, 'region_codes' => $p['region_codes'],
                'item_count' => $p['item_count'],
            ])->values(),
            'unmapped_items' => $result['unmapped_items']->map(fn ($i) => [
                'id' => $i->id, 'item_code' => $i->item_code, 'title' => $i->title,
            ])->values(),
        ];
    }
}
