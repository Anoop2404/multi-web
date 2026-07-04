<?php

namespace App\Services\Events;

use App\Models\FestEventItem;
use Illuminate\Support\Carbon;

class FestItemRegistrationGate
{
    public function isOpen(FestEventItem $item): bool
    {
        $event = $item->event ?? $item->event()->first();
        if (! $event || ! $event->isRegistrationOpen()) {
            return false;
        }

        $today = now()->startOfDay();

        if ($item->reg_start && $today->lt(Carbon::parse($item->reg_start)->startOfDay())) {
            return false;
        }

        if ($item->reg_end && $today->gt(Carbon::parse($item->reg_end)->startOfDay())) {
            return false;
        }

        return true;
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
        abort_if(! $this->isOpen($item), 422, 'Registration is closed for this item.');
    }
}
