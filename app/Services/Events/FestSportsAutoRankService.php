<?php

namespace App\Services\Events;

use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use Illuminate\Validation\ValidationException;

class FestSportsAutoRankService
{
    /**
     * Auto-rank one item's marks, dense tie-style (two tied for 1st -> next distinct
     * value is 2nd, not 3rd). Prefers measurement_value (sports track/field, where
     * "score" is otherwise unused and gets overwritten with the resulting points) when
     * present; otherwise ranks by score (a judged Grand Total) instead, in which case
     * score is left untouched since it already holds the real judged total, not a
     * placeholder — only position is written, and points continue to resolve live from
     * position/grade via FestRankPointService/FestGradePointService wherever they're
     * needed, exactly as they already do for every other mark on the platform.
     *
     * @return array{ranked: int, item_title: string}
     */
    public function rankItem(FestEvent $event, FestEventItem $item): array
    {
        abort_if($item->event_id !== $event->id, 404);

        $absentParticipantIds = FestAttendance::query()
            ->where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->where('status', 'absent')
            ->pluck('participant_id');

        $measurementMarks = FestMark::where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->whereNotNull('measurement_value')
            ->where('measurement_value', '!=', '')
            ->when($absentParticipantIds->isNotEmpty(), fn ($q) => $q->whereNotIn('participant_id', $absentParticipantIds))
            ->get();

        if ($measurementMarks->isNotEmpty()) {
            $lowerIsBetter = $this->lowerIsBetter($item);

            $sorted = $measurementMarks->sort(fn ($a, $b) => $this->compareNumeric(
                $this->parseNumeric((string) $a->measurement_value),
                $this->parseNumeric((string) $b->measurement_value),
                $lowerIsBetter,
            ))->values();

            $rankPointService = app(FestRankPointService::class);
            $participantType = $item->participant_type;

            $this->assignDenseRanks($sorted, fn ($mark) => $this->parseNumeric((string) $mark->measurement_value), function ($mark, int $rank) use ($event, $rankPointService, $participantType) {
                $points = $rankPointService->pointsForRank($event, $rank, $participantType);
                $mark->update(['position' => $rank, 'score' => $points > 0 ? $points : null]);
            });

            EventContext::for($event)->recalculateSchoolPoints();

            return ['ranked' => $sorted->count(), 'item_title' => $item->title];
        }

        $scoredMarks = FestMark::where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->whereNotNull('score')
            ->when($absentParticipantIds->isNotEmpty(), fn ($q) => $q->whereNotIn('participant_id', $absentParticipantIds))
            ->get();

        if ($scoredMarks->isEmpty()) {
            throw ValidationException::withMessages([
                'measurement' => 'Enter measurement values or judged scores before auto-ranking this item.',
            ]);
        }

        // Higher score is better for every judged Grand Total in this platform — unlike
        // measurement events, there's no "lower is better" case here (no judged item is
        // scored like a race time).
        $sorted = $scoredMarks->sort(fn ($a, $b) => $this->compareNumeric(
            (float) $a->score,
            (float) $b->score,
            lowerIsBetter: false,
        ))->values();

        $this->assignDenseRanks($sorted, fn ($mark) => (float) $mark->score, function ($mark, int $rank) {
            // Score already holds the real judged Grand Total — only position changes.
            $mark->update(['position' => $rank]);
        });

        EventContext::for($event)->recalculateSchoolPoints();

        return ['ranked' => $sorted->count(), 'item_title' => $item->title];
    }

    private function compareNumeric(?float $a, ?float $b, bool $lowerIsBetter): int
    {
        if ($a === null && $b === null) {
            return 0;
        }
        if ($a === null) {
            return 1;
        }
        if ($b === null) {
            return -1;
        }

        return $lowerIsBetter ? ($a <=> $b) : ($b <=> $a);
    }

    /**
     * Walk $sorted (best to worst) assigning dense ranks — a tie shares one rank number
     * and the next distinct value continues immediately after it (1,1,2,3 — never 1,1,3).
     * $valueOf reads the comparable numeric value off a mark; $assign receives (mark, rank)
     * for every mark with a non-null value and is expected to persist it.
     *
     * @param  \Illuminate\Support\Collection<int, FestMark>  $sorted
     * @param  callable(FestMark): ?float  $valueOf
     * @param  callable(FestMark, int): void  $assign
     */
    private function assignDenseRanks($sorted, callable $valueOf, callable $assign): void
    {
        $rank = 0;
        $lastValue = null;

        foreach ($sorted as $mark) {
            $value = $valueOf($mark);
            if ($value === null) {
                continue;
            }

            if ($lastValue === null || abs($value - $lastValue) > 0.000001) {
                $rank++;
                $lastValue = $value;
            }

            $assign($mark, $rank);
        }
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
