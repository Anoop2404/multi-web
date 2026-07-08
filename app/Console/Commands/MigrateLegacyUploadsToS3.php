<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Storage\LegacyStorageMigrationService;
use Illuminate\Console\Command;

class MigrateLegacyUploadsToS3 extends Command
{
    protected $signature = 'erp:migrate-uploads-to-s3
                            {--tenant= : Sahodaya tenant id (default: all)}
                            {--dry-run : Count only, do not copy}
                            {--delete-local : Remove local copy after successful upload}
                            {--filesystem : Also migrate orphan files under local disks}
                            {--scan : Show pending counts and exit}';

    protected $description = 'Migrate legacy local/shared uploads to AWS S3';

    public function handle(LegacyStorageMigrationService $migration): int
    {
        if ($this->option('scan')) {
            $scan = $migration->scan($this->option('tenant'));
            $this->table(
                ['Source', 'Records', 'Pending', 'On S3', 'Missing'],
                collect($scan['sources'])->map(fn ($r) => [
                    $r['label'], $r['records'], $r['pending'], $r['on_s3'], $r['missing'],
                ])->all(),
            );
            $this->info('Tenant logos pending: '.$scan['tenant_logos']);
            $this->info('Filesystem orphans pending: '.$scan['totals']['filesystem_orphans']);

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $deleteLocal = (bool) $this->option('delete-local');

        if ($deleteLocal && ! $this->confirm('Delete local files after upload to S3?')) {
            return self::FAILURE;
        }

        $tenantId = $this->option('tenant');
        if ($tenantId) {
            $name = Tenant::find($tenantId)?->name ?? $tenantId;
            $this->info("Migrating uploads for {$name}…");
        } else {
            $this->info('Migrating uploads for all Sahodaya tenants…');
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no files will be copied.');
        }

        $result = $migration->migrate(
            $tenantId,
            $dryRun,
            $deleteLocal,
            (bool) $this->option('filesystem'),
            function (array $outcome, string $label, string $path) {
                if ($outcome['status'] === 'migrated') {
                    $this->line("  ✓ {$label}: {$path}");
                }
            },
        );

        $this->newLine();
        $this->info("Migrated: {$result['migrated']} · Skipped: {$result['skipped']} · Failed: {$result['failed']} · Missing: {$result['missing']}");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
