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
use App\Services\Events\FestPhaseScoreboardService;
use App\Services\Events\FestPublicVisibilityService;
use App\Services\Events\FestWinnerPosterService;
use App\Services\Events\PublicFestScoreboardService;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FestPortalController extends Controller
{
    use RendersPublicPages;

    public function __construct(
        private FestPublicVisibilityService $visibility,
        private PublicFestScoreboardService $scoreboards,
        private FestPhaseScoreboardService $phaseScoreboards,
    ) {}

    public function index()
    {
        $tenant = $this->resolveTenant();

        $events = FestEvent::where('tenant_id', $tenant->id)
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            // Region/Cluster/Finale children are views inside their public hub, not
            // duplicate events in the public index. Non-partition children (notably
            // promoted Sports discipline events) remain independently discoverable.
            ->where(function ($query) {
                $query->whereNull('parent_event_id')
                    ->orWhere(function ($child) {
                        $child->whereNull('partition_key')
                            ->whereNull('cluster_key')
                            ->where(function ($role) {
                                $role->whereNull('partition_role')
                                    ->orWhereNotIn('partition_role', ['region', 'cluster', 'finale', 'phase']);
                            });
                    });
            })
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_type', 'event_start', 'venue', 'status', 'results_published']);

        return $this->renderPublic('public.fest.index', $tenant, compact('events'));
    }

    public function show(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $rootEvent = $this->scoreboards->rootEvent($event);

        if ($event->id !== $rootEvent->id) {
            $scope = $this->scoreboards->resolveScope($event);

            return redirect()->route('tenant.fest.show', array_filter([
                'event' => $rootEvent->id,
                'scope' => $scope['key'],
            ]));
        }

        $event = $rootEvent;
        $selectedScope = $this->scoreboards->resolveScope(
            $event,
            $this->stringQuery($request, 'scope'),
            $this->stringQuery($request, 'cluster'),
        );
        $scopes = $this->scoreboards->scopes($event);

        $itemsEventId = $selectedScope['event_id'] ?: $event->id;
        $items = FestEventItem::where('event_id', $itemsEventId)
            ->orderBy('display_order')
            ->get(['id', 'title', 'stage_type', 'category']);

        return $this->renderPublic('public.fest.show', $tenant, [
            'event' => $event,
            'items' => $items,
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
        $rootEvent = $this->scoreboards->rootEvent($event);

        if ($event->id !== $rootEvent->id) {
            $scope = $this->scoreboards->resolveScope($event);

            return redirect()->route('tenant.fest.results', array_filter([
                'event' => $rootEvent->id,
                'scope' => $scope['key'],
                'tab' => $this->stringQuery($request, 'tab'),
            ]));
        }

        $event = $rootEvent;
        abort_unless(
            $event->results_published
                || ($event->usesPhasedRegionalBilling() && $event->phases()->where('results_published', true)->exists()),
            404
        );

        $selectedScope = $this->scoreboards->resolveScope(
            $event,
            $this->stringQuery($request, 'scope'),
            $this->stringQuery($request, 'cluster'),
        );
        abort_unless($selectedScope['results_published'], 404);
        $scopes = $this->scoreboards->scopes($event);
        $tab = $request->query('tab', 'school');
        if (! in_array($tab, ['school', 'category', 'item', 'individual', 'championship'], true)) {
            $tab = 'school';
        }

        $publishedAt = FestResult::whereIn('event_id', $selectedScope['event_ids'])
            ->whereNull('item_id')
            ->max('published_at');

        $championshipEventId = $selectedScope['event_id'] ?: $event->id;
        $championship = FestIndividualChampionshipPoint::where('event_id', $championshipEventId)
            ->with(['student'])
            ->orderByDesc('points')
            ->orderBy('student_id')
            ->get()
            ->map(function (FestIndividualChampionshipPoint $row, int $index) {
                $school = Tenant::find($row->student?->tenant_id);

                return [
                    'rank' => $index + 1,
                    'points' => $row->points,
                    'category' => $row->category,
                    'gender' => $row->gender,
                    'student' => $row->student?->name,
                    'reg_no' => $row->student?->reg_no,
                    'school' => $school?->name,
                ];
            })
            ->values()
            ->all();

        $categories = $this->scoreboards->categories($event, $selectedScope);
        $categoryBoards = collect($categories)
            ->map(fn (string $key) => [
                'key' => $key,
                'label' => $this->scoreboards->categoryLabel($event, $key),
                'rows' => $this->scoreboards->scoreboard($event, $selectedScope, $key),
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

        $itemResults = $marks
            ->groupBy('item_id')
            ->map(function ($group) use ($event) {
                /** @var FestMark $first */
                $first = $group->first();

                return [
                    'item_id' => $first->item_id,
                    'item' => $first->item?->title,
                    'head' => $first->item?->head?->name,
                    'winners' => $group->map(fn (FestMark $mark) => $this->publicWinnerRow($mark, $event))->values()->all(),
                ];
            })
            ->values()
            ->all();

        $individualResults = $marks
            ->map(fn (FestMark $mark) => $this->publicWinnerRow($mark, $event) + [
                'item' => $mark->item?->title,
                'head' => $mark->item?->head?->name,
            ])
            ->sortBy(fn (array $row) => [$row['participant'] ?? '', $row['item'] ?? ''])
            ->values()
            ->all();

        return $this->renderPublic('public.fest.results', $tenant, [
            'event' => $event,
            'tab' => $tab,
            'schoolBoard' => $this->scoreboards->scoreboard($event, $selectedScope),
            'usesPhases' => $usesPhases,
            'phaseCumulativeBoard' => $phaseCumulativeBoard,
            'phaseBreakdown' => $phaseBreakdown,
            'categoryBoards' => $categoryBoards,
            'itemResults' => $itemResults,
            'individualResults' => $individualResults,
            'championship' => $championship,
            'publishedAt' => $publishedAt,
            'scopes' => $scopes,
            'selectedScope' => $selectedScope,
            'pageSeo' => ['title' => $event->title.' — Results'],
        ]);
    }

    public function schedule(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $rootEvent = $this->scoreboards->rootEvent($event);

        if ($event->id !== $rootEvent->id) {
            $scope = $this->scoreboards->resolveScope($event);

            return redirect()->route('tenant.fest.schedule', [
                'event' => $rootEvent->id,
                'scope' => $scope['key'],
            ]);
        }

        $event = $rootEvent;
        if (! $event->usesPhasedRegionalBilling()) {
            EventLifecycleGate::allowPublicSchedule($event);
        } else {
            abort_unless($event->phases()->where('schedule_published', true)->exists(), 404);
        }
        $selectedScope = $this->scoreboards->resolveScope(
            $event,
            $this->stringQuery($request, 'scope'),
            $this->stringQuery($request, 'cluster'),
        );
        abort_unless($selectedScope['schedule_published'], 404);
        $scopes = $this->scoreboards->scopes($event);

        $schedules = $this->mapScheduleRows($event, null, $selectedScope['event_ids']);

        return $this->renderPublic('public.fest.schedule', $tenant, compact(
            'event', 'schedules', 'scopes', 'selectedScope'
        ));
    }

    public function itemSchedule(int $eventId, FestEventItem $item)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless(in_array($item->event_id, $event->reportableEventIds(), true), 404);
        if (! $event->usesPhasedRegionalBilling()) {
            EventLifecycleGate::allowPublicSchedule($event);
        }
        $itemEvent = FestEvent::where('tenant_id', $tenant->id)->findOrFail($item->event_id);
        abort_unless($itemEvent->schedule_published, 404);

        $schedules = $this->mapScheduleRows($event, $item->id);

        return $this->renderPublic('public.fest.item-schedule', $tenant, compact('event', 'item', 'schedules'));
    }

    public function itemResults(int $eventId, FestEventItem $item)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_unless(in_array($item->event_id, $event->reportableEventIds(), true), 404);
        abort_unless($this->rootResultsAvailable($event), 404);
        $itemEvent = FestEvent::where('tenant_id', $tenant->id)->findOrFail($item->event_id);
        abort_unless($itemEvent->results_published, 404);

        $marks = FestMark::where('event_id', $item->event_id)
            ->where('item_id', $item->id)
            ->with(['participant.student', 'participant.teacher', 'participant.registration.school'])
            ->orderBy('position')
            ->orderByDesc('score')
            ->get()
            ->map(fn (FestMark $m) => [
                'mark_id' => $m->id,
                'reference' => $m->participant
                    ? $this->visibility->publicReference($event, $m->participant)
                    : '—',
                'name' => $m->participant?->student?->name ?? $m->participant?->teacher?->name,
                'school' => $m->participant?->registration?->school?->name,
                'position' => $m->position,
                'grade' => $m->grade,
                'score' => $m->score,
                'result' => trim(($m->measurement_value ?? '').' '.($m->measurement_unit ?? '')),
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
        abort_unless(in_array($item->event_id, $event->reportableEventIds(), true), 404);
        abort_if($mark->event_id !== $item->event_id || $mark->item_id !== $item->id, 404);
        abort_unless($this->rootResultsAvailable($event), 404);
        $itemEvent = FestEvent::where('tenant_id', $tenant->id)->findOrFail($item->event_id);
        abort_unless($itemEvent->results_published, 404);
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
        abort_unless(in_array($item->event_id, $event->reportableEventIds(), true), 404);
        abort_unless($this->rootResultsAvailable($event), 404);
        $itemEvent = FestEvent::where('tenant_id', $tenant->id)->findOrFail($item->event_id);
        abort_unless($itemEvent->results_published, 404);

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
        $rootEvent = $this->scoreboards->rootEvent($event);

        if ($event->id !== $rootEvent->id) {
            $scope = $this->scoreboards->resolveScope($event);

            return redirect()->route('tenant.fest.scoreboard', array_filter([
                'event' => $rootEvent->id,
                'scope' => $scope['key'],
                'category' => $this->stringQuery($request, 'category'),
            ]));
        }

        $event = $rootEvent;
        $selectedScope = $this->scoreboards->resolveScope(
            $event,
            $this->stringQuery($request, 'scope'),
            $this->stringQuery($request, 'cluster'),
        );
        $isPublished = (bool) $selectedScope['results_published'];
        $scopes = $this->scoreboards->scopes($event);
        $categories = $this->scoreboards->categories($event, $selectedScope);
        $category = $this->stringQuery($request, 'category');
        if ($category !== null) {
            abort_unless(in_array($category, $categories, true), 404);
        }

        $categoryLabels = collect($categories)
            ->mapWithKeys(fn (string $key) => [$key => $this->scoreboards->categoryLabel($event, $key)])
            ->all();
        $scoreboard = $isPublished
            ? $this->scoreboards->scoreboard($event, $selectedScope, $category)
            : [];
        $scoreboardTitle = $selectedScope['label'];
        if ($category) {
            $scoreboardTitle .= ' · '.($categoryLabels[$category] ?? strtoupper($category));
        }

        $latestWinners = FestMark::whereIn('event_id', $selectedScope['event_ids'])
            ->whereIn('position', [1, 2, 3])
            ->with(['item.head', 'participant.student', 'participant.teacher', 'participant.registration.school'])
            ->latest('updated_at')
            ->limit(12)
            ->when(! $isPublished, fn ($query) => $query->whereRaw('1 = 0'))
            ->get()
            ->map(fn (FestMark $mark) => $this->publicWinnerRow($mark, $event) + [
                'item' => $mark->item?->title,
                'head' => $mark->item?->head?->name,
            ])
            ->values()
            ->all();

        return $this->renderPublic('public.fest.scoreboard', $tenant, [
            'event' => $event,
            'scopes' => $scopes,
            'selectedScope' => $selectedScope,
            'categories' => $categories,
            'category' => $category,
            'categoryLabels' => $categoryLabels,
            'scoreboard' => $scoreboard,
            'scoreboardTitle' => $scoreboardTitle,
            'latestWinners' => $latestWinners,
            'isPublished' => $isPublished,
            'pageSeo' => ['title' => $event->title.' — Scoreboard'],
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
        $rootEvent = $this->scoreboards->rootEvent($event);

        if ($event->id !== $rootEvent->id) {
            $scope = $this->scoreboards->resolveScope($event);

            return redirect()->route('tenant.fest.live', [
                'event' => $rootEvent->id,
                'scope' => $scope['key'],
            ]);
        }

        $event = $rootEvent;
        $selectedScope = $this->scoreboards->resolveScope(
            $event,
            $this->stringQuery($request, 'scope'),
            $this->stringQuery($request, 'cluster'),
        );

        return $this->renderPublic('public.fest.live', $tenant, array_merge(
            ['event' => $event, 'selectedScope' => $selectedScope, 'scopes' => $this->scoreboards->scopes($event)],
            $this->livePayload($event, $selectedScope)
        ));
    }

    public function liveData(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $event = $this->scoreboards->rootEvent($event);
        $selectedScope = $this->scoreboards->resolveScope(
            $event,
            $this->stringQuery($request, 'scope'),
            $this->stringQuery($request, 'cluster'),
        );

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
                'scope' => $selectedScope['key'],
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
                ? $this->scoreboards->scoreboard($event, $selectedScope)
                : [],
            'standingsPublished' => (bool) $selectedScope['results_published'],
            'categoryLinks' => $categoryLinks,
            'houseScoreboard' => $selectedScope['results_published'] && ! $selectedScope['event_id']
                ? $ctx->scoreboardByHouse()
                : [],
            'nowPerforming' => $nowPerforming,
            'athleticRecords' => $this->publicAthleticRecords($event),
            'recentBreaks' => $this->recentRecordBreaks($event),
            'refreshedAt' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function publicWinnerRow(FestMark $mark, FestEvent $event): array
    {
        return [
            'position' => $mark->position,
            'grade' => $mark->grade,
            'score' => $mark->score,
            'measurement' => trim(($mark->measurement_value ?? '').' '.($mark->measurement_unit ?? '')),
            'participant' => $mark->participant?->student?->name ?? $mark->participant?->teacher?->name,
            'reference' => $mark->participant ? $this->visibility->publicReference($event, $mark->participant) : null,
            'school' => $mark->participant?->registration?->school?->name,
        ];
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
            $query = FestParticipant::whereHas('registration', fn ($r) => $r
                ->where('event_id', $event->id)
                ->where('status', 'approved'))
                ->with(['student', 'teacher', 'registration.item', 'registration.event']);

            if (ctype_digit($q)) {
                $query->where('chest_no', (int) $q);
            } elseif (preg_match('/^[A-Za-z]-\d+$/', $q)) {
                $query->where('level_registration_number', strtoupper($q));
            } elseif ($this->visibility->allowNameSearch($event)) {
                $query->where(function ($inner) use ($q) {
                    $inner->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$q}%"));
                });
            }

            $results = $query->limit(30)->get()
                ->map(fn (FestParticipant $p) => $this->visibility->formatPublicParticipant($event, $p));
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

        return $this->renderPublic('public.fest.participant', $tenant, compact(
            'event', 'public', 'participant', 'schedule', 'mark'
        ));
    }

    /** @return list<array<string, mixed>> */
    private function mapScheduleRows(FestEvent $event, ?int $itemId = null, ?array $eventIds = null): array
    {
        $query = FestSchedule::whereIn('event_id', $eventIds ?? $event->reportableEventIds())
            ->with(['item', 'participant.student', 'participant.teacher', 'participant.registration.item', 'participant.registration.event']);

        if ($itemId) {
            $query->where('item_id', $itemId);
        }

        return $query->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->get()
            ->map(function (FestSchedule $row) use ($event) {
                return [
                    'scheduled_at' => $row->scheduled_at,
                    'item_id' => $row->item_id,
                    'item_title' => $row->item?->title,
                    'stage' => $row->stage,
                    'sort_order' => $row->sort_order,
                    'participant' => $row->participant
                        ? $this->visibility->formatPublicParticipant($event, $row->participant, $row)
                        : null,
                ];
            })
            ->all();
    }

    private function findEvent(string $tenantId, int $eventId): FestEvent
    {
        return FestEvent::where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->firstOrFail();
    }

    private function rootResultsAvailable(FestEvent $event): bool
    {
        return (bool) $event->results_published
            || ($event->usesPhasedRegionalBilling() && $event->phases()->where('results_published', true)->exists());
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
