<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestMark;
use App\Models\Tenant;
use App\Support\FestCategoryMerge;
use App\Support\FestClassGroupScheme;
use App\Support\FestTeamSquadRules;
use Illuminate\Support\Collection;

/**
 * Individual (student) championship leaderboard — computed live, straight off
 * published marks, every time it's read. No "Recalculate" button or stored
 * snapshot to go stale: enter marks anywhere under the hub and every phase's
 * Championship page (and the public Results "Championship" tab, and the
 * Individual Championship export) reflects it immediately, summed automatically
 * across whichever phase leaf events actually have marks. Shared by the admin
 * Championship page (FestChampionshipController), the public portal
 * (FestPortalController::results()), and the report export
 * (FestReportService), so all three always agree.
 */
class FestIndividualChampionshipService
{
    /** The only categories this leaderboard may ever bucket a student into. */
    private const INDIVIDUAL_CATEGORY_KEYS = ['lp', 'up', 'hs', 'hss', 'open'];

    public function __construct(
        private FestGradePointService $gradePoints,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboardForEvent(FestEvent $event, bool $directPhotoUrls = false): Collection
    {
        return $this->rankAndFormat($this->pointsForEvent($event), $directPhotoUrls);
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
    public function rankAndFormat(Collection $allRows, bool $directPhotoUrls = false): Collection
    {
        // Ranking (both the category+gender rank and overall_rank below) depends on
        // row order — sort explicitly rather than trusting the caller's collection
        // order, so every caller (a single event's live points, or leaves summed
        // together) gets the same stable points/group_points/student_id tiebreak chain.
        // Primarily by individual points; a tie is broken by group points (a student
        // whose group/team results also outscore the other's ranks higher), then by
        // student_id only to keep the order fully deterministic.
        // NOTE: Collection::sortBy()'s [ [callback, direction], ... ] array form only
        // honors 'desc' for a plain string key — with a Closure key it silently sorts
        // ascending regardless of the direction given, so this uses an explicit
        // comparator instead (verified against Laravel's actual behavior, not assumed).
        $allRows = $allRows->sort(function ($a, $b) {
            return [$b->points, $b->group_points, $a->student_id]
                <=> [$a->points, $a->group_points, $b->student_id];
        })->values();

        // Dense ("1, 2, 2, 3") ranking within each category+gender group: students
        // tied on both points and group_points share the same rank, and the next
        // distinct total continues from there rather than skipping ranks for however
        // many students just tied (that "1, 2, 2, 4" skip-style would misrepresent how
        // many students are genuinely ahead of a given rank).
        $rankedByCategoryAndGender = $allRows->groupBy(fn ($row) => $row->category.'|'.$row->gender)
            ->flatMap(function ($groupRows) {
                $rank = 0;
                $previousKey = null;

                return $groupRows->values()->map(function ($row) use (&$rank, &$previousKey) {
                    $key = $row->points.'|'.$row->group_points;
                    if ($key !== $previousKey) {
                        $rank++;
                        $previousKey = $key;
                    }

                    return [$row, $rank];
                });
        });

        $overallRankByStudent = $allRows->values()->mapWithKeys(fn ($row, int $index) => [$row->student_id => $index + 1]);
        $schoolNames = Tenant::query()
            ->whereIn('id', $allRows->pluck('student.tenant_id')->filter()->unique())
            ->get(['id', 'name', 'type'])
            ->mapWithKeys(fn (Tenant $school) => [$school->id => $school->name]);

        return $rankedByCategoryAndGender->map(function (array $pair) use ($overallRankByStudent, $schoolNames, $directPhotoUrls) {
            [$row, $rank] = $pair;

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
                    'photo'  => $directPhotoUrls ? $row->student?->publicPhotoUrl() : $row->student?->photoDataUri(),
                    'photo_fallback' => $directPhotoUrls ? $row->student?->publicPhotoFallbackUrl() : null,
                ],
                'school' => $schoolNames[$row->student?->tenant_id] ?? null,
            ];
        })->sortBy([
            ['category', 'asc'],
            ['gender', 'asc'],
            ['rank', 'asc'],
        ])->values();
    }

    /**
     * A hub's individual championship is every student's total across every phase —
     * live-sums pointsForEvent() over each phase leaf under the hub, the same
     * "sum the isolated per-phase numbers" principle FestPhaseScoreboardService
     * applies for schools (see its class docblock).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function crossPhaseStanding(FestEvent $hub, bool $directPhotoUrls = false): Collection
    {
        return $this->rankAndFormat($this->sumAcrossLeaves($this->allPhaseLeaves($hub)), $directPhotoUrls);
    }

    /**
     * Same combine as crossPhaseStanding(), restricted to $visibleLeafIds — the public
     * portal must not let an unpublished phase's contribution leak into the combined
     * total just because a sibling phase is already published. Callers decide "visible"
     * the same way FestPortalController::crossPhaseScoreboard() already does for the
     * school-level board: each leaf's own results_published (or an authorized admin
     * preview of it).
     *
     * @param  Collection<int, int>  $visibleLeafIds
     * @return Collection<int, array<string, mixed>>
     */
    public function crossPhaseStandingForVisibleLeaves(FestEvent $hub, Collection $visibleLeafIds, bool $directPhotoUrls = false): Collection
    {
        $visible = $this->allPhaseLeaves($hub)->filter(fn (FestEvent $leaf) => $visibleLeafIds->contains($leaf->id));

        return $this->rankAndFormat($this->sumAcrossLeaves($visible), $directPhotoUrls);
    }

