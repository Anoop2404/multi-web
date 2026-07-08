<?php

namespace App\Services\Events;

use App\Models\FestEventItem;

class FestItemRegistrationGate
{
    public function __construct(
        private FestItemWindowResolver $windows,
    ) {}

    public function isOpen(FestEventItem $item): bool
    {
        $event = $item->event ?? $item->event()->first();
        if (! $event || ! $event->isRegistrationOpen()) {
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

    public function assertOpen(FestEventItem $item): void
    {
        abort_if(! ($item->is_enabled ?? true), 422, 'This item is not open for registration.');

        $event = $item->event ?? $item->event()->first();
        abort_if(! $event || ! $event->isRegistrationOpen(), 422, 'Registration is closed for this event.');

        if ($this->windows->isRegistrationOpen($item)) {
            return;
        }

        $start = $this->windows->effectiveRegStart($item)?->format('j M Y');
        $end = $this->windows->effectiveRegEnd($item)?->format('j M Y');
        $detail = ($start || $end) ? " Registration window: {$start} – {$end}." : '';

        abort(422, 'Registration is closed for this item.'.$detail);
    }
}
