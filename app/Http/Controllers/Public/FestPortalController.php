<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\FestAthleticRecord;
use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRecordBreak;
use App\Models\FestRegistration;
use App\Models\FestResult;
use App\Models\FestSchedule;
use App\Models\Tenant;
use App\Services\Events\EventContext;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestCumulativeChampionshipService;
use App\Services\Events\FestGradePointService;
use App\Services\Events\FestIndividualChampionshipService;
use App\Services\Events\FestItemResultsService;
use App\Services\Events\FestNumberingService;
use App\Services\Events\FestPhaseScoreboardService;
use App\Services\Events\FestPublicVisibilityService;
use App\Services\Events\FestWinnerPosterService;
use App\Services\Events\PublicFestScoreboardService;
use App\Services\Events\PublicOperationalEventService;
use App\Support\FestCategoryMerge;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use App\Support\FestOverallCategoryExclusion;
use App\Support\FestTeamSquadRules;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FestPortalController extends Controller
{
    use RendersPublicPages;

    public function __construct(
        private FestPublicVisibilityService $visibility,
        private PublicFestScoreboardService $scoreboards,
        private FestPhaseScoreboardService $phaseScoreboards,
        private PublicOperationalEventService $operationalEvents,
        private FestCumulativeChampionshipService $cumulativeChampionship,
        private FestGradePointService $gradePoints,
        private FestIndividualChampionshipService $individualChampionship,
    ) {}

    public function index()
    {
        $tenant = $this->resolveTenant();

        $events = $this->operationalEvents->listedForTenant($tenant->id);
        $eventGroups = $this->operationalEvents->catalogueGroups($events);

        $typeLabels = collect(\App\Support\SchoolFestProgram::MAP)->keyBy('eventType')->map(fn (array $m) => $m['label']);
        $eventTypes = $events->pluck('event_type')->filter()->unique()->sort()
            ->map(fn (string $type) => ['value' => $type, 'label' => $typeLabels[$type] ?? ucfirst(str_replace('_', ' ', $type))])
            ->values();

        return $this->renderPublic('public.fest.index', $tenant, compact('events', 'eventGroups', 'eventTypes'));
    }

    public function show(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $selectedScope = $this->operationalEvents->directScope($event);
        $scopes = [$selectedScope];

        $targetEvent = FestEventItem::where('event_id', $event->id)->where('is_enabled', true)->exists()
            ? $event
            : ($event->parent_event_id ? $event->rootEvent() : $event);

        $rawItems = FestEventItem::where('event_id', $targetEvent->id)
            ->where('is_enabled', true)
            ->with(['head:id,name', 'phase:id,source_phase_id'])
            ->orderBy('display_order')
            ->orderBy('title')
            ->get(['id', 'title', 'stage_type', 'category', 'class_group', 'age_group', 'participant_type', 'head_id', 'event_id', 'results_published_at', 'results_hidden', 'phase_id']);

        $items = \App\Services\Events\FestHeadItemNavigationService::filterToOwnPhase($rawItems, $event);

        $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);
        $isResultsPublished = (bool) $selectedScope['results_published'] || $isAdminPreview;

        // Same two gates item-finder.blade.php's own item grid uses (see itemFinder()
        // above): an item's own results_published_at alone isn't enough — itemResults()
        // hard-requires the EVENT-wide publish flag too (FestItemResultsService::
        // isItemVisible()), and a published item can still have zero marks recorded
        // (published too early, or a no-show item). Without both checks here, this
        // page's own item grid rendered a normal, inviting "Results" link into a page
        // that either 403s or just says "No published results for this item."
        $resultedItemIds = FestMark::where('event_id', $targetEvent->id)
            ->whereIn('item_id', $items->pluck('id'))
            ->distinct()
            ->pluck('item_id');
        $itemResultsService = app(FestItemResultsService::class);
        $visibleResultItemIds = $items
            ->filter(fn (FestEventItem $item) => $isAdminPreview || $itemResultsService->isItemVisible($item, $event))
            ->pluck('id');

        // Items whose results are actually out (the same condition the view's own
        // "Results" button renders on, see show.blade.php) float to the top of the item
        // finder grid, so a grid mostly full of "Not yet published" cards doesn't bury
        // the handful that are actually ready. sortByDesc() is a stable sort, so within
        // each of the two groups items keep their original display_order/title ordering.
        $items = $items->sortByDesc(
            fn (FestEventItem $item) => ($visibleResultItemIds->contains($item->id) && $resultedItemIds->contains($item->id)) ? 1 : 0
        )->values();

        $itemGroups = $items->groupBy('event_id')
            ->map(fn ($groupItems) => [
                'label' => $event->title,
                'items' => $groupItems->values(),
            ])
            ->values();

        // The item finder's Schedule/Results links used to show for every item once the
        // scope-wide flag was on, regardless of whether that specific item had a schedule
        // slot or published results yet — e.g. every catalog item got an equally-weighted
        // "Results" button even ones with no marks at all. Item-level gates: a schedule
        // link only for items that actually have a FestSchedule row, a results link only
        // for items with their own results_published_at.
        $scheduledItemIds = FestSchedule::where('event_id', $event->id)
            ->whereNotNull('item_id')
            ->distinct()
            ->pluck('item_id');

        $recentResults = collect();
        $publishedItemCount = 0;

        if ($isResultsPublished) {
            $recentMarks = FestMark::where('event_id', $event->id)
                ->whereIn('position', [1, 2, 3])
                // Matches the category/toppers scoreboard convention (see the comment on
                // PublicFestScoreboardService::scoreboard()'s category branch): an item only
                // belongs here once it has been individually published, and never again once
                // explicitly unpublished — the event-wide flag alone isn't enough for either
                // direction. Without whereNotNull() here, "Recently published" surfaced every
                // item's winners the moment the event overall went public, even ones nobody
                // had published yet.
                ->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')->where('results_hidden', false))
                ->with(['item', 'participant.student', 'participant.teacher', 'participant.registration.school'])
                ->latest('updated_at')
                ->limit(200)
                ->get();
            $recentRoster = $this->rosterForMarks($recentMarks);
            $recentClassGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
            $recentResults = $recentMarks
                ->unique(fn (FestMark $mark) => $mark->deduplicationKey())
                ->groupBy('item_id')
                ->sortByDesc(fn ($marksForItem) => $marksForItem->max('updated_at'))
                ->map(function ($marksForItem) use ($event, $recentRoster, $recentClassGroupLabels) {
                    $first = $marksForItem->first();

                    return [
                        'item_id' => $first->item_id,
                        'item' => $first->item?->title,
                        'participant_type' => $first->item?->participant_type,
                        'category_label' => FestItemCategoryLabel::resolve($first->item, $recentClassGroupLabels, config('fest_item_taxonomy.arts_category', [])),
                        'gender_label' => \App\Support\FestSportsAgeGroup::genderLabel($first->item?->gender),
                        'winners' => $marksForItem->sortBy('position')
                            ->map(fn (FestMark $mark) => $this->publicWinnerRow($mark, $event, $recentRoster))
                            ->values()
                            ->all(),
                    ];
                })
                ->take(6)
                ->values();

            $publishedItemCount = $items->filter(fn ($i) => $i->results_published_at !== null && ! $i->results_hidden)->count();
        }

        // The item finder groups by class_group/age_group/category — resolve each raw
        // key (e.g. "category_1") through the same scheme-aware lookup the scoreboard and
        // results tabs use, instead of the template ever falling back to strtoupper($key).
        $itemCategoryKeys = $itemGroups->pluck('items')->flatten()
            ->map(fn ($item) => $item->class_group ?: $item->age_group ?: $item->category)
            ->filter()->unique()->values();
        $categoryLabels = $itemCategoryKeys->mapWithKeys(
            fn (string $key) => [$key => $this->scoreboards->categoryLabel($event, $key)]
        );

        return $this->renderPublic('public.fest.show', $tenant, [
            'event' => $event,
            'eventContext' => $this->operationalEvents->publicContext($event),
            'itemGroups' => $itemGroups,
            'recentResults' => $recentResults,
            'categoryLabels' => $categoryLabels,
            'scopes' => $scopes,
            'selectedScope' => $selectedScope,
            'scopeResultsPublished' => $isResultsPublished,
            'isAdminPreview' => $isAdminPreview,
            'publishedItemCount' => $publishedItemCount,
            'scopeSchedulePublished' => (bool) $selectedScope['schedule_published'],
            'scheduledItemIds' => $scheduledItemIds,
            'resultedItemIds' => $resultedItemIds,
            'visibleResultItemIds' => $visibleResultItemIds,
            'pageSeo' => ['title' => $event->title.' — '.$tenant->name],
        ]);
    }

    /**
     * Standalone version of show()'s "Event item finder" section (search/filter grid of
     * every item, Schedule/Results buttons per item) — previously only reachable by
     * scrolling to the bottom of the long event landing page. Reuses the exact same
     * item/category/schedule data-building as show() (kept as its own small query here
     * rather than extracted into a shared helper, so this page can't regress show()'s
     * already-working landing page if this one changes later).
     */
    public function itemFinder(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $selectedScope = $this->operationalEvents->directScope($event);

        $targetEvent = FestEventItem::where('event_id', $event->id)->where('is_enabled', true)->exists()
            ? $event
            : ($event->parent_event_id ? $event->rootEvent() : $event);

        $rawItems = FestEventItem::where('event_id', $targetEvent->id)
            ->where('is_enabled', true)
            ->with(['head:id,name', 'phase:id,source_phase_id'])
            ->orderBy('display_order')
            ->orderBy('title')
            ->get(['id', 'title', 'stage_type', 'category', 'class_group', 'age_group', 'participant_type', 'head_id', 'event_id', 'results_published_at', 'results_hidden', 'phase_id']);

        $allItems = \App\Services\Events\FestHeadItemNavigationService::filterToOwnPhase($rawItems, $event);

        $scheduledItemIds = FestSchedule::where('event_id', $event->id)
            ->whereNotNull('item_id')
            ->distinct()
            ->pluck('item_id');

        // An item can be flagged results_published_at without a single mark ever having
        // been entered for it (published too early, or a no-show item) — the Results
        // button must not render as a normal, inviting link into a page that can only
        // ever say "No published results for this item." in that case.
        $resultedItemIds = FestMark::where('event_id', $targetEvent->id)
            ->whereIn('item_id', $allItems->pluck('id'))
            ->distinct()
            ->pluck('item_id');

        $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);

        // itemResults() itself requires the EVENT-wide results_published flag as a hard
        // gate (FestItemResultsService::isItemVisible()) — an item's own
        // results_published_at is not enough on its own, ever, even if it's set ahead of
        // the event-wide publish. The button must not show as a normal, clickable link
        // into a page that will just 403 the moment the event itself isn't published yet.
        $itemResultsService = app(FestItemResultsService::class);
        $visibleResultItemIds = $allItems
            ->filter(fn (FestEventItem $item) => $isAdminPreview || $itemResultsService->isItemVisible($item, $event))
            ->pluck('id');

        // Items whose results are actually out (the same "Results" button condition the
        // view itself renders on — visible AND has recorded marks) float to the top, so a
        // grid mostly full of "Not yet published" cards doesn't bury the handful that are
        // actually ready. Collection::sortByDesc() is a stable sort, so within each of the
        // two groups items keep their original display_order/title ordering.
        $allItems = $allItems
            ->sortByDesc(fn (FestEventItem $item) => ($visibleResultItemIds->contains($item->id) && $resultedItemIds->contains($item->id)) ? 1 : 0)
            ->values();

        $itemCategoryKeys = $allItems
            ->map(fn ($item) => $item->class_group ?: $item->age_group ?: $item->category)
            ->filter()->unique()->values();
        $categoryLabels = $itemCategoryKeys->mapWithKeys(
            fn (string $key) => [$key => $this->scoreboards->categoryLabel($event, $key)]
        );

        return $this->renderPublic('public.fest.item-finder', $tenant, [
            'event' => $event,
            'eventContext' => $this->operationalEvents->publicContext($event),
            'allItems' => $allItems,
            'categoryLabels' => $categoryLabels,
            'isAdminPreview' => $isAdminPreview,
            'scopeSchedulePublished' => (bool) $selectedScope['schedule_published'],
            'scheduledItemIds' => $scheduledItemIds,
            'resultedItemIds' => $resultedItemIds,
            'visibleResultItemIds' => $visibleResultItemIds,
            'pageSeo' => ['title' => 'Item Finder — '.$event->title.' — '.$tenant->name],
        ]);
    }

    public function results(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $selectedScope = $this->operationalEvents->directScope($event);

        $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);
        $isPublished = (bool) $selectedScope['results_published'] || $isAdminPreview;

        abort_unless($isPublished || $this->hasPublishedItems($selectedScope['event_ids']), 403, 'Public scoreboard & results are disabled for this event.');

        $scopes = [$selectedScope];
        $tab = $request->query('tab', $selectedScope['results_published'] ? 'school' : 'item');
        if (! in_array($tab, ['toppers', 'school', 'category', 'item', 'individual', 'championship'], true)) {
            $tab = $selectedScope['results_published'] ? 'school' : 'item';
        }

        $publishedAt = FestResult::whereIn('event_id', $selectedScope['event_ids'])
            ->whereNull('item_id')
            ->max('published_at');
        $resultsVersion = sha1(implode('|', [
            (string) $publishedAt,
            (string) FestEventItem::whereIn('event_id', $selectedScope['event_ids'])->max('results_published_at'),
            (string) FestMark::whereIn('event_id', $selectedScope['event_ids'])->max('updated_at'),
        ]));

        // Bypassed for ANY authenticated request, not just $isAdminPreview — the
        // championship computation below also runs a per-SIBLING-LEAF
        // isAuthorizedAdminPreview() check (crossPhaseStandingForVisibleLeaves()) that
        // can broaden the result. Anonymous traffic caches the final rendered HTML per
        // tab instead of serializing a large graph of Eloquent models into Redis. The
        // old shared model-payload cache was both memory-heavy and could fail while
        // unserializing on the next tab request.
        $bypassCache = (bool) ($request->user() ?? auth()->user());

        $renderResults = function () use ($request, $event, $selectedScope, $isPublished, $publishedAt, $scopes, $tenant, $tab) {
            $payload = $this->buildResultsPayload($request, $event, $selectedScope, $isPublished, $publishedAt, $scopes);

            return $this->renderPublic('public.fest.results', $tenant, $payload + ['tab' => $tab]);
        };

        if ($bypassCache) {
            return $renderResults();
        }

        $cacheKey = 'fest-results-html:v2:'.$event->tenant_id.':'.$event->id.':'.($selectedScope['event_id'] ?? $event->id).':'.$resultsVersion.':'.$tab;
        $html = $this->rememberPublicHotPath(
            $cacheKey,
            120,
            fn () => $renderResults()->getContent(),
            waitSeconds: 30,
        );

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * The shared results computation. results() caches only the final HTML for the
     * requested tab, so this method's Eloquent objects never enter the cache store.
     */
    private function buildResultsPayload(Request $request, FestEvent $event, array $selectedScope, bool $isPublished, mixed $publishedAt, array $scopes): array
    {
        // FestIndividualChampionshipPoint is a stored aggregate across every item in the
        // event (recalculated on demand by an admin action), not a live per-item query —
        // unlike the school scoreboard there's no "provisional, published-items-only"
        // variant to fall back to, so the whole tab stays empty until the event's
        // official publish has actually run (matches how $overallBoard/$categoryBoards
        // being empty pre-publish is already handled further down). Ranked within
        // category AND gender (FestIndividualChampionshipService::rankAndFormat()) — boys
        // and girls in the same category get separate #1s, not one mixed ranking — and,
        // when this hub uses phases, combined across every phase the viewer can actually
        // see (same per-leaf visibility gate crossPhaseScoreboard() uses for the school
        // board, so an unpublished sibling phase can't leak its points into the total).
        $championshipRoot = $event->rootEvent();
        $championshipUsesPhases = $championshipRoot->usesPhasedRegionalBilling();
        $championshipRows = collect();
        $championshipEventIds = [$selectedScope['event_id'] ?: $event->id];
        if ($isPublished) {
            if ($championshipUsesPhases) {
                $visibleLeafIds = collect();
                foreach (FestEventPhase::where('event_id', $championshipRoot->id)->get() as $phase) {
                    $leaves = FestEvent::where('parent_event_id', $championshipRoot->id)->where('source_phase_id', $phase->id)->get();
                    foreach ($leaves as $leaf) {
                        if ($this->operationalEvents->directScope($leaf)['results_published'] || $this->isAuthorizedAdminPreview($request, $leaf)) {
                            $visibleLeafIds->push($leaf->id);
                        }
                    }
                }
                $championshipRows = $this->individualChampionship->crossPhaseStandingForVisibleLeaves($championshipRoot, $visibleLeafIds, directPhotoUrls: true);
                // A student's ranked row here is a cross-phase total — their own eye-icon
                // link must resolve against WHICHEVER leaf they're actually registered in
                // (findParticipantByRef() requires an exact match), not the single leaf
                // $event happens to be, or every student outside that one leaf silently
                // loses the icon.
                $championshipEventIds = $visibleLeafIds->all();
            } else {
                $championshipRows = $this->individualChampionship->leaderboardForEvent($event, directPhotoUrls: true);
            }
        }

        // Link each championship row through the same typed participant reference used
        // by search, avoiding collisions between numeric chest and registration numbers.
        // Each ref is paired with the specific leaf event its registration actually
        // belongs to (not necessarily $event) — the link the view builds must point
        // there or the participant page's own lookup (scoped to one event) 404s.
        $championshipRefs = FestParticipant::whereHas('registration', fn ($q) => $q->whereIn('event_id', $championshipEventIds))
            ->whereIn('student_id', $championshipRows->pluck('student.id')->filter()->unique())
            ->with('registration:id,event_id')
            ->orderBy('id')
            ->get()
            ->unique('student_id')
            ->mapWithKeys(fn (FestParticipant $participant) => [
                $participant->student_id => [
                    'ref' => $this->visibility->participantLinkRef($participant),
                    'event_id' => $participant->registration?->event_id ?? $event->id,
                ],
            ]);
        // FestIndividualChampionshipPoint.category is always one of the fixed lp/up/hs/
        // hss/open keys, regardless of event_type — canonicalLabels() resolves each back
        // to this Sahodaya's own configured category name (e.g. "Category 3 — Classes 8,
        // 9 & 10"), the same names the rest of the portal already shows, with a generic
        // fallback for any key the scheme has no match for.
        $championshipCategoryLabels = FestClassGroupScheme::canonicalLabels(null, $championshipRoot);
        $championship = $championshipRows
            ->map(function (array $row) use ($championshipCategoryLabels, $championshipRefs, $event) {
                $link = $championshipRefs[$row['student']['id']] ?? null;

                return [
                    'rank' => $row['rank'],
                    'points' => $row['points'],
                    'category' => $championshipCategoryLabels[$row['category']] ?? strtoupper($row['category']),
                    'category_key' => $row['category'],
                    'gender_key' => $row['gender'],
                    'gender' => \App\Support\FestSportsAgeGroup::genderLabel($row['gender']) ?? $row['gender'],
                    'student' => $row['student']['name'],
                    'photo' => $row['student']['photo'],
                    'photo_fallback' => $row['student']['photo_fallback'] ?? null,
                    'reg_no' => $row['student']['reg_no'],
                    'school' => $row['school'],
                    'ref' => $link['ref'] ?? null,
                    'ref_event_id' => $link['event_id'] ?? $event->id,
                ];
            })
            ->values()
            ->all();

        $categories = $this->scoreboards->categories($event, $selectedScope);
        $categoryBoards = collect($categories)
            ->map(function (string $key) use ($event, $selectedScope, $isPublished) {
                [$rows] = $this->resolveScoreboard($event, $selectedScope, $key, $isPublished);

                return [
                    'key' => $key,
                    'label' => $this->scoreboards->categoryLabel($event, $key),
                    'rows' => $rows,
                ];
            })
            ->all();

        // §7.3a (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md, 2026-08-15): a phased event's
        // "Overall" school board is the progressive sum of every published phase's
        // points, not the region-partition combine PublicFestScoreboardService already
        // does for $schoolBoard below. Only applies to the whole-event 'overall' scope —
        // an individual region/cluster/finale scope keeps its own normal board — and only
        // when this event actually has FestEventPhase rows; every other event (the
        // default, phase-less case) falls through to exactly today's $schoolBoard.
        $usesPhases = $this->phaseScoreboards->usesPhases($event);
        $isOverallScope = ($selectedScope['role'] ?? null) === 'overall';
        $phaseCumulativeBoard = ($usesPhases && $isOverallScope)
            ? $this->phaseScoreboards->cumulativeOverallWithContributions($event)
            : null;
        $phaseBreakdown = ($usesPhases && $isOverallScope)
            ? $this->phaseScoreboards->phaseBreakdown($event)
            : [];

        // A partitioned hub's marks live on its region/finale children, not the hub's own
        // event_id — without this expansion, a partitioned hub's public results page
        // showed zero item results even after results_published was cascaded true.
        $marks = FestMark::whereIn('event_id', $selectedScope['event_ids'])
            ->whereIn('position', [1, 2, 3])
            // Unconditional, regardless of $isPublished: an item's own results_published_at
            // is the only thing that makes its marks visible to the public, whole-event
            // publish or not — see the comment on PublicFestScoreboardService::scoreboard()'s
            // category branch for the production leak this convention exists to prevent.
            ->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')->where('results_hidden', false))
            ->with(['item.head', 'participant.student', 'participant.teacher', 'participant.registration.school'])
            ->orderBy('item_id')
            ->orderBy('position')
            ->get();

        // Pair/group items register every performer as their own FestParticipant row
        // sharing one registration_id (FestRegistrationCreateService) — only the row a
        // judge happened to enter the mark against is on $mark->participant, so batch-
        // fetch every co-performer up front rather than trusting the single mark row.
        $rosterByRegistration = FestParticipant::whereIn(
            'registration_id',
            $marks->pluck('participant.registration_id')->filter()->unique()->values()
        )
            ->where('participant_role', 'performer')
            ->with(['student', 'teacher'])
            ->get()
            ->groupBy('registration_id');

        $categoryColumn = $event->event_type === 'sports' ? 'age_group' : 'class_group';

        $itemResults = $marks
            ->groupBy('item_id')
            ->map(function ($group) use ($event, $rosterByRegistration, $categoryColumn) {
                /** @var FestMark $first */
                $first = $group->first();

                return [
                    'item_id' => $first->item_id,
                    'item' => $first->item?->title,
                    'head' => $first->item?->head?->name,
                    'category' => $first->item?->{$categoryColumn},
                    'gender_label' => \App\Support\FestSportsAgeGroup::genderLabel($first->item?->gender),
                    'participant_type' => $first->item?->participant_type,
                    'stage_type' => $first->item?->stage_type,
                    'results_published_at' => $first->item?->results_published_at,
                    // Pair/group items save one FestMark per teammate (all with the same
                    // position/score — see the roster batch-fetch above), so without this
                    // the same team would render as N identical winner cards.
                    'winners' => $group
                        ->unique(fn (FestMark $mark) => $mark->deduplicationKey())
                        ->map(fn (FestMark $mark) => $this->publicWinnerRow($mark, $event, $rosterByRegistration))
                        ->values()
                        ->all(),
                ];
            })
            // Most recently published item first — items were previously left in
            // item_id order (registration order), which reads as arbitrary once results
            // start trickling in; visitors want to see what just got published.
            ->sortByDesc('results_published_at')
            ->values();

        // Group item-wise results under the same category labels/order already used by
        // the Category-wise tab above, so "region-wise, category-wise" results line up.
        $itemResultsByCategory = collect($categories)
            ->map(fn (string $key) => [
                'key' => $key,
                'label' => $this->scoreboards->categoryLabel($event, $key),
                'items' => $itemResults->where('category', $key)->values()->all(),
            ])
            ->filter(fn (array $group) => count($group['items']) > 0)
            ->values();

        $uncategorized = $itemResults->whereNotIn('category', $categories)->values()->all();
        if ($uncategorized !== []) {
            $itemResultsByCategory->push([
                'key' => null,
                'label' => 'Other Items',
                'items' => $uncategorized,
            ]);
        }
        $itemResultsByCategory = $itemResultsByCategory->all();

        $individualClassGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $individualResults = $marks
            ->map(fn (FestMark $mark) => $this->publicWinnerRow($mark, $event) + [
                'item' => $mark->item?->title,
                'head' => $mark->item?->head?->name,
                'category' => FestItemCategoryLabel::resolve(
                    $mark->item,
                    $individualClassGroupLabels,
                    config('fest_item_taxonomy.arts_category', [])
                ),
            ])
            ->sortBy(fn (array $row) => [$row['participant'] ?? '', $row['item'] ?? ''])
            ->values()
            ->all();

        // Medal tally (gold/silver/bronze counts) per school, layered onto $schoolBoard's
        // existing points-based rank rather than replacing it — points come from a grade
        // scheme (FestGradePointService) that doesn't always track 1st/2nd/3rd counts
        // 1:1, so the official rank stays points-driven and medals are informational.
        // Built from the same top-3 $marks already fetched above for the item tab, scoped
        // to whichever region/cluster/phase the page is currently showing. Deduped by
        // registration first — pair/group items save one FestMark per teammate, so an
        // 11-person choir's single silver would otherwise tally as 11 silvers for its
        // school (same root cause as the scoring-dedup note on EventContext).
        //
        // This only ever feeds $schoolBoard (the combined "All Categories" board) —
        // $categoryBoards above already gets its own rows straight from
        // resolveScoreboard(), unaffected by this — so it must honor
        // excluded_overall_categories the same way that combined total does, or an
        // excluded category's podium finishes would still show up in gold/silver/bronze
        // next to a Total Points that correctly leaves them out.
        $excludedCategoriesForMedals = FestOverallCategoryExclusion::excluded($event->rootEvent());
        $medalTally = $marks
            ->filter(fn (FestMark $m) => $m->participant?->registration?->school_id && ! $m->participant->disqualified_at)
            ->filter(fn (FestMark $m) => ! $excludedCategoriesForMedals
                || ! in_array(FestOverallCategoryExclusion::categoryKeyForItem($event, $m->item), $excludedCategoriesForMedals, true))
            ->unique(fn (FestMark $m) => $m->deduplicationKey())
            ->groupBy(fn (FestMark $m) => (string) $m->participant->registration->school_id)
            ->map(fn ($group) => [
                'gold' => $group->where('position', 1)->count(),
                'silver' => $group->where('position', 2)->count(),
                'bronze' => $group->where('position', 3)->count(),
            ]);

        [$overallRows, $lockedCumulativeStanding] = $this->resolveScoreboard($event, $selectedScope, null, $isPublished);
        $schoolBoard = collect($overallRows)
            ->map(fn (array $row) => $row + [
                'gold' => $medalTally[$row['school_id']]['gold'] ?? 0,
                'silver' => $medalTally[$row['school_id']]['silver'] ?? 0,
                'bronze' => $medalTally[$row['school_id']]['bronze'] ?? 0,
            ])
            ->all();

        // Per-school results roster — every item that school entered (not just the ones
        // it won), each with its position, grade, and points, so the sum of the listed
        // rows always agrees with $schoolBoard's official total above. Shared with
        // schoolResults()'s own dedicated per-school page.
        $resultsBySchool = $this->schoolResultsRoster($event, $selectedScope, $isPublished);
        $schoolWinnersBoard = collect($schoolBoard)
            ->map(fn (array $row) => $row + ['winners' => $resultsBySchool[$row['school_id']] ?? []])
            ->filter(fn (array $row) => $row['winners'] !== [])
            ->values()
            ->all();

        $showPhasePoints = collect($schoolBoard)->contains(
            fn (array $row) => (int) ($row['event_points'] ?? 0) !== (int) ($row['phase_points'] ?? 0)
        );
        $overallSchoolToppers = collect($schoolBoard)->take(3)->values()->all();
        $schoolCategoryToppers = collect($categoryBoards)
            ->map(fn (array $board) => $board + ['rows' => collect($board['rows'])->take(3)->values()->all()])
            ->filter(fn (array $board) => $board['rows'] !== [])
            ->values()
            ->all();
        $studentCategoryToppers = collect($championship)
            ->groupBy(fn (array $row) => trim(($row['category'] ?: 'Open').' · '.($row['gender'] ?: 'All')))
            ->map(fn (Collection $rows, string $label) => [
                'label' => $label,
                'rows' => $rows->take(3)->values()->map(fn (array $row, int $index) => $row + ['category_rank' => $index + 1])->all(),
            ])
            ->values()
            ->all();

        return [
            'event' => $event,
            'eventContext' => $this->operationalEvents->publicContext($event),
            'schoolBoard' => $schoolBoard,
            'schoolWinnersBoard' => $schoolWinnersBoard,
            'usesPhases' => $usesPhases,
            'phaseCumulativeBoard' => $phaseCumulativeBoard,
            'phaseBreakdown' => $phaseBreakdown,
            'categoryBoards' => $categoryBoards,
            'itemResultsByCategory' => $itemResultsByCategory,
            'individualResults' => $individualResults,
            'championship' => $championship,
            'championshipCombinesPhases' => $championshipUsesPhases,
            'publishedAt' => $publishedAt,
            'scopes' => $scopes,
            'selectedScope' => $selectedScope,
            'lockedCumulativeStanding' => $lockedCumulativeStanding,
            'showPhasePoints' => $showPhasePoints,
            'overallSchoolToppers' => $overallSchoolToppers,
            'schoolCategoryToppers' => $schoolCategoryToppers,
            'studentCategoryToppers' => $studentCategoryToppers,
            'pageSeo' => ['title' => $event->title.' — Results'],
        ];
    }

    /**
     * One school's full results roster on its own page — the compact card on the main
     * results page truncates names and keeps photos small to fit many schools on
     * screen at once; this gives one school room for full names and larger photos.
     * Reached from the scoreboard's eye icon, which carries along whichever category tab
     * was active there (?category=) — when present, the roster/total below narrow to
     * that one category instead of the school's full cross-category report. Reached from
     * the plain results-page ranking table (no category concept), ?category is absent and
     * behaviour is unchanged: full roster, overall total.
     */
    public function schoolResults(Request $request, int $eventId, string $schoolId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $selectedScope = $this->operationalEvents->directScope($event);

        $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);
        $isPublished = (bool) $selectedScope['results_published'] || $isAdminPreview;

        abort_unless($isPublished, 403, 'Public scoreboard & results are disabled for this event.');

        $categories = $this->scoreboards->categories($event, $selectedScope);
        $category = $this->stringQuery($request, 'category');
        if ($category !== null) {
            abort_unless(in_array($category, $categories, true), 404);
        }

        $school = Tenant::findOrFail($schoolId);

        [$overallRows] = $this->resolveScoreboard($event, $selectedScope, $category, $isPublished, $isAdminPreview);
        $schoolRow = collect($overallRows)->firstWhere('school_id', $schoolId);
        if (! $schoolRow && $isAdminPreview) {
            $schoolRow = [
                'school_id' => $schoolId,
                'school_name' => $school->name,
                'total_points' => '0.00',
                'rank' => '—',
                'gold' => 0,
                'silver' => 0,
                'bronze' => 0,
            ];
        } else {
            abort_unless($schoolRow, 404);
        }

        $roster = $this->schoolResultsRoster($event, $selectedScope, $isPublished, $category)->get($schoolId, []);
        if (empty($roster) && ! $isAdminPreview) {
            abort(404, 'No results recorded for this school yet.');
        }

        $rosterTotalPoints = collect($roster)->sum('points');
        if ($schoolRow) {
            $schoolRow['total_points'] = (string) $rosterTotalPoints;
        } else {
            $schoolRow = [
                'school_id' => $schoolId,
                'school_name' => $school->name,
                'total_points' => (string) $rosterTotalPoints,
                'rank' => '—',
                'gold' => 0,
                'silver' => 0,
                'bronze' => 0,
            ];
        }

        // This event may be one phase (or one region-partition child of a phase) of a
        // larger hub — show this school's cross-phase combined total instead of just
        // this one phase's number. See crossPhaseScoreboard()'s docblock.
        $crossPhaseBoard = $this->crossPhaseScoreboard($event, $category, $request);
        $phaseCumulativeTotal = $crossPhaseBoard
            ? collect($crossPhaseBoard)->firstWhere('school_id', $schoolId)['total_points'] ?? null
            : null;

        return $this->renderPublic('public.fest.school-results', $tenant, [
            'event' => $event,
            'eventContext' => $this->operationalEvents->publicContext($event),
            'school' => $school,
            'schoolRow' => $schoolRow,
            'phaseCumulativeTotal' => $phaseCumulativeTotal,
            'roster' => $roster,
            // Named activeCategory*, not category* — the view's own @foreach groups the
            // roster by each row's category LABEL using a loop variable also called
            // $category, which would silently shadow a same-named top-level variable.
            'activeCategory' => $category,
            'activeCategoryLabel' => $category ? $this->scoreboards->categoryLabel($event, $category) : null,
            'scopes' => [$selectedScope],
            'selectedScope' => $selectedScope,
            'isAdminPreview' => $isAdminPreview,
            'pageSeo' => ['title' => $event->title.' — '.$school->name.' — Results'],
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<string, array<int, array<string, mixed>>> roster keyed by school_id
     */
    private function schoolResultsRoster(FestEvent $event, array $selectedScope, bool $isPublished, ?string $category = null): \Illuminate\Support\Collection
    {
        $categoryColumn = $event->event_type === 'sports' ? 'age_group' : 'class_group';
        $participantTypeLabels = ['pair' => 'Pair', 'trio' => 'Trio', 'group' => 'Group', 'team' => 'Team'];
        // Only the full cross-category roster (no specific $category requested) honors
        // excluded_overall_categories — same rule as everywhere else this exclusion is
        // checked: a category's own filtered view still shows its own items, only the
        // combined roster/total skips it. Without this, a school's excluded-category
        // items (and their points, via $rosterTotalPoints below) still leaked onto this
        // page even though the main scoreboard's combined total already left them out.
        $excludedCategories = $category ? [] : FestOverallCategoryExclusion::excluded($event->rootEvent());

        // Deliberately a separate query from results()'s own $marks (which stays
        // top-3-only — it also feeds the item/individual/medal-tally tabs, where
        // "winners only" is the correct scope), not a reuse of it.

        $allSchoolMarks = FestMark::whereIn('event_id', $selectedScope['event_ids'])
            // Unconditional, regardless of whether the event overall is published — an
            // item that was never individually published (still "Pending" in admin) must
            // never resurface here just because the event-wide toggle went on. Previously
            // only enforced while the event was unpublished, so once an admin published
            // the event, every item not explicitly hidden leaked onto this roster
            // (including grade/points) even if that specific item was still Pending.
            ->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at'))
            // Unconditional too — an explicitly unpublished item must never resurface
            // just because the event overall got published.
            ->whereHas('item', fn ($q) => $q->where('results_hidden', false))
            // Only set when schoolResults() was reached from a category-filtered
            // scoreboard — narrows the roster to that one category instead of the
            // school's full cross-category report. $category may itself be a merge
            // TARGET (aggregation_config.championship_category_map, e.g. "category_5"
            // merged into "category_3") — sourceKeysFor() expands it back to every raw
            // category that now counts toward it, same as PublicFestScoreboardService::
            // scoreboard()'s category branch already does. A plain exact match here
            // left a merged source category's items missing from the target's own
            // roster page, even though the scoreboard total correctly combined them.
            ->when($category, fn ($query) => $query->whereHas(
                'item',
                fn ($q) => $q->whereIn($categoryColumn, FestCategoryMerge::sourceKeysFor($event->rootEvent(), $category))
            ))
            ->when($excludedCategories, fn ($query) => $query->whereHas('item', fn ($q) => $q->whereNotIn($categoryColumn, $excludedCategories)))
            ->with(['item.head', 'participant.student', 'participant.teacher', 'participant.registration.school'])
            ->get();

        // Same team-roster batch-fetch as the item tab's own $rosterByRegistration — a
        // group/team item's mark is attached to whichever performer a judge happened to
        // enter it against, so publicWinnerRow() needs every co-performer on that
        // registration to list the whole team, not just that one row.
        $allSchoolRosterByRegistration = FestParticipant::whereIn(
            'registration_id',
            $allSchoolMarks->pluck('participant.registration_id')->filter()->unique()->values()
        )
            ->where('participant_role', 'performer')
            ->with(['student', 'teacher'])
            ->get()
            ->groupBy('registration_id');

        return $allSchoolMarks
            ->filter(fn (FestMark $m) => $m->participant?->registration?->school_id && ! $m->participant->disqualified_at)
            ->unique(fn (FestMark $m) => $m->deduplicationKey())
            ->groupBy(fn (FestMark $m) => (string) $m->participant->registration->school_id)
            ->map(fn ($group) => $group
                ->map(function (FestMark $m) use ($event, $categoryColumn, $participantTypeLabels, $allSchoolRosterByRegistration) {
                    // Splits into (grade points, rank points) per the Kalolsavam Manual's
                    // formula only when those two components actually sum to this mark's
                    // real total — null/null otherwise (a custom rule with no defined
                    // split), in which case only the combined total is shown below.
                    $breakdown = $this->gradePoints->pointsBreakdown($event, $m);

                    // participant/photo/team come from the same publicWinnerRow() helper
                    // every other tab on this page already uses — one team member's photo
                    // for an individual item, every co-performer's for a group/team one.
                    // Own array goes FIRST: publicWinnerRow() only fills points/grade_points
                    // when the mark has a position (correct for the Individual tab, which is
                    // winners-only), but this roster lists every item a school entered,
                    // including grade-only ones with no numeric rank — and PHP's `+` keeps
                    // the left side on key collisions, so ours must win or its null does.
                    return [
                        'item' => $m->item?->title,
                        'category' => $m->item?->{$categoryColumn}
                            ? $this->scoreboards->categoryLabel($event, $m->item->{$categoryColumn})
                            : 'Uncategorized',
                        'gender' => \App\Support\FestSportsAgeGroup::genderLabel($m->item?->gender),
                        'participant_type' => $participantTypeLabels[$m->item?->participant_type] ?? 'Individual',
                        'rank_points' => $breakdown['rank_points'],
                        'grade_points' => $breakdown['grade_points'],
                        'points' => $breakdown['total'],
                    ] + $this->publicWinnerRow($m, $event, $allSchoolRosterByRegistration);
                })
                // Category first so items naturally cluster together on screen, then
                // position/item within each category — mirrors the Item-wise tab's own
                // category grouping/order above. Sorted on the mapped array (string keys)
                // rather than the raw FestMark collection — Collection::sortBy()'s
                // multi-criteria array form only accepts string/dot keys per criterion,
                // not a closure paired with a direction.
                ->sortBy([['category', 'asc'], ['position', 'asc'], ['item', 'asc']])
                ->values()
                ->all());
    }

    public function schedule(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $isAdminPreview = $this->isAuthorizedAdminPreview($request, $event);
        // allowPublicSchedule() used to throw its 404 unconditionally before this
        // method ever got a chance to compute $isAdminPreview — the admin-preview
        // bypass just below it was dead code, permanently unreachable, since this gate
        // ran first with no exception of its own.
        EventLifecycleGate::allowPublicSchedule($event, $isAdminPreview);
        $selectedScope = $this->operationalEvents->directScope($event);

        abort_unless($selectedScope['schedule_published'] || $isAdminPreview, 404);
        $scopes = [$selectedScope];

        $schedules = $this->mapScheduleRows($event, null, $selectedScope['event_ids'], $isAdminPreview);

        return $this->renderPublic('public.fest.schedule', $tenant, compact(
            'event', 'schedules', 'scopes', 'selectedScope'
        ) + ['isAdminPreview' => $isAdminPreview]);
    }

    public function itemSchedule(Request $request, int $eventId, FestEventItem $item)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless((int) $item->event_id === (int) $event->id, 404);
        $isAdminPreview = $this->isAuthorizedAdminPreview($request, $event);
        EventLifecycleGate::allowPublicSchedule($event, $isAdminPreview);

        $schedules = $this->mapScheduleRows($event, $item->id, [$event->id], $isAdminPreview);
        $categoryLabel = FestItemCategoryLabel::resolve($item, FestClassGroupScheme::labels(null, $event->rootEvent()), config('fest_item_taxonomy.arts_category', []));
        $genderLabel = \App\Support\FestSportsAgeGroup::genderLabel($item->gender);

        return $this->renderPublic('public.fest.item-schedule', $tenant, compact('event', 'item', 'schedules', 'categoryLabel', 'genderLabel') + ['isAdminPreview' => $isAdminPreview]);
    }

    public function itemResults(Request $request, int $eventId, FestEventItem $item)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless((int) $item->event_id === (int) $event->id, 404);

        $isAdminPreview = ! $event->results_published && $this->isAuthorizedAdminPreview($request, $event);
        $isPublished = (bool) $event->results_published || $isAdminPreview;

        abort_unless($isPublished, 403, 'Public results are disabled for this event.');
        // The whole-event gate above only says results ARE published for at least
        // something; it says nothing about THIS item. Direct-URL access previously
        // skipped that check entirely, showing a still-unpublished (or explicitly
        // hidden) item's winners the moment the event overall went public.
        abort_unless($isAdminPreview || app(FestItemResultsService::class)->isItemVisible($item, $event), 403, 'Results for this item are not published yet.');

        $allMarks = FestMark::where('event_id', $item->event_id)
            ->where('item_id', $item->id)
            ->with(['item', 'participant.student', 'participant.teacher', 'participant.registration.school'])
            ->orderBy('position')
            ->orderByDesc('score')
            ->get()
            // Pair/group/team/trio items save one FestMark row PER TEAMMATE (same
            // position/score, see the scoring-dedup note on EventContext) — without this,
            // an 11-person choir would render its own result card 11 times over.
            ->unique(fn (FestMark $m) => $m->deduplicationKey());

        // Pair/group/team/trio items: the mark is only ever attached to one performer
        // on the registration (see the same note in results() above) — resolve the rest
        // of the roster so this page doesn't show a single arbitrary member as if they
        // competed solo. Skipped for individual items, which are the common case.
        $rosterByRegistration = $item->isTeamItem()
            ? FestParticipant::whereIn(
                'registration_id',
                $allMarks->pluck('participant.registration_id')->filter()->unique()->values()
            )
                ->where('participant_role', 'performer')
                ->with(['student', 'teacher'])
                ->get()
                ->groupBy('registration_id')
            : null;

        // One query/roster resolution feeds both sections. The visual Winner Roster is
        // reserved for the podium (including ties), while Full Results remains the clear
        // source for every ranked participant and the exact points breakdown.
        $allMarks = $allMarks->map(fn (FestMark $m) => $this->publicWinnerRow($m, $event, $rosterByRegistration) + [
            'mark_id' => $m->id,
            'poster_url' => in_array((int) $m->position, [1, 2, 3], true)
                ? route('tenant.fest.winner-poster', [$event->id, $item->id, $m->id])
                : null,
        ])->values();

        $marks = $allMarks
            ->filter(fn (array $row) => in_array((int) $row['position'], [1, 2, 3], true))
            ->values();

        $categoryLabel = FestItemCategoryLabel::resolve(
            $item,
            FestClassGroupScheme::labels(null, $event->rootEvent()),
            config('fest_item_taxonomy.arts_category', [])
        );
        $genderLabel = \App\Support\FestSportsAgeGroup::genderLabel($item->gender);

        return $this->renderPublic('public.fest.item-results', $tenant, compact('event', 'item', 'marks', 'allMarks', 'categoryLabel', 'genderLabel'));
    }

    public function winnerPoster(Request $request, int $eventId, FestEventItem $item, FestMark $mark, FestWinnerPosterService $posters)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless((int) $item->event_id === (int) $event->id, 404);
        abort_if($mark->event_id !== $item->event_id || $mark->item_id !== $item->id, 404);

        $isAdminPreview = ! $event->results_published && $this->isAuthorizedAdminPreview($request, $event);
        $isPublished = (bool) $event->results_published || $isAdminPreview;

        abort_unless($isPublished, 403, 'Public results are disabled for this event.');
        abort_unless($isAdminPreview || app(FestItemResultsService::class)->isItemVisible($item, $event), 403, 'Results for this item are not published yet.');
        abort_if(! in_array((int) $mark->position, [1, 2, 3], true), 404);

        $rendered = $posters->render($event, $item, $mark, $tenant);

        return response($rendered['content'], 200, [
            'Content-Type' => $rendered['mime'],
            'Content-Disposition' => 'inline; filename="'.$rendered['filename'].'"',
        ]);
    }

    public function itemResultsPdf(Request $request, int $eventId, FestEventItem $item)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless((int) $item->event_id === (int) $event->id, 404);

        $isAdminPreview = ! $event->results_published && $this->isAuthorizedAdminPreview($request, $event);
        $isPublished = (bool) $event->results_published || $isAdminPreview;

        abort_unless($isPublished, 403, 'Public results are disabled for this event.');
        abort_unless($isAdminPreview || app(FestItemResultsService::class)->isItemVisible($item, $event), 403, 'Results for this item are not published yet.');

        $topN = min(50, max(1, $request->integer('top_n') ?: 10));

        $marks = FestMark::where('event_id', $item->event_id)
            ->where('item_id', $item->id)
            ->with(['participant.student', 'participant.teacher', 'participant.registration.school'])
            ->orderBy('position')
            ->orderByDesc('score')
            ->limit($topN)
            ->get();

        $categoryLabel = FestItemCategoryLabel::resolve($item, FestClassGroupScheme::labels(null, $event->rootEvent()));
        $genderLabel = \App\Support\FestSportsAgeGroup::genderLabel($item->gender);

        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($item->title)) ?: 'item';

        return Pdf::loadView('fest.reports.item-wise', [
            'event'         => $event,
            'item'          => $item,
            'categoryLabel' => $categoryLabel,
            'genderLabel'   => $genderLabel,
            'marks'         => $marks,
            'topN'          => $topN,
            'orgName'       => $tenant->name ?? 'Sahodaya',
            'logoSrc'       => TenantBranding::logoEmbedSrc($tenant),
        ])->download("{$slug}-results.pdf");
    }

