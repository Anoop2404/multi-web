<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\FestAthleticRecord;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestIndividualChampionshipPoint;
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
use App\Services\Events\FestNumberingService;
use App\Services\Events\FestPhaseScoreboardService;
use App\Services\Events\FestPublicVisibilityService;
use App\Services\Events\FestWinnerPosterService;
use App\Services\Events\PublicFestScoreboardService;
use App\Services\Events\PublicOperationalEventService;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

        $itemGroups = FestEventItem::where('event_id', $event->id)
            ->with('head:id,name')
            ->orderBy('display_order')
            ->get(['id', 'title', 'stage_type', 'category', 'class_group', 'age_group', 'participant_type', 'head_id', 'event_id', 'results_published_at'])
            ->groupBy('event_id')
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

        $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);
        $isResultsPublished = (bool) $selectedScope['results_published'] || $isAdminPreview;

        $recentResults = collect();
        $publishedItemCount = 0;

        if ($isResultsPublished) {
            $recentMarks = FestMark::where('event_id', $event->id)
                ->whereIn('position', [1, 2, 3])
                ->with(['item', 'participant.student', 'participant.teacher', 'participant.registration.school'])
                ->latest('updated_at')
                ->limit(200)
                ->get();
            $recentRoster = $this->rosterForMarks($recentMarks);
            $recentResults = $recentMarks
                ->unique(fn (FestMark $mark) => $mark->deduplicationKey())
                ->groupBy('item_id')
                ->sortByDesc(fn ($marksForItem) => $marksForItem->max('updated_at'))
                ->map(function ($marksForItem) use ($event, $recentRoster) {
                    $first = $marksForItem->first();

                    return [
                        'item_id' => $first->item_id,
                        'item' => $first->item?->title,
                        'participant_type' => $first->item?->participant_type,
                        'winners' => $marksForItem->sortBy('position')
                            ->map(fn (FestMark $mark) => $this->publicWinnerRow($mark, $event, $recentRoster))
                            ->values()
                            ->all(),
                    ];
                })
                ->values();

            $publishedItemCount = FestEventItem::where('event_id', $event->id)
                ->whereNotNull('results_published_at')
                ->count();
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
            'pageSeo' => ['title' => $event->title.' — '.$tenant->name],
        ]);
    }

    public function results(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $selectedScope = $this->operationalEvents->directScope($event);

        $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);
        $isPublished = (bool) $selectedScope['results_published'] || $isAdminPreview;

        abort_unless($isPublished, 403, 'Public scoreboard & results are disabled for this event.');

        $scopes = [$selectedScope];
        $tab = $request->query('tab', $selectedScope['results_published'] ? 'school' : 'item');
        if (! in_array($tab, ['toppers', 'school', 'category', 'item', 'individual', 'championship'], true)) {
            $tab = $selectedScope['results_published'] ? 'school' : 'item';
        }

        $publishedAt = FestResult::whereIn('event_id', $selectedScope['event_ids'])
            ->whereNull('item_id')
            ->max('published_at');

        // FestIndividualChampionshipPoint is a stored aggregate across every item in the
        // event (recalculated on demand by an admin action), not a live per-item query —
        // unlike the school scoreboard there's no "provisional, published-items-only"
        // variant to fall back to, so the whole tab stays empty until the event's
        // official publish has actually run (matches how $overallBoard/$categoryBoards
        // being empty pre-publish is already handled further down).
        $championshipEventId = $selectedScope['event_id'] ?: $event->id;
        $championshipRows = $isPublished
            ? FestIndividualChampionshipPoint::where('event_id', $championshipEventId)
                ->with(['student'])
                ->orderByDesc('points')
                ->orderByDesc('group_points')
                ->orderBy('student_id')
                ->get()
            : collect();
        $championshipSchools = Tenant::whereIn('id', $championshipRows->pluck('student.tenant_id')->filter()->unique())
            ->pluck('name', 'id');
        $championship = $championshipRows
            ->map(function (FestIndividualChampionshipPoint $row, int $index) use ($championshipSchools) {

                return [
                    'rank' => $index + 1,
                    'points' => $row->points,
                    'category' => $row->category,
                    'gender' => $row->gender,
                    'student' => $row->student?->name,
                    'photo' => $row->student?->photoDataUri(),
                    'reg_no' => $row->student?->reg_no,
                    'school' => $championshipSchools[$row->student?->tenant_id] ?? null,
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
            ->when(! $isPublished, fn ($query) => $query->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')))
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
                    'participant_type' => $first->item?->participant_type,
                    'stage_type' => $first->item?->stage_type,
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

        $individualResults = $marks
            ->map(fn (FestMark $mark) => $this->publicWinnerRow($mark, $event) + [
                'item' => $mark->item?->title,
                'head' => $mark->item?->head?->name,
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
        $medalTally = $marks
            ->filter(fn (FestMark $m) => $m->participant?->registration?->school_id && ! $m->participant->disqualified_at)
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

        return $this->renderPublic('public.fest.results', $tenant, [
            'event' => $event,
            'eventContext' => $this->operationalEvents->publicContext($event),
            'tab' => $tab,
            'schoolBoard' => $schoolBoard,
            'schoolWinnersBoard' => $schoolWinnersBoard,
            'usesPhases' => $usesPhases,
            'phaseCumulativeBoard' => $phaseCumulativeBoard,
            'phaseBreakdown' => $phaseBreakdown,
            'categoryBoards' => $categoryBoards,
            'itemResultsByCategory' => $itemResultsByCategory,
            'individualResults' => $individualResults,
            'championship' => $championship,
            'publishedAt' => $publishedAt,
            'scopes' => $scopes,
            'selectedScope' => $selectedScope,
            'lockedCumulativeStanding' => $lockedCumulativeStanding,
            'showPhasePoints' => $showPhasePoints,
            'overallSchoolToppers' => $overallSchoolToppers,
            'schoolCategoryToppers' => $schoolCategoryToppers,
            'studentCategoryToppers' => $studentCategoryToppers,
            'pageSeo' => ['title' => $event->title.' — Results'],
        ]);
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

        return $this->renderPublic('public.fest.school-results', $tenant, [
            'event' => $event,
            'eventContext' => $this->operationalEvents->publicContext($event),
            'school' => $school,
            'schoolRow' => $schoolRow,
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

        // Deliberately a separate query from results()'s own $marks (which stays
        // top-3-only — it also feeds the item/individual/medal-tally tabs, where
        // "winners only" is the correct scope), not a reuse of it.
        $allSchoolMarks = FestMark::whereIn('event_id', $selectedScope['event_ids'])
            ->when(! $isPublished, fn ($query) => $query->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')))
            // Only set when schoolResults() was reached from a category-filtered
            // scoreboard — narrows the roster to that one category instead of the
            // school's full cross-category report.
            ->when($category, fn ($query) => $query->whereHas('item', fn ($q) => $q->where($categoryColumn, $category)))
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
        EventLifecycleGate::allowPublicSchedule($event);
        $selectedScope = $this->operationalEvents->directScope($event);

        $isAdminPreview = $this->isAuthorizedAdminPreview($request, $event);
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
        EventLifecycleGate::allowPublicSchedule($event);

        $isAdminPreview = $this->isAuthorizedAdminPreview($request, $event);
        $schedules = $this->mapScheduleRows($event, $item->id, [$event->id], $isAdminPreview);

        return $this->renderPublic('public.fest.item-schedule', $tenant, compact('event', 'item', 'schedules') + ['isAdminPreview' => $isAdminPreview]);
    }

    public function itemResults(Request $request, int $eventId, FestEventItem $item)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless((int) $item->event_id === (int) $event->id, 404);

        $isAdminPreview = ! $event->results_published && $this->isAuthorizedAdminPreview($request, $event);
        $isPublished = (bool) $event->results_published || $isAdminPreview;

        abort_unless($isPublished, 403, 'Public results are disabled for this event.');

        $allMarks = FestMark::where('event_id', $item->event_id)
            ->where('item_id', $item->id)
            ->with(['participant.student', 'participant.teacher', 'participant.registration.school'])
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

        // Winner Roster (photo podium) stays capped to the top 3; the Full Results table
        // below it lists everyone, including non-placing participants — one query/roster
        // resolution feeds both instead of running this twice.
        $allMarks = $allMarks->map(fn (FestMark $m) => $this->publicWinnerRow($m, $event, $rosterByRegistration) + [
            'mark_id' => $m->id,
            'poster_url' => in_array((int) $m->position, [1, 2, 3], true)
                ? route('tenant.fest.winner-poster', [$event->id, $item->id, $m->id])
                : null,
        ])->values();

        $marks = $allMarks->filter(fn (array $row) => in_array((int) ($row['position'] ?? 0), [1, 2, 3], true))->values();

        return $this->renderPublic('public.fest.item-results', $tenant, compact('event', 'item', 'marks', 'allMarks'));
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

        $marks = FestMark::where('event_id', $item->event_id)
            ->where('item_id', $item->id)
            ->with(['participant.student', 'participant.teacher', 'participant.registration.school'])
            ->orderBy('position')
            ->orderByDesc('score')
            ->get();

        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($item->title)) ?: 'item';

        return Pdf::loadView('fest.reports.item-wise', [
        'orgName' => $tenant->name ?? 'Sahodaya',
        'logoSrc' => TenantBranding::logoEmbedSrc($tenant),
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

    $categoryLabels = collect($categories)
        ->mapWithKeys(fn (string $key) => [$key => $this->scoreboards->categoryLabel($event, $key)])
        ->all();
    $dynamic = $this->scoreboardDynamicData($event, $selectedScope, $category, $isPublished, $isAdminPreview);
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

    $isAdminPreview = ! $selectedScope['results_published'] && $this->isAuthorizedAdminPreview($request, $event);
    $isPublished = (bool) $selectedScope['results_published'] || $isAdminPreview;

    $dynamic = $this->scoreboardDynamicData($event, $selectedScope, $category, $isPublished, $isAdminPreview);

    return response()->json([
        'standingsPublished' => $isPublished,
        'isAdminPreview' => $isAdminPreview,
        'contentHtml' => view('public.fest.partials.scoreboard-content', $dynamic + compact('event', 'isPublished', 'category', 'isAdminPreview'))->render(),
        'refreshedAt' => now()->toIso8601String(),
    ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
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

    abort_unless($isPublished, 403, 'Public scoreboard is disabled for this event.');
    $categories = $this->scoreboards->categories($event, $selectedScope);

    $marks = FestMark::whereIn('event_id', $selectedScope['event_ids'])
        ->whereIn('position', [1, 2, 3])
        ->with(['item', 'participant.registration.school'])
        // Same fallback as resolveScoreboard()/scoreboardDynamicData(): before the
        // whole event is published, only count marks whose own item has published —
        // so the medal columns line up with the provisional points column instead of
        // sitting at 0 while points already show real numbers.
        ->when(! $isPublished, fn ($query) => $query->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')))
        ->get()
        ->filter(fn (FestMark $m) => $m->participant?->registration?->school_id && ! $m->participant->disqualified_at)
        ->unique(fn (FestMark $m) => $m->deduplicationKey());

    $categoryColumn = $event->event_type === 'sports' ? 'age_group' : 'class_group';
    $medalTallyFor = fn ($scopedMarks) => $scopedMarks
        ->groupBy(fn (FestMark $m) => (string) $m->participant->registration->school_id)
        ->map(fn ($group) => [
            'gold' => $group->where('position', 1)->count(),
            'silver' => $group->where('position', 2)->count(),
            'bronze' => $group->where('position', 3)->count(),
        ]);

    $withMedals = fn (array $rows, $tally) => collect($rows)
        ->map(fn (array $row) => $row + [
            'gold' => $tally[$row['school_id']]['gold'] ?? 0,
            'silver' => $tally[$row['school_id']]['silver'] ?? 0,
            'bronze' => $tally[$row['school_id']]['bronze'] ?? 0,
        ])
        ->values()
        ->all();

    [$overallScoreboard] = $this->resolveScoreboard($event, $selectedScope, null, $isPublished, $isAdminPreview);
    $overallBoard = $withMedals($overallScoreboard, $medalTallyFor($marks));

    $categoryBoards = collect($categories)
        ->map(function (string $key) use ($event, $selectedScope, $isPublished, $isAdminPreview, $marks, $categoryColumn, $withMedals, $medalTallyFor) {
            [$scoreboard] = $this->resolveScoreboard($event, $selectedScope, $key, $isPublished, $isAdminPreview);

            return [
                'key' => $key,
                'label' => $this->scoreboards->categoryLabel($event, $key),
                'rows' => $withMedals(
                    $scoreboard,
                    $medalTallyFor($marks->filter(fn (FestMark $m) => ($m->item?->{$categoryColumn}) === $key))
                ),
            ];
        })
        ->filter(fn (array $board) => count($board['rows']) > 0)
        ->values()
        ->all();

    $dynamic = $this->scoreboardDynamicData($event, $selectedScope, null, $isPublished, $isAdminPreview);

    // Pre-chunked into fixed-size, non-scrolling pages server-side — nobody is at the
    // TV to scroll a tall list, so "Page N of M" slides stand in for scroll the same
    // way pagination would on a normal page. 1 item/page: with 2+ side by side, CSS
    // grid's default row-stretch makes a short 1-position card match its taller
    // 2-position row-mate, so a page's height was driven by whichever item happened
    // to share its row — measured a page hit 1555px in a 1080px viewport this way.
    // One item per page removes the row-mate entirely, so each slide is exactly its
    // own item's height. A single item with 3+ awarded positions and a large roster
    // can still exceed one screen on its own; left as a rare residual case rather
    // than building full dynamic height-measured pagination for it.
    $boardsPerPage = 12;
    $winnersPerPage = 1;
    $slides = [];

    // Order: overall school ranking, then each category's ranking, then results —
    // standings open the rotation so the "big picture" leads, results follow as the
    // detail underneath it.
    $provisionalSuffix = $isPublished ? '' : ' · Provisional';

    $overallPages = array_chunk($overallBoard, $boardsPerPage);
    foreach ($overallPages as $i => $page) {
        $slides[] = [
            'type' => 'board',
            'title' => 'Overall Standings',
            'subtitle' => (count($overallPages) > 1 ? 'Page '.($i + 1).' of '.count($overallPages) : 'All Categories').$provisionalSuffix,
            'rows' => $page,
        ];
    }

    foreach ($categoryBoards as $board) {
        $categoryPages = array_chunk($board['rows'], $boardsPerPage);
        foreach ($categoryPages as $i => $page) {
            $slides[] = [
                'type' => 'board',
                'title' => $board['label'].' Standings',
                'subtitle' => trim((count($categoryPages) > 1 ? 'Page '.($i + 1).' of '.count($categoryPages) : '').$provisionalSuffix, ' ·') ?: null,
                'rows' => $page,
            ];
        }
    }

    $winnerPages = array_chunk($dynamic['latestWinners'], $winnersPerPage);
    foreach ($winnerPages as $i => $page) {
        $slides[] = [
            'type' => 'winners',
            'title' => 'Latest Item Winners',
            'subtitle' => count($winnerPages) > 1 ? 'Page '.($i + 1).' of '.count($winnerPages) : null,
            'items' => $page,
        ];
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
            'pageSeo' => ['title' => $event->title.' — Live Screen'],
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

    /** @return array<string, mixed> */
    private function livePayload(Request $request, FestEvent $event, array $selectedScope): array
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
        }

        $scoreboard = [];
        if ($isPublished) {
            $rawScoreboard = $this->cumulativeChampionship->publicStanding($event)['rows']
                ?? ($selectedScope['results_published']
                    ? $this->scoreboards->scoreboard($event, $selectedScope)
                    : $this->scoreboards->provisionalScoreboard($event, $selectedScope));

            $medalTally = $this->schoolMedalTally($selectedScope['event_ids'], true);
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
            'categoryLinks' => $categoryLinks,
            'houseScoreboard' => $isPublished
                ? $ctx->scoreboardByHouse()
                : [],
            'nowPerforming' => $nowPerforming,
            'athleticRecords' => $this->publicAthleticRecords($event),
            'recentBreaks' => $this->recentRecordBreaks($event),
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
            'photo' => $person?->photoDataUri(),
            'reference' => $participant ? $this->visibility->publicReference($event, $participant) : null,
            'school' => $participant?->registration?->school?->name,
        ];

        // Pair/group items: every co-performer on the same registration, not just
        // whichever one the mark happens to be attached to (see note where this is built).
        if ($rosterByRegistration && $participant?->registration_id) {
            $row['team'] = $rosterByRegistration->get($participant->registration_id, collect())
                ->map(function (FestParticipant $member) {
                    $memberPerson = $member->student ?? $member->teacher;

                    return [
                        'name' => $memberPerson?->name,
                        'photo' => $memberPerson?->photoDataUri(),
                    ];
                })
                ->values()
                ->all();
        }

        return $row;
    }

    /** @return array<string, mixed> */
    /**
     * The official points standing for a scope/category — scoreboard() plus the
     * cumulative-championship override when one applies. Shared by
     * scoreboardDynamicData() and tv() so the TV's medal-tally boards can never show a
     * different point total than the real Scoreboard page for the same category.
     *
     * @return array{0: array, 1: ?array}
     */
    /**
     * Gold/silver/bronze win-counts per school, keyed by school_id — same source marks
     * tv() uses for its medal columns (top-3 marks, scoped to published items when the
     * whole event isn't published yet), pulled out here so livePayload() can show the
     * same breakdown without duplicating the query inline a second time.
     */
    private function schoolMedalTally(array $eventIds, bool $isPublished): Collection
    {
        return FestMark::whereIn('event_id', $eventIds)
            ->whereIn('position', [1, 2, 3])
            ->with(['participant.registration.item', 'item'])
            ->when(! $isPublished, fn ($query) => $query->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')))
            ->get()
            ->filter(fn (FestMark $m) => $m->participant?->registration?->school_id && ! $m->participant->disqualified_at)
            ->unique(fn (FestMark $m) => $m->deduplicationKey())
            ->groupBy(fn (FestMark $m) => (string) $m->participant->registration->school_id)
            ->map(fn ($group) => [
                'gold' => $group->where('position', 1)->count(),
                'silver' => $group->where('position', 2)->count(),
                'bronze' => $group->where('position', 3)->count(),
            ]);
    }

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

    private function scoreboardDynamicData(FestEvent $event, array $selectedScope, ?string $category, bool $isPublished, bool $isAdminPreview = false): array
    {
        [$scoreboard, $cumulativeStanding] = $this->resolveScoreboard($event, $selectedScope, $category, $isPublished, $isAdminPreview);

        $categoryColumn = $event->event_type === 'sports' ? 'age_group' : 'class_group';
        $winnerMarks = FestMark::whereIn('event_id', $selectedScope['event_ids'])
            ->whereIn('position', [1, 2, 3])
            ->with(['item.head', 'participant.student', 'participant.teacher', 'participant.registration.school'])
            ->latest('updated_at')
            // Raw safety ceiling only, not a "recent teaser" cap — every published item's
            // winners should show here (the widget already scrolls its own container).
            ->limit(200)
            // Whole-event publish gates nothing here — matches show()'s $recentResults:
            // fall back to each item's own results_published_at so items published ahead
            // of the official whole-event publish still show their winners.
            ->when(! $isPublished, fn ($query) => $query->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')))
            // Selecting a category tab must scope BOTH panels — without this, Leading
            // Schools filtered to the chosen category while Latest Item Winners kept
            // showing every category's winners, which read as broken/inconsistent.
            ->when($category, fn ($query) => $query->whereHas('item', fn ($q) => $q->where($categoryColumn, $category)))
            ->get();
        $roster = $this->rosterForMarks($winnerMarks);
        $latestWinners = $winnerMarks
            ->unique(fn (FestMark $mark) => $mark->deduplicationKey())
            ->groupBy('item_id')
            // One card per ITEM (most recently updated item first), with every one of that
            // item's winners nested inside — not a flat list of winner-rows. A flat list
            // (even grouped-and-adjacent, the previous fix) still made a viewer visually
            // stitch together which cards belonged to the same item; a single item card
            // with its winners listed inside it doesn't require that at all.
            ->sortByDesc(fn ($marksForItem) => $marksForItem->max('updated_at'))
            ->map(function ($marksForItem) use ($event, $roster) {
                $first = $marksForItem->first();

                return [
                    'item_id' => $first->item_id,
                    'item' => $first->item?->title,
                    'head' => $first->item?->head?->name,
                    'participant_type' => $first->item?->participant_type,
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
            'breaks' => $this->recentRecordBreaks($event, 50),
            'isAdminPreview' => $isAdminPreview,
        ]);
    }

    public function search(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $isAdminPreview = $this->isAuthorizedAdminPreview($request, $event);

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
                // Fast path: chest number officially revealed and persisted to the column.
                $matches = (clone $base)->where('chest_no', (int) $q)->limit(30)->get();
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
            $results = $matches->map(fn (FestParticipant $p) => $this->visibility->formatPublicParticipant($event, $p, null, null, $isAdminPreview) + [
                'school' => $showSchool ? $p->registration?->school?->name : null,
            ]);
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
        $isAdminPreview = $this->isAuthorizedAdminPreview($request, $event);

        $participant = $this->visibility->findParticipantByRef($event, $ref);
        abort_unless($participant, 404);

        $mark = FestMark::where('participant_id', $participant->id)->first();
        $schedule = FestSchedule::where('participant_id', $participant->id)->first();

        $public = $this->visibility->formatPublicParticipant($event, $participant, $schedule, $mark, $isAdminPreview);
        $items = $this->visibility->publicParticipantItems($event, $participant, $isAdminPreview);

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
        return $rows
            ->groupBy(fn (FestSchedule $row) => $row->participant?->registration_id ?? 'solo-'.$row->id)
            ->map(function (Collection $group) use ($event, $isAdminPreview) {
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

    /** @return list<array<string, mixed>> */
    private function recentRecordBreaks(FestEvent $event, int $limit = 5): array
    {
        if (! $event->record_tracking_enabled) {
            return [];
        }

        return FestRecordBreak::where('event_id', $event->id)
            ->with(['item', 'participant.student'])
            ->orderByDesc('broken_at')
            ->limit($limit)
            ->get()
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
