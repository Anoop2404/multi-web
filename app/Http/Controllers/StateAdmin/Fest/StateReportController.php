<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\State\StateFestEvent;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StatePrintService;
use App\Services\State\Reports\StateReportDataService;
use App\Support\CsvSafety;
use App\Support\ExcelExport;
use App\Support\PdfGenerator;
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

        // A printed sheet has no table to preview — it is paper, with a section per item and blank
        // columns to write in. Previewing it as a summary (what will print, how many pages of it)
        // is more honest than rendering the blank columns on screen.
        $built = $this->isPrintable($definition)
            ? $this->sheetSummary($definition, $event, $this->filters($request))
            : $data->build($report, $event, $this->filters($request));

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

    public function download(Request $request, StateFestEvent $event, string $report, StateReportDataService $data)
    {
        StateScope::assertOwns($event->state_id);
        $definition = $this->definitionFor($report);

        $format = $request->query('format', $definition['formats'][0] ?? 'csv');
        abort_unless(in_array($format, $definition['formats'], true), 422, "This report does not offer a {$format} download.");

        if ($this->isPrintable($definition)) {
            return $this->printable($definition, $event, $this->filters($request));
        }

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

    private function isPrintable(array $definition): bool
    {
        return in_array($definition['kind'] ?? 'table', ['sheet', 'cards'], true);
    }

    /**
     * Render a printed sheet or a set of cards. Both go through the configured browser renderer when
     * one is available, because these lay out in a grid and DomPDF's flow breaks the card grid — but
     * neither is allowed to fail outright if the renderer is down, since a sheet printed by DomPDF is
     * still a usable sheet.
     */
    private function printable(array $definition, StateFestEvent $event, array $filters)
    {
        $print = app(StatePrintService::class);
        $filename = \Str::slug($event->name.' '.$definition['label']).'.pdf';
        $shared = ['eventName' => $event->name, 'generated' => now()->format('d M Y, H:i')];

        if (($definition['kind'] ?? null) === 'cards') {
            $html = view('state.print.cards', $shared + ['cards' => $print->cards($event, $filters)])->render();

            return PdfGenerator::download($html, $filename, margin: ['top' => '8mm', 'right' => '8mm', 'bottom' => '8mm', 'left' => '8mm']);
        }

        $sheet = match ($definition['id']) {
            'attendance-sheet' => $print->attendanceSheet($event, $filters),
            'timesheet' => $print->timesheet($event, $filters),
            'judge-sheet' => $print->judgeSheet($event, $filters),
            'green-room-sheet' => $print->greenRoomSheet($event, $filters),
            default => abort(404, 'No such printed sheet.'),
        };

        $html = view('state.print.sheet', $shared + $sheet)->render();

        return PdfGenerator::download($html, $filename, margin: ['top' => '12mm', 'right' => '10mm', 'bottom' => '12mm', 'left' => '10mm']);
    }

    /**
     * What a printed sheet will contain, as a table: the operator wants to know an item is scheduled
     * and how many entries it has before sending 40 pages to a printer.
     *
     * @return array{title: string, headers: list<string>, rows: list<list<string>>}
     */
    private function sheetSummary(array $definition, StateFestEvent $event, array $filters): array
    {
        $print = app(StatePrintService::class);

        if (($definition['kind'] ?? null) === 'cards') {
            return [
                'title' => $definition['label'],
                'headers' => ['Participant', 'Class', 'Sahodaya', 'School', 'Items & chest numbers'],
                'rows' => $print->cards($event, $filters)
                    ->map(fn (array $c) => [
                        $c['name'], (string) $c['class_name'], (string) $c['sahodaya'], (string) $c['school'],
                        collect($c['entries'])->map(fn (array $e) => $e['item_code'].' '.($e['chest_number'] ?: '—'))->implode(', '),
                    ])->all(),
            ];
        }

        $sheet = $print->attendanceSheet($event, $filters);

        return [
            'title' => $definition['label'].' — what will print',
            'headers' => ['Item', 'Date', 'Start', 'Venue', 'Entries'],
            'rows' => collect($sheet['groups'])->map(fn (array $g) => [
                (string) $g['item_code'], (string) ($g['when'] ?? 'Not scheduled'),
                (string) ($g['starts_at'] ?? '—'), (string) ($g['venue'] ?? 'No venue'), (string) $g['count'],
            ])->all(),
        ];
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
