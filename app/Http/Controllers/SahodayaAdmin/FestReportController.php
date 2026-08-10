<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Services\Events\EventContext;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestParticipationPolicyService;
use App\Services\Events\FestRegistrationRegisterService;
use App\Services\Events\FestReportService;
use App\Services\Events\Reports\FestReportScopeResolver;
use App\Support\FestPageActivity;
use App\Support\FestReportCatalog;
use App\Support\FestEventMeta;
use App\Services\Audit\PlatformAuditLogger;
use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsItemHeadReportContext;
use App\Http\Controllers\SahodayaAdmin\Concerns\ResolvesRegionAwareReportEvent;
use Illuminate\Http\Request;

class FestReportController extends SahodayaAdminController
{
    use BuildsItemHeadReportContext;
    use ResolvesRegionAwareReportEvent;
    /** @return array<string, mixed> */
    protected function reportProps(string $tenantId, FestEvent $event, array $extra = []): array
    {
        $base = "/sahodaya-admin/{$tenantId}/events/{$event->id}";

        $headContext = $event->event_type === 'sports'
            ? $this->itemHeadReportContext($event, null, $tenantId)
            : [
                'headItemGroups'  => [],
                'headsForFilter'  => [],
                'hasItemHeads'    => false,
                'headSummary'     => [],
            ];

        $regions = \App\Models\Region::forTenant($tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'code' => $r->code])
            ->values()
            ->all();

        // Detect whether this is a partitioned parent with region children so that Hub
        // and Downloads can render per-region navigation cards.
        //
        // Most tiles still link to the child event's own id/URL — that's each region
        // child's own report, and it's already correctly isolated to that child. The
        // exception is FestReportCatalog::REGION_ID_AWARE_IDS (item counts, head-wise
        // participants, discipline registration, mark-entry status, schedule/clashes,
        // assignment completeness): those builders resolve data via
        // $event->reportableEventIds()/reportableItemIds(), which, run directly on the
        // child, pulls in the hub's own uncopied item/registration rows alongside the
        // child's own. For just those ids, regionScopedRows() reroutes the tile through
        // the parent hub with an explicit region_id instead (same pattern already used
        // for Registration Register / Overall Ranking), which those six controller
        // methods now understand. See FestReportCatalog::REGION_ID_AWARE_IDS docblock.
        $regionChildren = $event->childrenForRoles(['region'])
            ->load('region:id,name,code')
            ->sortBy('sort_order')
            ->map(function (FestEvent $child) use ($tenantId, $event) {
                $childPages = FestReportCatalog::interactivePages($tenantId, $child->id, $event->event_type);
                $hubPagesForThisRegion = FestReportCatalog::withRegionParam(
                    FestReportCatalog::interactivePages($tenantId, $event->id, $event->event_type),
                    $child->region_id,
                );

                return [
                    'id'             => $child->id,
                    'title'          => $child->title,
                    'region_id'      => $child->region_id,
                    'region_name'    => $child->region?->name ?? $child->title,
                    'region_code'    => $child->region?->code,
                    'reportsBase'    => "/sahodaya-admin/{$tenantId}/events/{$child->id}/reports",
                    'downloadsBase'  => "/sahodaya-admin/{$tenantId}/events/{$child->id}/reports/downloads",
                    'interactivePages' => FestReportCatalog::regionScopedRows($childPages, $hubPagesForThisRegion),
                    'exportBase'     => "/sahodaya-admin/{$tenantId}/events/{$child->id}/reports/export",
                ];
            })
            ->values()
            ->all();

        $isPartitionedParent = $event->parent_event_id === null
            && count($regionChildren) > 0;

