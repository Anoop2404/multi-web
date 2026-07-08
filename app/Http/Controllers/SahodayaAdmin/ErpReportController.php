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

        return $runner->export($request->user(), $this->sahodaya->id, $reportId, $filters);
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
