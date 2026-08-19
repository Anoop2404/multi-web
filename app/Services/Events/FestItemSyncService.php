<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestItemHead;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramPropagation;
use App\Models\Tenant;
use App\Support\TenancyDatabase;
use Illuminate\Support\Str;

class FestItemSyncService
{
    /**
     * Create any State catalog items that don't yet exist on this Sahodaya tenant event.
     *
     * Same create-or-touch-nothing rule as FestStateProgramService::syncTenantEvent()
     * (STATE_SAHODAYA_RULE_BOUNDARY_FIX_PLAN_2026_08_13.md, Set 1 items 1-2): an existing
     * item belongs to the Sahodaya from creation onward. This used to updateOrCreate() on
     * every sync, reapplying toTenantAttributes() to already-existing items — so a
     * Sahodaya's own max_per_school/qualify_count/squad caps (set via
     * FestEventController::bulkUpdateItemCaps()) were silently reverted to the State
     * template every time State added a new item and the program re-synced. Only items
     * State has added since the last sync get created; existing ones are never touched.
     */
    public function syncProgramToEvent(FestStateProgram $program, FestEvent $event): int
    {
        $count = 0;

        foreach ($program->items()->orderBy('display_order')->get() as $stateItem) {
            $exists = FestEventItem::where('event_id', $event->id)
                ->where('state_program_item_id', $stateItem->id)
                ->exists();

            if ($exists) {
                continue;
            }

            FestEventItem::create(array_merge(
                ['event_id' => $event->id],
                $stateItem->toTenantAttributes()
            ));
            $count++;
        }

        return $count;
    }

    /**
     * Sync state items to every propagated Sahodaya event for this program.
     */
    public function syncProgramToAllPropagations(FestStateProgram $program): int
    {
        $total = 0;

        $propagations = FestStateProgramPropagation::query()
            ->where('state_program_id', $program->id)
            ->whereNotNull('tenant_event_id')
            ->with('sahodaya')
            ->get();

        foreach ($propagations as $propagation) {
            $sahodaya = $propagation->sahodaya;
            if (! $sahodaya) {
                continue;
            }

            try {
                $synced = TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($program, $propagation) {
                    $event = FestEvent::query()->find($propagation->tenant_event_id);
                    if (! $event) {
                        return 0;
                    }

                    return $this->syncProgramToEvent($program, $event);
                });
                $total += (int) $synced;
            } catch (\Throwable) {
                // Skip clusters whose DB is not ready.
            }
        }