        return array_merge([
            'event'               => $event->only([
                'id', 'title', 'event_type', 'status', 'event_start', 'event_end',
                'registration_open', 'registration_close', 'venue', 'results_published',
                'schedule_published', 'level_round',
            ]),
            'eventMeta'           => FestEventMeta::reportSnapshot($event, $base, "{$base}/settings"),
            'interactiveNav'      => FestReportCatalog::interactivePages($tenantId, $event->id, $event->event_type),
            'currentPhase'        => EventLifecycleGate::currentReportPhase($event),
            'allowedPhases'       => EventLifecycleGate::allowedReportPhases($event),
            'regions'             => $regions,
            'isPartitionedParent' => $isPartitionedParent,
            'regionChildren'      => $regionChildren,
        ], $headContext, $extra);
    }

    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($event->event_type === 'sports' && ! request()->boolean('all')) {
            return redirect()->route('sahodaya.events.reports.by-head', [
                'tenantId' => $tenantId,
                'event'    => $event->id,
            ]);
        }

        $service = new FestReportService($event);
        $allowedPhases = EventLifecycleGate::allowedReportPhases($event);
        $currentPhase = EventLifecycleGate::currentReportPhase($event);

        return $this->inertia('Sahodaya/Events/Reports/Hub', $this->reportProps($tenantId, $event, [
            'interactive'   => FestReportCatalog::interactivePages($tenantId, $event->id, $event->event_type),
            'currentPhase'  => $currentPhase,
            'allowedPhases' => $allowedPhases,
            'activityLogs'  => $this->pageActivityLogs($event, FestPageActivity::REPORTS),
        ]));
    }

    public function byHead(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($event->event_type === 'sports', 404);

        $navService = app(\App\Services\Events\FestHeadItemNavigationService::class);
        $nav = $navService->navigationForEvent($event);

        $headId = $this->resolveHeadQueryParam($request->query('head_id'));
        $itemId = $request->integer('item_id') ?: null;
        $selectedItem = null;

        if ($itemId) {
            $selectedItem = $navService->findItemInGroups($nav['headItemGroups'], $itemId);
            abort_unless($selectedItem, 404);
        }

        $selectedHeadId = match (true) {
            $headId === 0 => 'other',
            $headId !== null => $headId,
            default => null,
        };

        return $this->inertia('Sahodaya/Events/Reports/ByHead', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'selectedHeadId' => $selectedHeadId,
            'selectedItemId' => $itemId,
            'selectedItem'   => $selectedItem,
            'activityLogs'   => $this->pageActivityLogs($event, FestPageActivity::REPORTS),
        ])));
    }

    public function downloads(string $tenantId, FestEvent $event, string $phase)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless(in_array($phase, ['before', 'during', 'after'], true), 404);

        $service = new FestReportService($event);
        $allowedPhases = EventLifecycleGate::allowedReportPhases($event);
        $currentPhase = EventLifecycleGate::currentReportPhase($event);

        $exports = array_values(array_filter(
            FestReportCatalog::exportsWithPreview($tenantId, $event->id),
            fn ($exp) => ($exp['phase'] ?? 'before') === $phase
                && in_array($exp['phase'] ?? 'before', $allowedPhases, true)
        ));

        // For partitioned parent events: compute the same filtered export catalog for
        // each region child — mostly the child event's own export routes, except
        // FestReportCatalog::REGION_ID_AWARE_IDS, rerouted through the hub + region_id.
        // Same reasoning as reportProps()'s $regionChildren above.
        // Downloads.vue uses this to render per-region export sections inline on the page.
        $regionChildrenWithExports = array_map(function (array $child) use ($tenantId, $event, $phase, $allowedPhases) {
            $childExports = FestReportCatalog::regionScopedRows(
                FestReportCatalog::exportsWithPreview($tenantId, $child['id']),
                FestReportCatalog::withRegionParam(
                    FestReportCatalog::exportsWithPreview($tenantId, $event->id),
                    $child['region_id'],
                ),
            );

            $childExports = array_values(array_filter(
                $childExports,
                fn ($exp) => ($exp['phase'] ?? 'before') === $phase
                    && in_array($exp['phase'] ?? 'before', $allowedPhases, true)
            ));

            return array_merge($child, ['exports' => $childExports]);
        }, $this->reportProps($tenantId, $event)['regionChildren'] ?? []);

        return $this->inertia('Sahodaya/Events/Reports/Downloads', $this->reportProps($tenantId, $event, [
            'phase'                    => $phase,
            'exports'                  => $exports,
            'regionChildrenWithExports' => $regionChildrenWithExports,
            'schools'                  => $service->schools(),
            'items'                    => $service->items()->map->only(['id', 'title', 'class_group']),
            'heads'                    => $event->event_type === 'sports'
                ? ($event->isSportsSeasonEvent()
                    ? FestEvent::where('parent_event_id', $event->id)->ofType('sports')->orderBy('sort_order')->orderBy('title')->get(['id', 'title'])->map(fn ($e) => ['id' => $e->id, 'name' => $e->title])->values()
                    : collect([['id' => $event->id, 'name' => $event->title]]))
                : \App\Models\FestItemHead::forTenant($this->sahodaya->id)->forEvent($event->id)->orderBy('sort_order')->get(['id', 'name']),
            'stages'                   => $service->scheduleStages()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
            'classGroups'              => FestReportService::classGroups($event),
            'currentPhase'             => $currentPhase,
            'activityLogs'             => $this->pageActivityLogs($event, FestPageActivity::reportsPhase($phase)),
        ]));
    }

    public function storeRule(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return redirect("/sahodaya-admin/{$tenantId}/events/{$event->id}/settings/participation")
            ->with('info', 'Participation limits are configured under Event settings → Participation.');
    }

    public function schoolDetailed(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $service = new FestReportService($event);
        $schoolId = $request->input('school_id');
        $classGroup = $request->input('class_group');
        $grouped = [];

        if ($schoolId) {
            $marks = $service->marks($schoolId, null, $classGroup);
            foreach ($marks as $m) {
                $itemTitle = $m->item?->title ?? 'Item';
                $grouped[$itemTitle][] = [
                    'students' => $m->participant?->student?->name ?? $m->participant?->teacher?->name ?? '—',
                    'position' => $m->position,
                    'grade'    => $m->grade,
                    'score'    => $m->score,
                ];
            }
        }

        return $this->inertia('Sahodaya/Events/Reports/SchoolDetailed', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'schools'     => $service->schools(),
            'classGroups' => FestReportService::classGroups($event),
            'filters'     => ['school_id' => $schoolId, 'class_group' => $classGroup],
            'grouped'     => $grouped,
            'pdfUrl'      => $schoolId ? "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/school-wise?".http_build_query(array_filter([
                'school_id' => $schoolId, 'class_group' => $classGroup,
            ])) : null,
        ])));
    }

    /**
     * Phase 3 demonstration of FestReportScopeResolver-backed Combined vs Region-wise
     * Result selection (plan §3.4). $event has already been transparently narrowed to a
     * region-locked admin's own child by ResolveRegionScopedReportEvent, so the default
     * (no region_id) path below is unchanged for them — this only adds the ability for a
     * full/unrestricted admin to explicitly request one region's own ranking from the
     * hub route, via ?region_id=. Other results/ranking pages (medal tally,
     * championship, house-detailed, etc.) are not yet retrofitted this way — see the
     * Phase 3 checklist in the final status report.
     */
    public function overallRanking(Request $request, string $tenantId, FestEvent $event, FestReportScopeResolver $scopeResolver)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $regionId = $request->integer('region_id') ?: null;
        $targetEvent = $event;

        if ($regionId !== null) {
            $scope = $scopeResolver->resolve($event, $request->user(), ['mode' => 'region', 'region_id' => $regionId]);
            abort_if($scope->isEmpty(), 404, 'No results available for that region.');
            $targetEvent = FestEvent::findOrFail($scope->eventIds[0]);
        }

        $service = new FestReportService($targetEvent);

        return $this->inertia('Sahodaya/Events/Reports/OverallRanking', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rankings'       => $service->schoolRankingRows()->values(),
            'resultMode'     => $regionId !== null ? 'region' : 'combined',
            'filterRegionId' => $regionId,
            'pdfUrl'         => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/overall-ranking".($regionId ? "?region_id={$regionId}" : ''),
        ])));
    }

    public function houseDetailed(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return $this->inertia('Sahodaya/Events/Reports/HouseDetailed', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'board'   => EventContext::for($event)->scoreboardByHouse(),
            'pdfUrl'  => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/house-wise",
        ])));
    }

    public function participationCounts(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $service = new FestReportService($event);
        $policy = app(FestParticipationPolicyService::class)->resolveForEvent($event);

        $regs = $service->activeRegistrations();
        $used = [
            'total'      => $regs->count(),
            'on_stage'   => $regs->filter(fn ($r) => ($r->item?->stage_type ?? '') === 'on_stage')->count(),
            'off_stage'  => $regs->filter(fn ($r) => ($r->item?->stage_type ?? '') === 'off_stage')->count(),
            'individual' => $regs->filter(fn ($r) => $r->item?->participant_type === 'individual')->count(),
            'group'      => $regs->filter(fn ($r) => in_array($r->item?->participant_type, ['group', 'team'], true))->count(),
        ];

        return $this->inertia('Sahodaya/Events/Reports/ParticipationCounts', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'used'   => $used,
            'limits' => [
                'max_onstage_per_student' => $policy['max_onstage_per_student'] ?? null,
                'max_offstage_per_student' => $policy['max_offstage_per_student'] ?? null,
                'max_group_per_student' => $policy['max_group_per_student'] ?? null,
            ],
        ])));
    }

    public function markEntryStatus(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $service = new FestReportService($this->regionAwareTargetEvent($request, $event));
        $data = $service->markEntryStatusSummary();

        return $this->inertia('Sahodaya/Events/Reports/MarkEntryStatus', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'summary' => $data['summary'],
            'rows'    => $data['rows'],
            'csvUrl'  => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/mark-entry-status",
        ])));
    }

    public function scheduleClashes(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $service = new FestReportService($this->regionAwareTargetEvent($request, $event));
        $schoolId = $request->input('school_id');
        $clashes = $service->scheduleClashRows($schoolId);

        return $this->inertia('Sahodaya/Events/Reports/ScheduleClashes', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'schools'     => $service->schools(),
            'filters'     => ['school_id' => $schoolId],
            'participant' => $clashes['participant'],
            'stage'       => $clashes['stage'],
            'csvUrl'      => $schoolId
                ? "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/clashes?".http_build_query(['school_id' => $schoolId])
                : "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/clashes",
        ])));
    }

    public function itemSchedule(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $service = new FestReportService($event);
        $date = $request->input('date');
        $stageId = $request->integer('stage_id') ?: null;
        $rows = $service->itemScheduleRows($date, $stageId);
        $summary = $service->itemScheduleSummary();

        return $this->inertia('Sahodaya/Events/Reports/ItemSchedule', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'    => $rows,
            'summary' => $summary,
            'stages'  => $service->scheduleStages(),
            'filters' => ['date' => $date, 'stage_id' => $stageId],
            'csvUrl'  => '/sahodaya-admin/'.$tenantId.'/events/'.$event->id.'/reports/export/item-schedule?'.http_build_query(array_filter([
                'date'     => $date,
                'stage_id' => $stageId,
            ])),
            'pdfUrl'  => '/sahodaya-admin/'.$tenantId.'/events/'.$event->id.'/reports/export/item-schedule-pdf?'.http_build_query(array_filter([
                'date'     => $date,
                'stage_id' => $stageId,
            ])),
            'editUrl' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/schedule/items",
        ])));
    }

    public function itemCounts(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($this->regionAwareTargetEvent($request, $event));
        $rows = $analytics->itemRegistrationRows();

        return $this->inertia('Sahodaya/Events/Reports/ItemCounts', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'        => $rows,
            'headSummary' => $analytics->headRegistrationSummary(),
            'totals'      => $analytics->itemRegistrationTotals($rows),
            'pdfUrl'      => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/item-list",
        ])));
    }

    public function disciplineRegistration(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($this->regionAwareTargetEvent($request, $event));

        return $this->inertia('Sahodaya/Events/Reports/DisciplineRegistration', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'   => $analytics->disciplineRegistrationRows(),
            'xlsUrl' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/discipline-registration",
        ])));
    }

    public function headWiseParticipants(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($event->event_type === 'sports') {
            app(\App\Services\Events\FestItemHeadService::class)->syncEventHeads($event);
        }

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($this->regionAwareTargetEvent($request, $event));
        $headId = $request->input('head_id') !== null && $request->input('head_id') !== ''
            ? ($request->input('head_id') === 'other' ? 0 : $request->integer('head_id'))
            : null;
        $schoolId = $request->input('school_id') ?: null;
        $itemId = $request->integer('item_id') ?: null;

        return $this->inertia('Sahodaya/Events/Reports/HeadWiseParticipants', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'summary'        => $analytics->headRegistrationSummary($schoolId),
            'rows'           => $analytics->headWiseParticipantRows($headId ?: null, $schoolId),
            'schools'        => (new FestReportService($event))->schools(),
            'filterHeadId'   => $request->input('head_id') ?: null,
            'filterItemId'   => $itemId,
            'filterSchoolId' => $schoolId,
            'xlsUrl'         => '/sahodaya-admin/'.$tenantId.'/events/'.$event->id.'/reports/export/head-wise-participants?'.http_build_query(array_filter([
                'head_id'   => $request->input('head_id'),
                'school_id' => $schoolId,
            ])),
        ])));
    }

    public function areaWiseParticipants(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->event_type === 'sports', 404);

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($event);
        $areaId = $request->input('area_id') !== null && $request->input('area_id') !== ''
            ? ($request->input('area_id') === 'other' ? 0 : $request->integer('area_id'))
            : null;
        $schoolId = $request->input('school_id') ?: null;

        $areas = \App\Models\FestCompetitionArea::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->inertia('Sahodaya/Events/Reports/AreaWiseParticipants', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'summary'        => $analytics->areaWiseSummary($schoolId),
            'rows'           => $analytics->areaWiseParticipantRows($areaId, $schoolId),
            'areas'          => $areas,
            'schools'        => (new FestReportService($event))->schools(),
            'filterAreaId'   => $request->input('area_id') ?: null,
            'filterSchoolId' => $schoolId,
            'xlsUrl'         => '/sahodaya-admin/'.$tenantId.'/events/'.$event->id.'/reports/export/area-wise-participants?'.http_build_query(array_filter([
                'area_id'   => $request->input('area_id'),
                'school_id' => $schoolId,
            ])),
        ])));
    }

    public function ageGroupMatrix(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($event);
        $schoolId = $request->input('school_id');
        $data = $analytics->ageGroupMatrix($schoolId ?: null);

        return $this->inertia('Sahodaya/Events/Reports/AgeGroupMatrix', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'matrix'         => $data,
            'schools'        => (new FestReportService($event))->schools(),
            'filterSchoolId' => $schoolId,
            'xlsUrl'         => '/sahodaya-admin/'.$tenantId.'/events/'.$event->id.'/reports/export/age-group-matrix?'.http_build_query(array_filter(['school_id' => $schoolId])),
        ])));
    }

    public function feeCollection(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // Payment/fee report — same reportableEventIds() resolution issue as the six
        // report builders in FestReportCatalog::REGION_ID_AWARE_IDS, found when auditing
        // those; feeCollectionRows()/feeCollectionByHeadRows() have the same shape.
        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($this->regionAwareTargetEvent($request, $event));
        $rows = $analytics->feeCollectionRows();
        $regionId = $request->integer('region_id') ?: null;

        return $this->inertia('Sahodaya/Events/Reports/FeeCollection', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'   => $rows,
            'byHead' => $analytics->feeCollectionByHeadRows(),
            'totals' => [
                'schools'  => count($rows),
                'due'      => round(collect($rows)->sum('total_due'), 2),
                'collected'=> round(collect($rows)->where('status', 'approved')->sum('total_due'), 2),
                'pending'  => collect($rows)->whereIn('status', ['pending', 'proof_uploaded'])->count(),
            ],
            'xlsUrl' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/fee-pending-schools".($regionId ? "?region_id={$regionId}" : ''),
            'feesUrl' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/fees",
        ])));
    }

    /**
     * Resolves the FestEvent a region-aware report should actually read from, for the
     * six report builders in FestReportCatalog::REGION_ID_AWARE_IDS (and feeCollection —
     * added when the same issue was found in fee/payment reports). Two paths:
     *
     *   1. $event is already a region-partition child (parent_event_id set) — reached
     *      either via its own direct URL, or because ResolveRegionScopedReportEvent
     *      already substituted a region-locked admin's own child in. Isolate it so its
     *      own reportableEventIds() call doesn't also pull in the hub's rows.
     *   2. $event is the hub and ?region_id= was supplied (from a Hub.vue region tile,
     *      built via FestReportCatalog::regionScopedRows()) — resolve that region's
     *      child, then isolate it the same way.
     *   3. Neither — $event is used as-is (the existing Combined/no-region-filter path,
     *      unchanged).
     *
     * "Isolate" means returning a detached, unsaved clone with parent_event_id nulled
     * out in memory only, so FestEvent::reportableEventIds()/reportableItemIds() (used
     * throughout FestReportService/FestEventReportAnalyticsService) resolve to just that
     * child's own event_id instead of [child, hub] — the hub's own uncopied item/
     * registration rows were otherwise being pulled in alongside the child's own data.
     * Never persisted; nothing calls ->save() on the result.
     */
    public function registrationRegister(Request $request, string $tenantId, FestEvent $event, FestRegistrationRegisterService $register, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $scope = $this->resolveRegistrationRegisterScope($request, $event, $register);

        $data = $register->build($event, $scope['schoolId'], null, 50, null, null, $scope['schoolIds']);

        $audit->festEvent($event, FestPageActivity::REPORTS, 'fest.report.scope_resolved', 'Registration register viewed', [
            'report'    => 'registration-register',
            'region_id' => $scope['regionId'],
            'school_id' => $scope['schoolId'],
        ]);

        return $this->inertia('Sahodaya/Events/Reports/RegistrationRegister', $this->reportProps($tenantId, $event, [
            'rows'            => $data['rows'],
            'schoolSummaries' => $data['school_summaries'],
            'totals'          => $data['totals'],
            'schools'         => $register->schools($event),
            'filterSchoolId'  => $scope['schoolId'],
            'filterRegionId'  => $scope['regionId'],
            'feesUrl'         => "/sahodaya-admin/{$tenantId}/events/{$event->id}/fees",
            'activityLogs'    => $this->pageActivityLogs($event, FestPageActivity::REPORTS),
        ]));
    }

    public function exportRegistrationRegister(Request $request, string $tenantId, FestEvent $event, FestRegistrationRegisterService $register, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $scope = $this->resolveRegistrationRegisterScope($request, $event, $register);

        $audit->festEvent($event, FestPageActivity::REPORTS, 'fest.report.exported', 'Registration register exported', [
            'report'    => 'registration-register',
            'region_id' => $scope['regionId'],
            'school_id' => $scope['schoolId'],
        ]);

        return $register->exportCsv($event, $scope['schoolId'], $scope['schoolIds'], $scope['regionId']);
    }

    /**
     * Resolve the school-id scope shared by the Registration Register browser view and
     * its export, so both read exactly the same rows (gap G3) and a region-locked admin
     * is narrowed to their assigned region(s) on both paths even if no region_id/school_id
     * was supplied, using active-year SchoolRegionAssignment data (gap G5). Tampered
     * region_id/school_id values outside the admin's assigned region(s) are rejected.
     *
     * @return array{schoolId: ?string, schoolIds: ?list<string>, regionId: ?int}
     */
    private function resolveRegistrationRegisterScope(Request $request, FestEvent $event, FestRegistrationRegisterService $register): array
    {
        $schoolId = $request->input('school_id') ?: null;
        $regionId = $request->integer('region_id') ?: null;

        $regionScopes = $request->attributes->get('regionAdminScopes');

        if (! empty($regionScopes)) {
            $allowedRegionIds = collect($regionScopes)->pluck('region_id')->filter()->unique()->values()->all();
            abort_if($allowedRegionIds === [], 403, 'No region is assigned to your account.');
            abort_if($regionId !== null && ! in_array($regionId, $allowedRegionIds, true), 403, 'You are not assigned to that region.');

            if ($schoolId) {
                abort_unless(in_array($schoolId, $this->regionScopedSchoolIds([$schoolId]), true), 403, "You are not assigned to that school's region.");

                return ['schoolId' => $schoolId, 'schoolIds' => null, 'regionId' => $regionId ?? $allowedRegionIds[0]];
            }

            // Lock to the admin's single region automatically; with more than one assigned
            // region and none specified, still resolve the full set below rather than
            // falling through to build()'s unfiltered (null $schoolIds) "every school" path.
            $regionId ??= count($allowedRegionIds) === 1 ? $allowedRegionIds[0] : null;

            $candidateSchoolIds = $regionId
                ? \App\Models\SchoolRegionAssignment::forTenant($this->sahodaya->id)
                    ->forYear(\App\Support\AcademicYear::forSahodaya($this->sahodaya->id))
                    ->where('region_id', $regionId)
                    ->pluck('school_id')
                    ->all()
                : array_keys($register->schools($event));

            return [
                'schoolId'  => null,
                'schoolIds' => $this->regionScopedSchoolIds($candidateSchoolIds),
                'regionId'  => $regionId,
            ];
        }

        $schoolIds = null;
        if ($regionId && ! $schoolId) {
            $schoolIds = \App\Models\SchoolRegionAssignment::forTenant($this->sahodaya->id)
                ->forYear(\App\Support\AcademicYear::forSahodaya($this->sahodaya->id))
                ->where('region_id', $regionId)
                ->pluck('school_id')
                ->all();
        }

        return ['schoolId' => $schoolId, 'schoolIds' => $schoolIds, 'regionId' => $regionId];
    }

    public function assignmentCompleteness(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($this->regionAwareTargetEvent($request, $event));
        $rows = $analytics->assignmentCompletenessRows();

        return $this->inertia('Sahodaya/Events/Reports/AssignmentCompleteness', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'    => $rows,
            'totals'  => $analytics->assignmentCompletenessTotals($rows),
            'xlsUrl'  => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/assignment-completeness/export".($request->integer('region_id') ? '?region_id='.$request->integer('region_id') : ''),
        ])));
    }

    public function exportAssignmentCompleteness(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return (new \App\Services\Events\FestEventReportAnalyticsService($this->regionAwareTargetEvent($request, $event)))->exportAssignmentCompleteness();
    }

    public function numberingRegister(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($event);

        return $this->inertia('Sahodaya/Events/Reports/NumberingRegister', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'   => $analytics->numberingRegisterRows(),
            'xlsUrl' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/numbering-register/export",
        ])));
    }

    public function exportNumberingRegister(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return (new \App\Services\Events\FestEventReportAnalyticsService($event))->exportNumberingRegister();
    }

    public function pendingApprovals(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($event);
        $schoolId = $request->input('school_id');

        return $this->inertia('Sahodaya/Events/Reports/PendingApprovals', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'           => $analytics->pendingApprovalRows($schoolId ?: null),
            'schools'        => (new FestReportService($event))->schools(),
            'filterSchoolId' => $schoolId,
            'xlsUrl'         => '/sahodaya-admin/'.$tenantId.'/events/'.$event->id.'/reports/pending-approvals/export?'.http_build_query(array_filter(['school_id' => $schoolId])),
        ])));
    }

    public function exportPendingApprovals(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return (new \App\Services\Events\FestEventReportAnalyticsService($event))
            ->exportPendingApprovals($request->input('school_id'));
    }

    public function studentWise(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $service = new FestReportService($event);
        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($event);
        $schoolId = $request->input('school_id');
        $search = $request->input('search');
        $studentId = $request->integer('student_id') ?: null;
        $rows = $analytics->studentWiseBrowserRows($schoolId, $search);
        $selectedStudent = $studentId
            ? collect($rows)->firstWhere('student_id', $studentId)
            : null;

        $childEvents = [];
        if ($event->event_type === 'sports') {
            $seasonId = $event->parent_event_id ?? $event->id;
            $childEvents = FestEvent::where('parent_event_id', $seasonId)
                ->orWhere('id', $seasonId)
                ->ofType('sports')
                ->orderBy('title')
                ->get(['id', 'title', 'parent_event_id'])
                ->all();
        }

        return $this->inertia('Sahodaya/Events/Reports/StudentWise', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'            => $rows,
            'selectedStudent' => $selectedStudent,
            'filters'         => [
                'school_id'  => $schoolId,
                'search'     => $search,
                'student_id' => $studentId,
            ],
            'schools' => $service->schools()->values(),
            'xlsUrl'  => '/sahodaya-admin/'.$tenantId.'/events/'.$event->id.'/reports/export/student-wise-report?'.http_build_query(array_filter(['school_id' => $schoolId])),
            'childEvents' => $childEvents,
        ])));
    }

    public function itemWise(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($event->event_type === 'sports') {
            app(\App\Services\Events\FestItemHeadService::class)->syncEventHeads($event);
        }

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($event);
        $itemId = $request->integer('item_id') ?: null;
        $participants = $itemId ? $analytics->itemWiseBrowserRows($itemId) : [];

        $headId = $request->input('head_id') !== null && $request->input('head_id') !== ''
            ? ($request->input('head_id') === 'other' ? 'other' : (string) $request->integer('head_id'))
            : null;

        return $this->inertia('Sahodaya/Events/Reports/ItemWise', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'participants'   => $participants,
            'filterHeadId'   => $headId,
            'filterItemId'   => $itemId,
            'pdfUrl'         => $itemId ? '/sahodaya-admin/'.$tenantId.'/events/'.$event->id.'/reports/export/item-wise?'.http_build_query(['item_id' => $itemId]) : null,
            'xlsUrl'         => $itemId ? '/sahodaya-admin/'.$tenantId.'/events/'.$event->id.'/reports/export/item-participants?'.http_build_query(['item_id' => $itemId]) : null,
        ])));
    }

    public function export(Request $request, string $tenantId, FestEvent $event, string $exportType, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $catalog = collect(FestReportCatalog::exports($tenantId, $event->id))->firstWhere('id', $exportType);
        abort_unless(is_array($catalog), 404, 'Unknown report export.');
        $phase = $catalog['phase'] ?? 'before';

        $this->enforceReportLifecyclePhase($event, $phase, $request);

        // Phase 6 (partial — see final status report for what's NOT wired): an
        // 'after'-phase export additionally filtered to one named competition phase
        // (?competition_phase_id=) must respect that phase's own results_published flag,
        // not just the event's — plan §6.4: "A phase's After-event reports remain
        // unavailable until that phase's results are published."
        if ($phase === 'after' && $event->phase_mode_enabled && ($catalog['supports_competition_phase'] ?? false)) {
            $competitionPhaseId = $request->integer('competition_phase_id') ?: null;
            if ($competitionPhaseId !== null) {
                $lifecycle = app(\App\Services\Events\FestPhaseLifecycleService::class)
                    ->effectiveLifecycleForPhase($event, $competitionPhaseId);
                abort_unless($lifecycle->results_published || $request->user()?->can('fest.reports.lifecycle_override'), 403,
                    'Results for that competition phase are not published yet.');
            }
        }

        $audit->festEvent($event, FestPageActivity::reportsPhase($phase), 'fest.report.exported', "Report exported: {$exportType}", [
            'export_type' => $exportType,
        ]);

        // FestReportCatalog::REGION_ID_AWARE_IDS exports (item-list, discipline-
        // registration, head-wise-participants, mark-entry-status/mark-entered-summary,
        // clashes/clashes-school, assignment-completeness) resolve their data off
        // whatever event FestReportService is constructed with — reroute through the
        // same isolation used by the interactive pages above, so the export a region
        // tile links to matches what the interactive page for that region showed.
        $targetEvent = in_array($exportType, FestReportCatalog::REGION_ID_AWARE_IDS, true)
            ? $this->regionAwareTargetEvent($request, $event)
            : $event;

        return (new FestReportService($targetEvent))->export($exportType, $request);
    }

    /**
     * Phase 4 (plan §4.5, gap G6): the Downloads UI already filters exports to
     * EventLifecycleGate::allowedReportPhases($event), but until this check every export
     * endpoint served the file regardless — a direct URL to a Before/During/After export
     * bypassed the UI's own gate entirely. Enforced here at the generic export()
     * dispatcher (the ~50 FestReportCatalog exports) so it can't be bypassed by guessing
     * the URL. Not applied to the small number of report-specific export methods
     * (exportRegistrationRegister, exportAssignmentCompleteness, exportNumberingRegister,
     * exportPendingApprovals) — every one of those is catalogued as phase='before', which
     * is always in allowedReportPhases() regardless of event lifecycle, so the check
     * would be a permanent no-op for them today. Revisit if any of those four ever gets a
     * during/after catalog phase.
     *
     * fest.reports.lifecycle_override is a dedicated permission (not yet granted to any
     * role by a seeder — this only wires the check, per plan §4.5: "do not treat ordinary
     * staff access as an implicit override"). Granting it to specific roles/users is an
     * operational decision, not something this change makes for you.
     */
    private function enforceReportLifecyclePhase(FestEvent $event, string $phase, Request $request): void
    {
        $allowed = EventLifecycleGate::allowedReportPhases($event);

        if (in_array($phase, $allowed, true)) {
            return;
        }

        if ($request->user()?->can('fest.reports.lifecycle_override')) {
            return;
        }

        abort(403, 'This report is not available yet for the current event lifecycle.');
    }
}
