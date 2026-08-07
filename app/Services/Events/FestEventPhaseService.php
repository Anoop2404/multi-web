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
        $isDefault = (bool) ($data['is_default'] ?? false);

        // Exactly one phase per event may be the default — previously multiple phases
        // could all have is_default=true simultaneously (Phase 5 audit item 3), which
        // leaves any future "use the default phase" consumer to pick one arbitrarily.
        if ($isDefault) {
            $this->clearOtherDefaults($event);
        }

        return FestEventPhase::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
            'is_default' => $isDefault,
        ]);
    }

    public function updatePhase(FestEventPhase $phase, array $data): FestEventPhase
    {
        $isDefault = array_key_exists('is_default', $data) ? (bool) $data['is_default'] : $phase->is_default;

        if ($isDefault && ! $phase->is_default) {
            $this->clearOtherDefaults($phase->event, $phase->id);
        }

        $phase->update([
            'name' => $data['name'] ?? $phase->name,
            'code' => array_key_exists('code', $data) ? $data['code'] : $phase->code,
            'sort_order' => $data['sort_order'] ?? $phase->sort_order,
            'is_default' => $isDefault,
        ]);

        return $phase->fresh();
    }

    /** Unset is_default on every other phase for this event (so only one stays true). */
    private function clearOtherDefaults(FestEvent $event, ?int $exceptPhaseId = null): void
    {
        FestEventPhase::where('event_id', $event->id)
            ->where('is_default', true)
            ->when($exceptPhaseId, fn ($q) => $q->where('id', '!=', $exceptPhaseId))
            ->update(['is_default' => false]);
    }

    /**
     * Deleting a phase that still has items assigned previously just silently nulled
     * phase_id on every one of those items via the DB's nullOnDelete constraint — no
     * warning, no way to know afterward which items lost their phase (Phase 5 audit item
     * 5). Now refuses unless the caller explicitly acknowledges the reassignment via
     * $force, matching the "explicit, not silent" pattern used elsewhere in this app for
     * destructive actions with side effects on other records.
     */
    public function deletePhase(FestEventPhase $phase, bool $force = false): void
    {
        $itemCount = $phase->items()->count();

        abort_if(
            $itemCount > 0 && ! $force,
            422,
            "This phase has {$itemCount} item(s) assigned — pass force to delete anyway and unassign them, or reassign the items to another phase first."
        );

        $phase->delete();
    }

    public function assignItemsToPhase(FestEvent $event, ?int $phaseId, array $itemIds): int
    {
        if (empty($itemIds)) {
            return 0;
        }

        // A phase_id from a DIFFERENT event could previously be assigned to this event's
        // items — the caller-side validation (FestEventPhaseController::assignItems) is
        // the primary guard, but this service is the actual mutation, so it re-checks
        // rather than trusting every caller got the Rule::exists()::where() right (Phase 5
        // audit item 2).
        if ($phaseId !== null) {
            abort_unless(
                FestEventPhase::where('id', $phaseId)->where('event_id', $event->id)->exists(),
                422,
                'That phase does not belong to this event.'
            );
        }

        return FestEventItem::where('event_id', $event->id)
            ->whereIn('id', $itemIds)
            ->update(['phase_id' => $phaseId]);
    }
}
