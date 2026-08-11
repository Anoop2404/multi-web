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
     * Push all state catalog items into a Sahodaya tenant event.
     */
    public function syncProgramToEvent(FestStateProgram $program, FestEvent $event): int
    {
        $count = 0;

        foreach ($program->items()->orderBy('display_order')->get() as $stateItem) {
            FestEventItem::updateOrCreate(
                [
                    'event_id'              => $event->id,
                    'state_program_item_id' => $stateItem->id,
                ],
                $stateItem->toTenantAttributes()
            );
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
     */
    public function copyItemsToPartition(FestEvent $hub, FestEvent $child, string $partitionRole): int
    {
        $hub->loadMissing('items');
        $count = 0;

        foreach ($hub->items as $item) {
            if ($this->copyItemToPartition($hub, $item, $child, $partitionRole)) {
                $count++;
            }
        }

        return $count;
    }

    public function copyItemToPartition(
        FestEvent $hub,
        FestEventItem $item,
        FestEvent $child,
        string $partitionRole,
    ): ?FestEventItem {
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

        $attributes = array_merge($this->attributesFromItem($item), [
            'owner_level'            => $item->owner_level,
            'state_program_item_id'  => $item->state_program_item_id,
            'inherited_from_item_id' => $item->id,
            'max_per_school'         => $this->maxPerSchoolForPartition($item, $partitionRole),
            'phase_id'               => $this->resolvePhaseIdForTarget($item, $child),
            'head_id'                => $this->resolveHeadIdForTarget($item, $child),
        ]);

        if ($target) {
            $target->update($attributes);

            return $target->refresh();
        }

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

        // For sports meets and non-Kalotsav fests, conduct the complete catalogue in each
        // region. Do not inherit Kalotsav's implicit off-stage/on-stage split.
        if (in_array($hub->event_type, ['sports', 'english_fest', 'science_fest', 'kids_fest', 'teacher_fest'], true)) {
            return in_array($partitionRole, ['region', 'cluster'], true);
        }

        return match ($partitionRole) {
            'region', 'cluster' => ($item->stage_type ?? '') === 'off_stage'
                && ! in_array($item->participant_type, ['group', 'team'], true),
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

        $targetPhase = $sourcePhase->code
            ? FestEventPhase::where('event_id', $target->id)->where('code', $sourcePhase->code)->first()
            : FestEventPhase::where('event_id', $target->id)->where('name', $sourcePhase->name)->whereNull('code')->first();

        if ($targetPhase) {
            return $targetPhase->id;
        }

        $maxOrder = FestEventPhase::where('event_id', $target->id)->max('sort_order') ?? 0;

        return FestEventPhase::create([
            'event_id'   => $target->id,
            'name'       => $sourcePhase->name,
            'code'       => $sourcePhase->code,
            'sort_order' => $sourcePhase->sort_order ?? ($maxOrder + 1),
            'is_default' => false,
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
            'display_order'      => $item->display_order,
        ];
    }
}
