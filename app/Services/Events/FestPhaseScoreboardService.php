<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;

/**
 * §7.3a of docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md ("Cumulative overall result
 * across phases", added/clarified 2026-08-15).
 *
 * WHY THIS ISN'T FestPartitionService::combinedScoreboard():
 * combinedScoreboard() sums a school's points across sibling *region-partition*
 * FestEvent children for ONE phase (or one non-phased hub) — e.g. Tirur +
 * Manjeri region children of the same Off Stage conduct, combined into that
 * conduct's own standing. This service sums a school's points across *phases*
 * of the same event instead — e.g. Digi Fest (phase 1) + Off Stage (phase 2) +
 * Sargadhara (phase 3) + Common items (phase 4) — including phases that were
 * never region-partitioned at all. Region (combinedScoreboard) and phase (this
 * class) are two independent aggregation axes that both happen to look like
 * "sum some scoreboards into one ranked total"; this class is the phase axis,
 * and it reuses FestPartitionService::aggregateScoreboardAcrossPartitions() —
 * the exact accumulation/ranking loop combinedScoreboard() itself uses — for
 * its own regional-phase case, rather than duplicating that logic.
 */
class FestPhaseScoreboardService
{
    public function __construct(
        private FestPartitionService $partitions,
    ) {}

    /**
     * True when this event has any FestEventPhase rows at all — i.e. phase mode is
     * in use. Non-phased events (the overwhelming majority today) must see zero
     * behavior change; every public call site should gate on this first.
     */
    public function usesPhases(FestEvent $hub): bool
    {
        return FestEventPhase::where('event_id', $hub->id)->exists();
    }

    /**
     * Per-phase scoreboard (§7.3a bullet 1): a school's points for items where
     * item.phase_id = $phase->id.
     *
     * - Non-regional phase: computed directly from the hub event's own
     *   items/marks via EventContext::scoreboardByPhase().
     * - Regional phase (FestEventPhase::region_partition_group set — §7.3 item 2,
     *   built in the parallel Phase F/G work): summed across that phase's
     *   region-partition children instead, reusing
     *   FestPartitionService::aggregateScoreboardAcrossPartitions() with each
     *   partition's *phase-filtered* scoreboard (not its whole-event scoreboard —
     *   a region child can carry items from more than one regional phase, so we
     *   still filter by item.phase_id inside each partition).
     *
     * region_partition_group doesn't exist on FestEventPhase yet as of this
     * writing (2026-08-15) — it ships with the parallel Phase F/G migration. Until
     * then, accessing it here safely returns null (Eloquent returns null for an
     * attribute with no backing column) and every phase is treated as
     * non-regional, which is the correct degrade: no region-partition children
     * exist yet either, so summing the hub directly is already the right answer.
     *
     * @return list<array{school_id: string, school_name: string, total_points: int, rank: int}>
     */
    public function phaseScoreboard(FestEventPhase $phase): array
    {
        $sourcePhase = $phase->sourcePhase ?: $phase;
        $hub = ($sourcePhase->event ?: FestEvent::find($sourcePhase->event_id))?->rootEvent();
        if (! $hub) {
            return [];
        }

        $phaseLeaves = FestEvent::where('parent_event_id', $hub->id)
            ->where('source_phase_id', $sourcePhase->id)
            ->when($sourcePhase->isRegional(), function ($query) use ($sourcePhase) {
                $query->whereIn('region_id', $sourcePhase->allowedRegions()
                    ->where('enabled', true)
                    ->select('region_id'));
            })
            ->get();

        if ($phaseLeaves->isNotEmpty()) {
            return $this->partitions->aggregateScoreboardAcrossPartitions(
                $phaseLeaves,
                function (FestEvent $leaf) use ($sourcePhase) {
                    $childPhaseId = FestEventPhase::where('event_id', $leaf->id)
                        ->where('source_phase_id', $sourcePhase->id)
                        ->value('id');

                    return $childPhaseId
                        ? EventContext::for($leaf)->scoreboardByPhase((int) $childPhaseId)
                        : [];
                }
            );
        }

        return EventContext::for($hub)->scoreboardByPhase($sourcePhase->id);
    }

    /** @return list<array{school_id: string, school_name: string, total_points: int, rank: int}> */
    public function phaseScoreboardForRegion(FestEventPhase $phase, int $regionId): array
    {
        $source = $phase->sourcePhase ?: $phase;
        $hub = $source->event?->rootEvent();
        if (! $hub || ! $source->isRegional()) {
            return [];
        }

        $leaf = FestEvent::where('parent_event_id', $hub->id)
            ->where('source_phase_id', $source->id)
            ->where('region_id', $regionId)
            ->first();
        if (! $leaf) {
            return [];
        }

        $childPhaseId = FestEventPhase::where('event_id', $leaf->id)
            ->where('source_phase_id', $source->id)
            ->value('id');

        return $childPhaseId ? EventContext::for($leaf)->scoreboardByPhase((int) $childPhaseId) : [];
    }

