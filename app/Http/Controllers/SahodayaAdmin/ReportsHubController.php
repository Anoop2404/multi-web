<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsItemHeadReportContext;
use App\Models\FestEvent;
use App\Support\ErpReportMeta;
use App\Support\EventReportCatalogBridge;
use App\Support\ReportRegistry;
use Illuminate\Http\Request;

class ReportsHubController extends SahodayaAdminController
{
    use BuildsItemHeadReportContext;

    public function index(Request $request)
    {
        $all = collect(ReportRegistry::definitions($this->sahodaya->id));

        $operational = $all
            ->filter(fn (array $r) => ErpReportMeta::scope($r['id']) === 'sahodaya')
            ->groupBy('module');

        $crossEvent = $all
            ->filter(fn (array $r) => ErpReportMeta::scope($r['id']) === 'cross_event')
            ->values();

        $events = FestEvent::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_type', 'status', 'event_start', 'event_end']);

        $selectedEventId = $request->integer('event_id') ?: null;
        $selectedEvent = null;
        $eventPurposeGroups = [];
        $headSummary = [];
        $headItemGroups = [];
        $headWiseReportBase = null;
        $headWiseExportUrl = null;
        $itemHeadsManageUrl = null;
        $hasItemHeads = false;
        $eventHubUrl = null;
        $eventExportsUrl = null;

        if ($selectedEventId) {
            $selectedEvent = FestEvent::where('tenant_id', $this->sahodaya->id)->find($selectedEventId);

            if ($selectedEvent) {
                $eventPurposeGroups = EventReportCatalogBridge::purposeGroups($this->sahodaya->id, $selectedEvent);
                $eventHubUrl = EventReportCatalogBridge::eventHubUrl($this->sahodaya->id, $selectedEvent);
                $eventExportsUrl = EventReportCatalogBridge::eventExportsUrl($this->sahodaya->id, $selectedEvent);

                $headCtx = $this->itemHeadReportContext($selectedEvent, null, $this->sahodaya->id);
                $headSummary = $headCtx['headSummary'] ?? [];
                $headItemGroups = $headCtx['headItemGroups'] ?? [];
                $hasItemHeads = (bool) ($headCtx['hasItemHeads'] ?? false);
                $headWiseReportBase = $headCtx['headWiseReportBase'] ?? null;
                $headWiseExportUrl = $headCtx['headWiseExportUrl'] ?? null;
                $itemHeadsManageUrl = $headCtx['itemHeadsManageUrl'] ?? null;
            }
        }

        $runnableCount = $all->where('runnable', true)->count();

        return $this->inertia('Sahodaya/Reports/Hub', [
            'reportsByModule'    => $operational,
            'crossEventReports'  => $crossEvent,
            'events'             => $events,
            'selectedEventId'    => $selectedEventId,
            'selectedEvent'      => $selectedEvent?->only(['id', 'title', 'event_type', 'status', 'event_start', 'event_end']),
            'eventPurposeGroups' => $eventPurposeGroups,
            'eventHubUrl'        => $eventHubUrl,
            'eventExportsUrl'    => $eventExportsUrl,
            'headSummary'        => $headSummary,
            'headItemGroups'     => $headItemGroups,
            'hasItemHeads'       => $hasItemHeads,
            'headWiseReportBase' => $headWiseReportBase,
            'headWiseExportUrl'  => $headWiseExportUrl,
            'itemHeadsManageUrl' => $itemHeadsManageUrl,
            'asyncThreshold'     => ReportRegistry::asyncExportThreshold(),
            'runnableCount'      => $runnableCount,
            'hubBase'            => "/sahodaya-admin/{$this->sahodaya->id}/reports/hub",
        ]);
    }
}