public function scoreboard(Request $request, int $eventId)
{
    $tenant = $this->resolveTenant();
    $event = $this->findEvent($tenant->id, $eventId);
    $selectedScope = $this->operationalEvents->directScope($event);

    $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);
    $isPublished = (bool) $selectedScope['results_published'] || $isAdminPreview;

    $scopes = [$selectedScope];
    $categories = $this->scoreboards->categories($event, $selectedScope);
    $category = $this->stringQuery($request, 'category');
    if ($category !== null) {
        abort_unless(in_array($category, $categories, true), 404);
    }
    $category = $this->resolveScoreboardCategory($event, $category, $categories);

    $categoryLabels = collect($categories)
        ->mapWithKeys(fn (string $key) => [$key => $this->scoreboards->categoryLabel($event, $key)])
        ->all();
    $dynamic = $this->scoreboardDynamicData($event, $selectedScope, $category, $isPublished, $isAdminPreview, $request);
    $scoreboardTitle = $selectedScope['label'];
    if ($category) {
        $scoreboardTitle .= ' · '.($categoryLabels[$category] ?? strtoupper($category));
    }

    return $this->renderPublic('public.fest.scoreboard', $tenant, [
        'event' => $event,
        'scopes' => $scopes,
        'selectedScope' => $selectedScope,
        'categories' => $categories,
        'category' => $category,
        'categoryLabels' => $categoryLabels,
        'scoreboardTitle' => $scoreboardTitle,
        'isPublished' => $isPublished,
        'isAdminPreview' => $isAdminPreview,
        'eventContext' => $this->operationalEvents->publicContext($event),
        'pageSeo' => ['title' => $event->title.' — Scoreboard'],
    ] + $dynamic);
}

