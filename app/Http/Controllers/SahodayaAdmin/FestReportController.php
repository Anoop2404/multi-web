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
use App\Support\FestTeamSquadRules;
use App\Services\Audit\PlatformAuditLogger;
use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsItemHeadReportContext;
use App\Http\Controllers\SahodayaAdmin\Concerns\ResolvesRegionAwareReportEvent;
use Illuminate\Http\Request;

class FestReportController extends SahodayaAdminController
{
    use BuildsItemHeadReportContext;
    use ResolvesRegionAwareReportEvent;

    private function reportScope(Request $request, FestEvent $event): \App\Services\Events\Reports\FestReportScope
    {
        $mode = $request->input('scope_mode');
        if (! $mode) {
            $mode = $request->integer('region_id')
                ? 'region'
                : (($event->parent_event_id === null && $event->usesPhasedRegionalBilling()) ? 'combined' : 'self');
        }

        return app(FestReportScopeResolver::class)->resolve($event, $request->user(), [
            'mode' => $mode,
            'region_id' => $request->integer('region_id') ?: null,
            'competition_phase_id' => $request->integer('competition_phase_id') ?: null,
            'registration_batch_id' => $request->integer('registration_batch_id') ?: null,
            'school_id' => $request->input('school_id'),
        ]);
    }

    private function scopedReportService(Request $request, FestEvent $event): FestReportService
    {
        return new FestReportService($event, $this->reportScope($request, $event));
    }

