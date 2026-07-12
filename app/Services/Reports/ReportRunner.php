<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Exports\CsvExportDispatcher;
use App\Support\ReportRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportRunner
{
    public function __construct(
        private ErpReportQueryService $queries,
        private CsvExportDispatcher $exports,
    ) {}

    public function authorize(User $user, string $reportId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Seeded Sahodaya roles (RolesAndPermissionsSeeder) — not legacy secretary/finance_clerk names.
        if (! $user->hasAnyRole(['sahodaya_admin', 'sahodaya_staff', 'sahodaya_finance'])) {
            return false;
        }

        if (str_starts_with($reportId, 'RPT-AUTH-')) {
            return $user->hasAnyRole(['sahodaya_admin']);
        }

        if ($reportId === 'RPT-FIN-021') {
            // Fee receipt <-> ledger reconciliation surfaces raw cross-school discrepancies —
            // stricter than routine finance reports, so it's admin-only rather than shared
            // with the broader finance role.
            return $user->hasAnyRole(['sahodaya_admin']);
        }

        if (str_starts_with($reportId, 'RPT-FIN-') || str_starts_with($reportId, 'RPT-PAY-')) {
            return $user->hasAnyRole(['sahodaya_admin', 'sahodaya_finance']);
        }

        return true;
    }

    /** @return array{id: string, label: string, module: string, classification: string, href?: string}|null */
    public function find(string $sahodayaId, string $reportId): ?array
    {
        return collect(ReportRegistry::definitions($sahodayaId))
            ->firstWhere('id', $reportId);
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(User $user, string $sahodayaId): array
    {
        if (! $this->authorize($user, 'RPT-SCH-001')) {
            return [];
        }

        return ReportRegistry::definitions($sahodayaId);
    }

    public function isRunnable(string $reportId): bool
    {
        return $this->queries->isRunnable($reportId);
    }

    /** @return array{columns: list<array{key: string, label: string}>, filters: list<array{key: string, label: string, type: string}>} */
    public function meta(string $reportId): array
    {
        return $this->queries->meta($reportId);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function preview(string $sahodayaId, string $reportId, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $all = $this->queries->rows($sahodayaId, $reportId, $filters);
        $total = $all->count();
        $rows = $all->slice(($page - 1) * $perPage, $perPage)->values()->all();

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(User $user, string $sahodayaId, string $reportId, array $filters = []): StreamedResponse|RedirectResponse
    {
        $meta = $this->meta($reportId);
        $headers = array_column($meta['columns'], 'label');
        $keys = array_column($meta['columns'], 'key');
        $rows = $this->queries->rows($sahodayaId, $reportId, $filters);
        $filename = strtolower($reportId).'-'.now()->format('Y-m-d').'.csv';

        return $this->exports->dispatch(
            $user,
            $reportId,
            $filename,
            $rows,
            $headers,
            fn (array $row) => array_map(fn (string $key) => $row[$key] ?? '', $keys),
        );
    }
}
