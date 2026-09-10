<?php

namespace App\Services\Events;

use App\Models\FestEventItem;
use Illuminate\Validation\ValidationException;

class FestItemRegistrationGate
{
    public function __construct(
        private FestItemWindowResolver $windows,
    ) {}

    public function isOpen(FestEventItem $item): bool
    {
        $event = $item->event ?? $item->event()->first();
        if (! $event) {
            return false;
        }

        // Once phase mode is on, the item's phase lifecycle is the authoritative gate on
        // the actual write path (FestRegistrationCreateService::createForSchool() calls
        // EventLifecycleGate::allowRegistrationForItem() right after this class's own
        // assertOpen()) — it replaces the event-level/item-window check below entirely,
        // same as that method's own branching. Without this, an item could show "Open"
        // and let the Register button submit here, then fail with EventLifecycleGate's
        // own "closed for this item's competition phase" message only after the fact.
        if ($event->phase_mode_enabled) {
            return EventLifecycleGate::registrationBlockedReasonForItem($event, $item) === null;
        }

        if (! $event->isRegistrationOpen()) {
            return false;
        }

        return $this->windows->isRegistrationOpen($item);
    }

    public function resultsPublished(FestEventItem $item): bool
    {
        if ($item->results_published_at) {
            return true;
        }

        return (bool) ($item->event?->results_published ?? false);
    }

    public function assertOpen(FestEventItem $item, bool $adminOverride = false): void
    {
        if (! ($item->is_enabled ?? true)) {
            throw ValidationException::withMessages([
                'registration' => 'This item is not open for registration.',
            ]);
        }

        // Admin-initiated writes (Register on behalf, and roster edits on an existing
        // registration routed through the same call) exist precisely to let a Sahodaya
        // admin act after a school's own registration window/phase lifecycle would
        // otherwise block it. The only thing that still stops them is the item's own
        // results already being published -- same item-level-only rule already applied
        // to FestRegistrationService::addParticipant()/removeParticipant()/
        // substitutePerformer() earlier. Every other lifecycle/window/lock condition
        // below is deliberately skipped for this path.
        if ($adminOverride) {
            if ($item->results_published_at) {
                throw ValidationException::withMessages([
                    'registration' => "This item's results are already published.",
                ]);
            }

            return;
        }

        $event = $item->event ?? $item->event()->first();
        if (! $event) {
            throw ValidationException::withMessages([
                'registration' => 'Event not found.',
            ]);
        }

        // Mirrors isOpen()'s own branching just above -- once phase mode is on, the
        // item's phase lifecycle is the authoritative gate, replacing the event-level/
        // item-window check below entirely. Without this, a phase-mode hub that has
        // progressed to "ongoing" overall (its own registration window closed) blocked
        // every admin write here -- Register on behalf, and roster edits (substitute/
        // add/remove participant, which also route through createForSchool()/
        // updateForSchool()) -- for every individual phase/region, even ones still
        // actively open. Confirmed live: "Registration is closed for this event." on
        // Register on behalf for a phase whose own window was still open.
        if ($event->phase_mode_enabled) {
            $reason = EventLifecycleGate::registrationBlockedReasonForItem($event, $item);
            if ($reason !== null) {
                throw ValidationException::withMessages(['registration' => $reason]);
            }

            return;
        }

        if (! $event->isRegistrationOpen()) {
            throw ValidationException::withMessages([
                'registration' => 'Registration is closed for this event.',
            ]);
        }

        if ($this->windows->isRegistrationOpen($item)) {
            return;
        }

        $start = $this->windows->effectiveRegStart($item)?->format('j M Y');
        $end = $this->windows->effectiveRegEnd($item)?->format('j M Y');
        $detail = ($start || $end) ? " Registration window: {$start} – {$end}." : '';

        throw ValidationException::withMessages([
            'registration' => 'Registration is closed for this item.'.$detail,
        ]);
    }
}
