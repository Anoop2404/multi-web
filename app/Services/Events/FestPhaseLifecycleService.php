<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves the *effective* lifecycle for an item/registration, per
 * docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md §6.3.
 *
 * - Phase mode off (FestEvent::phase_mode_enabled = false): every item behaves as one
 *   implicit event-wide phase — the effective lifecycle is just the event's own
 *   lifecycle fields, unchanged from today's behavior.
 * - Phase mode on: the item's assigned named phase controls its lifecycle. An enabled
 *   item with phase mode on but no phase assigned fails closed (throws), per plan §6.1/
 *   §6.3 — "every enabled item must belong to a phase before publication/registration
 *   opens" / "A missing phase on an enabled item fails closed once phase mode is
 *   active."
 *
 * This service only *resolves* the effective lifecycle. Wiring it into the ten
 * operational areas listed in §6.3 (registration, food, schedule, marks, results,
 * appeals, certificates, promotion, public pages, report packs) is Phase 6 — see the
 * final status report for which of those are actually wired vs still reading event-level
 * fields directly today.
 */
class FestPhaseLifecycleService
{
    /**
     * @return object{
     *     registration_open: ?\Carbon\Carbon,
     *     registration_close: ?\Carbon\Carbon,
     *     registration_locked: bool,
     *     food_cutoff_at: ?\Carbon\Carbon,
     *     scoring_locked: bool,
     *     schedule_published: bool,
     *     results_published: bool,
     *     appeals_open: bool,
     *     appeal_deadline_at: ?\Carbon\Carbon,
     *     status: ?string,
     *     source: string,
     * }
     */
    public function effectiveLifecycleForItem(FestEventItem $item): object
    {
        $event = $item->relationLoaded('event') ? $item->event : FestEvent::find($item->event_id);

        if (! $event || ! $event->phase_mode_enabled) {
            return $this->fromEvent($event);
        }

        $phase = $item->relationLoaded('phase') ? $item->phase : ($item->phase_id ? FestEventPhase::find($item->phase_id) : null);

        if (! $phase) {
            if (! $item->is_enabled) {
                // A disabled item genuinely has nothing that needs a phase yet — don't
                // fail closed on catalog data that isn't live.
                return $this->closedLifecycle('item_disabled_no_phase');
            }

            throw new HttpException(422, "Item #{$item->id} is enabled but has no competition phase assigned, and phase mode is on for this event. Assign a phase before opening registration/publishing.");
        }

        if ($phase->event_id !== $event->id) {
            throw new HttpException(422, "Item #{$item->id} is assigned to a competition phase from another event.");
        }

        return $this->fromPhase($phase);
    }

    /**
     * Same resolution, but by (event, phase_id) directly — for report/export code that
     * has a competition_phase_id filter (FestReportScope::$competitionPhaseId) and needs
     * the phase's own lifecycle rather than any one item's.
     */
    public function effectiveLifecycleForPhase(FestEvent $event, ?int $phaseId): object
    {
        if (! $event->phase_mode_enabled || $phaseId === null) {
            return $this->fromEvent($event);
        }

        $phase = FestEventPhase::where('event_id', $event->id)->find($phaseId);

        return $phase ? $this->fromPhase($phase) : $this->closedLifecycle('phase_not_found');
    }

    /** Every enabled item under $event has an assigned phase — required before phase mode can be safely turned on (plan §6.1). */
    public function hasCompletePhaseAssignment(FestEvent $event): bool
    {
        return ! FestEventItem::where('event_id', $event->id)
            ->where('is_enabled', true)
            ->whereNull('phase_id')
            ->exists();
    }

    private function fromEvent(?FestEvent $event): object
    {
        if (! $event) {
            return $this->closedLifecycle('event_not_found');
        }

        return (object) [
            'registration_open'   => $event->registration_open,
            'registration_close'  => $event->registration_close,
            'registration_locked' => (bool) $event->registration_locked,
            'registration_batch_open' => true,
            'registration_batch_id' => null,
            'food_cutoff_at'      => null,
            'scoring_locked'      => (bool) $event->scoring_locked,
            'schedule_published'  => (bool) $event->schedule_published,
            'results_published'   => (bool) $event->results_published,
            'appeals_open'        => (bool) $event->appeals_open,
            'appeal_deadline_at'  => null,
            'status'              => $event->status,
            'source'              => 'event',
        ];
    }

    private function fromPhase(FestEventPhase $phase): object
    {
        $phase->loadMissing('registrationBatch');
        $batch = $phase->registrationBatch;
        $registrationOpen = $this->laterDate($phase->registration_open, $batch?->registration_open);
        $registrationClose = $this->earlierDate($phase->registration_close, $batch?->registration_close);

        return (object) [
            'registration_open'   => $registrationOpen,
            'registration_close'  => $registrationClose,
            'registration_locked' => (bool) $phase->registration_locked || (bool) ($batch?->registration_locked),
            'registration_batch_open' => ! $batch || $batch->isRegistrationOpen(),
            'registration_batch_id' => $batch?->id,
            'food_cutoff_at'      => $phase->food_cutoff_at,
            'scoring_locked'      => (bool) $phase->scoring_locked,
            'schedule_published'  => (bool) $phase->schedule_published,
            'results_published'   => (bool) $phase->results_published,
            'appeals_open'        => (bool) $phase->appeals_open,
            'appeal_deadline_at'  => $phase->appeal_deadline_at,
            'status'              => $phase->status,
            'source'              => 'phase:'.$phase->id,
        ];
    }

    private function closedLifecycle(string $reason): object
    {
        return (object) [
            'registration_open'   => null,
            'registration_close'  => null,
            'registration_locked' => true,
            'registration_batch_open' => false,
            'registration_batch_id' => null,
            'food_cutoff_at'      => null,
            'scoring_locked'      => true,
            'schedule_published'  => false,
            'results_published'   => false,
            'appeals_open'        => false,
            'appeal_deadline_at'  => null,
            'status'              => null,
            'source'              => "closed:{$reason}",
        ];
    }

    private function laterDate($first, $second)
    {
        if (! $first) {
            return $second;
        }
        if (! $second) {
            return $first;
        }

        return $first->gte($second) ? $first : $second;
    }

    private function earlierDate($first, $second)
    {
        if (! $first) {
            return $second;
        }
        if (! $second) {
            return $first;
        }

        return $first->lte($second) ? $first : $second;
    }
}
