<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestTrophy;
use App\Services\Events\EventContext;
use App\Services\Events\FestIndividualChampionshipService;
use App\Services\Events\FestTrophyService;
use App\Support\FestClassGroupScheme;
use App\Support\FestPageActivity;
use Illuminate\Http\JsonResponse;
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

        $leaderboard = $championship->leaderboardForEvent($event);
        $championsSummary = $championship->championsSummary($event, false, $leaderboard);

        return $this->inertia('Sahodaya/Events/Championship', $this->withEventActivity($event, FestPageActivity::CHAMPIONSHIP, [
            'event'                      => $event,
            'leaderboard'                => $leaderboard,
            'championsSummary'           => $championsSummary,
            'championshipConfig'         => $championship->getConfig($event),
            'categoryOptions'            => collect($categoryLabels)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'championshipCategoryLabels' => FestClassGroupScheme::canonicalLabels(null, $root),
            'categoryMergeGroups'        => $this->mergeGroupsForDisplay($categoryMap),
            'excludedOverallCategories'  => \App\Support\FestOverallCategoryExclusion::excluded($root),
            'usesPhases'                 => $usesPhases,
            'cumulativeLeaderboard'      => $usesPhases ? $championship->crossPhaseStanding($root) : [],
        ]));
    }

    /**
     * Updates individual championship builder rules (titles, max counting items, solo/group, first place req).
     */
    public function updateConfig(Request $request, string $tenantId, FestEvent $event, FestIndividualChampionshipService $championship)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'male_title'               => 'required|string|max:50',
            'female_title'             => 'required|string|max:50',
            'runner_up_title'          => 'required|string|max:50',
            'max_counting_items'       => 'nullable|integer|min:0|max:50',
            'multi_person_mode'        => 'required|string|in:tie_break_only,include_weighted,exclude',
            'group_weight_percent'     => 'nullable|integer|min:1|max:100',
            'must_have_first_place'    => 'boolean',
            'minimum_points'           => 'nullable|integer|min:0|max:999',
            'excluded_item_categories' => 'nullable|array',
            'excluded_item_categories.*' => 'string',
            'disabled'                 => 'nullable|boolean',
            'group_by_gender'          => 'nullable|boolean',
            'excluded_individual_categories' => 'nullable|array',
            'excluded_individual_categories.*' => 'string|in:lp,up,hs,hss,open',
        ]);

        $root = $event->rootEvent();
        $config = $root->aggregation_config ?? [];
        $config['individual_championship_config'] = [
            'male_title'               => $data['male_title'],
            'female_title'             => $data['female_title'],
            'runner_up_title'          => $data['runner_up_title'],
            'disabled'                 => (bool) ($data['disabled'] ?? false),
            'max_counting_items'       => (int) ($data['max_counting_items'] ?? 0),
            'multi_person_mode'        => $data['multi_person_mode'],
            'group_weight_percent'     => (int) ($data['group_weight_percent'] ?? 100),
            'must_have_first_place'    => (bool) ($data['must_have_first_place'] ?? false),
            'minimum_points'           => (int) ($data['minimum_points'] ?? 0),
            'excluded_item_categories' => array_values(array_unique($data['excluded_item_categories'] ?? [])),
            'group_by_gender'          => (bool) ($data['group_by_gender'] ?? true),
            'excluded_individual_categories' => array_values(array_unique($data['excluded_individual_categories'] ?? [])),
        ];

        $root->update(['aggregation_config' => $config]);

        return back()->with('success', 'Championship builder settings saved.');
    }

    /**
     * JSON endpoint for student's item-by-item scoring breakdown modal.
     */
    public function studentBreakdown(Request $request, string $tenantId, FestEvent $event, int $studentId, FestIndividualChampionshipService $championship): JsonResponse
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $breakdown = $championship->studentItemBreakdown($event, $studentId);

        return response()->json($breakdown);
    }

    /**
     * 1-click sync from individual champions into the event's Trophy Distribution list.
     */
    public function syncToTrophyTemplate(Request $request, string $tenantId, FestEvent $event, FestIndividualChampionshipService $championship, FestTrophyService $trophyService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $summary = $championship->championsSummary($event);
        $cfg = $summary['config'];

        $existingMaxNo = (int) FestTrophy::where('event_id', $event->id)->max('trophy_no');
        $added = 0;

        foreach ($summary['category_champions'] as $cat) {
            $catKey = $cat['category'];
            $catLabel = $cat['category_label'];

            // Boy Champion Trophy
            $boyTrophy = FestTrophy::where('event_id', $event->id)
                ->where('trophy_type', FestTrophy::TYPE_INDIVIDUAL_CHAMPIONSHIP)
                ->where('category_key', $catKey)
                ->where('gender', 'male')
                ->where('position', 1)
                ->first();

            if (! $boyTrophy) {
                $existingMaxNo++;
                FestTrophy::create([
                    'event_id' => $event->id,
                    'trophy_no' => $existingMaxNo,
                    'title' => "{$cfg['male_title']} — {$catLabel} (Boys)",
                    'trophy_type' => FestTrophy::TYPE_INDIVIDUAL_CHAMPIONSHIP,
                    'position' => 1,
                    'award_type' => FestTrophy::AWARD_INDIVIDUAL,
                    'category_key' => $catKey,
                    'gender' => 'male',
                    'sort_order' => $existingMaxNo,
                    'is_active' => true,
                ]);
                $added++;
            }

            // Girl Champion Trophy
            $girlTrophy = FestTrophy::where('event_id', $event->id)
                ->where('trophy_type', FestTrophy::TYPE_INDIVIDUAL_CHAMPIONSHIP)
                ->where('category_key', $catKey)
                ->where('gender', 'female')
                ->where('position', 1)
                ->first();

            if (! $girlTrophy) {
                $existingMaxNo++;
                FestTrophy::create([
                    'event_id' => $event->id,
                    'trophy_no' => $existingMaxNo,
                    'title' => "{$cfg['female_title']} — {$catLabel} (Girls)",
                    'trophy_type' => FestTrophy::TYPE_INDIVIDUAL_CHAMPIONSHIP,
                    'position' => 1,
                    'award_type' => FestTrophy::AWARD_INDIVIDUAL,
                    'category_key' => $catKey,
                    'gender' => 'female',
                    'sort_order' => $existingMaxNo,
                    'is_active' => true,
                ]);
                $added++;
            }
        }

        return back()->with('success', "Individual championship trophies synced ({$added} new trophies added to template).");
    }

    /**
     * Groups scoring categories.
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
     * Leaves one or more categories out of overall school total.
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
