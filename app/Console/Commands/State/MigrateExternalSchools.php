<?php

namespace App\Console\Commands\State;

use App\Models\ExternalSahodaya;
use App\Models\ExternalSchool;
use App\Services\State\ExternalSchoolMigrator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Phase 3 of docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md — the follow-up to
 * state:promote-sahodayas. Once a Sahodaya is a tenant, its access-code roster of schools becomes
 * real school tenants underneath it, sharing the Sahodaya's database.
 *
 * Run it after a promotion has been verified, not as part of one: this writes the schools and their
 * logins, and a Sahodaya whose own provisioning is still broken has nowhere sound to put them.
 *
 * As with promotion, one bad school never aborts the batch.
 */
class MigrateExternalSchools extends Command
{
    protected $signature = 'state:migrate-external-schools
        {sahodaya?        : ExternalSahodaya uuid; omit with --all-promoted}
        {--all-promoted   : Every school under every promoted Sahodaya}
        {--limit=         : Stop after this many schools}
        {--dry-run        : Print what would happen and exit without writing anything}';

    protected $description = 'Migrate the schools on a promoted Sahodaya\'s outside roster into real school tenants';

    public function handle(ExternalSchoolMigrator $migrator): int
    {
        $query = ExternalSchool::query()
            ->with('sahodaya.tenant')
            ->whereNull('tenant_id')
            // Appeal-pool schools are a synthetic entry bucket, not a real school, so they are
            // filtered out here rather than reported as a failure on every run.
            ->where('is_appeal_pool', false);

        if ($id = $this->argument('sahodaya')) {
            if (! ExternalSahodaya::find($id)) {
                $this->error('No ExternalSahodaya found with that id.');

                return Command::FAILURE;
            }
            $query->where('external_sahodaya_id', $id);
        } elseif (! $this->option('all-promoted')) {
            $this->error('Pass a Sahodaya id, or --all-promoted for every promoted Sahodaya.');

            return Command::FAILURE;
        } else {
            $query->whereHas('sahodaya', fn ($q) => $q->whereNotNull('tenant_id'));
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $schools = $query->get()->sortBy(fn (ExternalSchool $s) => [$s->sahodaya?->name, $s->name])->values();

        if ($schools->isEmpty()) {
            $this->info('No schools to migrate for that selection.');
            $this->line('Appeal-pool rows and already-migrated schools are skipped.');

            return Command::SUCCESS;
        }

        return $this->option('dry-run')
            ? $this->printPlan($schools, $migrator)
            : $this->migrateAll($schools, $migrator);
    }

    private function printPlan($schools, ExternalSchoolMigrator $migrator): int
    {
        $this->info("Dry run — {$schools->count()} school(s) selected. Nothing will be written.");
        $this->newLine();

        // Prefixes claimed within this plan are tracked per Sahodaya so the preview shows the same
        // distinct prefixes a real run would produce, rather than proposing one twice.
        $claimed = [];
        $rows = [];
        $blocked = 0;

        foreach ($schools as $school) {
            $key = $school->external_sahodaya_id;
            $plan = $migrator->plan($school, $claimed[$key] ?? []);

            if ($plan['migratable']) {
                $claimed[$key][$plan['prefix']] = true;
            } else {
                $blocked++;
            }

            $rows[] = [
                $school->sahodaya?->name ?? '—',
                $school->name,
                $plan['prefix'] ?? '—',
                $plan['username'] ?: 'none',
                (string) $plan['entries'],
                $plan['migratable'] ? 'ready' : $plan['reason'],
            ];
        }

        $this->table(['Sahodaya', 'School', 'Prefix', 'Login', 'Entries', 'Status'], $rows);
        $this->info(($schools->count() - $blocked).' ready, '.$blocked.' blocked.');
        $this->line('State qualifier entries are not copied — they stay in the State ledger, linked to the new school tenant.');

        return Command::SUCCESS;
    }

    private function migrateAll($schools, ExternalSchoolMigrator $migrator): int
    {
        $done = 0;
        $failed = [];
        $currentSahodaya = null;

        foreach ($schools as $school) {
            if ($school->sahodaya?->name !== $currentSahodaya) {
                $currentSahodaya = $school->sahodaya?->name;
                $this->newLine();
                $this->line("<options=bold>{$currentSahodaya}</>");
            }

            $this->line("  {$school->name}");

            try {
                $migrator->migrate($school, [], fn ($k, $label) => $this->line("    ✓ {$label}"));
                $done++;
            } catch (Throwable $e) {
                $failed["{$currentSahodaya} / {$school->name}"] = $e->getMessage();
                $this->error('    ✗ '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Migrated {$done} of {$schools->count()}.");

        if ($failed) {
            $this->newLine();
            $this->error('Failed:');
            foreach ($failed as $name => $message) {
                $this->line("  - {$name}: {$message}");
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