    /**
     * Cumulative overall (§7.3a bullet 2): for each school, the sum of per-phase
     * totals across every phase whose results_published is true so far —
     * revealed progressively as each phase publishes (phase 1 only, then
     * +phase 2, then +phase 3, ...), not held back until every phase is done.
     *
     * Deliberately a LIVE query, not a stored/cached total. A phased event
     * realistically involves a handful of phases (2-4 per §7 of the plan) and,
     * at most, the low hundreds of schools in one Sahodaya — summing a handful
     * of phaseScoreboard() calls per page view is cheap, and a live read stays
     * correct automatically if a phase's results are corrected and republished
     * before the next phase runs (no cache-invalidation bookkeeping needed for
     * that case, called out as an open question in §8 of the plan doc). Add a
     * cached column later only if this is ever shown to be a measured hot path.
     *
     * @return list<array{school_id: string, school_name: string, total_points: int, rank: int}>
     */
    public function cumulativeOverall(FestEvent $hub): array
    {
        $publishedPhases = FestEventPhase::where('event_id', $hub->id)
            ->where('results_published', true)
            ->orderBy('sort_order')
            ->get();

        if ($publishedPhases->isEmpty()) {
            return [];
        }

        $totals = [];
        foreach ($publishedPhases as $phase) {
            foreach ($this->phaseScoreboard($phase) as $row) {
                $sid = $row['school_id'];
                $totals[$sid] = ($totals[$sid] ?? 0) + (int) $row['total_points'];
            }
        }

        return $this->partitions->rankSchoolTotals($totals);
    }

    /**
     * Running overall with auditable per-phase contribution columns.
     *
     * @return list<array{school_id: string, school_name: string, phase_points: array<int, int>, total_points: int, rank: int}>
     */
    public function cumulativeOverallWithContributions(FestEvent $hub): array
    {
        $published = FestEventPhase::where('event_id', $hub->rootEvent()->id)
            ->where('results_published', true)
            ->orderBy('sort_order')
            ->get();
        $rows = [];

        foreach ($published as $phase) {
            foreach ($this->phaseScoreboard($phase) as $score) {
                $schoolId = $score['school_id'];
                $rows[$schoolId] ??= [
                    'school_id' => $schoolId,
                    'school_name' => $score['school_name'],
                    'phase_points' => [],
                    'total_points' => 0,
                ];
                $points = (int) $score['total_points'];
                $rows[$schoolId]['phase_points'][$phase->id] = $points;
                $rows[$schoolId]['total_points'] += $points;
            }
        }

        $ranked = $this->partitions->rankSchoolTotals(
            collect($rows)->mapWithKeys(fn (array $row) => [$row['school_id'] => $row['total_points']])->all()
        );
        $rankBySchool = collect($ranked)->keyBy('school_id');

        return collect($rows)
            ->map(function (array $row) use ($rankBySchool) {
                $row['rank'] = (int) ($rankBySchool->get($row['school_id'])['rank'] ?? 0);

                return $row;
            })
            ->sortBy('rank')
            ->values()
            ->all();
    }

    /**
     * Per-phase status + board, for a public page to show "which phases have
     * contributed to the cumulative total so far" alongside the total itself.
     * Unpublished phases are listed (so the page can say "Sargadhara — not yet
     * published") but their board is withheld, matching every other public
     * results_published gate in this codebase.
     *
     * @return list<array{phase_id: int, name: string, code: ?string, results_published: bool, board: list<array{school_id: string, school_name: string, total_points: int, rank: int}>}>
     */
    public function phaseBreakdown(FestEvent $hub): array
    {
        return FestEventPhase::where('event_id', $hub->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (FestEventPhase $phase) => [
                'phase_id' => $phase->id,
                'name' => $phase->name,
                'code' => $phase->code,
                'results_published' => (bool) $phase->results_published,
                'board' => $phase->results_published ? $this->phaseScoreboard($phase) : [],
                'regions' => $phase->isRegional()
                    ? $phase->allowedRegions()->where('enabled', true)->with('region')->get()->map(fn ($allowed) => [
                        'region_id' => $allowed->region_id,
                        'region_name' => $allowed->region?->name,
                        'board' => $phase->results_published
                            ? $this->phaseScoreboardForRegion($phase, $allowed->region_id)
                            : [],
                    ])->values()->all()
                    : [],
            ])
            ->values()
            ->all();
    }
}