public function scoreboardData(Request $request, int $eventId)
{
    $tenant = $this->resolveTenant();
    $event = $this->findEvent($tenant->id, $eventId);
    $selectedScope = $this->operationalEvents->directScope($event);
    $categories = $this->scoreboards->categories($event, $selectedScope);
    $category = $this->stringQuery($request, 'category');
    if ($category !== null) {
        abort_unless(in_array($category, $categories, true), 404);
    }
    $category = $this->resolveScoreboardCategory($event, $category, $categories);

    $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);
    $isPublished = (bool) $selectedScope['results_published'] || $isAdminPreview;

    $dynamic = $this->scoreboardDynamicData($event, $selectedScope, $category, $isPublished, $isAdminPreview, $request);

    return response()->json([
        'standingsPublished' => $isPublished,
        'isAdminPreview' => $isAdminPreview,
        'contentHtml' => view('public.fest.partials.scoreboard-content', $dynamic + compact('event', 'isPublished', 'category', 'isAdminPreview'))->render(),
        'refreshedAt' => now()->toIso8601String(),
    ])->header('Cache-Control', 'public, max-age=0, s-maxage=10, stale-while-revalidate=30');
}

/**
 * Big-format, no-chrome, unattended view meant for a projector/TV at the venue — not
 * a phone or laptop, and nobody's there to click a category filter. Auto-rotates
 * through a fixed sequence of slides (overall school medal tally + points, the same
 * table per category, then latest item winners) instead of showing one static view.
 * The medal tally here mirrors results()'s $schoolBoard computation (see the note
 * there) but additionally scopes it per category, which nothing else in the portal
 * needed until now.
 */
