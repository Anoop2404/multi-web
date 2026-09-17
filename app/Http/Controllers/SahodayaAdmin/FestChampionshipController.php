<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestClassGroupScheme;
use App\Support\FestPageActivity;
use App\Support\FestTeamSquadRules;
use App\Models\FestEvent;
use App\Models\FestIndividualChampionshipPoint;
use App\Models\FestMark;
use App\Services\Events\EventContext;
use App\Services\Events\FestGradePointService;
use App\Services\Events\FestIndividualChampionshipService;
use Illuminate\Http\Request;

class FestChampionshipController extends SahodayaAdminController
{
    /** The only categories fest_individual_championship_points.category may ever hold (native DB enum) — a merge target outside this set is silently ignored rather than raising a constraint violation. */
    private const INDIVIDUAL_CATEGORY_KEYS = ['lp', 'up', 'hs', 'hss', 'open'];

    public function index(string $tenantId, FestEvent $event, FestIndividualChampionshipService $championship)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $root = $event->rootEvent();
        $categoryLabels = FestClassGroupScheme::labels(null, $root);
        $categoryMap = $this->categoryMergeMap($root);
        $usesPhases = $root->usesPhasedRegionalBilling();

        return $this->inertia('Sahodaya/Events/Championship', $this->withEventActivity($event, FestPageActivity::CHAMPIONSHIP, [
            'event'       => $event,
            'leaderboard' => $championship->leaderboardForEvent($event),
            'categoryOptions' => collect($categoryLabels)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'categoryMergeGroups' => $this->mergeGroupsForDisplay($categoryMap),
            'excludedOverallCategories' => \App\Support\FestOverallCategoryExclusion::excluded($root),
            'usesPhases' => $usesPhases,
            'cumulativeLeaderboard' => $usesPhases ? $championship->crossPhaseStanding($root) : [],
        ]));
    }

    /**
     * Groups any set of scoring categories (class/age brackets — e.g. a Sahodaya's own
     * "Category 3" and "Open") so they're tallied together as one bucket instead of
     * separately, wherever the admin feels two categories should really compete as one
     * (a small Sahodaya with too few Open-category entries to matter on their own, say).
     * Reuses FestCumulativeChampionshipService's existing (previously unexposed)
     * aggregation_config.championship_category_map reader — this is the first UI to write
     * it, and now the individual championship's recalculate() below reads the same map.
     */
    public function updateCategoryMerge(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'groups' => 'nullable|array',
            'groups.*.target' => 'required|string',
            'groups.*.sources' => 'required|array|min:1',
            'groups.*.sources.*' => 'string',
        ]);

        $map = [];
        $claimed = [];
        foreach ($data['groups'] ?? [] as $group) {
            foreach ($group['sources'] as $source) {
                if ($source === $group['target']) {
                    continue;
                }
                abort_if(isset($claimed[$source]), 422, "\"{$source}\" can't be merged into more than one target category.");
                $claimed[$source] = true;
                $map[$source] = $group['target'];
            }
        }

        $root = $event->rootEvent();
        $config = $root->aggregation_config ?? [];
        if ($map === []) {
            unset($config['championship_category_map']);
        } else {
            $config['championship_category_map'] = $map;
        }
        $root->update(['aggregation_config' => $config]);

        return back()->with('success', 'Category merge rules saved.');
    }

    /**
     * Leaves one or more categories' points out of the combined "All Categories"
     * school scoreboard total (EventContext::recalculateSchoolPoints(),
     * PublicFestScoreboardService::provisionalScoreboard()) — that category's own
     * scoreboard tab is unaffected, only the combined total. Recalculates immediately
     * so the change is visible without waiting on the next mark save.
     */
    public function updateExcludedOverallCategories(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'categories' => 'nullable|array',
            'categories.*' => 'string',
        ]);

        $root = $event->rootEvent();
        $config = $root->aggregation_config ?? [];
        $categories = array_values(array_unique($data['categories'] ?? []));
        if ($categories === []) {
            unset($config['excluded_overall_categories']);
        } else {
            $config['excluded_overall_categories'] = $categories;
        }
        $root->update(['aggregation_config' => $config]);

        EventContext::for($event)->recalculateSchoolPoints();

        return back()->with('success', 'Overall scoreboard exclusions saved.');
    }

    /** @return array<string, string> */
    private function categoryMergeMap(FestEvent $root): array
    {
        return \App\Support\FestCategoryMerge::map($root);
    }

    /** @return list<array{target: string, sources: list<string>}> */
    private function mergeGroupsForDisplay(array $map): array
    {
        $groups = [];
        foreach ($map as $source => $target) {
            $groups[$target][] = $source;
        }

        return collect($groups)->map(fn ($sources, $target) => ['target' => $target, 'sources' => $sources])->values()->all();
    }

    public function recalculate(string $tenantId, FestEvent $event, FestGradePointService $gradePointService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $categoryMap = $this->categoryMergeMap($event->rootEvent());
        $aggregated = [];

        FestMark::where('event_id', $event->id)
            ->with(['participant.student', 'participant.registration.item'])
            ->each(function (FestMark $mark) use ($event, $gradePointService, $categoryMap, &$aggregated) {
                $student = $mark->participant?->student;
                if (! $student) {
                    return;
                }

                $item = $mark->participant->registration?->item;
                $points = $gradePointService->pointsForMark($event, $mark);
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
                // mapped target is itself one of the five allowed values; the DB enum
                // would otherwise reject it.
                $merged = $categoryMap[$rawClassGroup] ?? $categoryMap[$canonicalCategory] ?? $canonicalCategory;
                $category = in_array($merged, self::INDIVIDUAL_CATEGORY_KEYS, true) ? $merged : $canonicalCategory;
                // Individual championship is always shown split Boys/Girls — there is no
                // correct way to guess which bucket a student with gender 'other' or no
                // gender on file at all belongs in, so rather than inventing a third
                // "Open" bucket (or silently mislabeling them into one binary bucket) they
                // are simply left out of the individual championship until their profile
                // has male/female set. This does not affect the school-level scoreboard —
                // only this student-level table.
                $gender = match ($student->gender) {
                    'male'   => 'male',
                    'female' => 'female',
                    default  => null,
                };
                if ($gender === null) {
                    return;
                }

                if (! isset($aggregated[$student->id])) {
                    $aggregated[$student->id] = [
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
                // used as a tiebreak (see the sort in index() and results()), never added
                // to the primary `points` total.
                if ($item && FestTeamSquadRules::isMultiPerson($item->participant_type)) {
                    $aggregated[$student->id]['group_points'] += $points;
                } else {
                    $aggregated[$student->id]['points'] += $points;
                }
            });

        foreach ($aggregated as $studentId => $data) {
            FestIndividualChampionshipPoint::updateOrCreate(
                ['event_id' => $event->id, 'student_id' => $studentId],
                [
                    'category'     => $data['category'],
                    'gender'       => $data['gender'],
                    'points'       => $data['points'],
                    'group_points' => $data['group_points'],
                ]
            );
        }

        FestIndividualChampionshipPoint::where('event_id', $event->id)
            ->whereNotIn('student_id', array_keys($aggregated))
            ->delete();

        return back()->with('success', count($aggregated).' championship point row(s) updated.');
    }
}
