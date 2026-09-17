<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestIndividualChampionshipPoint;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Ranking/formatting for the individual (student) championship leaderboard —
 * shared between the Sahodaya admin's Championship page (FestChampionshipController)
 * and the public portal's Champions page (FestPortalController::champions()), so
 * both read the exact same category+gender ranking and cross-phase combine instead
 * of two copies drifting apart.
 */
class FestIndividualChampionshipService
{
    /** @return Collection<int, array<string, mixed>> */
    public function leaderboardForEvent(FestEvent $event): Collection
    {
        $allRows = FestIndividualChampionshipPoint::where('event_id', $event->id)
            ->with(['student'])
            ->orderByDesc('points')
            ->orderByDesc('group_points')
            ->orderBy('student_id')
            ->get();

        return $this->rankAndFormat($allRows);
    }

    /**
     * Ranked within category AND gender together (e.g. "HS Boys" vs "HS Girls" are
     * separate #1s) — grouping by category alone would let a category's "#1" silently
     * be whichever gender happened to score higher, hiding the other gender's real
     * champion behind "#2" or worse. overall_rank stays a single global ranking across
     * every category/gender combined — a reference number, not a title, so it's
     * deliberately not category/gender-scoped the way `rank` is.
     *
     * @param  Collection<int, object>  $allRows  Each row needs student_id, student
     *   (relation), category, gender, points, group_points.
     * @return Collection<int, array<string, mixed>>
     */
    public function rankAndFormat(Collection $allRows): Collection
    {
        $rankedByCategoryAndGender = $allRows->groupBy(fn ($row) => $row->category.'|'.$row->gender)
            ->flatMap(function ($groupRows) {
                return $groupRows->values()->map(fn ($row, int $index) => [$row, $index + 1]);
            });

        $overallRankByStudent = $allRows->values()->mapWithKeys(fn ($row, int $index) => [$row->student_id => $index + 1]);

        return $rankedByCategoryAndGender->map(function (array $pair) use ($overallRankByStudent) {
            [$row, $rank] = $pair;
            $school = Tenant::find($row->student?->tenant_id);

            return [
                'rank'         => $rank,
                'overall_rank' => $overallRankByStudent[$row->student_id] ?? null,
                'points'       => $row->points,
                'group_points' => $row->group_points,
                'category'     => $row->category,
                'gender'       => $row->gender,
                'student'      => [
                    'id'     => $row->student_id,
                    'name'   => $row->student?->name,
                    'reg_no' => $row->student?->reg_no,
                    'photo'  => $row->student?->photoDataUri(),
                ],
                'school' => $school?->name,
            ];
        })->sortBy([
            ['category', 'asc'],
            ['gender', 'asc'],
            ['rank', 'asc'],
        ])->values();
    }

    /**
     * A hub's individual championship needs a student's total across every phase, the
     * same "sum the isolated per-phase numbers" principle FestPhaseScoreboardService
     * applies for schools (see its class docblock) — except here there's no
     * isolated-vs-cumulative distinction to worry about at all: FestChampionshipController
     * ::recalculate() already writes one FestIndividualChampionshipPoint row per (phase
     * leaf event, student), each already isolated to that one phase, so this just sums
     * those existing rows per student across every phase leaf under the hub.
     *
     * Deliberately NOT gated on any phase-level "published" flag — recalculate() is a
     * manual, admin-triggered snapshot, so a phase simply having no rows yet (nobody has
     * clicked Recalculate for it) naturally contributes zero. The public-facing caller is
     * responsible for its own event-level publish gate before calling this at all.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function crossPhaseStanding(FestEvent $hub): Collection
    {
        return $this->sumAcrossLeaves($this->allPhaseLeafIds($hub));
    }

    /**
     * Same combine as crossPhaseStanding(), restricted to $visibleLeafIds — the public
     * portal must not let an unpublished phase's contribution leak into the combined
     * total just because a sibling phase is already published (recalculate() is a
     * manual admin action, un-gated by any publish flag, so a not-yet-public phase can
     * easily already have real rows sitting in the table). Callers decide "visible" the
     * same way FestPortalController::crossPhaseScoreboard() already does for the
     * school-level board: each leaf's own results_published (or an authorized admin
     * preview of it).
     *
     * @param  Collection<int, int>  $visibleLeafIds
     * @return Collection<int, array<string, mixed>>
     */
    public function crossPhaseStandingForVisibleLeaves(FestEvent $hub, Collection $visibleLeafIds): Collection
    {
        return $this->sumAcrossLeaves($this->allPhaseLeafIds($hub)->intersect($visibleLeafIds));
    }

    /** @return Collection<int, int> */
    private function allPhaseLeafIds(FestEvent $hub): Collection
    {
        $phaseIds = FestEventPhase::where('event_id', $hub->id)->pluck('id');
        if ($phaseIds->isEmpty()) {
            return collect();
        }

        return FestEvent::where('parent_event_id', $hub->id)
            ->whereIn('source_phase_id', $phaseIds)
            ->pluck('id');
    }

    /**
     * @param  Collection<int, int>  $leafIds
     * @return Collection<int, array<string, mixed>>
     */
    private function sumAcrossLeaves(Collection $leafIds): Collection
    {
        if ($leafIds->isEmpty()) {
            return collect();
        }

        $summed = FestIndividualChampionshipPoint::whereIn('event_id', $leafIds)
            ->with('student')
            ->get()
            ->groupBy('student_id')
            ->map(function ($studentRows) {
                $first = $studentRows->first();

                // A student's category/gender shouldn't change phase to phase (same
                // student, same age bracket, all within one academic-year event) —
                // taking the first row's is safe and avoids re-deriving it here.
                return (object) [
                    'student_id'   => $first->student_id,
                    'student'      => $first->student,
                    'category'     => $first->category,
                    'gender'       => $first->gender,
                    'points'       => $studentRows->sum('points'),
                    'group_points' => $studentRows->sum('group_points'),
                ];
            })
            ->sortBy([
                [fn ($r) => $r->points, 'desc'],
                [fn ($r) => $r->group_points, 'desc'],
                [fn ($r) => $r->student_id, 'asc'],
            ])
            ->values();

        return $this->rankAndFormat($summed);
    }
}
