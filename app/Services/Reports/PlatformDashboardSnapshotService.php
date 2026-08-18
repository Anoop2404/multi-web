<?php

namespace App\Services\Reports;

use App\Models\PlatformDashboardSnapshot;
use App\Models\Student;
use App\Models\SubscriptionInvoice;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Support\TenancyDatabase;
use Illuminate\Support\Facades\Log;

/**
 * Students/teachers live in per-Sahodaya databases, so a platform-wide total means
 * looping every Sahodaya's database — too slow to do live on every dashboard request.
 * compute() does that loop and persists one row; the dashboard just reads latest().
 */
class PlatformDashboardSnapshotService
{
    public function compute(): PlatformDashboardSnapshot
    {
        $sahodayas = Tenant::query()->where('type', 'sahodaya')->orderBy('name')->get();
        $totalStudents = 0;
        $totalTeachers = 0;
        $included = 0;

        $countInCurrentContext = function () use (&$totalStudents, &$totalTeachers) {
            $totalStudents += Student::count();
            $totalTeachers += Teacher::count();
        };

        if (! TenancyDatabase::enabled()) {
            $countInCurrentContext();
            $included = $sahodayas->count();
        } else {
            foreach ($sahodayas as $sahodaya) {
                try {
                    TenancyDatabase::withTenantDatabase($sahodaya, $countInCurrentContext);
                    $included++;
                } catch (\Throwable $e) {
                    Log::warning("Platform dashboard snapshot: could not query Sahodaya {$sahodaya->id} ({$sahodaya->name}): {$e->getMessage()}");
                }
            }
        }

        $revenue = (float) SubscriptionInvoice::query()
            ->where('status', 'approved')
            ->whereYear('approved_at', now()->year)
            ->whereMonth('approved_at', now()->month)
            ->sum('amount');

        return PlatformDashboardSnapshot::create([
            'total_students' => $totalStudents,
            'total_teachers' => $totalTeachers,
            'revenue_this_month_inr' => $revenue,
            'sahodayas_included' => $included,
            'sahodayas_total' => $sahodayas->count(),
            'computed_at' => now(),
        ]);
    }

    public function latest(): ?PlatformDashboardSnapshot
    {
        return PlatformDashboardSnapshot::query()->latest('computed_at')->first();
    }
}
