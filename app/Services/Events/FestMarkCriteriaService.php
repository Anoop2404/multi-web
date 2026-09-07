<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMarkCriterion;
use App\Models\FestMarkCriterionScore;
use App\Models\FestMarkJudgeScore;
use App\Models\FestScoringRubricTemplate;
use App\Models\FestScoringRubricTemplateCriterion;
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

    /**
     * Batched counterpart to criteriaForItem() for rendering many items at once
     * (e.g. a whole event's mark-entry sheets) without one query per item.
     *
     * @param Collection<int, FestEventItem> $items
     * @return Collection<int, Collection<int, FestMarkCriterion>> item_id => criteria
     */
    public function criteriaForItems(Collection $items): Collection
    {
        return FestMarkCriterion::whereIn('item_id', $items->pluck('id'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('item_id');
    }

    /** @param array<int, array{label: string, max_score: float|int|null}> $rows */
    public function saveCriteria(FestEvent $event, FestEventItem $item, array $rows): Collection
    {
        $keepIds = [];

        foreach ($rows as $i => $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $maxScore = (float) ($row['max_score'] ?? 10);

            if ($label === '') {
                $label = 'Criterion ' . ($i + 1);
            }

            $criterion = FestMarkCriterion::updateOrCreate(
                ['id' => $row['id'] ?? null, 'item_id' => $item->id],
                [
                    'event_id'   => $event->id,
                    'item_id'    => $item->id,
                    'label'      => $label,
                    'max_score'  => $maxScore > 0 ? $maxScore : 10,
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
     * Replace an item's scoring criteria and judge count with a copy of another
     * item's — for items in the same event that share an identical rubric, so an
     * admin doesn't have to re-type the same columns for every one of them.
     */
    public function copyCriteriaFromItem(FestEvent $event, FestEventItem $sourceItem, FestEventItem $targetItem): Collection
    {
        $rows = $this->criteriaForItem($sourceItem)
            ->map(fn (FestMarkCriterion $c) => ['label' => $c->label, 'max_score' => $c->max_score])
            ->values()
            ->all();

        $criteria = $this->saveCriteria($event, $targetItem, $rows);
        $this->setJudgeCount($targetItem, $this->judgeCountForItem($sourceItem));

        return $criteria;
    }

    /**
     * Replace an item's scoring criteria with a copy of a named, reusable
     * FestScoringRubricTemplate's criteria — same replace-and-recreate shape as
     * copyCriteriaFromItem(), just sourcing rows from a template instead of another item.
     * Deliberately does not touch judge count — a template is a set of scoring columns,
     * not a judge-panel-size preset.
     */
    public function applyTemplateToItem(FestEvent $event, FestScoringRubricTemplate $template, FestEventItem $targetItem): Collection
    {
        $rows = $template->criteria
            ->map(fn (FestScoringRubricTemplateCriterion $c) => ['label' => $c->label, 'max_score' => $c->max_score])
            ->values()
            ->all();

        return $this->saveCriteria($event, $targetItem, $rows);
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

    /**
     * Apply one rubric template to many items in a single pass — the bulk counterpart to
     * applyTemplateToItem(), for configuring a whole event's judging sheets (e.g. every
     * Kalotsav item) in one action instead of opening each item individually. Criteria and
     * total marks are both common across an item's whole family (hub/phases/regions), so
     * this always propagates both to matching items elsewhere in the family — judge count is
     * the one field left untouched, since (unlike criteria/total marks) it may differ per
     * region.
     *
     * @param  list<int>  $itemIds  ids of FestEventItem rows belonging to $event
     * @return array{applied: int, synced: int}
     */
    public function applyTemplateToItems(FestEvent $event, FestScoringRubricTemplate $template, array $itemIds): array
    {
        $items = FestEventItem::where('event_id', $event->id)->whereIn('id', $itemIds)->get();

        $applied = 0;
        $synced = 0;

        foreach ($items as $item) {
            $this->applyTemplateToItem($event, $template, $item);
            $applied++;
            $synced += $this->syncCriteriaToChildEvents($event, $item);
        }

        return ['applied' => $applied, 'synced' => $synced];
    }

    /**
     * Total marks is common across an item's whole family too, just like criteria — set it
     * here and it's mirrored to every matching item elsewhere in the family (same "item_code,
     * else title+class_group" matching as syncCriteriaToChildEvents()). Use this when only
     * Total Marks changes, without re-applying the whole judging sheet. Judge count is
     * deliberately never touched here — it's the one field allowed to differ per region.
     */
    public function syncTotalMarksToFamily(FestEvent $event, FestEventItem $item, ?float $totalMarks): int
    {
        $item->update(['total_marks' => $totalMarks]);

        $synced = 0;
        foreach ($this->matchingFamilyItems($event, $item) as $targetItem) {
            $targetItem->update(['total_marks' => $totalMarks]);
            $synced++;
        }

        return $synced;
    }

    /**
     * Every item elsewhere in $sourceItem's event family (hub, every phase, every region)
     * that represents "the same item" — matched by item_code, else by title+class_group —
     * the shared matching rule behind both criteria/judge-count sync and rubric propagation.
     *
     * @return Collection<int, FestEventItem>
     */
    private function matchingFamilyItems(FestEvent $event, FestEventItem $sourceItem): Collection
    {
        $rootEventId = $event->root_event_id ?: ($event->parent_event_id ?: $event->id);
        $rootEvent = FestEvent::find($rootEventId) ?? $event;
        $eventIds = $rootEvent->reportableEventIds();

        if (empty($eventIds)) {
            return collect();
        }

        return FestEventItem::whereIn('event_id', $eventIds)
            ->where('id', '!=', $sourceItem->id)
            ->where(function ($q) use ($sourceItem) {
                if (! empty($sourceItem->item_code)) {
                    $q->where('item_code', $sourceItem->item_code)
                      ->orWhere(function ($q2) use ($sourceItem) {
                          $q2->where('title', $sourceItem->title)
                             ->where('class_group', $sourceItem->class_group);
                      });
                } else {
                    $q->where('title', $sourceItem->title)
                      ->where('class_group', $sourceItem->class_group);
                }
            })
            ->with('event')
            ->get();
    }

    /**
     * Propagate an item's criteria and total marks to matching items across all related
     * child/hub events (e.g. phases/regions) — both are common across an item's whole
     * family. Judge count is deliberately never propagated here (unlike copyCriteriaFromItem(),
     * which this does NOT call) — it's the one field allowed to differ per region, so each
     * region's own judge count is left exactly as it was.
     */
    public function syncCriteriaToChildEvents(FestEvent $event, FestEventItem $sourceItem): int
    {
        $rows = $this->criteriaForItem($sourceItem)
            ->map(fn (FestMarkCriterion $c) => ['label' => $c->label, 'max_score' => $c->max_score])
            ->values()
            ->all();

        $syncedCount = 0;

        foreach ($this->matchingFamilyItems($event, $sourceItem) as $targetItem) {
            if (! $targetItem->event) {
                continue;
            }

            $this->saveCriteria($targetItem->event, $targetItem, $rows);
            $targetItem->update(['total_marks' => $sourceItem->total_marks]);
            $syncedCount++;
        }

        return $syncedCount;
    }
}
