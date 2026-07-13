<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Events\FestCompetitionTypeRegistry;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class EnsureFestCompetitionTypes extends Command
{
    protected $signature = 'fest:ensure-competition-types {--sahodaya= : Limit to one Sahodaya UUID}';

    protected $description = 'Seed/backfill fest_competition_types rows for all active Sahodayas (FRD-08 Phase 8)';

    public function handle(FestCompetitionTypeRegistry $registry): int
    {
        $query = Tenant::query()->sahodayas()->where('is_active', true);
        if ($id = $this->option('sahodaya')) {
            $query->where('id', $id);
        }

        $done = 0;
        foreach ($query->get() as $sahodaya) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($registry, $sahodaya, &$done) {
                $registry->forTenant($sahodaya->id)->ensureDefaults();
                $done++;
                $this->line("Seeded competition types for {$sahodaya->name}");
            });
        }

        $this->info("Ensured competition types for {$done} Sahodaya(s).");

        return self::SUCCESS;
    }
}