    /** @return Collection<int, FestEvent> */
    private function allPhaseLeaves(FestEvent $hub): Collection
    {
        $phaseIds = FestEventPhase::where('event_id', $hub->id)->pluck('id');
        if ($phaseIds->isEmpty()) {
            return collect();
        }

        return FestEvent::where('parent_event_id', $hub->id)
            ->whereIn('source_phase_id', $phaseIds)
            ->get();
    }

    /**
     * @param  Collection<int, FestEvent>  $leaves
     * @return Collection<int, object>
     */
    private function sumAcrossLeaves(Collection $leaves): Collection
    {
        if ($leaves->isEmpty()) {
            return collect();
        }

        return $leaves
            ->flatMap(fn (FestEvent $leaf) => $this->pointsForEvent($leaf))
            ->groupBy('student_id')
            ->map(function (Collection $studentRows) {
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
            ->values();
    }

    /**
     * Live aggregate of one event's own published marks into per-student championship
     * points — the computation FestChampionshipController::recalculate() used to run
     * on demand and cache into fest_individual_championship_points; now run fresh on
     * every read instead, so there is nothing to go stale and nothing an admin needs
     * to remember to click.
     *
     * @return Collection<int, object>
     */
    public function pointsForEvent(FestEvent $event): Collection
    {
        $categoryMap = FestCategoryMerge::map($event->rootEvent());
        $aggregated = [];

        // Only counts a mark once its own item has actually published results — same
        // rule the public scoreboard's "Latest Item Winners" widget and tv() use
        // (FestPortalController::scoreboardDynamicData()'s $winnerMarks query). A mark
        // just sitting entered-but-unpublished must not move the championship standing;
        // this is the live replacement for the old admin "Recalculate" button, so it
        // needs the same publish discipline that button's manual timing used to provide.
        FestMark::where('event_id', $event->id)
            ->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')->where('results_hidden', false))
            ->with(['item', 'participant.student', 'participant.registration.item'])
            ->each(function (FestMark $mark) use ($event, $categoryMap, &$aggregated) {
                $student = $mark->participant?->student;
                if (! $student) {
                    return;
                }

                $item = $mark->participant->registration?->item;
                $points = $this->gradePoints->pointsForMark($event, $mark);
                // fest_individual_championship_points.category is constrained to
                // lp/up/hs/hss/open — but English Fest / Kalotsav-style events store
                // class_group in a different scheme (category_1, category_2, ...).
                // canonicalKey() maps every known alias onto the constrained scheme;
                // anything it doesn't recognize falls back to 'open' rather than
                // violating the DB check constraint outright.
                $rawClassGroup = $item?->class_group ?: 'open';
                $canonicalCategory = FestClassGroupScheme::canonicalKey($rawClassGroup);
                $canonicalCategory = in_array($canonicalCategory, self::INDIVIDUAL_CATEGORY_KEYS, true) ? $canonicalCategory : 'open';
                // Admin-configured category merge (e.g. fold "Category 3" into "Open") —
                // same aggregation_config.championship_category_map the school/team
                // cumulative scoreboard already reads, and the same source keys the merge
                // settings UI offers (the event's real scheme keys, tried before falling
                // back to the canonical lp/up/hs/hss/open bucket). Only honored when the
                // mapped target is itself one of the five allowed values.
                $merged = $categoryMap[$rawClassGroup] ?? $categoryMap[$canonicalCategory] ?? $canonicalCategory;
                $category = in_array($merged, self::INDIVIDUAL_CATEGORY_KEYS, true) ? $merged : $canonicalCategory;
                // Individual championship is always shown split Boys/Girls — there is no
                // correct way to guess which bucket a student with gender 'other' or no
                // gender on file at all belongs in, so rather than inventing a third
                // "Open" bucket (or silently mislabeling them into one binary bucket) they
                // are simply left out of the individual championship until their profile
                // has male/female set. This does not affect the school-level scoreboard.
                $gender = match ($student->gender) {
                    'male'   => 'male',
                    'female' => 'female',
                    default  => null,
                };
                if ($gender === null) {
                    return;
                }

                if (! isset($aggregated[$student->id])) {
                    $aggregated[$student->id] = (object) [
                        'student_id'   => $student->id,
                        'student'      => $student,
                        'points'       => 0,
                        'group_points' => 0,
                        'category'     => $category,
                        'gender'       => $gender,
                    ];
                }

                // Pair/trio/group/team items save one FestMark row per teammate with the
                // same position/points — crediting the full value to every member's
                // individual total would let an 11-person group's 1st place outweigh a
                // genuine solo achievement. Group results are tracked separately and only
                // used as a tiebreak, never added to the primary `points` total.
                if ($item && FestTeamSquadRules::isMultiPerson($item->participant_type)) {
                    $aggregated[$student->id]->group_points += $points;
                } else {
                    $aggregated[$student->id]->points += $points;
                }
            });

        return collect(array_values($aggregated));
    }
}
