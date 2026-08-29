<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformDashboardSnapshot;
use App\Models\SubscriptionInvoice;
use App\Models\TenantSubscription;
use App\Services\Spreadsheet\SpreadsheetWriter;
use Illuminate\Support\Facades\Date;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FRD-13 §11 reports — trend views over what Phase 3 built (dashboard snapshots,
 * subscription lifecycle) rather than duplicating the existing flat Audit Log page
 * (AuditLogController), which already has its own filtering, category summary, and
 * CSV export.
 */
class PlatformReportsController extends Controller
{
    public function index(): Response
    {
        return inertia('Reports/Index', [
            'snapshots' => PlatformDashboardSnapshot::query()->orderByDesc('computed_at')->limit(90)->get(),
            'revenueByMonth' => $this->revenueByMonth(),
            'subscriptionStatusBreakdown' => TenantSubscription::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function exportSnapshots(): StreamedResponse
    {
        $rows = [['Computed at', 'Students', 'Teachers', 'Revenue this month (INR)', 'Sahodayas included', 'Sahodayas total']];

        foreach (PlatformDashboardSnapshot::query()->orderByDesc('computed_at')->limit(365)->get() as $snapshot) {
            $rows[] = [
                (string) $snapshot->computed_at?->toDateTimeString(),
                (string) $snapshot->total_students,
                (string) $snapshot->total_teachers,
                (string) $snapshot->revenue_this_month_inr,
                (string) $snapshot->sahodayas_included,
                (string) $snapshot->sahodayas_total,
            ];
        }

        $xlsx = SpreadsheetWriter::xlsx($rows);
        $filename = 'platform-snapshots-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(
            fn () => print $xlsx,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * Grouped in PHP rather than a SQL date-bucket (DATE_TRUNC vs strftime differ
     * between the Postgres dev DB and the SQLite test DB) — twelve months of
     * already-approved invoices is a small enough set that this costs nothing.
     *
     * @return list<array{month: string, revenue_inr: float}>
     */
    private function revenueByMonth(): array
    {
        $since = Date::now()->subMonths(11)->startOfMonth();

        $totalsByMonth = SubscriptionInvoice::query()
            ->where('status', 'approved')
            ->where('approved_at', '>=', $since)
            ->get(['amount', 'approved_at'])
            ->reduce(function (array $carry, SubscriptionInvoice $invoice) {
                $key = $invoice->approved_at->format('Y-m');
                $carry[$key] = ($carry[$key] ?? 0) + (float) $invoice->amount;

                return $carry;
            }, []);

        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Date::now()->subMonths($i);
            $months[] = [
                'month' => $date->format('M Y'),
                'revenue_inr' => round($totalsByMonth[$date->format('Y-m')] ?? 0, 2),
            ];
        }

        return $months;
    }
}
