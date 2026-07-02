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
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class FestReportController extends SahodayaAdminController
{
    /** @return array<string, mixed> */
    protected function reportProps(string $tenantId, FestEvent $event, array $extra = []): array
    {
        return array_merge([
            'event'          => $event->only('id', 'title', 'event_type', 'status'),
            'interactiveNav' => FestReportCatalog::interactivePages($tenantId, $event->id, $event->event_type),
        ], $extra);
    }

    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $service = new FestReportService($event);
        $allowedPhases = EventLifecycleGate::allowedReportPhases($event);
        $currentPhase = EventLifecycleGate::currentReportPhase($event);

        return $this->inertia('Sahodaya/Events/Reports/Hub', $this->reportProps($tenantId, $event, [
            'interactive' => FestReportCatalog::interactivePages($tenantId, $event->id, $event->event_type),
            'currentPhase'=> $currentPhase,
            'allowedPhases' => $allowedPhases,
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::REPORTS),
        ]));
    }

    public function downloads(string $tenantId, FestEvent $event, string $phase)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless(in_array($phase, ['before', 'during', 'after'], true), 404);

        $service = new FestReportService($event);
        $allowedPhases = EventLifecycleGate::allowedReportPhases($event);
        $currentPhase = EventLifecycleGate::currentReportPhase($event);

        $exports = array_values(array_filter(
            FestReportCatalog::exports($tenantId, $event->id),
            fn ($exp) => ($exp['phase'] ?? 'before') === $phase
                && in_array($exp['phase'] ?? 'before', $allowedPhases, true)
        ));

        return $this->inertia('Sahodaya/Events/Reports/Downloads', $this->reportProps($tenantId, $event, [
            'phase'        => $phase,
            'exports'      => $exports,
            'schools'      => $service->schools(),
            'items'        => $service->items()->map->only(['id', 'title', 'class_group']),
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

        $regs = $service->approvedRegistrations();
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

        $service = new FestReportService($event);
        $rows = $service->itemRegistrationCountRows();

        return $this->inertia('Sahodaya/Events/Reports/ItemCounts', $this->withEventActivity($event, FestPageActivity::REPORTS, $this->reportProps($tenantId, $event, [
            'rows'   => $rows,
            'totals' => [
                'items'    => count($rows),
                'approved' => array_sum(array_column($rows, 'approved')),
                'pending'  => array_sum(array_column($rows, 'pending')),
            ],
            'pdfUrl' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/export/item-list",
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

    public function export(Request $request, string $tenantId, FestEvent $event, string $exportType, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $catalog = collect(FestReportCatalog::exports($tenantId, $event->id))->firstWhere('id', $exportType);
        $phase = $catalog['phase'] ?? 'before';

        $audit->festEvent($event, FestPageActivity::reportsPhase($phase), 'fest.report.exported', "Report exported: {$exportType}", [
            'export_type' => $exportType,
        ]);

        return (new FestReportService($event))->export($exportType, $request);
    }
}
