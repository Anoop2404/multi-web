<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Migration\KannurLegacySchoolImporter;
use Illuminate\Console\Command;

class ImportKannurLegacySchools extends Command
{
    protected $signature = 'sahodaya:import-kannur-schools
                            {tenant : Kannur Sahodaya tenant UUID}
                            {--sql= : Path to legacy SQL dump (default: ~/Downloads/kannursahodaya-3533d24f.sql)}
                            {--dry-run : Preview school creation without writing}';

    protected $description = 'Create Kannur member schools from the legacy SQL dump (required before membership import)';

    public function handle(KannurLegacySchoolImporter $importer): int
    {
        $tenantId = (string) $this->argument('tenant');
        $sqlPath = $this->option('sql') ?: (getenv('HOME').'/Downloads/kannursahodaya-3533d24f.sql');
        $dryRun = (bool) $this->option('dry-run');

        $sahodaya = Tenant::query()->where('type', 'sahodaya')->find($tenantId);
        if (! $sahodaya) {
            $this->error("Sahodaya tenant not found: {$tenantId}");

            return self::FAILURE;
        }

        if (! is_readable($sqlPath)) {
            $this->error("Legacy SQL dump not readable: {$sqlPath}");
            $this->line('Pass --sql=/path/to/kannursahodaya.sql');

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Importing Kannur schools for {$sahodaya->name}");
        $this->line("SQL: {$sqlPath}");
        $this->line('Each school gets a portal login with password = login email.');

        try {
            $stats = $importer->import($sahodaya, $sqlPath, $dryRun, $this);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($stats)
                ->except('unmatched_remaining')
                ->map(fn ($value, $key) => [$key, is_scalar($value) ? $value : json_encode($value)])
                ->values()
                ->all(),
        );

        if (! empty($stats['unmatched_remaining'])) {
            $this->warn('Schools still unmatched after import:');
            $this->table(
                ['Legacy user', 'Name', 'Affiliation', 'Email'],
                collect($stats['unmatched_remaining'])->map(fn (array $row) => [
                    $row['legacy_user_id'] ?? '',
                    $row['legacy_name'] ?? '',
                    $row['affiliation_no'] ?? '',
                    $row['email'] ?? '',
                ])->all(),
            );
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete.' : 'School import complete.');
        $this->line('Next: run sahodaya:import-kannur-membership, then verify in Membership → Schools.');

        return self::SUCCESS;
    }
}
