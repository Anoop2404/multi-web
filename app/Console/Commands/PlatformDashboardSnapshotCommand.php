<?php

namespace App\Console\Commands;

use App\Services\Reports\PlatformDashboardSnapshotService;
use Illuminate\Console\Command;

class PlatformDashboardSnapshotCommand extends Command
{
    protected $signature = 'platform:snapshot-dashboard';

    protected $description = 'Compute platform-wide student/teacher/revenue totals for the superadmin dashboard';

    public function handle(PlatformDashboardSnapshotService $service): int
    {
        $snapshot = $service->compute();

        $this->info(
            "Students: {$snapshot->total_students}. Teachers: {$snapshot->total_teachers}. ".
            "Revenue this month: ₹{$snapshot->revenue_this_month_inr}. ".
            "Sahodayas included: {$snapshot->sahodayas_included}/{$snapshot->sahodayas_total}."
        );

        return self::SUCCESS;
    }
}
