<?php

namespace App\Console\Commands\State;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Backfills the one placeholder "Appeal School" tenant for every managed Sahodaya that
 * doesn't already have one — see Tenant::ensureAppealPoolSchool() (already used on-demand
 * by FestAppealWildcardService) and chat conversation 2026-09-14. Safe to re-run.
 */
class EnsureAppealSchools extends Command
{
    protected $signature = 'state:ensure-appeal-schools';

    protected $description = 'Ensure every managed Sahodaya tenant has its Appeal School placeholder';

    public function handle(): int
    {
        $count = 0;

        foreach (Tenant::sahodayas()->get() as $sahodaya) {
            $school = Tenant::ensureAppealPoolSchool($sahodaya->id);
            $this->line("{$sahodaya->name} — {$school->name} ({$school->id})");
            $count++;
        }

        $this->info("Checked {$count} Sahodaya(s).");

        return Command::SUCCESS;
    }
}
