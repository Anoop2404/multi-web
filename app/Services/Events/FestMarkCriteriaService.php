<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMarkCriterion;
use App\Models\FestMarkCriterionScore;
use Illuminate\Support\Collection;

/**
 * Configurable multi-column judge/criteria mark entry.
 *
 * An item can optionally define N scoring criteria (e.g. "Content", "Voice
 * modulation", "Time management" for an elocution item, or "Judge 1",
 * "Judge 2", "Judge 3" for a panel-judged item). When criteria exist for an
 * item, Mark Entry renders one input column per criterion instead of a
 * single free-form score, and the final mark is the sum of the criterion
 * scores.
 */
class FestMarkCriteriaService
{
    /** @return Collection<int, FestMarkCriterion> */
    public function criteriaForItem(FestEventItem $item): Collection
    {
        return FestMarkCriterion::where('item_id', $item->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function hasCriteria(FestEventItem $item): bool
    {
        return FestMarkCriterion::where('item_id', $item->id)->exists();
    }

    /** @param array<int, array{label: string, max_score: float|int|null}> $rows */
    public function saveCriteria(FestEvent $event, FestEventItem $item, array $rows): Collection
    {
        $keepIds = [];

        foreach ($rows as $i => $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $criterion = FestMarkCriterion::updateOrCreate(
                ['id' => $row['id'] ?? null, 'item_id' => $item->id],
                [
                    'event_id' => $event->id,
                    'item_id' => $item->id,
                    'label' => $label,
                    'max_score' => (float) ($row['max_score'] ?? 10),
                    'sort_order' => $i,
                ]
            );

            $keepIds[] = $criterion->id;
        }

        FestMarkCriterion::where('item_id', $item->id)
            ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
            ->when($keepIds === [], fn ($q) => $q)
            ->delete();

        return $this->criteriaForItem($item);
    }

    /**
     * Persist one participant's per-criterion scores and return the total
     * (sum of all criteria for this item), which becomes FestMark.score.
     *
     * @param array<int|string, mixed> $scores criterion_id => score
     */
    public function saveParticipantScores(FestEventItem $item, int $participantId, array $scores): float
    {
        $criteria = $this->criteriaForItem($item);
        $total = 0.0;

        foreach ($criteria as $criterion) {
            $raw = $scores[$criterion->id] ?? null;
            $value = ($raw === null || $raw === '') ? null : max(0, min((float) $raw, (float) $criterion->max_score));

            FestMarkCriterionScore::updateOrCreate(
                ['criterion_id' => $criterion->id, 'participant_id' => $participantId],
                ['item_id' => $item->id, 'score' => $value]
            );

            $total += (float) ($value ?? 0);
        }

        return round($total, 2);
    }

    /**
     * @return array<int, array<int, float|null>> participant_id => [criterion_id => score]
     */
    public function scoresForItem(FestEventItem $item): array
    {
        $rows = FestMarkCriterionScore::whereHas('criterion', fn ($q) => $q->where('item_id', $item->id))
            ->get(['criterion_id', 'participant_id', 'score']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->participant_id][$row->criterion_id] = $row->score === null ? null : (float) $row->score;
        }

        return $map;
    }
}
