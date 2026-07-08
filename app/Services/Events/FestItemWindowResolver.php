<?php

namespace App\Services\Events;

use App\Models\FestEventItem;
use App\Models\FestItemHead;
use Illuminate\Support\Carbon;

class FestItemWindowResolver
{
    public function effectiveRegStart(FestEventItem $item): ?Carbon
    {
        return $this->firstDate($item->reg_start, $this->head($item)?->reg_start);
    }

    public function effectiveRegEnd(FestEventItem $item): ?Carbon
    {
        return $this->firstDate($item->reg_end, $this->head($item)?->reg_end);
    }

    public function effectiveCompetitionStart(FestEventItem $item): ?Carbon
    {
        return $this->firstDate($item->competition_start, $this->head($item)?->competition_start);
    }

    public function effectiveCompetitionEnd(FestEventItem $item): ?Carbon
    {
        return $this->firstDate($item->competition_end, $this->head($item)?->competition_end);
    }

    /** Effective time-of-day ('HH:MM') for the item's competition, if set. */
    public function effectiveCompetitionTime(FestEventItem $item): ?string
    {
        $raw = $item->competition_time ?: $this->head($item)?->competition_time;

        return $raw ? substr((string) $raw, 0, 5) : null;
    }

    public function isRegistrationOpen(FestEventItem $item): bool
    {
        $today = now()->startOfDay();
        $start = $this->effectiveRegStart($item);
        $end = $this->effectiveRegEnd($item);

        if ($start && $today->lt($start->startOfDay())) {
            return false;
        }

        if ($end && $today->gt($end->startOfDay())) {
            return false;
        }

        return true;
    }

    public function competitionLine(FestEventItem $item): ?string
    {
        $start = $this->effectiveCompetitionStart($item);
        $end = $this->effectiveCompetitionEnd($item);
        $time = $this->effectiveCompetitionTime($item);

        if (! $start && ! $end) {
            return $time ? $this->formatTime($time) : null;
        }

        if ($start && $end && ! $start->isSameDay($end)) {
            return $start->format('d M').' – '.$end->format('d M Y');
        }

        $day = $start ? $start->format('d M Y') : $end->format('d M Y');

        return $time ? $day.', '.$this->formatTime($time) : $day;
    }

    /** '14:30' → '2:30 PM'. */
    private function formatTime(string $time): string
    {
        try {
            return Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('g:i A');
        } catch (\Throwable) {
            return $time;
        }
    }

    private function head(FestEventItem $item): ?FestItemHead
    {
        if ($item->relationLoaded('head')) {
            return $item->head;
        }

        if (! $item->head_id) {
            return null;
        }

        return $item->head()->first(['id', 'name', 'reg_start', 'reg_end', 'competition_start', 'competition_end', 'schedule_mode', 'competition_time']);
    }

    private function firstDate(mixed $primary, mixed $fallback): ?Carbon
    {
        if ($primary) {
            return Carbon::parse($primary)->startOfDay();
        }

        if ($fallback) {
            return Carbon::parse($fallback)->startOfDay();
        }

        return null;
    }
}
