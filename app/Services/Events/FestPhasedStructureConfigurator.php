<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestRegistrationBatch;
use App\Models\Region;
use Illuminate\Support\Collection;

/**
 * Applies an MCS-style phase/batch/region/item-assignment config (see
 * app/Support/data/mcs_kalotsav_phase_plan.php) to a root FestEvent, driving the same
 * services the Sahodaya Admin Phases screen already uses
 * (FestPhasedWorkflowService::syncAllowedRegions, FestEventPhaseService::assignItemsToPhase).
 *
 * Batches and phases are upserted by (event_id, code) via firstOrNew()+fill(), with the
 * actual write gated on $commit -- so a dry-run ($commit=false) builds the exact same
 * in-memory target state a subsequent --commit would persist, and reports create/update/
 * unchanged per row without touching the database. Re-running with $commit=true is
 * idempotent: matching rows are updated in place rather than duplicated.
 */
class FestPhasedStructureConfigurator
{
    public function __construct(
        private FestPhasedWorkflowService $workflow,
        private FestEventPhaseService $phaseService,
    ) {}

    /**
     * @return array{
     *   batches: Collection<int, array{code: string, action: string, model: FestRegistrationBatch}>,
     *   phases: Collection<int, array{code: string, action: string, model: FestEventPhase, region_codes: array<string>, item_count: int}>,
     *   unmapped_items: Collection<int, FestEventItem>,
     * }
     */
    public function configure(FestEvent $root, array $config, bool $commit): array
    {
        abort_if($root->parent_event_id, 422, 'Configure the root event, not an operational leaf.');

        $this->validateShape($config);

        $regionsByCode = Region::forTenant($root->tenant_id)->active()->visibleToEvent($root->id)->get()->keyBy('code');
        $itemsByCode = FestEventItem::where('event_id', $root->id)->get()->keyBy('item_code');

        $this->validateReferences($config, $regionsByCode, $itemsByCode);

        $unmappedItems = FestEventItem::where('event_id', $root->id)
            ->enabled()
            ->whereNotIn('item_code', array_keys($config['item_phase_map']))
            ->get(['id', 'item_code', 'title']);

        $batches = collect($config['batches'])
            ->map(fn (array $data) => $this->upsertBatch($root, $data, $commit));
        $batchIdByCode = $batches->pluck('model.id', 'code');

        $itemCodesByPhaseCode = collect($config['item_phase_map'])
            ->mapToGroups(fn (string $phaseCode, string $itemCode) => [$phaseCode => $itemCode]);

        $phases = collect($config['phases'])->map(function (array $data) use ($root, $batchIdByCode, $itemCodesByPhaseCode, $itemsByCode, $regionsByCode, $commit) {
            $result = $this->upsertPhase($root, $data, (int) $batchIdByCode[$data['batch_code']], $commit);

            if ($commit && ($data['region_codes'] ?? []) !== []) {
                $regionIds = collect($data['region_codes'])->map(fn (string $c) => $regionsByCode[$c]->id)->all();
                $this->workflow->syncAllowedRegions($result['model'], $regionIds);
            }

            $itemIds = $itemCodesByPhaseCode->get($data['code'], collect())
                ->map(fn (string $code) => $itemsByCode[$code]->id)
                ->all();

            if ($commit && $itemIds !== []) {
                $this->phaseService->assignItemsToPhase($root, $result['model']->id, $itemIds);
            }

            return $result + [
                'region_codes' => $data['region_codes'] ?? [],
                'item_count' => count($itemIds),
            ];
        });

        return [
            'batches' => $batches,
            'phases' => $phases,
            'unmapped_items' => $unmappedItems,
        ];
    }

    /** @return array{code: string, action: string, model: FestRegistrationBatch} */
    private function upsertBatch(FestEvent $root, array $data, bool $commit): array
    {
        $batch = FestRegistrationBatch::firstOrNew(['event_id' => $root->id, 'code' => $data['code']]);
        $isNew = ! $batch->exists;
        $batch->fill([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? ($batch->sort_order ?? 0),
            'school_base_fee' => $data['school_base_fee'] ?? 0,
            'status' => $batch->exists ? $batch->status : 'draft',
        ]);
        $action = $isNew ? 'create' : ($batch->isDirty() ? 'update' : 'unchanged');

        if ($commit) {
            $batch->save();
        }

        return ['code' => $data['code'], 'action' => $action, 'model' => $batch];
    }

    /** @return array{code: string, action: string, model: FestEventPhase} */
    private function upsertPhase(FestEvent $root, array $data, int $batchId, bool $commit): array
    {
        // No DB-unique index on (event_id, code) for phases (unlike batches) -- the
        // existence check has to happen here to keep re-runs idempotent.
        $phase = FestEventPhase::where('event_id', $root->id)->where('code', $data['code'])->first();
        $isNew = $phase === null;
        $phase ??= new FestEventPhase(['event_id' => $root->id, 'code' => $data['code']]);

        $phase->fill([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? ($phase->sort_order ?? 0),
            'registration_batch_id' => $batchId,
            'is_regional' => (bool) ($data['is_regional'] ?? false),
        ]);
        $action = $isNew ? 'create' : ($phase->isDirty() ? 'update' : 'unchanged');

        if ($commit) {
            if ($isNew) {
                $phase = $this->phaseService->createPhase($root, $phase->getAttributes());
            } else {
                $phase = $this->phaseService->updatePhase($phase, $phase->getDirty() + ['registration_batch_id' => $batchId, 'is_regional' => $phase->is_regional]);
            }
        }

        return ['code' => $data['code'], 'action' => $action, 'model' => $phase];
    }

    private function validateShape(array $config): void
    {
        foreach (['tenant_id', 'batches', 'phases', 'item_phase_map'] as $key) {
            abort_unless(array_key_exists($key, $config), 422, "Missing required config key '{$key}'.");
        }

        foreach ($config['batches'] as $batch) {
            foreach (['code', 'name'] as $key) {
                abort_if(empty($batch[$key]), 422, "Every batch requires a '{$key}'.");
            }
        }

        $batchCodes = collect($config['batches'])->pluck('code');
        abort_if($batchCodes->count() !== $batchCodes->unique()->count(), 422, 'Batch codes must be unique.');

        foreach ($config['phases'] as $phase) {
            foreach (['code', 'name', 'batch_code'] as $key) {
                abort_if(empty($phase[$key]), 422, "Every phase requires a '{$key}'.");
            }
            abort_unless($batchCodes->contains($phase['batch_code']), 422, "Phase '{$phase['code']}' references unknown batch '{$phase['batch_code']}'.");
        }

        $phaseCodes = collect($config['phases'])->pluck('code');
        abort_if($phaseCodes->count() !== $phaseCodes->unique()->count(), 422, 'Phase codes must be unique.');

        foreach ($config['item_phase_map'] as $itemCode => $phaseCode) {
            abort_unless($phaseCodes->contains($phaseCode), 422, "Item '{$itemCode}' is mapped to unknown phase '{$phaseCode}'.");
        }
    }

    private function validateReferences(array $config, Collection $regionsByCode, Collection $itemsByCode): void
    {
        foreach ($config['phases'] as $phase) {
            foreach ($phase['region_codes'] ?? [] as $regionCode) {
                abort_unless($regionsByCode->has($regionCode), 422, "Phase '{$phase['code']}' references unknown region code '{$regionCode}' for this Sahodaya.");
            }
        }

        foreach (array_keys($config['item_phase_map']) as $itemCode) {
            abort_unless($itemsByCode->has($itemCode), 422, "Item code '{$itemCode}' does not exist on this event.");
        }
    }
}
