<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Migration\KannurLegacyMembershipImporter;
use Illuminate\Console\Command;

class ImportKannurLegacyMembership extends Command
{
    protected $signature = 'sahodaya:import-kannur-membership
                            {tenant : Kannur Sahodaya tenant UUID}
                            {--sql= : Path to legacy SQL dump (default: ~/Downloads/kannursahodaya-3533d24f.sql)}
                            {--legacy-uploads= : Path to old receipt uploads directory on disk}
                            {--storage-disk= : Storage disk for proofs (shared, local, s3; default: upload disk)}
                            {--proofs-only : Only copy receipt files for already-imported legacy payments}
                            {--dry-run : Preview mappings without writing}';

    protected $description = 'Import Kannur legacy membership fees, student counts, payments, and dues from the old Sahodaya SQL dump';

    public function handle(KannurLegacyMembershipImporter $importer): int
    {
        $tenantId = (string) $this->argument('tenant');
        $sqlPath = $this->option('sql') ?: (getenv('HOME').'/Downloads/kannursahodaya-3533d24f.sql');
        $dryRun = (bool) $this->option('dry-run');
        $proofsOnly = (bool) $this->option('proofs-only');
        $storageDisk = $this->option('storage-disk') ?: null;
        $legacyUploads = $this->option('legacy-uploads') ?: null;

        if ($proofsOnly && ! $legacyUploads) {
            $this->error('--legacy-uploads is required when using --proofs-only');

            return self::FAILURE;
        }

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

        $this->info(($dryRun ? '[DRY RUN] ' : '').($proofsOnly ? 'Syncing legacy proofs' : 'Importing legacy membership')." for {$sahodaya->name}");
        $this->line("SQL: {$sqlPath}");
        if ($legacyUploads) {
            $this->line("Legacy uploads: {$legacyUploads}");
        }
        if ($storageDisk) {
            $this->line("Storage disk: {$storageDisk}");
        }

        try {
            $stats = $importer->import(
                $sahodaya,
                $sqlPath,
                $dryRun,
                $legacyUploads,
                $this,
                $storageDisk,
                $proofsOnly,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($stats)
                ->except('unmatched_schools')
                ->map(fn ($value, $key) => [$key, is_scalar($value) ? $value : json_encode($value)])
                ->values()
                ->all(),
        );

        if (! empty($stats['unmatched_schools'])) {
            $this->warn('Schools that could not be matched to the new system:');
            $this->table(
                ['Legacy user', 'Name', 'Affiliation', 'Email', 'Payment', 'Strength'],
                collect($stats['unmatched_schools'])->map(fn (array $row) => [
                    $row['legacy_user_id'] ?? '',
                    $row['legacy_name'] ?? '',
                    $row['affiliation_no'] ?? '',
                    $row['email'] ?? '',
                    ! empty($row['has_payment']) ? 'yes' : 'no',
                    ! empty($row['has_strength']) ? 'yes' : 'no',
                ])->all(),
            );
            $this->line('Add these schools in the new system first, or provide a manual mapping CSV in a follow-up if needed.');
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Legacy membership import complete.');

        return self::SUCCESS;
    }
}
