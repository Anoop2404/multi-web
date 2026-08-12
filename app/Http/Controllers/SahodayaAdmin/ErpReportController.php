<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Services\Reports\ReportFilterOptionsService;
use App\Services\Reports\ReportRunner;
use Illuminate\Http\Request;

class ErpReportController extends SahodayaAdminController
{
    public function show(string $tenantId, string $reportId, Request $request, ReportRunner $runner, ReportFilterOptionsService $filterOptions)
    {
        abort_unless($runner->isRunnable($reportId), 404);
        abort_unless($runner->authorize($request->user(), $reportId), 403);

        $definition = $runner->find($this->sahodaya->id, $reportId);
        abort_if(! $definition, 404);

        $filters = $this->validatedFilters($request, $runner, $reportId);
        $filters = $this->withCurrentUserFilter($request, $reportId, $filters);
        $meta = $runner->meta($reportId);
        $preview = $runner->preview($this->sahodaya->id, $reportId, $filters, (int) $request->integer('page', 1));

        return $this->inertia('Sahodaya/Reports/Run', [
            'report'        => $definition,
            'meta'          => $meta,
            'preview'       => $preview,
            'filters'       => $filters,
            'filterOptions' => $filterOptions->forFilters(
                $this->sahodaya->id,
                collect($meta['filters'])->pluck('key')->all(),
                $filters,
            ),
            'exportUrl'     => "/sahodaya-admin/{$this->sahodaya->id}/reports/{$reportId}/export",
            'hubUrl'        => "/sahodaya-admin/{$this->sahodaya->id}/reports/hub",
        ]);
    }

    public function export(string $tenantId, string $reportId, Request $request, ReportRunner $runner)
    {
        abort_unless($runner->isRunnable($reportId), 404);
        abort_unless($runner->authorize($request->user(), $reportId), 403);

        $filters = $this->validatedFilters($request, $runner, $reportId);
        $filters = $this->withCurrentUserFilter($request, $reportId, $filters);
        $format = strtolower($request->string('format')->toString());

        if ($format === 'pdf' || $request->has('pdf')) {
            $definition = $runner->find($this->sahodaya->id, $reportId);
            $meta = $runner->meta($reportId);
            $queryService = app(\App\Services\Reports\ErpReportQueryService::class);
            $rows = $queryService->rows($this->sahodaya->id, $reportId, $filters);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.generic-pdf', [
                'title'         => $definition['title'] ?? $reportId,
                'columns'       => $meta['columns'] ?? [],
                'rows'          => $rows,
                'academicYear'  => $filters['academic_year'] ?? null,
                'selectedClass' => $filters['class'] ?? null,
                'orgName'       => $this->sahodaya->name ?? 'Sahodaya',
                'logoSrc'       => \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya),
                'generatedAt'   => now()->format('d M Y · h:i A'),
            ])->setPaper('a4', 'portrait');

            if ($request->boolean('download')) {
                return $pdf->download(strtolower($reportId).'-'.now()->format('Y-m-d').'.pdf');
            }

            return $pdf->stream(strtolower($reportId).'-'.now()->format('Y-m-d').'.pdf');
        }

        return $runner->export($request->user(), $this->sahodaya->id, $reportId, $filters);
    }

    /**
     * RPT-DSH-007 (functional audit 2026-08-11/12, action-plan item 14): the only report
     * in this hub that needs the ACTING user, not just the sahodaya, to compute its rows
     * (see ErpReportQueryService::myPendingApprovals()) — every other report is
     * sahodaya-scoped only. Injected here rather than widening ReportRunner::preview()/
     * export()'s signature for every other report's sake. Deliberately overwrites any
     * client-supplied '_current_user_id' — validatedFilters() above never lets that key
     * through anyway (it's not in this report's meta filters), but this is the one spot
     * that would matter if that ever changed.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function withCurrentUserFilter(Request $request, string $reportId, array $filters): array
    {
        if ($reportId === 'RPT-DSH-007') {
            $filters['_current_user_id'] = $request->user()?->id;
        }

        return $filters;
    }

    /** @return array<string, mixed> */
    private function validatedFilters(Request $request, ReportRunner $runner, string $reportId): array
    {
        $allowed = collect($runner->meta($reportId)['filters'])->pluck('key')->all();
        $filters = [];

        foreach ($allowed as $key) {
            if ($request->filled($key)) {
                $filters[$key] = $request->string($key)->toString();
            }
        }

        return $filters;
    }
}
