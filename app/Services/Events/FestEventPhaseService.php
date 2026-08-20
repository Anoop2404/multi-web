<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Support\StatusTransitionGuard;
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
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'registration_open' => $data['registration_open'] ?? null,
            'registration_close' => $data['registration_close'] ?? null,
            'status' => $data['status'] ?? 'registration_open',
            'school_registration_fee_share' => $data['school_registration_fee_share'] ?? null,
            'student_registration_fee' => $data['student_registration_fee'] ?? null,
            'registration_batch_id' => $data['registration_batch_id'] ?? null,
            'is_regional' => (bool) ($data['is_regional'] ?? false),
            'result_publish_mode' => $data['result_publish_mode'] ?? 'all_regions',
        ]);
    }

    public function updatePhase(FestEventPhase $phase, array $data): FestEventPhase
    {
        $isDefault = array_key_exists('is_default', $data) ? (bool) $data['is_default'] : (bool) ($phase->is_default ?? false);

        if ($isDefault && ! $phase->is_default) {
            $this->clearOtherDefaults($phase->event, $phase->id);
        }

        // LIFE-05 fix (functional audit, 2026-08-11/12): this method previously
        // only ever wrote name/code/sort_order/is_default — every lifecycle
        // column added by the 2026_09_17_000001 migration (status,
        // registration_open/close, registration_locked, scoring_locked,
        // schedule_published, results_published, appeals_open,
        // appeal_deadline_at, starts_at/ends_at, food_cutoff_at) had no write
        // path anywhere in the app, so every phase's lifecycle was
        // permanently stuck at its migration default the moment
        // phase_mode_enabled was turned on for an event — see
        // FestPhaseLifecycleService, which reads these columns but nothing
        // ever set them. 'status' is guarded by the same transition matrix
        // FestEvent uses (identical vocabulary: draft/published/
        // registration_open/ongoing/completed/cancelled) via
        // transitionStatus() below, NOT here — this method only writes the
        // other lifecycle fields, so a caller can't bypass the guard by
        // slipping 'status' into a plain updatePhase() call.
        $lifecycleFields = [
            'starts_at', 'ends_at', 'registration_open', 'registration_close',
            'registration_locked', 'food_cutoff_at', 'scoring_locked',
            'schedule_published', 'results_published', 'appeals_open', 'appeal_deadline_at',
            // Not a lifecycle field in the LIFE-05 sense, but same "allow-list, only write if
            // explicitly present" shape fits it — see FestEventPhase::school_registration_fee_share
            // and docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §3 item 4.
            'school_registration_fee_share', 'student_registration_fee',
            'registration_batch_id', 'is_regional', 'result_publish_mode',
        ];

        $payload = [
            'name' => $data['name'] ?? $phase->name,
            'code' => array_key_exists('code', $data) ? $data['code'] : $phase->code,
            'sort_order' => $data['sort_order'] ?? $phase->sort_order,
            'is_default' => $isDefault,
        ];

        foreach ($lifecycleFields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        $phase->update($payload);

        return $phase->fresh();
    }

    /**
     * Transition a phase's status through the same guarded state machine
     * FestEvent uses (see StatusTransitionGuard::FEST_EVENT_TRANSITIONS) —
     * the dedicated write path that was entirely missing before the LIFE-05
     * fix. Mirrors FestEventController::quickStatus()'s pattern: a
     * lightweight, single-field transition separate from the general-purpose
     * updatePhase() above.
     */
    public function transitionStatus(FestEventPhase $phase, string $newStatus): FestEventPhase
    {
        StatusTransitionGuard::assert(
            $phase,
            $newStatus,
            StatusTransitionGuard::FEST_EVENT_TRANSITIONS,
        );

        $phase->update(['status' => $newStatus]);

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
        $operationalDataExists = \App\Models\FestEvent::where('source_phase_id', $phase->id)
            ->where(function ($query) {
                $query->whereHas('registrations')->orWhereHas('results');
            })
            ->exists();

        abort_if(
            ($itemCount > 0 || $operationalDataExists || $phase->regionSelections()->exists()) && ! $force,
            422,
            "This phase has assigned items, region selections, or operational data. Migrate those records before deleting it."
        );

        // Clean up operational child events spawned from this phase that have no registrations/results
        \App\Models\FestEvent::where('source_phase_id', $phase->id)
            ->whereDoesntHave('registrations')
            ->delete();

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
