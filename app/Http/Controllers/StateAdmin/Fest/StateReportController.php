<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\State\StateFestEvent;
use App\Models\State\StateSahodaya;
use App\Services\State\Reports\StateReportDataService;
use App\Support\CsvSafety;
use App\Support\ExcelExport;
use App\Support\StateFestReportCatalog;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 8 of the State Kalotsav module — the State reports hub.
 *
 * Scoped to the reports reachable from the sidebar and workspace tabs, mirroring what the Sahodaya
 * module actually exposes in its navigation rather than the full inventory of export permutations.
 */
class StateReportController extends Controller
{
    public function index(Request $request, StateFestEvent $event)
    {
        StateScope::assertOwns($event->state_id);

        return Inertia::render('State/Fest/Reports', [
            'event' => [
                'id' => $event->id, 'name' => $event->name, 'status' => $event->status,
                'results_published' => (bool) $event->results_published,
                'scoring_locked' => (bool) $event->scoring_locked,
                'starts_on' => $event->starts_on?->toDateString(), 'ends_on' => $event->ends_on?->toDateString(),
            ],
            'events' => StateScope::apply(StateFestEvent::query())->orderByDesc('starts_on')
                ->get(['id', 'name', 'status'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'status' => $e->status, 'href' => "/admin/state/fest/{$e->id}"]),
            'permissions' => app(StateFestWorkspaceController::class)->permissionsForRequest($request),
            'sahodayas' => StateSahodaya::query()
                ->when(StateScope::shouldScope(), fn ($q) => $q->forState(StateScope::id()))
                ->orderBy('name')->get(['id', 'name', 'district', 'origin']),
            'groups' => StateFestReportCatalog::groups(),
            'reports' => StateFestReportCatalog::reports(),
            'baseUrl' => "/admin/state/fest/{$event->id}/reports",
        ]);
    }

    /** On-screen preview — the same rows the download produces, capped so a big report stays usable. */
    public function show(Request $request, StateFestEvent $event, string $report, StateReportDataService $data)
    {
        StateScope::assertOwns($event->state_id);
        $definition = $this->definitionFor($report);

        $built = $data->build($report, $event, $this->filters($request));

        return Inertia::render('State/Fest/ReportPreview', [
            'event' => ['id' => $event->id, 'name' => $event->name],
            'permissions' => app(StateFestWorkspaceController::class)->permissionsForRequest($request),
            'report' => $definition,
            'title' => $built['title'],
            'headers' => $built['headers'],
            'rows' => array_slice($built['rows'], 0, 500),
            'total' => count($built['rows']),
            'truncated' => count($built['rows']) > 500,
            'filters' => $this->filters($request),
            'sahodayas' => StateSahodaya::query()
                ->when(StateScope::shouldScope(), fn ($q) => $q->forState(StateScope::id()))
                ->orderBy('name')->get(['id', 'name', 'district']),
            'downloadUrl' => "/admin/state/fest/{$event->id}/reports/{$report}/download",
            'backUrl' => "/admin/state/fest/{$event->id}/reports",
        ]);
    }

    public function download(Request $request, StateFestEvent $event, string $report, StateReportDataService $data): StreamedResponse
    {
        StateScope::assertOwns($event->state_id);
        $definition = $this->definitionFor($report);

        $format = $request->query('format', $definition['formats'][0] ?? 'csv');
        abort_unless(in_array($format, $definition['formats'], true), 422, "This report does not offer a {$format} download.");

        $built = $data->build($report, $event, $this->filters($request));
        $filename = \Str::slug($event->name.' '.$built['title']);

        return match ($format) {
            'xls' => ExcelExport::download("{$filename}.xls", $built['headers'], $built['rows']),
            'csv' => $this->csv("{$filename}.csv", $built['headers'], $built['rows']),
            'pdf' => $this->pdf($filename, $event, $built),
            default => abort(422, 'Unsupported format.'),
        };
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'sahodaya_id' => 'nullable|uuid',
            'school_id'   => 'nullable|string|max:191',
            'item_id'     => 'nullable|uuid',
            'origin'      => 'nullable|in:managed,external',
            'search'      => 'nullable|string|max:120',
        ]);
    }

    /** @return array<string, mixed> */
    private function definitionFor(string $report): array
    {
        $definition = StateFestReportCatalog::find($report);

        abort_unless($definition, 404, 'No such State report.');

        // A report whose phase is not built would otherwise render an empty table that reads as
        // "nobody has registered" rather than "this does not exist yet".
        abort_if(
            ! ($definition['available'] ?? false),
            409,
            $definition['blocked_by'] ?? 'This report is not available yet.',
        );

        return $definition;
    }

    private function csv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            // CsvSafety, not fputcsv: a participant or school name beginning with =, + or @ is a
            // formula injection the moment the file is opened in Excel.
            CsvSafety::fputcsv($out, $headers);
            foreach ($rows as $row) {
                CsvSafety::fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function pdf(string $filename, StateFestEvent $event, array $built): StreamedResponse
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('state.reports.table', [
            'eventName' => $event->name,
            'title'     => $built['title'],
            'headers'   => $built['headers'],
            'rows'      => $built['rows'],
            'generated' => now()->format('d M Y, H:i'),
        ])->setPaper('a4', count($built['headers']) > 5 ? 'landscape' : 'portrait');

        return response()->streamDownload(fn () => print($pdf->output()), "{$filename}.pdf", ['Content-Type' => 'application/pdf']);
    }
}