        return $total;
    }

    public function removeProgramItemFromAllPropagations(FestStateProgram $program, string $stateProgramItemId): int
    {
        $affected = 0;
        $propagations = FestStateProgramPropagation::query()
            ->where('state_program_id', $program->id)
            ->whereNotNull('tenant_event_id')
            ->with('sahodaya')
            ->get();

        foreach ($propagations as $propagation) {
            if (! $propagation->sahodaya) {
                continue;
            }

            try {
                $affected += (int) TenancyDatabase::runWhenDatabaseReady($propagation->sahodaya, function () use ($stateProgramItemId) {
                    $count = 0;
                    foreach (FestEventItem::where('state_program_item_id', $stateProgramItemId)->get() as $item) {
                        if ($item->registrations()->exists()) {
                            $item->update(['is_enabled' => false]);
                        } else {
                            $item->delete();
                        }
                        $count++;
                    }

                    return $count;
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $affected;
    }

    /**
     * Copy inherited items (state + sahodaya) from a cluster event into a school event.
     *
     * @param  list<string>  $levels
     */
    public function copyInheritedItems(FestEvent $source, FestEvent $target, array $levels = ['state', 'sahodaya']): int
    {
        $count = 0;

        $source->loadMissing('items');

        foreach ($source->items as $item) {
            if (! in_array($item->owner_level, $levels, true)) {
                continue;
            }

            $match = ['event_id' => $target->id];

            if ($item->state_program_item_id) {
                $match['state_program_item_id'] = $item->state_program_item_id;
            } else {
                $match['inherited_from_item_id'] = $item->id;
            }

            FestEventItem::updateOrCreate($match, array_merge(
                $this->attributesFromItem($item),
                [
                    'owner_level'            => $item->owner_level,
                    'state_program_item_id'  => $item->state_program_item_id,
                    'inherited_from_item_id' => $item->id,
                    'phase_id'               => $this->resolvePhaseIdForTarget($item, $target),
                    'head_id'                => $this->resolveHeadIdForTarget($item, $target),
                ]
            ));

            $count++;
        }

        return $count;
    }

    /**
     * Copy items from hub to partition child, filtering by partition role when configured.
     *
     * §7.3 item 5 (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md, 2026-08-15): optional
     * $phase parameter, added on top of the pre-existing signature. Every caller that
     * already existed before this change passes 3 arguments, so $phase defaults to
     * null — and null preserves the exact existing behavior (copy every item that
     * passes the $partitionRole filter, regardless of which phase it belongs to).
     * Omitting $phase is always safe.
     *
     * When a caller DOES pass $phase (the owning regional FestEventPhase on the hub —
     * see FestRegionPartitionService::syncPartitionsFromRegionsForPhase()), items are
     * ADDITIONALLY required to belong to that exact phase (item.phase_id === phase.id)
     * before being copied — so an Off Stage region child receives only Off Stage items,
     * not Sargadhara items, even though both phases share the same "region" children on
     * the hub.
     */
    public function copyItemsToPartition(FestEvent $hub, FestEvent $child, string $partitionRole, ?FestEventPhase $phase = null): int
    {
        $hub->loadMissing('items');
        $count = 0;

        foreach ($hub->items as $item) {
            if ($this->copyItemToPartition($hub, $item, $child, $partitionRole, $phase)) {
                $count++;
            }
        }

        return $count;
    }

    public function resyncAllItemsToPartitions(FestEvent $hub): int
    {
        $partitions = app(FestPartitionService::class);
        if (! $partitions->isPartitionedHub($hub)) {
            return 0;
        }

        $hub->loadMissing('items');
        $count = 0;

        foreach ($partitions->partitions($hub) as $child) {
            $role = $partitions->partitionRole($child) ?? 'region';
            $count += $this->copyItemsToPartition($hub, $child, $role);
        }

        return $count;
    }

    public function copyItemToPartition(
        FestEvent $hub,
        FestEventItem $item,
        FestEvent $child,
        string $partitionRole,
        ?FestEventPhase $phase = null,
    ): ?FestEventItem {
        // §7.3 item 5: when $phase is given, only that phase's own items are eligible —
        // see copyItemsToPartition()'s docblock above for the backward-compat guarantee
        // ($phase omitted/null never filters by phase at all, matching today's behavior).
        if ($phase !== null && (int) ($item->phase_id ?? 0) !== (int) $phase->id) {
            return null;
        }

        if (! $this->itemEnabledForPartition($hub, $item, $partitionRole)) {
            return null;
        }

        $target = FestEventItem::where('event_id', $child->id)
            ->where('inherited_from_item_id', $item->id)
            ->first();

        // Older partition copies may predate inherited_from_item_id. Reuse the
        // matching item code so registration cannot pick a stale duplicate.
        if (! $target && filled($item->item_code)) {
            $target = FestEventItem::where('event_id', $child->id)
                ->where('item_code', $item->item_code)
                ->first();
        }

        // When a partition child's item row exists, sync parent metadata edits (title, category,
        // class_group, gender, criteria_json, squad rules, etc.) to keep child partition items
        // up to date, while preserving any region-specific overrides (max_per_school, fee_amount, is_enabled).
        if ($target) {
            $target->update([
                'title'              => $item->title,
                'item_code'          => $item->item_code,
                'category'           => $item->category,
                'stage_type'         => $item->stage_type,
                'venue_type'         => $item->venue_type,
                'competition_format' => $item->competition_format,
                'sport_discipline'   => $item->sport_discipline,
                'ranking_direction'  => $item->ranking_direction,
                'result_method'      => $item->result_method,
                'duration_minutes'   => $item->duration_minutes,
                'criteria_json'      => $item->criteria_json,
                'participant_type'   => $item->participant_type,
                'gender'             => $item->gender,
                'class_group'        => $item->class_group,
                'age_group'          => $item->age_group,
                'kids_band'          => $item->kids_band,
                'min_group_size'     => $item->min_group_size,
                'max_group_size'     => $item->max_group_size,
                'qualify_count'      => $item->qualify_count,
                'is_mandatory'       => $item->is_mandatory,
                'quota_eligible'     => $item->quota_eligible,
                'tiebreak_mode'      => $item->tiebreak_mode,
            ]);

            return $target;
        }

        $attributes = array_merge($this->attributesFromItem($item), [
            'owner_level'            => $item->owner_level,
            'state_program_item_id'  => $item->state_program_item_id,
            'inherited_from_item_id' => $item->id,
            'max_per_school'         => $this->maxPerSchoolForPartition($item, $partitionRole),
            'phase_id'               => $this->resolvePhaseIdForTarget($item, $child),
            'head_id'                => $this->resolveHeadIdForTarget($item, $child),
        ]);

        return FestEventItem::create(array_merge(
            ['event_id' => $child->id],
            $attributes,
        ));
    }

    private function itemEnabledForPartition(
        FestEvent $hub,
        FestEventItem $item,
        string $partitionRole,
    ): bool
    {
        $criteria = $item->criteria_json ?? [];
        $roles = $criteria['partition_roles'] ?? null;

        if ($roles !== null) {
            return in_array($partitionRole, (array) $roles, true);
        }

        // Conduct the complete catalogue in each region/cluster partition by default.
        if (in_array($partitionRole, ['region', 'cluster'], true)) {
            return true;
        }

        return match ($partitionRole) {
            'finale' => ($item->stage_type ?? '') === 'on_stage'
                || in_array($item->participant_type, ['group', 'team'], true),
            default => true,
        };
    }

    private function maxPerSchoolForPartition(FestEventItem $item, string $partitionRole): int
    {
        $base = (int) ($item->max_per_school ?? 1);
        $criteria = $item->criteria_json ?? [];

        if ($partitionRole === 'region' && isset($criteria['regional_max_per_item'])) {
            return (int) $criteria['regional_max_per_item'];
        }

        if ($partitionRole === 'finale' && isset($criteria['district_max_per_item_per_school'])) {
            return (int) $criteria['district_max_per_item_per_school'];
        }

        return $base;
    }

    /**
     * Copy every item from parent event to child (cascade / school rounds).
     */
    public function copyAllItemsToChild(FestEvent $parent, FestEvent $child): void
    {
        $parent->loadMissing('items');

        foreach ($parent->items as $item) {
            FestEventItem::create(array_merge(
                $this->attributesFromItem($item),
                [
                    'event_id'               => $child->id,
                    'owner_level'            => $item->owner_level,
                    'state_program_item_id'  => $item->state_program_item_id,
                    'inherited_from_item_id' => $item->id,
                    'phase_id'               => $this->resolvePhaseIdForTarget($item, $child),
                    'head_id'                => $this->resolveHeadIdForTarget($item, $child),
                ]
            ));
        }
    }

    /**
     * Inherit cluster catalog into a standalone school event (state + sahodaya items).
     */
    public function inheritClusterCatalog(FestEvent $schoolEvent, Tenant $school): int
    {
        $sahodayaId = $school->parent_id;
        if (! $sahodayaId) {
            return 0;
        }

        $clusterEvent = FestEvent::query()
            ->where('tenant_id', $sahodayaId)
            ->where('event_type', $schoolEvent->event_type)
            ->where('level_round', 'sahodaya')
            ->when($schoolEvent->state_program_id, fn ($q) => $q->where('state_program_id', $schoolEvent->state_program_id))
            ->orderByDesc('event_start')
            ->first();

        if (! $clusterEvent) {
            return 0;
        }

        return $this->copyInheritedItems($clusterEvent, $schoolEvent);
    }

    /**
     * Remove/disable the copies of a hub item that already exist on spawned partition
     * children, mirroring the create/update propagation that already happens in
     * FestEventController::syncItemToExistingPartitions(). Previously deleting a hub item
     * left every already-copied child item (region/finale/cluster) in place — schools could
     * keep registering for, and paying for, a competition item that no longer existed on the
     * hub at all (Phase 6 audit). Child copies that already have registrations are disabled
     * rather than hard-deleted, so existing registration/payment history is never orphaned;
     * copies with no registrations are removed outright.
     */
    public function removeItemFromPartitions(int $hubItemId): void
    {
        $children = FestEventItem::where('inherited_from_item_id', $hubItemId)->get();

        foreach ($children as $childItem) {
            if ($childItem->registrations()->exists()) {
                $childItem->update(['is_enabled' => false]);
            } else {
                $childItem->delete();
            }
        }
    }

    /**
     * Resolve (creating if needed) the phase on $target that corresponds to $item's phase on
     * its source event. Previously every partition/cascade/inherit copy silently dropped
     * phase_id (attributesFromItem() never included it), so items landed on the child event
     * with no phase at all regardless of how they were organized on the hub (Phase 5 audit
     * items 6 & 7). Matches by code when the source phase has one (codes are meant to be
     * stable identifiers across an event family), else falls back to matching by name for
     * codeless phases. Does not copy lifecycle fields (dates, locks, publication flags) onto
     * the created target phase — those are specific to each event's own schedule.
     */
    private function resolvePhaseIdForTarget(FestEventItem $item, FestEvent $target): ?int
    {
        if (! $item->phase_id) {
            return null;
        }

        $sourcePhase = $item->phase ?? FestEventPhase::find($item->phase_id);
        if (! $sourcePhase) {
            return null;
        }

        $canonicalSource = $sourcePhase->sourcePhase ?: $sourcePhase;

        $targetPhase = FestEventPhase::where('event_id', $target->id)
            ->where('source_phase_id', $canonicalSource->id)
            ->first();

        $targetPhase ??= $canonicalSource->code
            ? FestEventPhase::where('event_id', $target->id)->where('code', $canonicalSource->code)->first()
            : FestEventPhase::where('event_id', $target->id)->where('name', $canonicalSource->name)->whereNull('code')->first();

        if ($targetPhase) {
            if (! $targetPhase->source_phase_id) {
                $targetPhase->update(['source_phase_id' => $canonicalSource->id]);
            }

            return $targetPhase->id;
        }

        $maxOrder = FestEventPhase::where('event_id', $target->id)->max('sort_order') ?? 0;

        return FestEventPhase::create([
            'event_id'   => $target->id,
            'source_phase_id' => $canonicalSource->id,
            'registration_batch_id' => $canonicalSource->registration_batch_id,
            'name'       => $canonicalSource->name,
            'code'       => $canonicalSource->code,
            'sort_order' => $canonicalSource->sort_order ?? ($maxOrder + 1),
            'is_default' => true,
            'is_regional' => $canonicalSource->is_regional,
            'region_partition_group' => $canonicalSource->region_partition_group,
            'result_publish_mode' => $canonicalSource->result_publish_mode ?? 'all_regions',
            'starts_at' => $canonicalSource->starts_at,
            'ends_at' => $canonicalSource->ends_at,
            'registration_open' => $canonicalSource->registration_open,
            'registration_close' => $canonicalSource->registration_close,
            'registration_locked' => $canonicalSource->registration_locked,
            'food_cutoff_at' => $canonicalSource->food_cutoff_at,
            'status' => $canonicalSource->status,
            'scoring_locked' => $canonicalSource->scoring_locked,
            'schedule_published' => $canonicalSource->schedule_published,
            'results_published' => $canonicalSource->results_published,
            'appeals_open' => $canonicalSource->appeals_open,
            'appeal_deadline_at' => $canonicalSource->appeal_deadline_at,
        ])->id;
    }

    /**
     * Resolve (creating if needed) the Event Head on $target that corresponds to $item's head
     * on its source event. Same gap as phase_id: attributesFromItem() never included head_id,
     * so every partition/cascade/inherit copy silently dropped the item's Event Head
     * assignment, even though propagateFeeSettingsToChildren() already assumes matching heads
     * exist on children (it looks them up by name to push fee columns down) — items copied
     * before this fix would simply never be found by that lookup (Phase 6 audit). Matches by
     * catalog_key when the source head has one (catalog heads are meant to be stable across
     * an event family), else falls back to name. Copies only identity + fee/policy fields
     * onto a newly created target head, not schedule fields (reg/competition windows) — those
     * are specific to each event.
     */
    private function resolveHeadIdForTarget(FestEventItem $item, FestEvent $target): ?int
    {
        if (! $item->head_id) {
            return null;
        }

        $sourceHead = $item->head ?? FestItemHead::find($item->head_id);
        if (! $sourceHead) {
            return null;
        }

        $targetHead = $sourceHead->catalog_key
            ? FestItemHead::where('event_id', $target->id)->where('catalog_key', $sourceHead->catalog_key)->first()
            : FestItemHead::where('event_id', $target->id)->where('name', $sourceHead->name)->first();

        if ($targetHead) {
            return $targetHead->id;
        }

        $maxOrder = FestItemHead::where('event_id', $target->id)->max('sort_order') ?? 0;

        return FestItemHead::create([
            'tenant_id'                   => $target->tenant_id,
            'event_id'                    => $target->id,
            'event_type'                  => $target->event_type,
            'name'                        => $sourceHead->name,
            'slug'                        => Str::slug($sourceHead->name),
            'sport_discipline'            => $sourceHead->sport_discipline,
            'catalog_key'                 => $sourceHead->catalog_key,
            'is_team_heading'             => $sourceHead->is_team_heading,
            'sort_order'                  => $maxOrder + 1,
            'default_item_fee'            => $sourceHead->default_item_fee,
            'extra_item_fee'              => $sourceHead->extra_item_fee,
            'school_registration_fee'     => $sourceHead->school_registration_fee,
            'student_registration_fee'    => $sourceHead->student_registration_fee,
            'team_registration_fee'       => $sourceHead->team_registration_fee,
            'included_items_per_student'  => $sourceHead->included_items_per_student,
            'included_teams'              => $sourceHead->included_teams,
            'verification_policy'         => $sourceHead->verification_policy,
            'approval_policy'             => $sourceHead->approval_policy,
            'max_participants'            => $sourceHead->max_participants,
            'max_teams'                   => $sourceHead->max_teams,
        ])->id;
    }

    /** @return array<string, mixed> */
    public function attributesFromItem(FestEventItem $item): array
    {
        return [
            'title'              => $item->title,
            'item_code'          => $item->item_code,
            'category'           => $item->category,
            'stage_type'         => $item->stage_type,
            'venue_type'         => $item->venue_type,
            'competition_format' => $item->competition_format,
            'sport_discipline'   => $item->sport_discipline,
            'ranking_direction'  => $item->ranking_direction,
            'result_method'      => $item->result_method,
            'duration_minutes'   => $item->duration_minutes,
            'criteria_json'      => $item->criteria_json,
            'participant_type'   => $item->participant_type,
            'gender'             => $item->gender,
            'class_group'        => $item->class_group,
            'age_group'          => $item->age_group,
            'kids_band'          => $item->kids_band,
            'max_per_school'     => $item->max_per_school,
            'min_group_size'     => $item->min_group_size,
            'max_group_size'     => $item->max_group_size,
            'qualify_count'      => $item->qualify_count,
            'fee_amount'         => $item->fee_amount,
            'group_item_flat_fee' => $item->group_item_flat_fee,
            'group_item_per_participant_rate' => $item->group_item_per_participant_rate,
            'is_enabled'         => $item->is_enabled,
            'is_mandatory'       => $item->is_mandatory,
            'quota_eligible'     => $item->quota_eligible,
            'tiebreak_mode'      => $item->tiebreak_mode,
            'tiebreak_secondary' => $item->tiebreak_secondary,
            'mark_judge_count'   => $item->mark_judge_count,
            'display_order'      => $item->display_order,
        ];
    }
}
