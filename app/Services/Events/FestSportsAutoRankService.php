<?php

namespace App\Services\Events;

use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use Illuminate\Validation\ValidationException;

class FestSportsAutoRankService
{
    /** @return array{ranked: int, item_title: string} */
    public function rankItem(FestEvent $event, FestEventItem $item): array
    {
        abort_unless($event->event_type === 'sports', 422, 'Auto-rank applies to sports events only.');
        abort_if($item->event_id !== $event->id, 404);

        $absentParticipantIds = FestAttendance::query()
            ->where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->where('status', 'absent')
            ->pluck('participant_id');

        $marks = FestMark::where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->whereNotNull('measurement_value')
            ->where('measurement_value', '!=', '')
            ->when($absentParticipantIds->isNotEmpty(), fn ($q) => $q->whereNotIn('participant_id', $absentParticipantIds))
            ->get();

        if ($marks->isEmpty()) {
            throw ValidationException::withMessages([
                'measurement' => 'Enter measurement values before auto-ranking this item.',
            ]);
        }

        $lowerIsBetter = $this->lowerIsBetter($item);

        $sorted = $marks->sort(function ($a, $b) use ($lowerIsBetter) {
            $va = $this->parseNumeric((string) $a->measurement_value);
            $vb = $this->parseNumeric((string) $b->measurement_value);
            if ($va === null && $vb === null) {
                return 0;
            }
            if ($va === null) {
                return 1;
            }
            if ($vb === null) {
                return -1;
            }

            return $lowerIsBetter ? ($va <=> $vb) : ($vb <=> $va);
        })->values();

        $position = 0;
        $lastValue = null;
        $lastAssigned = 0;
        $rankPointService = app(FestRankPointService::class);
        $isGroup = in_array($item->participant_type, ['group', 'team'], true);

        foreach ($sorted as $mark) {
            $value = $this->parseNumeric((string) $mark->measurement_value);
            if ($value === null) {
                continue;
            }

            if ($lastValue === null || abs($value - $lastValue) > 0.000001) {
                $position++;
                $lastAssigned = $position;
                $lastValue = $value;
            }

            $points = $rankPointService->pointsForRank($event, $lastAssigned, $isGroup);

            $mark->update([
                'position' => $lastAssigned,
                'score'    => $points > 0 ? $points : null,
            ]);
        }

        EventContext::for($event)->recalculateSchoolPoints();

        return [
            'ranked'     => $sorted->count(),
            'item_title' => $item->title,
        ];
    }

    private function lowerIsBetter(FestEventItem $item): bool
    {
        if ($item->ranking_direction === 'asc') {
            return true;
        }

        if ($item->ranking_direction === 'desc') {
            return false;
        }

        $section = strtolower((string) ($item->section ?? ''));
        $title = strtolower((string) $item->title);

        if (str_contains($section, 'field') || str_contains($title, 'jump') || str_contains($title, 'throw')) {
            return false;
        }

        return true;
    }

    private function parseNumeric(string $value): ?float
    {
        $clean = preg_replace('/[^0-9.]/', '', $value);
        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }
}
