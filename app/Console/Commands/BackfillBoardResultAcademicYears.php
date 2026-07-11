<?php

namespace App\Console\Commands;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\Tenant;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class BackfillBoardResultAcademicYears extends Command
{
    protected $signature = 'board-results:backfill-academic-years {--tenant= : Sahodaya tenant id}';

    protected $description = 'Backfill board_results.academic_year_id from academic_years.label';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $sahodayas = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($tenantId, fn ($q) => $q->where('id', $tenantId))
            ->get();

        $total = 0;
        foreach ($sahodayas as $sahodaya) {
            TenancyDatabase::withTenantDatabase($sahodaya, function () use ($sahodaya, &$total) {
                $map = AcademicYearRecord::query()->pluck('id', 'label');
                $updated = 0;
                BoardResult::query()->whereNull('academic_year_id')->orderBy('id')->each(function (BoardResult $result) use ($map, &$updated) {
                    $id = $map[$result->academic_year] ?? null;
                    if ($id) {
                        $result->update(['academic_year_id' => $id]);
                        $updated++;
                    }
                });
                $this->info("{$sahodaya->name}: linked {$updated} board result(s).");
                $total += $updated;
            });
        }

        $this->info("Done. Linked {$total} row(s).");

        return self::SUCCESS;
    }
}
