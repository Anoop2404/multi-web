<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Services\Events\EventContext;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestParticipationPolicyService;
use App\Services\Events\FestRegistrationRegisterService;
use App\Services\Events\FestReportService;
use App\Support\FestPageActivity;
use App\Support\FestReportCatalog;
use App\Support\FestEventMeta;
use App\Services\Audit\PlatformAuditLogger;
use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsItemHeadReportContext;
use Illuminate\Http\Request;

class FestReportController extends SahodayaAdminController
{
    use BuildsItemHeadReportContext;
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

        return array_merge([
            'event'          => $event->only([
                'id', 'title', 'event_type', 'status', 'event_start', 'event_end',
                'registration_open', 'registration_close', 'venue', 'results_published',
                'schedule_published', 'level_round',
            ]),
            'eventMeta'      => FestEventMeta::reportSnapshot($event, $base, "{$base}/settings"),
            'interactiveNav' => FestReportCatalog::interactivePages($tenantId, $event->id, $event->event_type),
            'currentPhase'   => EventLifecycleGate::currentReportPhase($event),
            'allowedPhases'  => EventLifecycleGate::allowedReportPhases($event),
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

        return $this->inertia('Sahodaya/Events/Reports/Downloads', $this->reportProps($tenantId, $event, [
            'phase'        => $phase,
            'exports'      => $exports,
            'schools'      => $service->schools(),
            'items'        => $service->items()->map->only(['id', 'title', 'class_group']),
            'heads'        => $event->event_type === 'sports'
                ? ($event->isSportsSeasonEvent()
                    ? FestEvent::where('parent_event_id', $event->id)->ofType('sports')->orderBy('sort_order')->orderBy('title')->get(['id', 'title'])->map(fn ($e) => ['id' => $e->id, 'name' => $e->title])->values()
                    : collect([['id' => $event->id, 'name' => $event->title]]))
                : \App\Models\FestItemHead::forTenant($this->sahodaya->id)->forEvent($event->id)->orderBy('sort_order')->get(['id', 'name']),
            'stages'       => $service->scheduleStages()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
            'classGroups'  => FestReportService::classGroups($event),
            'currentPhase' => $currentPhase,
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::reportsPhase($phase)),
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

    public function overallRanking(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $service = new FestReportService($event);

        return $this->inertia('Sahodaya/Events/Reports/OverallRanking', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rankings' => $service->schoolRankingRows()->values(),
            'pdfUrl'   => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/overall-ranking",
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

    public function markEntryStatus(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $service = new FestReportService($event);
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

        $service = new FestReportService($event);
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

    public function itemCounts(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($event);
        $rows = $analytics->itemRegistrationRows();

        return $this->inertia('Sahodaya/Events/Reports/ItemCounts', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'        => $rows,
            'headSummary' => $analytics->headRegistrationSummary(),
            'totals'      => $analytics->itemRegistrationTotals($rows),
            'pdfUrl'      => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/item-list",
        ])));
    }

    public function disciplineRegistration(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($event);

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

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($event);
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

    public function feeCollection(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($event);
        $rows = $analytics->feeCollectionRows();

        return $this->inertia('Sahodaya/Events/Reports/FeeCollection', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'   => $rows,
            'byHead' => $analytics->feeCollectionByHeadRows(),
            'totals' => [
                'schools'  => count($rows),
                'due'      => round(collect($rows)->sum('total_due'), 2),
                'collected'=> round(collect($rows)->where('status', 'approved')->sum('total_due'), 2),
                'pending'  => collect($rows)->whereIn('status', ['pending', 'proof_uploaded'])->count(),
            ],
            'xlsUrl' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/fee-pending-schools",
            'feesUrl' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/fees",
        ])));
    }

    public function registrationRegister(Request $request, string $tenantId, FestEvent $event, FestRegistrationRegisterService $register)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $schoolId = $request->input('school_id');
        $data = $register->build($event, $schoolId ?: null);

        return $this->inertia('Sahodaya/Events/Reports/RegistrationRegister', $this->reportProps($tenantId, $event, [
            'rows'            => $data['rows'],
            'schoolSummaries' => $data['school_summaries'],
            'totals'          => $data['totals'],
            'schools'         => $register->schools($event),
            'filterSchoolId'  => $schoolId,
            'feesUrl'         => "/sahodaya-admin/{$tenantId}/events/{$event->id}/fees",
            'activityLogs'    => $this->pageActivityLogs($event, FestPageActivity::REPORTS),
        ]));
    }

    public function exportRegistrationRegister(Request $request, string $tenantId, FestEvent $event, FestRegistrationRegisterService $register)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return $register->exportCsv($event, $request->input('school_id'));
    }

    public function assignmentCompleteness(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $analytics = new \App\Services\Events\FestEventReportAnalyticsService($event);
        $rows = $analytics->assignmentCompletenessRows();

        return $this->inertia('Sahodaya/Events/Reports/AssignmentCompleteness', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'    => $rows,
            'totals'  => $analytics->assignmentCompletenessTotals($rows),
            'xlsUrl'  => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/assignment-completeness/export",
        ])));
    }

    public function exportAssignmentCompleteness(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return (new \App\Services\Events\FestEventReportAnalyticsService($event))->exportAssignmentCompleteness();
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
            'schools' => collect($service->schools())->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
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

        $audit->festEvent($event, FestPageActivity::reportsPhase($phase), 'fest.report.exported', "Report exported: {$exportType}", [
            'export_type' => $exportType,
        ]);

        return (new FestReportService($event))->export($exportType, $request);
    }
}
