<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Stancl's own `tenants:migrate` (no --tenants filter) runs
 * tenancy()->runForMultiple(null, ...), which defaults to EVERY row in the tenants
 * table — Sahodayas AND schools alike. That's wrong for this app: only Sahodaya-type
 * tenants get their own database (config('tenancy.database_per_sahodaya') — "member
 * schools share the parent DB"). Neither the package nor App\Models\Tenant filters by
 * `type` anywhere, so running the bare command tries to resolve/migrate a separate
 * database keyed by each SCHOOL's own id too — a database that was never created for
 * that school (schools don't have one), multiplying the run time by however many
 * schools exist and touching the wrong tenant key entirely.
 *
 * This wraps the real `tenants:migrate` with an explicit --tenants list built from
 * only Sahodaya ids, so the existing command's actual migration logic (and any
 * --force/--path/etc. passed through) is unchanged — just correctly scoped.
 */
class MigrateSahodayaTenants extends Command
{
    protected $signature = 'tenants:migrate-sahodayas {--force : Force the operation to run when in production}';

    protected $description = 'Run tenant migrations for Sahodaya-type tenants only (schools share their parent Sahodaya\'s database)';

    public function handle(): int
    {
        $sahodayaIds = Tenant::where('type', 'sahodaya')->pluck('id')->all();

        if ($sahodayaIds === []) {
            $this->warn('No Sahodaya tenants found — nothing to migrate.');

            return self::SUCCESS;
        }

        $this->info('Migrating '.count($sahodayaIds).' Sahodaya database(s), skipping school tenants entirely.');

        return Artisan::call('tenants:migrate', [
            '--tenants' => $sahodayaIds,
            '--force' => (bool) $this->option('force'),
        ], $this->output);
    }
}
