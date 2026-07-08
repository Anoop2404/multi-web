<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Migration\KannurLegacySchoolCredentialSync;
use Illuminate\Console\Command;

class ResetKannurSchoolPasswords extends Command
{
    protected $signature = 'sahodaya:reset-kannur-school-passwords
                            {tenant : Kannur Sahodaya tenant UUID}
                            {--sql= : Path to legacy SQL dump (default: ~/Downloads/kannursahodaya-3533d24f.sql)}
                            {--create-missing : Create school_admin login when matched school has no portal user}
                            {--dry-run : Preview credential updates without writing}';

    protected $description = 'Reset matched Kannur school portal passwords to each school Gmail login address';

    public function handle(KannurLegacySchoolCredentialSync $sync): int
    {
        $tenantId = (string) $this->argument('tenant');
        $sqlPath = $this->option('sql') ?: (getenv('HOME').'/Downloads/kannursahodaya-3533d24f.sql');
        $dryRun = (bool) $this->option('dry-run');
        $createMissing = (bool) $this->option('create-missing');

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

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Syncing Kannur school credentials for {$sahodaya->name}");
        $this->line("SQL: {$sqlPath}");
        $this->line('Password will be set to each school Gmail login email.');
        if ($createMissing) {
            $this->line('Missing logins will be created when --create-missing is set.');
        }

        try {
            $stats = $sync->sync($sahodaya, $sqlPath, $dryRun, $createMissing, $this);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($stats)
                ->except('unmatched')
                ->map(fn ($value, $key) => [$key, is_scalar($value) ? $value : json_encode($value)])
                ->values()
                ->all(),
        );

        if (! empty($stats['unmatched'])) {
            $this->warn('Legacy schools that could not be matched:');
            $this->table(
                ['Legacy user', 'Name', 'Affiliation', 'Email'],
                collect($stats['unmatched'])->map(fn (array $row) => [
                    $row['legacy_user_id'] ?? '',
                    $row['legacy_name'] ?? '',
                    $row['affiliation_no'] ?? '',
                    $row['email'] ?? '',
                ])->all(),
            );
        }

        $this->info($dryRun ? 'Dry run complete.' : 'School credential sync complete.');
        $this->line('Schools sign in at /school-login using Gmail + same Gmail as password, then change password on first login.');

        return self::SUCCESS;
    }
}
