<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMarkCriterion;
use App\Models\FestMarkCriterionScore;
use App\Models\FestMarkJudgeScore;
use Illuminate\Support\Collection;

/**
 * Judge-panel mark entry.
 *
 * An item can define N named scoring columns (e.g. "Content", "Voice
 * modulation", "Time management") that are printed on a blank paper sheet
 * for each judge to fill in by hand, and a judge count. When judge count
 * > 1, the printed mark-entry sheet produces one such blank sheet per judge
 * plus a consolidated Sum Sheet, and online Mark Entry shows one input
 * column per judge — that judge's paper subtotal — instead of per-criterion
 * inputs. The final mark saved to FestMark.score is the sum across judges.
 */
class FestMarkCriteriaService
{
    public function judgeCountForItem(FestEventItem $item): int
    {
        return max(1, (int) ($item->mark_judge_count ?? 1));
    }

    public function setJudgeCount(FestEventItem $item, int $count): void
    {
        $item->update(['mark_judge_count' => max(1, $count)]);
    }

    public function hasJudgePanel(FestEventItem $item): bool
    {
        return $this->judgeCountForItem($item) > 1;
    }

    /**
     * @return array<int, array<int, float|null>> participant_id => [judge_number => score]
     */
    public function judgeScoresForItem(FestEventItem $item): array
    {
        $rows = FestMarkJudgeScore::where('item_id', $item->id)
            ->get(['participant_id', 'judge_number', 'score']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->participant_id][$row->judge_number] = $row->score === null ? null : (float) $row->score;
        }

        return $map;
    }

    /**
     * Persist one participant's per-judge subtotals and return the grand
     * total (sum across judges), which becomes FestMark.score.
     *
     * @param array<int|string, mixed> $scores judge_number => score
     */
    public function saveParticipantJudgeScores(FestEventItem $item, int $participantId, array $scores): float
    {
        $judgeCount = $this->judgeCountForItem($item);
        $total = 0.0;

        for ($judgeNumber = 1; $judgeNumber <= $judgeCount; $judgeNumber++) {
            $raw = $scores[$judgeNumber] ?? null;
            $value = ($raw === null || $raw === '') ? null : (float) $raw;

            FestMarkJudgeScore::updateOrCreate(
                ['item_id' => $item->id, 'participant_id' => $participantId, 'judge_number' => $judgeNumber],
                ['score' => $value]
            );

            $total += (float) ($value ?? 0);
        }

        return round($total, 2);
    }

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
