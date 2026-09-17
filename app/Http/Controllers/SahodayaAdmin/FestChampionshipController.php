<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestClassGroupScheme;
use App\Support\FestPageActivity;
use App\Models\FestEvent;
use App\Services\Events\EventContext;
use App\Services\Events\FestIndividualChampionshipService;
use Illuminate\Http\Request;

class FestChampionshipController extends SahodayaAdminController
{

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
     * it, and FestIndividualChampionshipService::pointsForEvent() reads the same map
     * live on every request.
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

}