public function tv(Request $request, int $eventId)
{
    $tenant = $this->resolveTenant();
    $event = $this->findEvent($tenant->id, $eventId);
    $selectedScope = $this->operationalEvents->directScope($event);

    $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);
    $isPublished = (bool) $selectedScope['results_published'] || $isAdminPreview;

    // Unlike results()/itemResults()/scoreboard(), the TV board is meant to run
    // continuously at the venue regardless of publish state — it already has a
    // graceful "nothing published yet" fallback (the schools-only roster further
    // below) instead of needing to be blocked off, so it never 403s here.
    $categories = $this->scoreboards->categories($event, $selectedScope);

    // When this event's hub has other phases visible too, the boards below show each
    // school's CROSS-PHASE combined total_points (see crossPhaseScoreboard()) — the
    // medal tally must be scoped to those same combined events, or a medal earned in a
    // different phase silently drops out of gold/silver/bronze and gets swept into the
    // catch-all "Grade" column instead (grade_points = total - gold - silver - bronze),
    // even though the total itself correctly includes it. Falls back to just this
    // event's own scope when there's no phase combining to do.
    $crossPhaseEventIds = $this->crossPhaseVisibleEventIds($event, $request);

    $marks = FestMark::whereIn('event_id', $crossPhaseEventIds ?? $selectedScope['event_ids'])
        ->whereIn('position', [1, 2, 3])
        ->with(['item', 'participant.registration.school'])
        // Unconditional, regardless of $isPublished: an item's own results_published_at
        // is the only thing that makes its marks visible to the public — see the note on
        // results()'s $marks query above.
        ->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')->where('results_hidden', false))
        ->get()
        ->filter(fn (FestMark $m) => $m->participant?->registration?->school_id && ! $m->participant->disqualified_at)
        ->unique(fn (FestMark $m) => $m->deduplicationKey());

    $categoryColumn = $event->event_type === 'sports' ? 'age_group' : 'class_group';
    // Points earned FROM rank-1/2/3 finishes specifically, not raw medal counts — reuses
    // the same per-mark point computation the standings' own Points column sums
    // (FestGradePointService::pointsForMark(), position+grade rule tables for
    // kalolsavam, FestRankPointService for sports), so a gold/silver/bronze here always
    // matches what that position actually contributed to the school's total.
    $medalTallyFor = fn ($scopedMarks) => $scopedMarks
        ->groupBy(fn (FestMark $m) => (string) $m->participant->registration->school_id)
        ->map(fn ($group) => [
            'gold' => $group->where('position', 1)->sum(fn (FestMark $m) => $this->gradePoints->pointsForMark($event, $m)),
            'silver' => $group->where('position', 2)->sum(fn (FestMark $m) => $this->gradePoints->pointsForMark($event, $m)),
            'bronze' => $group->where('position', 3)->sum(fn (FestMark $m) => $this->gradePoints->pointsForMark($event, $m)),
        ]);

    // Total Points includes every scored mark (grade points awarded regardless of
    // podium finish, e.g. Grade A off the podium), while gold/silver/bronze above only
    // tally points earned FROM a 1st/2nd/3rd place finish specifically. Without this,
    // a school whose points are entirely grade-only (no podium item) rendered as
    // 0/0/0 next to a nonzero Total with no visible source for the difference.
    $withMedals = fn (array $rows, $tally) => collect($rows)
        ->map(function (array $row) use ($tally) {
            $gold = $tally[$row['school_id']]['gold'] ?? 0;
            $silver = $tally[$row['school_id']]['silver'] ?? 0;
            $bronze = $tally[$row['school_id']]['bronze'] ?? 0;

            return $row + [
                'gold' => $gold,
                'silver' => $silver,
                'bronze' => $bronze,
                'grade_points' => max(0, ($row['total_points'] ?? 0) - $gold - $silver - $bronze),
            ];
        })
        ->values()
        ->all();

    // Same cross-phase combine as scoreboardDynamicData() (used by the Scoreboard page):
    // a phase/region-partition scope's own board is isolated to that one phase, so the
    // TV's standings need the same override or they'd silently show single-phase totals
    // instead of each school's real combined standing across the hub.
    [$overallScoreboard, $overallCumulative] = $this->resolveScoreboard($event, $selectedScope, null, $isPublished, $isAdminPreview);
    if ($overallCumulative === null) {
        $crossPhaseOverall = $this->crossPhaseScoreboard($event, null, $request);
        if ($crossPhaseOverall !== null) {
            $overallScoreboard = $crossPhaseOverall;
        }
    }
    // Same "only the combined total honors this" rule as everywhere else
    // excluded_overall_categories is checked — the overall board's medal columns must
    // leave an excluded category's podium finishes out too, or gold/silver/bronze would
    // sum to more than the (correctly-excluding) Total Points next to them.
    $root = $event->rootEvent();
    $excludedCategories = FestOverallCategoryExclusion::excluded($root);
    $overallMedalMarks = $excludedCategories
        ? $marks->filter(fn (FestMark $m) => ! in_array(FestOverallCategoryExclusion::categoryKeyForItem($event, $m->item), $excludedCategories, true))
        : $marks;
    $overallBoard = $withMedals($overallScoreboard, $medalTallyFor($overallMedalMarks));

    $categoryBoards = collect($categories)
        ->map(function (string $key) use ($event, $root, $selectedScope, $isPublished, $isAdminPreview, $marks, $categoryColumn, $withMedals, $medalTallyFor, $request) {
            [$scoreboard, $cumulativeStanding] = $this->resolveScoreboard($event, $selectedScope, $key, $isPublished, $isAdminPreview);
            if ($cumulativeStanding === null) {
                $crossPhaseBoard = $this->crossPhaseScoreboard($event, $key, $request);
                if ($crossPhaseBoard !== null) {
                    $scoreboard = $crossPhaseBoard;
                }
            }

            // $key may itself be a merge TARGET (aggregation_config.
            // championship_category_map) — expand it back to every raw category that
            // now counts toward it, same as PublicFestScoreboardService::scoreboard()'s
            // category branch. A plain `=== $key` equality here left a merged source
            // category's podium points out of the target category's own medal columns,
            // even though that board's Total Points (from resolveScoreboard) already
            // correctly folds them together.
            $sourceKeys = FestCategoryMerge::sourceKeysFor($root, $key);

            return [
                'key' => $key,
                'label' => $this->scoreboards->categoryLabel($event, $key),
                'rows' => $withMedals(
                    $scoreboard,
                    $medalTallyFor($marks->filter(fn (FestMark $m) => in_array($m->item?->{$categoryColumn}, $sourceKeys, true)))
                ),
            ];
        })
        ->filter(fn (array $board) => count($board['rows']) > 0)
        ->values()
        ->all();

    $dynamic = $this->scoreboardDynamicData($event, $selectedScope, null, $isPublished, $isAdminPreview, $request);

    // Pre-chunked into fixed-size, non-scrolling pages server-side — nobody is at the
    // TV to scroll a tall list, so "Page N of M" slides stand in for scroll the same
    // way pagination would on a normal page. 1 item/page: with 2+ side by side, CSS
    // grid's default row-stretch makes a short 1-position card match its taller
    // 2-position row-mate, so a page's height was driven by whichever item happened
    // to share its row — measured a page hit 1555px in a 1080px viewport this way.
    // One item per page removes the row-mate entirely, so each slide is exactly its
    // own item's height.
    // fest-medal-board.blade.php's rows were enlarged for venue-distance legibility
    // (the previous sizing — 11px/9px header labels, 14-16px row text — was unreadable
    // from more than a couple of meters away). At that larger size each row measures
    // ~91px tall on the fixed 1920x1080 canvas; 9 rows (the old count, calibrated for
    // the smaller text) now overflows past the visible canvas and clips the bottom
    // schools entirely. 5 rows is what the enlarged rows actually fit, measured against
    // the canvas's fixed height minus the header/title/controls chrome.
    $boardsPerPage = 5;
    $winnersPerPage = 1;
    $slides = [];

    // The TV rotates through "Latest Item Winners" before standings — dozens of
    // published items (a full event can publish 50+) meant it could take many minutes
    // of rotation just to cycle the winners slides once before ever reaching a
    // standings board. $dynamic['latestWinners'] is already sorted most-recently-
    // updated-item-first, so the newest 10 are exactly "what just got published" —
    // the thing people at the venue actually want to see; older results are still on
    // the school/item pages, just not worth this screen's limited rotation time.
    $recentItemsForTv = 10;

    // A squad/team item's roster can run anywhere from 2 (a duet) to 25+ (a band), and
    // 2-3 winning positions sharing one slide side by side (fest-winner-item-card-tv.
    // blade.php's flex-wrap columns) squeezes each team into a fraction of the width —
    // cramped and, at large enough rosters, tiles could even overflow their column.
    // Split such an item's winners across one slide per position, AND split any single
    // position's own roster into pages of $rosterPerPage, so nobody's photo is ever
    // dropped behind a "+N more" tile — a large team just takes a couple of extra,
    // clearly-labeled slides instead. Individual items (a roster of one) never hit
    // either path and render exactly as before.
    //
    // 9, not a rounder number: fest-winner-item-card-tv.blade.php's roster tiles are
    // w-24 (6rem), which at this page's 26px root font-size render at 156px — at that
    // size, exactly 9 fit across one row of the available width (measured directly
    // against the compiled CSS, not estimated). A 10th tile wraps to a second row, and
    // a second row of tiles NEVER fits the remaining vertical space on the fixed
    // 1920x1080 canvas (overflows by ~190px, not a close call) — so this cap must keep
    // every roster page to a single row, not just "small enough to usually fit".
    $rosterPerPage = 9;

    // Ties are not artificially broken, so an individual item can have any number of
    // winners sharing position 1 (or 2, 3). Each winner column below is a flat
    // min-w-[22rem]; measured against the compiled CSS, 3 fit across one row with
    // margin to spare (89px), but a 4th always wraps to a second row that overflows
    // the fixed canvas by ~266px — not a close call, and the same overflow whether
    // it's 4, 5, or 6 tied winners, since the wrap point never moves. So an individual
    // item's winners get the same treatment as a squad item's oversized roster: split
    // into pages of $winnersPerSlide, reusing the same split_position/split_total
    // "Slide X of Y" mechanism. This only ever fires on a tie beyond gold/silver/
    // bronze — the common 1-3 winner case renders exactly as before, one slide, no
    // badge. Squad items never need this: they already get one winner per slide
    // below regardless of count, so their winner columns never share a row at all.
    $winnersPerSlide = 3;

    $tvWinnerItems = collect($dynamic['latestWinners'])
        ->take($recentItemsForTv)
        ->flatMap(function (array $itemGroup) use ($rosterPerPage, $winnersPerSlide) {
            $isSquadItem = FestTeamSquadRules::isMultiPerson($itemGroup['participant_type'] ?? null);
            if (! $isSquadItem) {
                $winners = collect($itemGroup['winners']);

                if ($winners->count() <= $winnersPerSlide) {
                    return [$itemGroup];
                }

                $chunks = $winners->chunk($winnersPerSlide)->values();
                $total = $chunks->count();

                return $chunks
                    ->map(fn ($chunk, int $i) => [
                        'winners' => $chunk->values()->all(),
                        'split_position' => $i + 1,
                        'split_total' => $total,
                    ] + $itemGroup)
                    ->all();
            }

            // Expand each winning position into one slide per $rosterPerPage-sized
            // chunk of its roster — a position whose roster already fits on one slide
            // expands to exactly one entry, so a normal 2-12 member team is completely
            // unaffected by this step. roster_total/roster_range travel with every
            // chunk so the card can say "Members 1-9 of 12" instead of just showing 9
            // photos with no indication there's a 10th, 11th, 12th anywhere — "Result 5
            // of 6" alone doesn't tell a viewer whether 6 means six winning positions or
            // six roster pages of one team.
            $slidesForItem = collect($itemGroup['winners'])
                ->flatMap(function (array $winner) use ($rosterPerPage) {
                    $team = $winner['team'] ?? null;
                    $rosterTotal = $team ? count($team) : null;

                    if (! $team || $rosterTotal <= $rosterPerPage) {
                        return [['roster_total' => $rosterTotal, 'roster_pages' => 1] + $winner];
                    }

                    $chunks = array_chunk($team, $rosterPerPage);

                    return collect($chunks)
                        ->map(fn (array $chunk, int $i) => [
                            'team' => $chunk,
                            'roster_total' => $rosterTotal,
                            'roster_pages' => count($chunks),
                            // Precomputed here (not in the view) because it needs
                            // $rosterPerPage, which the view has no business knowing —
                            // it's a display-count concern, not the card's to decide.
                            'roster_range' => [$i * $rosterPerPage + 1, $i * $rosterPerPage + count($chunk)],
                        ] + $winner)
                        ->all();
                })
                ->values();

            // Nothing needed splitting (one position, roster fit on one slide) — same
            // shape as an individual item, no "Result 1 of 1" clutter. roster_total is
            // still attached to the single winner so the card can show a plain "N
            // members" badge even when nothing was split.
            if ($slidesForItem->count() <= 1) {
                return [['winners' => $slidesForItem->all()] + $itemGroup];
            }

            $total = $slidesForItem->count();

            // split_position/split_total tell the card it's one of several consecutive
            // slides for the same item, so it can show "Result 2 of 3" — without this a
            // viewer had no way to know two slides in a row sharing a title were related,
            // rather than, say, a coincidence or a glitch.
            return $slidesForItem
                ->map(fn (array $winner, int $i) => [
                    'winners' => [$winner],
                    'split_position' => $i + 1,
                    'split_total' => $total,
                ] + $itemGroup)
                ->all();
        })
        ->values()
        ->all();

    // Order: latest results first, then standings — a result that just got published
    // is the thing people at the venue actually want to see right away (who just won
    // the item that was on stage a minute ago), not buried behind however many
    // standings pages happen to exist. $dynamic['latestWinners'] is already sorted
    // most-recently-updated-item-first, so the very first slide is the newest result.
    $winnerPages = array_chunk($tvWinnerItems, $winnersPerPage);
    foreach ($winnerPages as $i => $page) {
        $slides[] = [
            'type' => 'winners',
            'title' => 'Latest Item Winners',
            'subtitle' => count($winnerPages) > 1 ? 'Page '.($i + 1).' of '.count($winnerPages) : null,
            'items' => $page,
        ];
    }

    $provisionalSuffix = $isPublished ? '' : ' · Provisional';

    // tv_show_overall_standings only hides the fest-wide slide — category-wise boards
    // below always rotate regardless, and $overallBoard itself still feeds the
    // "nothing published yet" fallback check further down.
    if ($event->tv_show_overall_standings ?? true) {
        $overallPages = array_chunk($overallBoard, $boardsPerPage);
        foreach ($overallPages as $i => $page) {
            $slides[] = [
                'type' => 'board',
                'title' => 'Overall Standings',
                'subtitle' => (count($overallPages) > 1 ? 'Page '.($i + 1).' of '.count($overallPages) : 'All Categories').$provisionalSuffix,
                'rows' => $page,
            ];
        }
    }

    // Capped to the top 2 pages (top 10 schools at $boardsPerPage=5) — a category board
    // can run to 20-30+ schools on a busy event, and cycling through every one of them
    // for every category ate into how often the TV got back to the boards people
    // actually care about (Overall Standings, Latest Item Winners). The top 10 is what a
    // category board is really for; unlike Overall Standings there's no "everyone
    // deserves to see their own row" expectation here.
    $categoryBoardPageCap = 2;

    foreach ($categoryBoards as $board) {
        $categoryPages = array_slice(array_chunk($board['rows'], $boardsPerPage), 0, $categoryBoardPageCap);
        foreach ($categoryPages as $i => $page) {
            $slides[] = [
                'type' => 'board',
                'title' => $board['label'].' Standings',
                'subtitle' => trim((count($categoryPages) > 1 ? 'Page '.($i + 1).' of '.count($categoryPages) : '').$provisionalSuffix, ' ·') ?: null,
                'rows' => $page,
            ];
        }
    }

    // Only fall back to a schools-only roster when there's truly nothing published
    // yet (overallBoard/categoryBoards are already real provisional data — built
    // above via resolveScoreboard() — the moment even one item is published).
    // Registrations, not marks, so this still has real content from the moment
    // schools sign up, before any item is scored at all.
    if (! $isPublished && empty($overallBoard) && empty($categoryBoards)) {
        $registrations = FestRegistration::whereIn('event_id', $selectedScope['event_ids'])
            ->active()
            ->whereHas('item', fn ($q) => $q->where('is_enabled', true))
            ->with('item:id,'.$categoryColumn)
            ->get(['id', 'item_id', 'school_id']);

        $schoolNames = Tenant::whereIn('id', $registrations->pluck('school_id')->unique())
            ->orderBy('name')
            ->pluck('name', 'id');

        // Same table shape as the real standings board (rank/school/medal columns/
        // points), just every value at 0 — so the TV looks consistent across the
        // event's whole lifecycle instead of switching to a differently-styled list
        // the moment scoring starts. fest-medal-board's showMedalRank=false keeps rows
        // 1-3 from showing medal icons here, since nobody's actually won anything yet.
        $rowsFor = fn ($regs) => $regs->pluck('school_id')->unique()
            ->map(fn ($id) => $schoolNames[$id] ?? null)
            ->filter()
            ->sort()
            ->values()
            ->map(fn ($name, $i) => [
                'rank' => $i + 1,
                'school_name' => $name,
                'gold' => 0,
                'silver' => 0,
                'bronze' => 0,
                // String, matching the "27.00"-style formatting a real decimal points
                // total renders as — keeps the pre- and post-results table visually
                // identical apart from the numbers themselves.
                'total_points' => '0.00',
            ])
            ->all();

        $schoolPages = array_chunk($rowsFor($registrations), $boardsPerPage);
        foreach ($schoolPages as $i => $page) {
            $slides[] = [
                'type' => 'schools',
                'title' => 'Participating Schools',
                'subtitle' => count($schoolPages) > 1 ? 'Page '.($i + 1).' of '.count($schoolPages) : 'All Categories',
                'rows' => $page,
            ];
        }

        foreach ($categories as $key) {
            $categoryRows = $rowsFor($registrations->filter(fn ($r) => $r->item?->{$categoryColumn} === $key));
            if (! $categoryRows) {
                continue;
            }
            $categoryPages = array_chunk($categoryRows, $boardsPerPage);
            foreach ($categoryPages as $i => $page) {
                $slides[] = [
                    'type' => 'schools',
                    'title' => $this->scoreboards->categoryLabel($event, $key).' — Participating Schools',
                    'subtitle' => count($categoryPages) > 1 ? 'Page '.($i + 1).' of '.count($categoryPages) : null,
                    'rows' => $page,
                ];
            }
        }
    }

        if (! $slides) {
            $slides[] = ['type' => 'waiting'];
        }

        return $this->renderPublic('public.fest.tv', $tenant, [
            'event' => $event,
            'selectedScope' => $selectedScope,
            'isPublished' => $isPublished,
            'isAdminPreview' => $isAdminPreview,
            'slides' => $slides,
            'pageSeo' => ['title' => $event->title.' — Results Display'],
        ]);
    }

    public function manual(int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless($event->manual_pdf_path, 404);

        return TenantStorage::downloadResponse($tenant, $event->manual_pdf_path);
    }

    public function live(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $selectedScope = $this->operationalEvents->directScope($event);

        $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);

        return $this->renderPublic('public.fest.live', $tenant, array_merge(
            ['event' => $event, 'selectedScope' => $selectedScope, 'scopes' => [$selectedScope], 'isAdminPreview' => $isAdminPreview],
            $this->livePayload($request, $event, $selectedScope)
        ));
    }

    public function liveData(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $selectedScope = $this->operationalEvents->directScope($event);

        return response()->json($this->livePayload($request, $event, $selectedScope));
    }

    /**
     * Cache::remember() wraps computeLivePayload() below — hot path behind both live()
     * (full page) and liveData() (the JSON endpoint public viewers poll). Bypassed for
     * ANY authenticated request, same reasoning as scoreboardDynamicData()'s cache: the
     * cross-phase branch inside (crossPhaseScoreboard()/crossPhaseVisibleEventIds())
     * runs a per-sibling-leaf isAuthorizedAdminPreview() check that can broaden the
     * result independent of this event's own admin-preview state.
     *
     * @return array<string, mixed>
     */
    private function livePayload(Request $request, FestEvent $event, array $selectedScope): array
    {
        $bypassCache = (bool) ($request->user() ?? auth()->user());
        $compute = fn () => $this->computeLivePayload($request, $event, $selectedScope);

        if ($bypassCache) {
            return $compute();
        }

        $publishedFlag = $selectedScope['results_published'] ? '1' : '0';
        $cacheKey = 'fest-live-payload:v2:'.$event->tenant_id.':'.$event->id.':'.($selectedScope['event_id'] ?? $event->id).':'.$publishedFlag;

        return $this->rememberPublicHotPath($cacheKey, 10, $compute, waitSeconds: 15);
    }

    /** @return array<string, mixed> */
    private function computeLivePayload(Request $request, FestEvent $event, array $selectedScope): array
    {
        $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);
        $isPublished = (bool) $selectedScope['results_published'] || $isAdminPreview;

        $ctx = EventContext::for($event);
        $categories = $this->scoreboards->categories($event, $selectedScope);
        $categoryLinks = collect($categories)->map(fn (string $key) => [
            'key' => $key,
            'label' => $this->scoreboards->categoryLabel($event, $key),
            'url' => route('tenant.fest.scoreboard', [
                'event' => $event->id,
                'category' => $key,
            ]),
        ])->all();

        $nowSlot = FestSchedule::whereIn('event_id', $selectedScope['event_ids'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderByDesc('scheduled_at')
            ->with(['item', 'participant.student', 'participant.teacher', 'participant.registration.event', 'participant.registration.item'])
            ->first();

        $nowPerforming = null;
        if ($nowSlot?->participant) {
            $nowPerforming = $this->visibility->formatPublicParticipant($event, $nowSlot->participant, $nowSlot, null, $isAdminPreview);
            $nowPerforming['item_title'] = $nowSlot->item?->title;
            // Same reasoning as the item_title override above: this widget is anchored to the
            // schedule slot's own item, not necessarily the registration's item, so recompute
            // the category label from the same $nowSlot->item rather than trusting the one
            // formatPublicParticipant() derived from the participant's registration.
            $nowPerforming['category_label'] = FestItemCategoryLabel::resolve(
                $nowSlot->item,
                FestClassGroupScheme::labels(null, $event->rootEvent()),
                config('fest_item_taxonomy.arts_category', [])
            );
            $nowPerforming['gender_label'] = \App\Support\FestSportsAgeGroup::genderLabel($nowSlot->item?->gender);
        }

        $scoreboard = [];
        if ($isPublished) {
            $cumulativeStanding = $this->cumulativeChampionship->publicStanding($event);
            $rawScoreboard = $cumulativeStanding['rows']
                ?? ($selectedScope['results_published']
                    ? $this->scoreboards->scoreboard($event, $selectedScope)
                    : $this->scoreboards->provisionalScoreboard($event, $selectedScope));

            // Same "this phase/region's own board is isolated to just that scope" gap
            // that tv()/scoreboardDynamicData() already close via crossPhaseScoreboard()
            // — without this, /live silently stayed single-phase even once another phase
            // became visible, while /tv and /scoreboard (same event) showed the real
            // cross-phase combined total. Same school, three pages, three different
            // numbers, with nothing on screen explaining why.
            if ($cumulativeStanding === null) {
                $crossPhaseBoard = $this->crossPhaseScoreboard($event, null, $request);
                if ($crossPhaseBoard !== null) {
                    $rawScoreboard = $crossPhaseBoard;
                }
            }

            // Same class of bug as tv()'s $marks scoping: when the board above reflects a
            // combined (cumulative-championship or cross-phase) total, the medal tally
            // needs the same combined scope, or a medal earned outside this one phase
            // silently disappears from gold/silver/bronze even though it's still counted
            // in the total.
            $medalEventIds = match (true) {
                $cumulativeStanding !== null => $this->cumulativeChampionshipEventIds($event) ?? $selectedScope['event_ids'],
                default => $this->crossPhaseVisibleEventIds($event, $request) ?? $selectedScope['event_ids'],
            };
            $medalTally = $this->schoolMedalTally($event, $medalEventIds);
            $scoreboard = collect($rawScoreboard)
                ->map(fn (array $row) => $row + [
                    'gold' => $medalTally[$row['school_id']]['gold'] ?? 0,
                    'silver' => $medalTally[$row['school_id']]['silver'] ?? 0,
                    'bronze' => $medalTally[$row['school_id']]['bronze'] ?? 0,
                ])
                ->values()
                ->all();
        }

        return [
            'scoreboard' => $scoreboard,
            'standingsPublished' => $isPublished,
            'standingsProvisional' => $isAdminPreview,
            'isAdminPreview' => $isAdminPreview,
            // Same toggle as the TV screen's Overall Standings slide and the Scoreboard
            // page's "All Categories" tab — when off, only the per-category links below
            // stand in for standings here.
            'showOverallStandings' => $event->tv_show_overall_standings ?? true,
            'categoryLinks' => $categoryLinks,
            'houseScoreboard' => $isPublished
                ? $ctx->scoreboardByHouse()
                : [],
            'nowPerforming' => $nowPerforming,
            'athleticRecords' => $this->publicAthleticRecords($event),
            'recentBreaks' => $this->recentRecordBreaks($event, 5, $isAdminPreview),
            'refreshedAt' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function publicWinnerRow(FestMark $mark, FestEvent $event, ?Collection $rosterByRegistration = null): array
    {
        $participant = $mark->participant;
        $person = $participant?->student ?? $participant?->teacher;

        // rank_points/grade_points split out from the combined total per the official
        // Kalolsavam Manual formula — null for both when this mark's actual points don't
        // match that formula (a custom/Any-Position rule), so the public page can show
        // just the total in that case instead of an invented split. Computed unconditionally
        // (not gated on $mark->position): a grade-only mark with no numeric rank still earns
        // points via pointsForMark()'s "Any Position"/default-grade-only fallback, and
        // callers that list every entrant — not just top-3 winners — need that real value,
        // not a null that renders as a blank/zero points column.
        $breakdown = $this->gradePoints->pointsBreakdown($event, $mark);

        $row = [
            'position' => $mark->position,
            'grade' => $mark->grade,
            'score' => $mark->score,
            'points' => $breakdown['total'],
            'rank_points' => $breakdown['rank_points'],
            'grade_points' => $breakdown['grade_points'],
            'measurement' => trim(($mark->measurement_value ?? '').' '.($mark->measurement_unit ?? '')),
            'participant' => $person?->name,
            'photo' => $person?->publicPhotoUrl(),
            'photo_fallback' => $person?->publicPhotoFallbackUrl(),
            'reference' => $participant ? $this->visibility->publicReference($event, $participant) : null,
            'school' => $participant?->registration?->school?->name,
        ];

        // Pair/group items: every co-performer on the same registration, not just
        // whichever one the mark happens to be attached to (see note where this is built).
        // Gated on the item actually being a team/pair/group/trio item, not just on a
        // roster having been passed in — several callers (results()'s item tab,
        // schoolResultsRoster(), the landing page's recent-results section) build their
        // $rosterByRegistration unconditionally across every item in view, individual and
        // team alike. An individual item with max_per_school > 1 lets one school register
        // several distinct students under one FestRegistration row
        // (FestRegistrationCreateService::createForSchool()) — without this guard, every
        // one of those students' own placements rendered ALL of them under EVERY
        // placement card for that item, as if they'd competed as a team.
        if ($rosterByRegistration && $participant?->registration_id
            && ($mark->item ?? $participant->registration?->item)?->isTeamItem()) {
            $row['team'] = $rosterByRegistration->get($participant->registration_id, collect())
                ->map(function (FestParticipant $member) {
                    $memberPerson = $member->student ?? $member->teacher;

                    return [
                        'name' => $memberPerson?->name,
                        'photo' => $memberPerson?->publicPhotoUrl(),
                        'photo_fallback' => $memberPerson?->publicPhotoFallbackUrl(),
                    ];
                })
                ->values()
                ->all();
        }

        return $row;
    }

    /**
     * tv_show_overall_standings also gates the Scoreboard page's "All Categories" tab
     * (matching tv()'s own use of the same flag) — when it's off and the visitor didn't
     * ask for a specific category, default to the first one instead of the fest-wide
     * combined view, the same way tv() drops the Overall Standings slide from rotation
     * while still cycling through each category's own board.
     *
     * @param  list<string>  $categories
     */
    private function resolveScoreboardCategory(FestEvent $event, ?string $category, array $categories): ?string
    {
        if ($category === null && ! ($event->tv_show_overall_standings ?? true) && $categories !== []) {
            return $categories[0];
        }

        return $category;
    }

    /**
     * Gold/silver/bronze win-counts per school, keyed by school_id — same source marks
     * tv() uses for its medal columns (top-3 marks, scoped to published items), pulled
     * out here so livePayload() can show the same breakdown without duplicating the
     * query inline a second time. Always feeds the combined "All Categories" board (the
     * only board /live shows), so it honors excluded_overall_categories the same way
     * that combined total already does — without this, an excluded category's podium
     * finishes still showed up in gold/silver/bronze next to a Total Points that
     * correctly left them out.
     */
    private function schoolMedalTally(FestEvent $event, array $eventIds): Collection
    {
        $excludedCategories = FestOverallCategoryExclusion::excluded($event->rootEvent());

        return FestMark::whereIn('event_id', $eventIds)
            ->whereIn('position', [1, 2, 3])
            ->with(['participant.registration.item', 'item'])
            // Unconditional: an item's own results_published_at is the only thing that
            // makes its marks visible to the public — see the note on results()'s $marks
            // query above.
            ->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')->where('results_hidden', false))
            ->get()
            ->filter(fn (FestMark $m) => $m->participant?->registration?->school_id && ! $m->participant->disqualified_at)
            ->filter(fn (FestMark $m) => ! $excludedCategories
                || ! in_array(FestOverallCategoryExclusion::categoryKeyForItem($event, $m->item), $excludedCategories, true))
            ->unique(fn (FestMark $m) => $m->deduplicationKey())
            ->groupBy(fn (FestMark $m) => (string) $m->participant->registration->school_id)
            ->map(fn ($group) => [
                'gold' => $group->where('position', 1)->count(),
                'silver' => $group->where('position', 2)->count(),
                'bronze' => $group->where('position', 3)->count(),
            ]);
    }

    /**
     * The official points standing for a scope/category — scoreboard() plus the
     * cumulative-championship override when one applies. Shared by
     * scoreboardDynamicData() and tv() so the TV's medal-tally boards can never show a
     * different point total than the real Scoreboard page for the same category.
     *
     * @return array{0: array, 1: ?array}
     */
    private function resolveScoreboard(FestEvent $event, array $selectedScope, ?string $category, bool $isPublished, bool $isAdminPreview = false): array
    {
        if (! $isPublished) {
            // When public results visibility is disabled for this event, do not compute
            // or reveal provisional standings/scores on the public portal.
            return [[], null];
        }

        if ($isAdminPreview && ! $selectedScope['results_published']) {
            return [$this->scoreboards->provisionalScoreboard($event, $selectedScope, $category), null];
        }

        $scoreboard = $this->scoreboards->scoreboard($event, $selectedScope, $category);
        $cumulativeStanding = $this->cumulativeChampionship->publicStanding($event, $category);
        if ($cumulativeStanding !== null) {
            $scoreboard = $cumulativeStanding['rows'];
        }

        return [$scoreboard, $cumulativeStanding];
    }

    /**
     * When $event is one phase (or one region-partition child of a phase) of a larger
     * hub, every school's points here are that ONE phase's isolated total — a school's
     * true standing across the whole hub needs every phase summed. Returns a ranked
     * board of every school's cross-phase total, or null when $event's hub doesn't use
     * phases at all, or no phase is visible yet (neither published nor an authorized
     * admin preview) — callers should fall back to their own single-phase/event board
     * in that case.
     *
     * Deliberately sums FestPhaseScoreboardService::phaseScoreboard() (each phase's
     * ISOLATED points), never resolveScoreboard()'s cumulative-championship standing —
     * that is already a running total that carries every earlier phase forward, so
     * summing it across phases would count each earlier phase's points again for every
     * later phase.
     *
     * Gated on each phase's own leaf event(s) results_published, deliberately NOT on
     * FestEventPhase::results_published — that is a separate administrative flag an
     * admin can easily leave off even after the leaf itself is already publicly (or, in
     * preview, visibly) showing results, which would make this total silently drop back
     * to the single-phase number for no visible reason.
     *
     * @return list<array{school_id: string, school_name: string, total_points: float, rank: int}>|null
     */
    private function crossPhaseScoreboard(FestEvent $event, ?string $category, Request $request): ?array
    {
        $hub = $event->rootEvent();
        $phases = FestEventPhase::where('event_id', $hub->id)->get();
        if ($phases->isEmpty()) {
            return null;
        }

        $totals = [];
        $names = [];
        $anyVisible = false;
        foreach ($phases as $phase) {
            $leaves = FestEvent::where('parent_event_id', $hub->id)->where('source_phase_id', $phase->id)->get();
            $leafVisible = $leaves->contains(fn (FestEvent $leaf) => $this->operationalEvents->directScope($leaf)['results_published']
                || $this->isAuthorizedAdminPreview($request, $leaf));
            if (! $leafVisible) {
                continue;
            }
            $anyVisible = true;
            foreach ($this->phaseScoreboards->phaseScoreboard($phase, $category) as $row) {
                $sid = $row['school_id'];
                $totals[$sid] = ($totals[$sid] ?? 0) + (float) $row['total_points'];
                $names[$sid] = $row['school_name'];
            }
        }

        if (! $anyVisible) {
            return null;
        }

        $rank = 0;
        $previousTotal = null;
        $rows = [];
        foreach (collect($totals)->sortDesc() as $schoolId => $total) {
            if ($previousTotal === null || $total < $previousTotal) {
                $rank++;
            }
            $previousTotal = $total;
            $rows[] = [
                'school_id' => $schoolId,
                'school_name' => $names[$schoolId],
                'total_points' => $total,
                'rank' => $rank,
            ];
        }

        return $rows;
    }

    /**
     * The event_ids of every leaf across every VISIBLE phase of $event's hub — the same
     * phase/leaf-visibility gating crossPhaseScoreboard() uses to decide whether to sum
     * points across phases, exposed separately so callers that need to scope a raw
     * FestMark/FestRegistration query (rather than a pre-aggregated scoreboard) can stay
     * consistent with whatever total crossPhaseScoreboard() is showing. Returns null on
     * the same conditions crossPhaseScoreboard() would return null (no phases, or none
     * visible) — callers should fall back to their own single-event scope in that case.
     *
     * @return list<int>|null
     */
    private function crossPhaseVisibleEventIds(FestEvent $event, Request $request): ?array
    {
        $hub = $event->rootEvent();
        $phases = FestEventPhase::where('event_id', $hub->id)->get();
        if ($phases->isEmpty()) {
            return null;
        }

        $eventIds = [];
        foreach ($phases as $phase) {
            $leaves = FestEvent::where('parent_event_id', $hub->id)->where('source_phase_id', $phase->id)->get();
            $visibleLeaves = $leaves->filter(fn (FestEvent $leaf) => $this->operationalEvents->directScope($leaf)['results_published']
                || $this->isAuthorizedAdminPreview($request, $leaf));

            foreach ($visibleLeaves as $leaf) {
                $eventIds[] = $leaf->id;
            }
        }

        return $eventIds ?: null;
    }

    /**
     * The event_ids of every leaf across every phase up to and including $event's own
     * phase (by sort_order) — the scope that FestCumulativeChampionshipService's
     * FestPhaseScoreSnapshot.closing_points was built from (opening_points carries
     * every earlier phase's total forward; see FestCumulativeChampionshipService::
     * publicStanding()'s row mapping). Unlike crossPhaseVisibleEventIds(), this
     * deliberately does NOT re-check each leaf's own results_published — the snapshot
     * is an already-computed admin artifact, not something recomputed live per
     * request, so matching its own scope means including every phase it drew from
     * regardless of that phase's current public-visibility flag. Returns null when
     * $event isn't a phase leaf at all.
     *
     * @return list<int>|null
     */
    private function cumulativeChampionshipEventIds(FestEvent $event): ?array
    {
        if (! $event->parent_event_id || ! $event->source_phase_id) {
            return null;
        }

        $currentPhase = FestEventPhase::find($event->source_phase_id);
        if (! $currentPhase) {
            return null;
        }

        $hub = $event->rootEvent();
        $phasesUpToCurrent = FestEventPhase::where('event_id', $hub->id)
            ->where('sort_order', '<=', $currentPhase->sort_order)
            ->get();

        $eventIds = [];
        foreach ($phasesUpToCurrent as $phase) {
            foreach (FestEvent::where('parent_event_id', $hub->id)->where('source_phase_id', $phase->id)->get() as $leaf) {
                $eventIds[] = $leaf->id;
            }
        }

        return $eventIds ?: null;
    }

    /**
     * Cache::remember() wraps computeScoreboardDynamicData() below — this is the hot
     * path behind both scoreboard() (full page) and scoreboardData() (the JSON endpoint
     * public viewers poll), so a short shared cache directly cuts the DB work Cloudflare's
     * own 5s edge cache can't avoid recomputing on every gap.
     *
     * Bypassed for ANY authenticated request (not just $isAdminPreview): the cross-phase
     * branch below (crossPhaseScoreboard()) runs its own per-SIBLING-LEAF
     * isAuthorizedAdminPreview() check that can broaden the result even when this
     * event/category's own $isAdminPreview is false — see the identical note on
     * results()'s cache. Only genuinely anonymous requests share the cache.
     */
    private function scoreboardDynamicData(FestEvent $event, array $selectedScope, ?string $category, bool $isPublished, bool $isAdminPreview = false, ?Request $request = null): array
    {
        $bypassCache = (bool) ($request?->user() ?? auth()->user());

        $compute = fn () => $this->computeScoreboardDynamicData($event, $selectedScope, $category, $isPublished, $isAdminPreview, $request);

        if ($bypassCache) {
            return $compute();
        }

        $cacheKey = 'fest-scoreboard-dynamic:v2:'.$event->tenant_id.':'.$event->id.':'.($selectedScope['event_id'] ?? $event->id).':'.($category ?? 'all').':'.($isPublished ? '1' : '0');

        return $this->rememberPublicHotPath($cacheKey, 10, $compute, waitSeconds: 15);
    }

    /**
     * Shared-cache single flight for anonymous event-day hot paths. Cache::remember()
     * alone lets every PHP worker recompute the same cold key at once; under a traffic
     * spike that turns one expiry into dozens of identical database-heavy renders.
     */
    private function rememberPublicHotPath(string $key, int $ttlSeconds, callable $compute, int $waitSeconds = 10): mixed
    {
        // A separate has()-then-get() risks the key expiring between the two calls
        // under this method's short TTLs, returning null instead of falling through
        // to recompute — check the value itself, not existence.
        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        $remember = fn () => Cache::remember($key, now()->addSeconds($ttlSeconds), $compute);

        try {
            return Cache::lock('lock:'.$key, max(30, $waitSeconds + 5))->block($waitSeconds, $remember);
        } catch (\Throwable) {
            // A request may have filled the key just before our lock timed out. Recheck
            // before falling back so an unavailable lock driver never breaks the page.
            return Cache::get($key) ?? $remember();
        }
    }

    private function computeScoreboardDynamicData(FestEvent $event, array $selectedScope, ?string $category, bool $isPublished, bool $isAdminPreview = false, ?Request $request = null): array
    {
        [$scoreboard, $cumulativeStanding] = $this->resolveScoreboard($event, $selectedScope, $category, $isPublished, $isAdminPreview);

        // A phase/region-partition scope's own board is isolated to that one phase —
        // "Leading Schools" should reflect each school's true standing across every
        // visible phase of the hub instead. Only the plain scoreboard gets replaced;
        // an existing cumulative-championship $cumulativeStanding (a different,
        // already-hub-wide running total — see resolveScoreboard()) takes precedence
        // when present.
        if ($cumulativeStanding === null && $request) {
            $crossPhaseBoard = $this->crossPhaseScoreboard($event, $category, $request);
            if ($crossPhaseBoard !== null) {
                $scoreboard = $crossPhaseBoard;
            }
        }

        $categoryColumn = $event->event_type === 'sports' ? 'age_group' : 'class_group';
        $winnerMarks = FestMark::whereIn('event_id', $selectedScope['event_ids'])
            ->whereIn('position', [1, 2, 3])
            ->with(['item.head', 'participant.student', 'participant.teacher', 'participant.registration.school'])
            ->latest('updated_at')
            // Raw safety ceiling only, not a "recent teaser" cap — every published item's
            // winners should show here (the widget already scrolls its own container).
            ->limit(200)
            // Whole-event publish gates nothing here — matches show()'s $recentResults:
            // unconditionally require each item's own results_published_at (was
            // previously only enforced when !$isPublished, so a mark from an item that
            // never published on its own would resurface here the moment the event
            // overall did — the same leak class already fixed on show() and scoreboard()).
            ->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')->where('results_hidden', false))
            // Selecting a category tab must scope BOTH panels — without this, Leading
            // Schools filtered to the chosen category while Latest Item Winners kept
            // showing every category's winners, which read as broken/inconsistent.
            ->when($category, fn ($query) => $query->whereHas('item', fn ($q) => $q->where($categoryColumn, $category)))
            ->get();
        $roster = $this->rosterForMarks($winnerMarks);
        // Item titles repeat across different categories/genders (e.g. "Extempore -
        // English" run separately for Category 1 Boys and Category 3 Girls), so a card
        // with just the title is ambiguous about which specific item it is.
        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $latestWinners = $winnerMarks
            ->unique(fn (FestMark $mark) => $mark->deduplicationKey())
            ->groupBy('item_id')
            // One card per ITEM (most recently updated item first), with every one of that
            // item's winners nested inside — not a flat list of winner-rows. A flat list
            // (even grouped-and-adjacent, the previous fix) still made a viewer visually
            // stitch together which cards belonged to the same item; a single item card
            // with its winners listed inside it doesn't require that at all.
            ->sortByDesc(fn ($marksForItem) => $marksForItem->max('updated_at'))
            ->map(function ($marksForItem) use ($event, $roster, $classGroupLabels) {
                $first = $marksForItem->first();

                return [
                    'item_id' => $first->item_id,
                    'item' => $first->item?->title,
                    'head' => $first->item?->head?->name,
                    'participant_type' => $first->item?->participant_type,
                    'category_label' => FestItemCategoryLabel::resolve($first->item, $classGroupLabels, config('fest_item_taxonomy.arts_category', [])),
                    'gender_label' => \App\Support\FestSportsAgeGroup::genderLabel($first->item?->gender),
                    'winners' => $marksForItem->sortBy('position')
                        ->map(fn (FestMark $mark) => $this->publicWinnerRow($mark, $event, $roster))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'scoreboard' => $scoreboard,
            'latestWinners' => $latestWinners,
            'cumulativeStanding' => $cumulativeStanding,
            'isProvisional' => ! $isPublished,
            'showPhasePoints' => collect($scoreboard)->contains(
                fn (array $row) => (int) ($row['event_points'] ?? 0) !== (int) ($row['phase_points'] ?? 0)
            ),
        ];
    }

    /** @return Collection<int|string, Collection<int, FestParticipant>> */
    private function rosterForMarks(Collection $marks): Collection
    {
        return FestParticipant::whereIn(
            'registration_id',
            $marks->pluck('participant.registration_id')->filter()->unique()->values()
        )
            ->where('participant_role', 'performer')
            ->with(['student', 'teacher'])
            ->get()
            ->groupBy('registration_id');
    }

    public function records(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $isAdminPreview = $this->isAuthorizedAdminPreview($request, $event);

        return $this->renderPublic('public.fest.records', $tenant, [
            'event' => $event,
            'records' => $this->publicAthleticRecords($event),
            'breaks' => $this->recentRecordBreaks($event, 50, $isAdminPreview),
            'isAdminPreview' => $isAdminPreview,
        ]);
    }

    public function search(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $selectedScope = $this->operationalEvents->directScope($event);
        $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);
        $isPublished = (bool) $selectedScope['results_published'] || $isAdminPreview;

        // Matches results()/itemResults()/scoreboard()/tv()'s own gate — without this,
        // an event with public results fully disabled (and no item individually
        // published yet) still served this page and, via chest-number/level-reg-number
        // lookup, could surface a participant's item/category/school even though every
        // other public surface correctly shows "disabled" for the same event. Content
        // within the page is separately, unconditionally gated by showParticipantName()/
        // isItemVisible() (both now require the event-wide flag too), so allowing page
        // access here as soon as one item has published doesn't leak anything on its own.
        abort_unless($isPublished || $this->hasPublishedItems($selectedScope['event_ids']), 403, 'Public results are disabled for this event.');

        // Cast: $request->query() returns whatever the client sends for this key, including an
        // array (e.g. ?q[]=x), which would fatally TypeError trim(). This is a public,
        // unauthenticated endpoint, so it must not trust the type of client input.
        $q = trim((string) $request->query('q', ''));

        $results = collect();
        if (strlen($q) >= 1) {
            $base = FestParticipant::whereHas('registration', fn ($r) => $r
                ->where('event_id', $event->id)
                ->where('status', 'approved'))
                ->with(['student', 'teacher', 'registration.item', 'registration.event', 'registration.school']);

            if (ctype_digit($q)) {
                // A numeric query can legitimately be either a chest number or a level
                // registration number. Return both instead of letting one namespace hide
                // the other when the same number exists in each.
                $matches = (clone $base)
                    ->where(fn ($query) => $query
                        ->where('chest_no', (int) $q)
                        ->orWhere('level_registration_number', $q))
                    ->limit(30)
                    ->get();
                if ($matches->isEmpty()) {
                    // Before reveal, the chest number shown/linked publicly is a *computed*
                    // preview (FestNumberingService::effectiveChestNumber()) that isn't a
                    // persisted column — see the matching note on findParticipantByRef().
                    $numbering = app(FestNumberingService::class);
                    $matches = $base->get()
                        ->filter(fn (FestParticipant $p) => $numbering->effectiveChestNumber($p) === (int) $q)
                        ->take(30);
                }
            } elseif (preg_match('/^[A-Za-z]-\d+$/', $q)) {
                $matches = $base->where('level_registration_number', strtoupper($q))->limit(30)->get();
            } elseif ($this->visibility->allowNameSearch($event, $isAdminPreview)) {
                $matches = $base->where(function ($inner) use ($q) {
                    $inner->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$q}%"));
                })->limit(30)->get();
            } else {
                $matches = collect();
            }

            $showSchool = $this->visibility->showSchoolName($event, $isAdminPreview);
            $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
            $results = $matches
                // A student has one FestParticipant row per registration. Showing each
                // row separately made one person appear several times and amplified the
                // ambiguous-reference bug fixed in participantLinkRef().
                ->groupBy(fn (FestParticipant $p) => $p->student_id
                    ? 'student-'.$p->student_id
                    : ($p->teacher_id ? 'teacher-'.$p->teacher_id : 'participant-'.$p->id))
                ->map(function (Collection $entries) use ($event, $isAdminPreview, $showSchool, $classGroupLabels) {
                    /** @var FestParticipant $participant */
                    $participant = $entries->sortBy('id')->first();
                    $public = $this->visibility->formatPublicParticipant($event, $participant, null, null, $isAdminPreview);
                    $matchedItems = $entries
                        ->map(fn (FestParticipant $entry) => [
                            'title' => $entry->registration?->item?->title,
                            'category' => FestItemCategoryLabel::resolve(
                                $entry->registration?->item,
                                $classGroupLabels,
                                config('fest_item_taxonomy.arts_category', [])
                            ),
                        ])
                        ->filter(fn (array $item) => filled($item['title']))
                        ->unique('title')
                        ->values()
                        ->all();

                    return $public + [
                        'school' => $showSchool ? $participant->registration?->school?->name : null,
                        'matched_items' => $matchedItems,
                        'item_count' => count($matchedItems),
                    ];
                })
                ->take(30)
                ->values();
        }

        return $this->renderPublic('public.fest.search', $tenant, [
            'event' => $event,
            'q' => $q,
            'results' => $results,
            'searchHint' => $this->visibility->searchPlaceholder($event, $isAdminPreview),
            'nameSearch' => $this->visibility->allowNameSearch($event, $isAdminPreview),
            'isAdminPreview' => $isAdminPreview,
        ]);
    }

    public function participant(Request $request, int $eventId, string $ref)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $selectedScope = $this->operationalEvents->directScope($event);
        $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);
        $isPublished = (bool) $selectedScope['results_published'] || $isAdminPreview;

        // Same gate as search()/results()/itemResults()/scoreboard()/tv(): a direct link
        // to a participant page must not bypass the event's public-disable lock, even
        // though formatPublicParticipant()/publicParticipantItems() already hide the
        // name/marks for any item that isn't individually published (and, now, also
        // require the event-wide flag itself — see showParticipantName()/isItemVisible()).
        abort_unless($isPublished || $this->hasPublishedItems($selectedScope['event_ids']), 403, 'Public results are disabled for this event.');

        $participant = $this->visibility->findParticipantByRef($event, $ref);
        abort_unless($participant, 404);

        $mark = FestMark::where('participant_id', $participant->id)->first();
        $schedule = FestSchedule::where('participant_id', $participant->id)->first();

        $public = $this->visibility->formatPublicParticipant($event, $participant, $schedule, $mark, $isAdminPreview);
        // Cross-phase: a championship/results page linking here already shows this
        // student's combined standing across every phase, so their own page should
        // list every item across those same phases too, not just the one leaf $event
        // happened to be reached through.
        $items = $this->visibility->publicParticipantItems($event, $participant, $isAdminPreview, acrossPhases: true);

        return $this->renderPublic('public.fest.participant', $tenant, compact(
            'event', 'public', 'participant', 'schedule', 'mark', 'items'
        ) + ['isAdminPreview' => $isAdminPreview]);
    }

    /** @return list<array<string, mixed>> */
    private function mapScheduleRows(FestEvent $event, ?int $itemId = null, ?array $eventIds = null, bool $isAdminPreview = false): array
    {
        $query = FestSchedule::whereIn('event_id', $eventIds ?? [(int) $event->id])
            ->with(['item', 'participant.student', 'participant.teacher', 'participant.registration.item', 'participant.registration.event']);

        if ($itemId) {
            $query->where('item_id', $itemId);
        }

        $rows = $query->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->get();

        // Pair/group/team/trio items get one FestSchedule row PER PERFORMER (see
        // FestScheduleController::autoGenerate()), so without grouping, an 11-person
        // choir would repeat the same item 11 times back to back with one name each.
        // Group by registration so the public schedule shows one slot per performance,
        // with the full roster attached, matching how results()/itemResults() already
        // resolve rosters for the same reason.
        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());

        return $rows
            ->groupBy(fn (FestSchedule $row) => $row->participant?->registration_id ?? 'solo-'.$row->id)
            ->map(function (Collection $group) use ($event, $isAdminPreview, $classGroupLabels) {
                $first = $group->first();
                // showParticipantName()'s $item param gates on THIS item's own publish
                // state, not just the event-wide results_published flag — that flag flips
                // once for the whole event's final "Publish Results" action, which would
                // otherwise reveal every participant across every item (including ones
                // that haven't run/been judged yet) the moment any part of the event is
                // finalized. The schedule's job is logistics (when does it happen);
                // identity belongs on that item's own results page, after that item
                // specifically has published.
                $showName = $first->participant
                    && $this->visibility->showParticipantName($event, $first->participant, $first->item, $isAdminPreview);

                $roster = $group->pluck('participant')
                    ->filter()
                    ->map(fn (FestParticipant $p) => $p->student?->name ?? $p->teacher?->name)
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'scheduled_at' => $first->scheduled_at,
                    'item_id' => $first->item_id,
                    'item_title' => $first->item?->title,
                    'category_label' => FestItemCategoryLabel::resolve($first->item, $classGroupLabels, config('fest_item_taxonomy.arts_category', [])),
                    'gender_label' => \App\Support\FestSportsAgeGroup::genderLabel($first->item?->gender),
                    'results_published_at' => $first->item?->results_published_at,
                    'results_hidden' => (bool) $first->item?->results_hidden,
                    'stage' => $first->stage,
                    'sort_order' => $first->sort_order,
                    'participant' => $first->participant
                        ? $this->visibility->formatPublicParticipant($event, $first->participant, $first)
                        : null,
                    'roster' => $showName ? $roster : [],
                    'roster_count' => $group->pluck('participant')->filter()->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Whether any item in this scope has been individually published (and not since
     * hidden again) — the gate for "is there anything at all to show here" on a page
     * that isn't behind the whole-event results_published flag yet. A school shouldn't
     * get a blanket 403 on /results, /tv, /search, or /participant just because the
     * official "Publish Results" action hasn't run, as long as at least one item has
     * already published on its own — page ACCESS only; actual content is separately,
     * unconditionally gated by showParticipantName()/showIndividualMarks()/
     * isItemVisible() (all of which also require the event-wide flag itself).
     */
    private function hasPublishedItems(array $eventIds): bool
    {
        return FestEventItem::whereIn('event_id', $eventIds)
            ->whereNotNull('results_published_at')
            ->where('results_hidden', false)
            ->exists();
    }

    private function findEvent(string $tenantId, int $eventId): FestEvent
    {
        $event = FestEvent::where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->whereIn('status', PublicOperationalEventService::PUBLIC_STATUSES)
            ->firstOrFail();

        abort_unless($this->operationalEvents->isPubliclyAccessible($event), 404);

        return $event;
    }

    private function stringQuery(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<array<string, mixed>> */
    private function publicAthleticRecords(FestEvent $event): array
    {
        if (! $event->record_tracking_enabled) {
            return [];
        }

        return FestAthleticRecord::where('event_id', $event->id)
            ->with('item')
            ->orderBy('item_id')
            ->get()
            ->map(fn (FestAthleticRecord $r) => [
                'item' => $r->item?->title,
                'class_group' => strtoupper($r->class_group),
                'gender' => ucfirst($r->gender),
                'value' => $r->record_value,
                'unit' => $r->record_unit,
                'holder' => $r->holder_name,
            ])
            ->all();
    }

    /**
     * Unlike publicAthleticRecords() (the standing all-time record table, always visible
     * once tracking is on), a record BREAK names who broke it and when during THIS
     * event's own competition — the same kind of result data everything else on this
     * page gates behind item visibility, so an unpublished (or explicitly hidden) item's
     * winner shouldn't be identifiable here just because record tracking is enabled.
     *
     * @return list<array<string, mixed>>
     */
    private function recentRecordBreaks(FestEvent $event, int $limit = 5, bool $isAdminPreview = false): array
    {
        if (! $event->record_tracking_enabled) {
            return [];
        }

        $itemResults = app(FestItemResultsService::class);

        return FestRecordBreak::where('event_id', $event->id)
            ->with(['item', 'participant.student'])
            ->when(! $isAdminPreview, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('results_hidden', false)))
            ->orderByDesc('broken_at')
            ->get()
            ->filter(fn (FestRecordBreak $b) => $isAdminPreview || ($b->item && $itemResults->isItemVisible($b->item, $event)))
            ->take($limit)
            ->map(fn (FestRecordBreak $b) => [
                'item' => $b->item?->title,
                'name' => $b->participant?->student?->name ?? $b->participant?->teacher?->name,
                'new_value' => $b->new_value,
                'unit' => $b->record_unit,
                'prize_label' => $b->prize_label,
                'broken_at' => $b->broken_at?->format('d M Y H:i'),
            ])
            ->all();
    }

    private function isAuthorizedAdminPreview(Request $request, FestEvent $event): bool
    {
        $user = $request->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        if (method_exists($user, 'isStateUser') && $user->isStateUser()) {
            return true;
        }

        if (isset($user->tenant_id) && $user->tenant_id === $event->tenant_id) {
            return true;
        }

        try {
            if ($user->hasAnyRole(['sahodaya_admin', 'sahodaya_staff', 'event_admin', 'fest_ops', 'mark_entry_admin', 'exam_controller'])) {
                return true;
            }
        } catch (\Throwable) {
        }

        return false;
    }
}
