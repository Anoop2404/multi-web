<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestPhaseRegion;
use App\Models\FestSchoolPhaseRegionSelection;
use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Converts an event's existing region-partition round (real schools already registered
 * against real items — see docs, "Branch B") into the phased-regional-billing structure,
 * without losing any of that data. Two operations, per docs/MCS_REGION_ROUND_MIGRATION_PLAN:
 *
 *  - Adopt in place: a legacy region child (FestPartitionService::legacyPartitions()) that
 *    should keep being regional under the SAME region becomes that phase's operational leaf
 *    for that region — its FestEvent row is backfilled with the new-system fields
 *    (mirroring FestPhaseTopologyService::syncLeaf()'s own fill list) rather than replaced,
 *    so every registration/participant/mark/etc. already pointing at it needs no migration
 *    at all. Its own live lifecycle (registration window, publication flags, venue) is
 *    copied onto the target FestEventPhase BEFORE the adopt step, so syncLeaf()'s normal
 *    phase-to-leaf fill reproduces what's already live instead of silently reverting it.
 *
 *  - Move/merge: for items on an adopted child whose real category is a DIFFERENT phase,
 *    the FestEventItem and every row hanging off its registrations move to that phase's own
 *    (freshly-synced) leaf, matched onto the existing canonical item there by item_code —
 *    because a common (non-regional) phase's item already exists as two independent rows
 *    today (one per region, each copied down when the region children were first created),
 *    "move" a common-phase item is really "merge both regions' copies into the canonical
 *    one," including renumbering chest numbers where both sides already used low numbers.
 */
class FestRegionRoundMigrationService
{
    /**
     * Tables scoped by (event_id, item_id) whose rows fully relocate with the item —
     * confirmed against every migration under database/migrations/tenant that adds an
     * event_id+item_id pair. fest_qualifications.next_level_event_id and
     * fest_qualification_lot_draws.from_event_id are deliberately NOT chased here — those
     * point at a *different* (later) round's event id, not this item's own row, and no
     * later round exists yet for an event still at "registration open."
     */
    private const ITEM_SCOPED_TABLES = [
        'fest_registrations',
        'fest_marks',
        'fest_attendance',
        'fest_results',
        'fest_qualifications',
        'fest_schedules',
        'fest_judge_assignments',
        'fest_judge_scores',
        'fest_athletic_records',
        'fest_qualification_lot_draws',
        'fest_mark_criteria',
        'fest_mark_sheet_uploads',
        'fest_record_breaks',
    ];

    public function __construct(
        private FestPhasedStructureConfigurator $configurator,
        private FestPhaseTopologyService $topology,
        private FestPartitionService $partitions,
    ) {}

    /**
     * @param  array{tenant_id: string, batches: array, phases: array, item_phase_map: array<string,string>, legacy_adoption: array<int,string>}  $config
     * @return array{
     *   batches: Collection, phases: Collection, unmapped_items: Collection,
     *   adoption: list<array{child_event_id: int, child_title: string, region: string, target_phase: string, staying_items: int, leaving_items: list<array{item_code: string, title: string, target_phase: string, registrations: int, participants: int}>}>,
     *   leaves: ?Collection<int, FestEvent>,
     * }
     */
    public function migrate(FestEvent $root, array $config, bool $commit): array
    {
        abort_if($root->parent_event_id, 422, 'Migrate from the root event, not an operational leaf.');
        abort_unless(array_key_exists('legacy_adoption', $config), 422, "Missing required config key 'legacy_adoption'.");

        $legacyChildren = $this->resolveLegacyChildren($root, $config['legacy_adoption']);
        $preview = $this->configurator->configure($root, $config, commit: false);

        if (! $commit) {
            // Dry run: preview's phase models are unsaved/in-memory (commit: false never
            // persists them), which is fine here — buildAdoptionPlan() only reads their
            // is_regional/code for the report, never their id.
            return [
                'batches' => $preview['batches'],
                'phases' => $preview['phases'],
                'unmapped_items' => $preview['unmapped_items'],
                'adoption' => $this->buildAdoptionPlan($root, $legacyChildren, $config, $preview['phases']),
                'leaves' => null,
            ];
        }

        abort_if($preview['unmapped_items']->isNotEmpty(), 422, 'Refusing to commit: unmapped items must be assigned to a phase first.');

        return DB::transaction(function () use ($root, $config, $legacyChildren) {
            $committed = $this->configurator->configure($root, $config, commit: true);
            $root->update([
                'workflow_mode' => FestPhasedWorkflowService::MODE,
                'phase_mode_enabled' => true,
                'conduct_mode' => 'partitioned',
            ]);

            // Built AFTER the real configure() so targetPhase carries a real, persisted id
            // — the dry-run preview's phase models above are not safe to reuse here.
            $adoptionPlan = $this->buildAdoptionPlan($root, $legacyChildren, $config, $committed['phases']);

            // Snapshot each adopted child's LIVE lifecycle before syncLeaf() overwrites it
            // from the (freshly-configured, effectively-default) phase — two children can
            // share one phase (that's the normal shape of a two-region phase), so the
            // lifecycle can't be round-tripped through the phase itself without one child's
            // values clobbering the other's; restore each child's own snapshot directly
            // after sync() instead.
            $lifecycleSnapshots = [];
            foreach ($adoptionPlan as $i => $plan) {
                $lifecycleSnapshots[$i] = $this->snapshotLifecycle($plan['child']);
                $plan['child']->update(['source_phase_id' => $plan['targetPhase']->id]);
            }

            // firstOrNew() inside syncLeaf() now matches (parent_event_id, source_phase_id,
            // region_id) against the just-tagged legacy children, so it fills/updates them
            // in place instead of creating empty duplicates; every other phase/region gets
            // its normal freshly-created leaf.
            $leaves = $this->topology->sync($root->fresh());
            $leavesByKey = $leaves->keyBy(fn (FestEvent $l) => $this->leafKey($l->source_phase_id, $l->region_id));

            foreach ($adoptionPlan as $i => $plan) {
                $plan['child']->fresh()->update($lifecycleSnapshots[$i]);
                $this->backfillStayingItemPhaseIds($plan);
                $this->relocateLeavingItems($plan, $leavesByKey);
            }

            $this->copyRankPointsToNewLeaves($root, $leaves);
            $this->backfillPhaseRegionSelections($root, $adoptionPlan);

            return [
                'batches' => $committed['batches'],
                'phases' => $committed['phases'],
                'unmapped_items' => $committed['unmapped_items'],
                'adoption' => $adoptionPlan,
                'leaves' => $leaves,
            ];
        });
    }

    /** @return Collection<int, FestEvent> keyed by child event id */
    private function resolveLegacyChildren(FestEvent $root, array $legacyAdoption): Collection
    {
        $legacyIds = $this->partitions->legacyPartitions($root)->pluck('id');

        $children = collect();
        foreach ($legacyAdoption as $childId => $phaseCode) {
            abort_unless($legacyIds->contains((int) $childId), 422, "Event #{$childId} is not a legacy region partition of event #{$root->id}.");
            $child = FestEvent::findOrFail($childId);
            abort_unless($child->region_id, 422, "Event #{$childId} has no region_id — cannot adopt it into a regional phase.");
            $children->put((int) $childId, $child);
        }

        return $children;
    }

    /**
     * @param  Collection<int, array{code: string, action: string, model: FestEventPhase}>  $phases  From FestPhasedStructureConfigurator::configure() — the dry-run preview's models when $commit is false, or the just-persisted models when true. Never re-queried directly, since a dry-run's phases don't exist in the database yet.
     * @return list<array{child: FestEvent, targetPhase: FestEventPhase, region: Region, stayingItems: Collection<int, FestEventItem>, leavingItems: Collection<int, array{item: FestEventItem, targetPhaseCode: string}>}>
     */
    private function buildAdoptionPlan(FestEvent $root, Collection $legacyChildren, array $config, Collection $phases): array
    {
        $phasesByCode = collect($config['phases'])->keyBy('code');
        $phaseModelsByCode = $phases->keyBy('code');
        $itemPhaseMap = $config['item_phase_map'];
        $plan = [];

        foreach ($config['legacy_adoption'] as $childId => $phaseCode) {
            $child = $legacyChildren->get((int) $childId);
            $phaseConfig = $phasesByCode->get($phaseCode);
            abort_unless($phaseConfig, 422, "legacy_adoption references unknown phase code '{$phaseCode}'.");
            abort_unless($phaseConfig['is_regional'] ?? false, 422, "Phase '{$phaseCode}' is not regional — a legacy region child can only be adopted by a regional phase.");

            $region = Region::forTenant($root->tenant_id)->findOrFail($child->region_id);
            abort_unless(
                in_array($region->code, $phaseConfig['region_codes'] ?? [], true),
                422,
                "Phase '{$phaseCode}' does not list region '{$region->code}' — cannot adopt event #{$childId} into it."
            );

            $targetPhase = $phaseModelsByCode->get($phaseCode)['model'] ?? null;
            abort_unless($targetPhase, 422, "Phase '{$phaseCode}' was not found in the configured phase set.");

            $childItems = FestEventItem::where('event_id', $child->id)->get();
            $staying = collect();
            $leaving = collect();

            foreach ($childItems as $item) {
                $target = $itemPhaseMap[$item->item_code] ?? null;
                if ($target === null || $target === $phaseCode) {
                    $staying->push($item);
                } else {
                    $leaving->push(['item' => $item, 'targetPhaseCode' => $target]);
                }
            }

            $plan[] = [
                'child' => $child,
                'region' => $region,
                'targetPhase' => $targetPhase,
                'stayingItems' => $staying,
                'leavingItems' => $leaving,
            ];
        }

        return $plan;
    }

    /** @return array<string, mixed> */
    private function snapshotLifecycle(FestEvent $child): array
    {
        return [
            'status' => $child->status,
            'registration_open' => $child->registration_open,
            'registration_close' => $child->registration_close,
            'registration_locked' => $child->registration_locked,
            'schedule_published' => $child->schedule_published,
            'results_published' => $child->results_published,
            'scoring_locked' => $child->scoring_locked,
            'appeals_open' => $child->appeals_open,
            'appeal_deadline_at' => $child->appeal_deadline_at,
            'food_cutoff_at' => $child->food_cutoff_at,
        ];
    }

    private function backfillStayingItemPhaseIds(array $plan): void
    {
        $childPhase = FestEventPhase::where('event_id', $plan['child']->id)
            ->where('source_phase_id', $plan['targetPhase']->id)
            ->first();

        if (! $childPhase) {
            return;
        }

        FestEventItem::whereIn('id', $plan['stayingItems']->pluck('id'))
            ->update(['phase_id' => $childPhase->id]);
    }

    private function relocateLeavingItems(array $plan, Collection $leavesByKey): void
    {
        foreach ($plan['leavingItems'] as $leaving) {
            $item = $leaving['item'];
            $targetPhaseCode = $leaving['targetPhaseCode'];
            $targetPhase = FestEventPhase::where('event_id', $plan['child']->rootEvent()->id)->where('code', $targetPhaseCode)->firstOrFail();

            // Regional target (e.g. Sargadhara): the same region the school already
            // implicitly chose, for continuity. Common target (Digi Fest/District):
            // no region — a single shared leaf.
            $targetRegionId = $targetPhase->is_regional ? $plan['region']->id : null;
            $targetLeaf = $leavesByKey->get($this->leafKey($targetPhase->id, $targetRegionId));
            abort_unless($targetLeaf, 422, "No synced leaf found for phase '{$targetPhaseCode}'".($targetRegionId ? " region #{$targetRegionId}" : '').' — topology sync must run before relocation.');

            $canonicalItem = FestEventItem::where('event_id', $targetLeaf->id)->where('item_code', $item->item_code)->first();
            abort_unless($canonicalItem, 422, "No canonical item '{$item->item_code}' found on leaf #{$targetLeaf->id} — expected syncLeaf() to have copied it down already.");

            $this->mergeItem($item, $canonicalItem, $plan['child']->id, $targetLeaf->id);
        }
    }

    /** Move every row for $sourceItem onto $targetItem (possibly already populated), renumbering chest numbers on collision, then delete the now-empty source item. */
    private function mergeItem(FestEventItem $sourceItem, FestEventItem $targetItem, int $sourceEventId, int $targetEventId): void
    {
        foreach (self::ITEM_SCOPED_TABLES as $table) {
            DB::table($table)
                ->where('event_id', $sourceEventId)
                ->where('item_id', $sourceItem->id)
                ->update(['event_id' => $targetEventId, 'item_id' => $targetItem->id]);
        }

        $registrationIds = DB::table('fest_registrations')->where('event_id', $targetEventId)->where('item_id', $targetItem->id)
            ->pluck('id');

        // Registrations just re-pointed above are the ones that came from $sourceItem —
        // their participants/groups still carry the OLD event_id (denormalized columns,
        // not FKs, so the UPDATE above never touched them) and need it set explicitly,
        // then chest numbers renumbered wherever the target scope already has any.
        $this->relocateParticipants($registrationIds, $sourceEventId, $targetEventId, $targetItem->id);
        $this->relocateGroups($registrationIds, $sourceEventId, $targetEventId);

        FestEventItem::where('id', $sourceItem->id)->delete();
    }

    private function relocateParticipants(Collection $registrationIds, int $sourceEventId, int $targetEventId, int $targetItemId): void
    {
        $participants = DB::table('fest_participants')
            ->whereIn('registration_id', $registrationIds)
            ->where('event_id', $sourceEventId)
            ->orderBy('id')
            ->get(['id', 'chest_no']);

        if ($participants->isEmpty()) {
            return;
        }

        $nextChest = (int) (DB::table('fest_participants')->where('event_id', $targetEventId)->where('chest_head_id', $targetItemId)->max('chest_no') ?? 0) + 1;
        $taken = DB::table('fest_participants')->where('event_id', $targetEventId)->where('chest_head_id', $targetItemId)->pluck('chest_no')->filter()->all();

        foreach ($participants as $participant) {
            $chestNo = $participant->chest_no;
            if ($chestNo !== null && in_array($chestNo, $taken, true)) {
                $chestNo = $nextChest++;
            } elseif ($chestNo !== null) {
                $taken[] = $chestNo;
            }

            DB::table('fest_participants')->where('id', $participant->id)->update([
                'event_id' => $targetEventId,
                'chest_head_id' => $targetItemId,
                'chest_no' => $chestNo,
            ]);
        }
    }

    private function relocateGroups(Collection $registrationIds, int $sourceEventId, int $targetEventId): void
    {
        $groups = DB::table('fest_groups')
            ->whereIn('registration_id', $registrationIds)
            ->where('event_id', $sourceEventId)
            ->orderBy('id')
            ->get(['id', 'chest_no']);

        if ($groups->isEmpty()) {
            return;
        }

        $nextChest = (int) (DB::table('fest_groups')->where('event_id', $targetEventId)->max('chest_no') ?? 0) + 1;
        $taken = DB::table('fest_groups')->where('event_id', $targetEventId)->pluck('chest_no')->filter()->all();

        foreach ($groups as $group) {
            $chestNo = $group->chest_no;
            if ($chestNo !== null && in_array($chestNo, $taken, true)) {
                $chestNo = $nextChest++;
            } elseif ($chestNo !== null) {
                $taken[] = $chestNo;
            }

            DB::table('fest_groups')->where('id', $group->id)->update([
                'event_id' => $targetEventId,
                'chest_no' => $chestNo,
            ]);
        }
    }

    /** Copy each touched leaf's rank-point config from the root, same as fee settings already propagate to new children. */
    private function copyRankPointsToNewLeaves(FestEvent $root, Collection $leaves): void
    {
        $hubPoints = DB::table('fest_rank_points')->where('event_id', $root->id)->get();
        if ($hubPoints->isEmpty()) {
            return;
        }

        foreach ($leaves as $leaf) {
            $exists = DB::table('fest_rank_points')->where('event_id', $leaf->id)->exists();
            if ($exists) {
                continue;
            }

            foreach ($hubPoints as $row) {
                $attrs = (array) $row;
                unset($attrs['id']);
                $attrs['event_id'] = $leaf->id;
                DB::table('fest_rank_points')->insert($attrs);
            }
        }
    }

    /** Every school with real registrations under an adopted region already implicitly chose that region — lock the choice so the new UI never re-asks. */
    private function backfillPhaseRegionSelections(FestEvent $root, array $adoptionPlan): void
    {
        foreach ($adoptionPlan as $plan) {
            FestPhaseRegion::firstOrCreate(
                ['phase_id' => $plan['targetPhase']->id, 'region_id' => $plan['region']->id],
                ['enabled' => true]
            );

            $schoolIds = DB::table('fest_registrations')
                ->where('event_id', $plan['child']->id)
                ->distinct()
                ->pluck('school_id');

            foreach ($schoolIds as $schoolId) {
                FestSchoolPhaseRegionSelection::updateOrCreate(
                    ['event_id' => $root->id, 'phase_id' => $plan['targetPhase']->id, 'school_id' => $schoolId],
                    ['region_id' => $plan['region']->id, 'selected_at' => now(), 'locked_at' => now()]
                );
            }
        }
    }

    private function leafKey(?int $phaseId, ?int $regionId): string
    {
        return $phaseId.':'.($regionId ?? 'none');
    }
}
