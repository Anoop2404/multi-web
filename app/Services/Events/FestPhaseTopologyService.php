<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FestPhaseTopologyService
{
    public function __construct(
        private FestItemSyncService $items,
    ) {}

    /** @return Collection<int, FestEvent> */
    public function sync(FestEvent $root): Collection
    {
        abort_unless($root->workflow_mode === FestPhasedWorkflowService::MODE, 422, 'This event does not use phased regional billing.');
        abort_if($root->parent_event_id, 422, 'Synchronize topology from the root event.');

        return DB::transaction(function () use ($root) {
            $leaves = collect();
            $root->loadMissing('phases.allowedRegions.region', 'items.phase');

            foreach ($root->phases as $phase) {
                if ($phase->isRegional()) {
                    foreach ($phase->allowedRegions->where('enabled', true) as $allowed) {
                        $leaves->push($this->syncLeaf($root, $phase, $allowed->region));
                    }
                } else {
                    $leaves->push($this->syncLeaf($root, $phase, null));
                }
            }

            $validLeafIds = $leaves->pluck('id')->filter()->all();

            // Prune obsolete child events under this root whose phase/region was deleted
            FestEvent::where('parent_event_id', $root->id)
                ->whereNotIn('id', $validLeafIds)
                ->whereDoesntHave('registrations')
                ->delete();

            return $leaves->values();
        });
    }

    public function syncLeaf(FestEvent $root, FestEventPhase $phase, ?Region $region): FestEvent
    {
        abort_if($phase->event_id !== $root->id, 422, 'Phase does not belong to the root event.');
        abort_if($phase->isRegional() && ! $region, 422, 'A regional phase requires a region.');

        $key = Str::slug($phase->code ?: $phase->name);
        if ($region) {
            $key .= '--'.Str::slug($region->code ?: $region->name);
        }

        $leaf = FestEvent::firstOrNew([
            'parent_event_id' => $root->id,
            'source_phase_id' => $phase->id,
            'region_id' => $region?->id,
        ]);

        $leaf->fill([
            'tenant_id' => $root->tenant_id,
            'academic_year_id' => $root->academic_year_id,
            'title' => $phase->name.($region ? ' — '.$region->name : ''),
            'event_type' => $root->event_type,
            'conductor_level' => $root->conductor_level,
            'conduct_levels' => $root->conduct_levels,
            'level_round' => $root->level_round,
            'state_program_id' => $root->state_program_id,
            'is_cascaded' => true,
            'root_event_id' => $root->id,
            'registration_batch_id' => $phase->registration_batch_id,
            'workflow_leaf_key' => $key,
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'phase_mode_enabled' => true,
            'conduct_mode' => 'standard',
            'partition_role' => $region ? 'region' : 'phase',
            'partition_key' => $key,
            'cluster_key' => $key,
            'cluster_label' => $phase->name.($region ? ' — '.$region->name : ''),
            'registration_open' => $phase->registration_open,
            'registration_close' => $phase->registration_close,
            'event_start' => ($region ? $phase->allowedRegions->firstWhere('region_id', $region->id)?->conduct_start_at : null) ?? $phase->starts_at ?? $root->event_start,
            'event_end' => ($region ? $phase->allowedRegions->firstWhere('region_id', $region->id)?->conduct_end_at : null) ?? $phase->ends_at ?? $root->event_end,
            'venue' => $region
                ? $phase->allowedRegions->firstWhere('region_id', $region->id)?->venue
                : $root->venue,
            'fee_type' => $root->fee_type,
            'fee_amount' => $root->fee_amount,
            'fee_settings' => $root->fee_settings,
            'scoring_preset' => $root->scoring_preset,
            'status' => $phase->status,
            'registration_locked' => $phase->registration_locked,
            'schedule_published' => $phase->schedule_published,
            'results_published' => $phase->results_published,
            'scoring_locked' => $phase->scoring_locked,
            'appeals_open' => $phase->appeals_open,
            'nav_hidden' => false,
        ]);
        $leaf->save();

        $this->syncChildPhase($leaf, $phase);
        $this->items->copyItemsToPartition($root, $leaf, $region ? 'region' : 'phase', $phase);
        if ($root->foodMenuItems()->exists()) {
            app(FestFoodMenuSyncService::class)->copyMenuToPartition($root, $leaf);
        }

        return $leaf->fresh();
    }

    private function syncChildPhase(FestEvent $leaf, FestEventPhase $source): FestEventPhase
    {
        return FestEventPhase::updateOrCreate(
            ['event_id' => $leaf->id, 'source_phase_id' => $source->id],
            [
                'registration_batch_id' => $source->registration_batch_id,
                'name' => $source->name,
                'code' => $source->code,
                'is_regional' => $source->is_regional,
                'region_partition_group' => $source->region_partition_group,
                'result_publish_mode' => $source->result_publish_mode,
                'sort_order' => $source->sort_order,
                'is_default' => true,
                'starts_at' => $source->starts_at,
                'ends_at' => $source->ends_at,
                'registration_open' => $source->registration_open,
                'registration_close' => $source->registration_close,
                'registration_locked' => $source->registration_locked,
                'food_cutoff_at' => $source->food_cutoff_at,
                'status' => $source->status,
                'scoring_locked' => $source->scoring_locked,
                'schedule_published' => $source->schedule_published,
                'results_published' => $source->results_published,
                'appeals_open' => $source->appeals_open,
                'appeal_deadline_at' => $source->appeal_deadline_at,
            ]
        );
    }
}
