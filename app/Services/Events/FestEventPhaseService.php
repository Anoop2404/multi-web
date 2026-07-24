<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use Illuminate\Support\Collection;

class FestEventPhaseService
{
    /** @return Collection<int, FestEventPhase> */
    public function getPhases(FestEvent $event): Collection
    {
        return FestEventPhase::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function createPhase(FestEvent $event, array $data): FestEventPhase
    {
        $maxOrder = FestEventPhase::where('event_id', $event->id)->max('sort_order') ?? 0;

        return FestEventPhase::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
            'is_default' => $data['is_default'] ?? false,
        ]);
    }

    public function updatePhase(FestEventPhase $phase, array $data): FestEventPhase
    {
        $phase->update([
            'name' => $data['name'] ?? $phase->name,
            'code' => array_key_exists('code', $data) ? $data['code'] : $phase->code,
            'sort_order' => $data['sort_order'] ?? $phase->sort_order,
            'is_default' => $data['is_default'] ?? $phase->is_default,
        ]);

        return $phase->fresh();
    }

    public function deletePhase(FestEventPhase $phase): void
    {
        // Items with this phase will have phase_id set to null via DB nullOnDelete constraint
        $phase->delete();
    }

    public function assignItemsToPhase(FestEvent $event, ?int $phaseId, array $itemIds): int
    {
        if (empty($itemIds)) {
            return 0;
        }

        return FestEventItem::where('event_id', $event->id)
            ->whereIn('id', $itemIds)
            ->update(['phase_id' => $phaseId]);
    }
}
