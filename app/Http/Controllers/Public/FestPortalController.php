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
use App\Models\FestResult;
use App\Models\FestSchedule;
use App\Models\Tenant;
use App\Services\Events\EventContext;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestCumulativeChampionshipService;
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
    ) {}

    public function index()
    {
        $tenant = $this->resolveTenant();

        $events = $this->operationalEvents->listedForTenant($tenant->id);
        $eventGroups = $this->operationalEvents->catalogueGroups($events);

        return $this->renderPublic('public.fest.index', $tenant, compact('events', 'eventGroups'));
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
            ->get(['id', 'title', 'stage_type', 'category', 'class_group', 'age_group', 'participant_type', 'head_id', 'event_id'])
            ->groupBy('event_id')
            ->map(fn ($groupItems) => [
                'label' => $event->title,
                'items' => $groupItems->values(),
            ])
            ->values();

        $recentMarks = FestMark::where('event_id', $event->id)
            ->whereIn('position', [1, 2, 3])
            ->with(['item', 'participant.student', 'participant.teacher', 'participant.registration.school'])
            ->latest('updated_at')
            ->limit(30)
            ->when(! $selectedScope['results_published'], fn ($query) => $query->whereRaw('1 = 0'))
            ->get();
        $recentRoster = $this->rosterForMarks($recentMarks);
        $recentResults = $recentMarks
            ->unique(fn (FestMark $mark) => $mark->participant?->registration_id ?? $mark->id)
            ->take(6)
            ->map(fn (FestMark $mark) => $this->publicWinnerRow($mark, $event, $recentRoster) + [
                'item_id' => $mark->item_id,
                'item' => $mark->item?->title,
                'participant_type' => $mark->item?->participant_type,
            ])
            ->values();

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
            'scopeResultsPublished' => (bool) $selectedScope['results_published'],
            'scopeSchedulePublished' => (bool) $selectedScope['schedule_published'],
            'pageSeo' => ['title' => $event->title.' — '.$tenant->name],
        ]);
    }

    public function results(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless($event->results_published, 404);

        $selectedScope = $this->operationalEvents->directScope($event);
        abort_unless($selectedScope['results_published'], 404);
        $scopes = [$selectedScope];
        $tab = $request->query('tab', 'school');
        if (! in_array($tab, ['toppers', 'school', 'category', 'item', 'individual', 'championship'], true)) {
            $tab = 'school';
        }

        $publishedAt = FestResult::whereIn('event_id', $selectedScope['event_ids'])
            ->whereNull('item_id')
            ->max('published_at');

        $championshipEventId = $selectedScope['event_id'] ?: $event->id;
        $championshipRows = FestIndividualChampionshipPoint::where('event_id', $championshipEventId)
            ->with(['student'])
            ->orderByDesc('points')
            ->orderBy('student_id')
            ->get();
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
            ->map(fn (string $key) => [
                'key' => $key,
                'label' => $this->scoreboards->categoryLabel($event, $key),
                'rows' => $this->cumulativeChampionship->publicStanding($event, $key)['rows']
                    ?? $this->scoreboards->scoreboard($event, $selectedScope, $key),
            ])
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
                        ->unique(fn (FestMark $mark) => $mark->participant?->registration_id ?? $mark->id)
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
            ->unique(fn (FestMark $m) => $m->participant->registration_id ?? $m->id)
            ->groupBy(fn (FestMark $m) => (string) $m->participant->registration->school_id)
            ->map(fn ($group) => [
                'gold' => $group->where('position', 1)->count(),
                'silver' => $group->where('position', 2)->count(),
                'bronze' => $group->where('position', 3)->count(),
            ]);

        $schoolBoard = collect($this->scoreboards->scoreboard($event, $selectedScope))
            ->map(fn (array $row) => $row + [
                'gold' => $medalTally[$row['school_id']]['gold'] ?? 0,
                'silver' => $medalTally[$row['school_id']]['silver'] ?? 0,
                'bronze' => $medalTally[$row['school_id']]['bronze'] ?? 0,
            ])
            ->all();
        $lockedCumulativeStanding = $this->cumulativeChampionship->publicStanding($event);
        if ($lockedCumulativeStanding !== null) {
            $schoolBoard = collect($lockedCumulativeStanding['rows'])
                ->map(fn (array $row) => $row + [
                    'gold' => $medalTally[$row['school_id']]['gold'] ?? 0,
                    'silver' => $medalTally[$row['school_id']]['silver'] ?? 0,
                    'bronze' => $medalTally[$row['school_id']]['bronze'] ?? 0,
                ])->all();
        }

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

    public function schedule(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        EventLifecycleGate::allowPublicSchedule($event);
        $selectedScope = $this->operationalEvents->directScope($event);
        abort_unless($selectedScope['schedule_published'], 404);
        $scopes = [$selectedScope];

        $schedules = $this->mapScheduleRows($event, null, $selectedScope['event_ids']);

        return $this->renderPublic('public.fest.schedule', $tenant, compact(
            'event', 'schedules', 'scopes', 'selectedScope'
        ));
    }

    public function itemSchedule(int $eventId, FestEventItem $item)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless((int) $item->event_id === (int) $event->id, 404);
        EventLifecycleGate::allowPublicSchedule($event);

        $schedules = $this->mapScheduleRows($event, $item->id, [$event->id]);

        return $this->renderPublic('public.fest.item-schedule', $tenant, compact('event', 'item', 'schedules'));
    }

    public function itemResults(int $eventId, FestEventItem $item)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless((int) $item->event_id === (int) $event->id, 404);
        abort_unless($event->results_published, 404);

        $marks = FestMark::where('event_id', $item->event_id)
            ->where('item_id', $item->id)
            ->with(['participant.student', 'participant.teacher', 'participant.registration.school'])
            ->orderBy('position')
            ->orderByDesc('score')
            ->get()
            // Pair/group/team/trio items save one FestMark row PER TEAMMATE (same
            // position/score, see the scoring-dedup note on EventContext) — without this,
            // an 11-person choir would render its own result card 11 times over.
            ->unique(fn (FestMark $m) => $m->participant?->registration_id ?? $m->id);

        // Pair/group/team/trio items: the mark is only ever attached to one performer
        // on the registration (see the same note in results() above) — resolve the rest
        // of the roster so this page doesn't show a single arbitrary member as if they
        // competed solo. Skipped for individual items, which are the common case.
        $rosterByRegistration = $item->isTeamItem()
            ? FestParticipant::whereIn(
                'registration_id',
                $marks->pluck('participant.registration_id')->filter()->unique()->values()
            )
                ->where('participant_role', 'performer')
                ->with(['student', 'teacher'])
                ->get()
                ->groupBy('registration_id')
            : null;

        $marks = $marks->map(fn (FestMark $m) => $this->publicWinnerRow($m, $event, $rosterByRegistration) + [
            'mark_id' => $m->id,
            'poster_url' => in_array((int) $m->position, [1, 2, 3], true)
                ? route('tenant.fest.winner-poster', [$event->id, $item->id, $m->id])
                : null,
        ]);

        return $this->renderPublic('public.fest.item-results', $tenant, compact('event', 'item', 'marks'));
    }

    public function winnerPoster(int $eventId, FestEventItem $item, FestMark $mark, FestWinnerPosterService $posters)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless((int) $item->event_id === (int) $event->id, 404);
        abort_if($mark->event_id !== $item->event_id || $mark->item_id !== $item->id, 404);
        abort_unless($event->results_published, 404);
        abort_if(! in_array((int) $mark->position, [1, 2, 3], true), 404);

        $rendered = $posters->render($event, $item, $mark, $tenant);

        return response($rendered['content'], 200, [
            'Content-Type' => $rendered['mime'],
            'Content-Disposition' => 'inline; filename="'.$rendered['filename'].'"',
        ]);
    }

    public function itemResultsPdf(int $eventId, FestEventItem $item)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless((int) $item->event_id === (int) $event->id, 404);
        abort_unless($event->results_published, 404);

        $marks = FestMark::where('event_id', $item->event_id)
            ->where('item_id', $item->id)
            ->with(['participant.student', 'participant.teacher', 'participant.registration.school'])
            ->orderBy('position')
            ->orderByDesc('score')
            ->get();

        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($item->title)) ?: 'item';

        return Pdf::loadView('fest.reports.item-wise', [
            'event' => $event,
            'item' => $item,
            'marks' => $marks,
            'topN' => $marks->count(),
            'orgName' => $tenant->name ?? 'Sahodaya',
            'logoSrc' => TenantBranding::logoEmbedSrc($tenant),
        ])->download("{$slug}-results.pdf");
    }

    public function scoreboard(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $selectedScope = $this->operationalEvents->directScope($event);
        $isPublished = (bool) $selectedScope['results_published'];
        $scopes = [$selectedScope];
        $categories = $this->scoreboards->categories($event, $selectedScope);
        $category = $this->stringQuery($request, 'category');
        if ($category !== null) {
            abort_unless(in_array($category, $categories, true), 404);
        }

        $categoryLabels = collect($categories)
            ->mapWithKeys(fn (string $key) => [$key => $this->scoreboards->categoryLabel($event, $key)])
            ->all();
        $dynamic = $this->scoreboardDynamicData($event, $selectedScope, $category, $isPublished);
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

        $isPublished = (bool) $selectedScope['results_published'];
        $dynamic = $this->scoreboardDynamicData($event, $selectedScope, $category, $isPublished);

        return response()->json([
            'standingsPublished' => $isPublished,
            'contentHtml' => view('public.fest.partials.scoreboard-content', $dynamic + compact('event', 'isPublished'))->render(),
            'refreshedAt' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
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

        return $this->renderPublic('public.fest.live', $tenant, array_merge(
            ['event' => $event, 'selectedScope' => $selectedScope, 'scopes' => [$selectedScope]],
            $this->livePayload($event, $selectedScope)
        ));
    }

    public function liveData(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $selectedScope = $this->operationalEvents->directScope($event);

        return response()->json($this->livePayload($event, $selectedScope));
    }

    /** @return array<string, mixed> */
    private function livePayload(FestEvent $event, array $selectedScope): array
    {
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
            $nowPerforming = $this->visibility->formatPublicParticipant($event, $nowSlot->participant, $nowSlot);
            $nowPerforming['item_title'] = $nowSlot->item?->title;
        }

        return [
            // Live operations remain available before publish, but official school
            // standings do not leak through this alternate public endpoint.
            'scoreboard' => $selectedScope['results_published']
                ? ($this->cumulativeChampionship->publicStanding($event)['rows']
                    ?? $this->scoreboards->scoreboard($event, $selectedScope))
                : [],
            'standingsPublished' => (bool) $selectedScope['results_published'],
            'categoryLinks' => $categoryLinks,
            'houseScoreboard' => $selectedScope['results_published']
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

        $row = [
            'position' => $mark->position,
            'grade' => $mark->grade,
            'score' => $mark->score,
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
    private function scoreboardDynamicData(FestEvent $event, array $selectedScope, ?string $category, bool $isPublished): array
    {
        $scoreboard = $isPublished
            ? $this->scoreboards->scoreboard($event, $selectedScope, $category)
            : [];
        $cumulativeStanding = $isPublished
            ? $this->cumulativeChampionship->publicStanding($event, $category)
            : null;
        if ($cumulativeStanding !== null) {
            $scoreboard = $cumulativeStanding['rows'];
        }

        $winnerMarks = FestMark::whereIn('event_id', $selectedScope['event_ids'])
            ->whereIn('position', [1, 2, 3])
            ->with(['item.head', 'participant.student', 'participant.teacher', 'participant.registration.school'])
            ->latest('updated_at')
            ->limit(60)
            ->when(! $isPublished, fn ($query) => $query->whereRaw('1 = 0'))
            ->get();
        $roster = $this->rosterForMarks($winnerMarks);
        $latestWinners = $winnerMarks
            ->unique(fn (FestMark $mark) => $mark->participant?->registration_id ?? $mark->id)
            ->take(12)
            ->map(fn (FestMark $mark) => $this->publicWinnerRow($mark, $event, $roster) + [
                'item' => $mark->item?->title,
                'head' => $mark->item?->head?->name,
                'participant_type' => $mark->item?->participant_type,
            ])
            ->values()
            ->all();

        return [
            'scoreboard' => $scoreboard,
            'latestWinners' => $latestWinners,
            'cumulativeStanding' => $cumulativeStanding,
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

    public function records(int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);

        return $this->renderPublic('public.fest.records', $tenant, [
            'event' => $event,
            'records' => $this->publicAthleticRecords($event),
            'breaks' => $this->recentRecordBreaks($event, 50),
        ]);
    }

    public function search(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
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
            } elseif ($this->visibility->allowNameSearch($event)) {
                $matches = $base->where(function ($inner) use ($q) {
                    $inner->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$q}%"));
                })->limit(30)->get();
            } else {
                $matches = collect();
            }

            $showSchool = $this->visibility->showSchoolName($event);
            $results = $matches->map(fn (FestParticipant $p) => $this->visibility->formatPublicParticipant($event, $p) + [
                'school' => $showSchool ? $p->registration?->school?->name : null,
            ]);
        }

        return $this->renderPublic('public.fest.search', $tenant, [
            'event' => $event,
            'q' => $q,
            'results' => $results,
            'searchHint' => $this->visibility->searchPlaceholder($event),
            'nameSearch' => $this->visibility->allowNameSearch($event),
        ]);
    }

    public function participant(int $eventId, string $ref)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);

        $participant = $this->visibility->findParticipantByRef($event, $ref);
        abort_unless($participant, 404);

        $mark = FestMark::where('participant_id', $participant->id)->first();
        $schedule = FestSchedule::where('participant_id', $participant->id)->first();

        $public = $this->visibility->formatPublicParticipant($event, $participant, $schedule, $mark);
        $items = $this->visibility->publicParticipantItems($event, $participant);

        return $this->renderPublic('public.fest.participant', $tenant, compact(
            'event', 'public', 'participant', 'schedule', 'mark', 'items'
        ));
    }

    /** @return list<array<string, mixed>> */
    private function mapScheduleRows(FestEvent $event, ?int $itemId = null, ?array $eventIds = null): array
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
            ->map(function (Collection $group) use ($event) {
                $first = $group->first();
                $showName = $first->participant ? $this->visibility->showParticipantName($event, $first->participant) : false;

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
}
