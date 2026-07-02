<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramPropagation;
use App\Models\Tenant;
use App\Support\TenancyDatabase;

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
                ]
            ));

            $count++;
        }

        return $count;
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