    private function scopedAnalytics(Request $request, FestEvent $event): \App\Services\Events\FestEventReportAnalyticsService
    {
        return new \App\Services\Events\FestEventReportAnalyticsService($event, $this->reportScope($request, $event));
    }
    /** @return array<string, mixed> */
    protected function reportProps(string $tenantId, FestEvent $event, array $extra = []): array
    {
        $base = "/sahodaya-admin/{$tenantId}/events/{$event->id}";
        $rootEvent = $event->rootEvent();

        $headContext = $this->itemHeadReportContext($event, null, $tenantId);

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
        // exception is FestReportCatalog::REGION_ID_AWARE_IDS: those builders resolve
        // data via $event->reportableEventIds()/reportableItemIds(), which, run
        // directly on the child, pulls in the hub's own uncopied item/registration rows
        // alongside the child's own. For just those ids, regionScopedRows() reroutes the
        // tile through the parent hub with an explicit region_id instead (same pattern
        // already used for Registration Register / Overall Ranking), which every
        // controller method behind those ids now understands via regionAwareTargetEvent().
        // See FestReportCatalog::REGION_ID_AWARE_IDS docblock for the current list.
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
            && count($regionChildren) > 0
            && ! $rootEvent->usesPhasedRegionalBilling();

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
            'childEvents'         => $event->sportEventDropdownOptions(),
            'competitionPhases'   => $rootEvent->usesPhasedRegionalBilling()
                ? $rootEvent->phases()->get(['id', 'name', 'code', 'is_regional'])
                : collect(),
            'registrationBatches' => $rootEvent->usesPhasedRegionalBilling()
                ? $rootEvent->registrationBatches()->get(['id', 'name', 'code'])
                : collect(),
            'reportScopeSelection' => [
                'competition_phase_id' => request()->integer('competition_phase_id') ?: null,
                'registration_batch_id' => request()->integer('registration_batch_id') ?: null,
                'region_id' => request()->integer('region_id') ?: null,
                'scope_mode' => request()->input('scope_mode', $rootEvent->usesPhasedRegionalBilling() ? 'combined' : 'self'),
            ],
        ], $headContext, $extra);
    }

    /**
     * Deliberately excluded from the regionAwareTargetEvent() retrofit (plan §4.4/Phase
     * 3, second pass): this is the Hub landing page — pure navigation, not a data
     * export. It renders $event as-is (already narrowed to the actor's own region child
     * by ResolveRegionScopedReportEvent when applicable); its 'interactive' catalog is
     * just a list of report page links, and nothing here runs
     * reportableEventIds()/reportableItemIds().
     */
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

        // Region-wise retrofit (plan §4.4/Phase 3, second pass): same
        // regionAwareTargetEvent() isolation as schoolDetailed/itemSchedule/etc. —
        // navigationForEvent() computes head/item participant counts via
        // $event->reportableEventIds()/reportableItemIds(), which otherwise pulled every
        // region's combined counts in for a hub opened directly (or with ?region_id=).
        $navService = app(\App\Services\Events\FestHeadItemNavigationService::class);
        $nav = $navService->navigationForEvent($this->regionAwareTargetEvent($request, $event));

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

    public function downloads(Request $request, string $tenantId, FestEvent $event, string $phase)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless(in_array($phase, ['before', 'during', 'after'], true), 404);

        // Region-wise retrofit (plan §4.4/Phase 3, second pass): same
        // regionAwareTargetEvent() isolation as itemSchedule/schoolDetailed/etc. — the
        // schools()/scheduleStages()/heads lookups below resolve via
        // reportableEventIds(), which otherwise pulled every region's combined rows in
        // for a hub opened directly (or with ?region_id=). $event itself stays
        // untouched for reportProps()'s nav/regionChildren and for URL building, same as
        // every other retrofitted method.
        $targetEvent = $this->regionAwareTargetEvent($request, $event);
        $service = $this->scopedReportService($request, $targetEvent);
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
                    ? FestEvent::where('parent_event_id', $targetEvent->id)->ofType('sports')->orderBy('sort_order')->orderBy('title')->get(['id', 'title'])->map(fn ($e) => ['id' => $e->id, 'name' => $e->title])->values()
                    : collect([['id' => $targetEvent->id, 'name' => $targetEvent->title]]))
                : \App\Models\FestItemHead::forTenant($this->sahodaya->id)->forEvent($targetEvent->id)->orderBy('sort_order')->get(['id', 'name']),
            'stages'                   => $service->scheduleStages()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
            'classGroups'              => FestReportService::classGroups($event),
            'currentPhase'             => $currentPhase,
            'activityLogs'             => $this->pageActivityLogs($event, FestPageActivity::reportsPhase($phase)),
        ]));
    }

    public function schoolDetailed(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // Region-wise retrofit (plan §4.4/Phase 3): same regionAwareTargetEvent()
        // isolation already applied to markEntryStatus/scheduleClashes/itemCounts/etc. —
        // a region-partition child opened directly (or the hub with ?region_id=) now
        // resolves through it instead of reading $event's own
        // reportableEventIds()/reportableItemIds(), which otherwise pulled the hub's
        // uncopied rows in alongside the child's.
        $service = $this->scopedReportService($request, $this->regionAwareTargetEvent($request, $event));
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

        $service = $this->scopedReportService($request, $targetEvent);

        return $this->inertia('Sahodaya/Events/Reports/OverallRanking', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rankings'       => $service->schoolRankingRows()->values(),
            'resultMode'     => $regionId !== null ? 'region' : 'combined',
            'filterRegionId' => $regionId,
            'pdfUrl'         => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/overall-ranking".($regionId ? "?region_id={$regionId}" : ''),
        ])));
    }

    public function houseDetailed(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return $this->inertia('Sahodaya/Events/Reports/HouseDetailed', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'board'   => EventContext::for($this->regionAwareTargetEvent($request, $event))->scoreboardByHouse(),
            'pdfUrl'  => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/house-wise",
        ])));
    }

    public function participationCounts(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $service = $this->scopedReportService($request, $this->regionAwareTargetEvent($request, $event));
        $policy = app(FestParticipationPolicyService::class)->resolveForEvent($event);

        $regs = $service->activeRegistrations();
        $used = [
            'total'      => $regs->count(),
            'on_stage'   => $regs->filter(fn ($r) => ($r->item?->stage_type ?? '') === 'on_stage')->count(),
            'off_stage'  => $regs->filter(fn ($r) => ($r->item?->stage_type ?? '') === 'off_stage')->count(),
            'individual' => $regs->filter(fn ($r) => $r->item?->participant_type === 'individual')->count(),
            'group'      => $regs->filter(fn ($r) => FestTeamSquadRules::isMultiPerson($r->item?->participant_type))->count(),
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

        $service = $this->scopedReportService($request, $this->regionAwareTargetEvent($request, $event));
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

        $service = $this->scopedReportService($request, $this->regionAwareTargetEvent($request, $event));
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

        $service = $this->scopedReportService($request, $this->regionAwareTargetEvent($request, $event));
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

        $analytics = $this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event));
        $rows = $analytics->itemRegistrationRows();

        return $this->inertia('Sahodaya/Events/Reports/ItemCounts', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'        => $rows,
            'headSummary' => $analytics->headRegistrationSummary(),
            'totals'      => $analytics->itemRegistrationTotals($rows),
            'pdfUrl'      => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/item-list",
            'childEvents' => $event->sportEventDropdownOptions(),
        ])));
    }

    public function disciplineRegistration(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = $this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event));

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

        $analytics = $this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event));
        $headId = $request->input('head_id') !== null && $request->input('head_id') !== ''
            ? ($request->input('head_id') === 'other' ? 0 : $request->integer('head_id'))
            : null;
        $schoolId = $request->input('school_id') ?: null;
        $itemId = $request->integer('item_id') ?: null;

        return $this->inertia('Sahodaya/Events/Reports/HeadWiseParticipants', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'summary'        => $analytics->headRegistrationSummary($schoolId),
            'rows'           => $analytics->headWiseParticipantRows($headId ?: null, $schoolId),
            'schools'        => $this->scopedReportService($request, $event)->schools(),
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

        $analytics = $this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event));
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
            'schools'        => $this->scopedReportService($request, $event)->schools(),
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

        $analytics = $this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event));
        $schoolId = $request->input('school_id');
        $data = $analytics->ageGroupMatrix($schoolId ?: null);

        return $this->inertia('Sahodaya/Events/Reports/AgeGroupMatrix', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'matrix'         => $data,
            'schools'        => $this->scopedReportService($request, $event)->schools(),
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
        $analytics = $this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event));
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
     * Resolves the FestEvent a region-aware report should actually read from, for every
     * report builder in FestReportCatalog::REGION_ID_AWARE_IDS plus feeCollection,
     * schoolDetailed, houseDetailed, participationCounts, areaWiseParticipants,
     * ageGroupMatrix, itemSchedule, numberingRegister/exportNumberingRegister,
     * pendingApprovals/exportPendingApprovals, studentWise, and itemWise (retrofitted
     * in the same pass once the same reportableEventIds()-on-a-region-child issue was
     * confirmed in each). Two paths:
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

        $data = $register->build($event, $scope['schoolId'], null, 50, null, null, $scope['schoolIds'], $scope['eventIds']);

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

        return $register->exportCsv($event, $scope['schoolId'], $scope['schoolIds'], $scope['regionId'], $scope['eventIds']);
    }

    /**
     * Resolve the school-id scope shared by the Registration Register browser view and
     * its export, so both read exactly the same rows (gap G3) and a region-locked admin
     * is narrowed to their assigned region(s) on both paths even if no region_id/school_id
     * was supplied, using active-year SchoolRegionAssignment data (gap G5). Tampered
     * region_id/school_id values outside the admin's assigned region(s) are rejected.
     *
     * The default (non-region-locked-admin) branch below delegates to
     * FestReportScopeResolver rather than querying SchoolRegionAssignment directly —
     * that resolver already knows a phased-regional-billing event's school-per-region
     * choice lives in FestSchoolPhaseRegionSelection (phase+region scoped), not the
     * legacy single-region-per-year SchoolRegionAssignment table, and also narrows
     * eventIds to just the requested phase+region leaf so a scoped register doesn't
     * pull in a school's registrations from every other leaf under the root. The
     * regionAdminScopes-restricted branch above is left untouched — it is
     * permission-scoping for the acting admin, not report-data scoping, and already
     * has its own (correct, tested) region-only resolution.
     *
     * @return array{schoolId: ?string, schoolIds: ?list<string>, regionId: ?int, eventIds: ?list<int>}
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

            $eventYear = $event->academicYear?->label;

            if ($schoolId) {
                abort_unless(in_array($schoolId, $this->regionScopedSchoolIds([$schoolId], $eventYear), true), 403, "You are not assigned to that school's region.");

                return ['schoolId' => $schoolId, 'schoolIds' => null, 'regionId' => $regionId ?? $allowedRegionIds[0], 'eventIds' => null];
            }

            // Lock to the admin's single region automatically; with more than one assigned
            // region and none specified, still resolve the full set below rather than
            // falling through to build()'s unfiltered (null $schoolIds) "every school" path.
            $regionId ??= count($allowedRegionIds) === 1 ? $allowedRegionIds[0] : null;

            // REG-06 fix (functional audit, 2026-08-11/12): use $event's own
            // academic year, not always "today's" — see the identical fix and
            // full rationale in FestReportScopeResolver::regionScope(). A
            // region-locked admin opening this register for a PAST event must
            // see the region's roster as it stood that year, not as it stands
            // today.
            $candidateSchoolIds = $regionId
                ? \App\Models\SchoolRegionAssignment::forTenant($this->sahodaya->id)
                    ->forYear($event->academicYear?->label ?? \App\Support\AcademicYear::forSahodaya($this->sahodaya->id))
                    ->where('region_id', $regionId)
                    ->pluck('school_id')
                    ->all()
                : array_keys($register->schools($event));

            return [
                'schoolId'  => null,
                'schoolIds' => $this->regionScopedSchoolIds($candidateSchoolIds, $eventYear),
                'regionId'  => $regionId,
                'eventIds'  => null,
            ];
        }

        $schoolIds = null;
        $eventIds = null;
        if ($regionId && ! $schoolId) {
            $scope = $this->reportScope($request, $event);
            $schoolIds = $scope->schoolIds;
            $eventIds = $scope->eventIds;
        }

        return ['schoolId' => $schoolId, 'schoolIds' => $schoolIds, 'regionId' => $regionId, 'eventIds' => $eventIds];
    }

    public function assignmentCompleteness(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = $this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event));
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

        return ($this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event)))->exportAssignmentCompleteness();
    }

    public function numberingRegister(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = $this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event));

        return $this->inertia('Sahodaya/Events/Reports/NumberingRegister', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'   => $analytics->numberingRegisterRows(),
            'xlsUrl' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/numbering-register/export".($request->integer('region_id') ? '?region_id='.$request->integer('region_id') : ''),
        ])));
    }

    public function exportNumberingRegister(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return ($this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event)))->exportNumberingRegister();
    }

    public function pendingApprovals(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = $this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event));
        $schoolId = $request->input('school_id');

        return $this->inertia('Sahodaya/Events/Reports/PendingApprovals', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'           => $analytics->pendingApprovalRows($schoolId ?: null),
            'schools'        => $this->scopedReportService($request, $event)->schools(),
            'filterSchoolId' => $schoolId,
            'xlsUrl'         => '/sahodaya-admin/'.$tenantId.'/events/'.$event->id.'/reports/pending-approvals/export?'.http_build_query(array_filter([
                'school_id' => $schoolId,
                'region_id' => $request->integer('region_id') ?: null,
            ])),
        ])));
    }

    public function exportPendingApprovals(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return ($this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event)))
            ->exportPendingApprovals($request->input('school_id'));
    }

    public function studentWise(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);
        $service = $this->scopedReportService($request, $targetEvent);
        $analytics = $this->scopedAnalytics($request, $targetEvent);
        $schoolId = $request->input('school_id');
        $search = $request->input('search');
        $studentId = $request->integer('student_id') ?: null;
        $rows = $analytics->studentWiseBrowserRows($schoolId, $search);
        $selectedStudent = $studentId
            ? collect($rows)->firstWhere('student_id', $studentId)
            : null;

        $childEvents = $event->sportEventDropdownOptions();

        return $this->inertia('Sahodaya/Events/Reports/StudentWise', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'            => $rows,
            'selectedStudent' => $selectedStudent,
            'filters'         => [
                'school_id'  => $schoolId,
                'search'     => $search,
                'student_id' => $studentId,
            ],
            'schools' => $service->schools()->values(),
            'pdfUrl'  => '/sahodaya-admin/'.$tenantId.'/events/'.$event->id.'/reports/export/student-wise-pdf?'.http_build_query(array_filter(['school_id' => $schoolId, 'search' => $search])),
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

        $analytics = $this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event));
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
            'childEvents'    => $event->sportEventDropdownOptions(),
        ])));
    }

    public function categoryWisePoints(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);
        $analytics = $this->scopedAnalytics($request, $targetEvent);
        $itemsByCategory = $analytics->categoryWiseItemRows();

        $scoreboards = app(\App\Services\Events\PublicFestScoreboardService::class);
        $categories = collect($itemsByCategory)
            ->map(fn (array $items, string $key) => [
                'key'   => $key,
                'label' => $key === 'open' ? 'Open' : $scoreboards->categoryLabel($targetEvent, $key),
                'items' => $items,
            ])
            ->sortBy('label')
            ->values()
            ->all();

        return $this->inertia('Sahodaya/Events/Reports/CategoryWisePoints', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'categories'  => $categories,
            'childEvents' => $event->sportEventDropdownOptions(),
        ])));
    }

    /** JSON endpoint the Category-wise Points report's eye-icon modal fetches directly. */
    public function categoryWisePointsParticipants(Request $request, string $tenantId, FestEvent $event, int $itemId)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $item = \App\Models\FestEventItem::find($itemId);
        abort_if(! $item || $item->event_id !== $event->id, 404);

        $analytics = $this->scopedAnalytics($request, $this->regionAwareTargetEvent($request, $event));

        return response()->json([
            'item_title'   => $item->title,
            'participants' => $analytics->itemPointsBreakdownRows($itemId),
        ]);
    }

    /** Consolidated category-wise & item-wise report — school rows, item columns grouped by category, subtotal + overall columns. */
    public function categoryItemMatrix(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);
        $analytics = $this->scopedAnalytics($request, $targetEvent);
        $matrix = $analytics->schoolItemPointsMatrix();

        return $this->inertia('Sahodaya/Events/Reports/CategoryItemMatrix', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'categories'  => $matrix['categories'],
            'schools'     => $matrix['schools'],
            'childEvents' => $event->sportEventDropdownOptions(),
        ])));
    }

    public function export(Request $request, string $tenantId, FestEvent $event, string $exportType, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $catalog = collect(FestReportCatalog::exports($tenantId, $event->id))->firstWhere('id', $exportType);
        abort_unless(is_array($catalog), 404, 'Unknown report export.');
        $phase = $catalog['phase'] ?? 'before';

        // Staff/admin reports are no longer gated by event lifecycle phase — organizers
        // need to be able to cross-verify data at any time, not just once the event
        // reaches a certain stage. See Documents/Fest_Improvements_Proposal.md §3.8.
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

        // FestReportCatalog::REGION_ID_AWARE_IDS exports resolve their data off whatever
        // event FestReportService is constructed with — reroute through the same
        // isolation used by the interactive pages above, so the export a region tile
        // links to (and every catalog-driven Downloads.vue export button) matches what
        // the interactive page for that region showed. See the catalog constant's own
        // docblock for the current id list.
        $targetEvent = in_array($exportType, FestReportCatalog::REGION_ID_AWARE_IDS, true)
            ? $this->regionAwareTargetEvent($request, $event)
            : $event;

        return $this->scopedReportService($request, $targetEvent)->export($exportType, $request);
    }

}
