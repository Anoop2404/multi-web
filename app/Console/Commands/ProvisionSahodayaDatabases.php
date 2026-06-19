<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenancy\SahodayaDatabaseProvisioner;
use Illuminate\Console\Command;

class ProvisionSahodayaDatabases extends Command
{
    protected $signature = 'sahodaya:provision-databases
                            {--seed : Seed default Sahodaya profile and site template after migrate}
                            {--create : Create the PostgreSQL database if it does not exist}
                            {--tenant= : Provision a single Sahodaya tenant id}';

    protected $description = 'Configure, create (optional), and migrate Sahodaya tenant databases';

    public function handle(SahodayaDatabaseProvisioner $provisioner): int
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            $this->warn('TENANCY_DATABASE_PER_SAHODAYA=false — dedicated databases are disabled.');

            return self::SUCCESS;
        }

        $seed = (bool) $this->option('seed');
        $create = (bool) $this->option('create');
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $sahodaya = Tenant::query()
                ->where('type', 'sahodaya')
                ->where('id', $tenantId)
                ->firstOrFail();

            $provisioner->ensureReady($sahodaya, $seed, $create);

            $this->info("Provisioned {$sahodaya->name} ({$sahodaya->getInternal('db_name')}).");

            return self::SUCCESS;
        }

        $count = 0;
        Tenant::query()->where('type', 'sahodaya')->orderBy('name')->each(function (Tenant $sahodaya) use ($provisioner, $seed, $create, &$count) {
            try {
                $provisioner->ensureReady($sahodaya, $seed, $create);
                $count++;
                $this->line("  ✓ {$sahodaya->name}");
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$sahodaya->name}: {$e->getMessage()}");
            }
        });

        $this->info("Provisioned {$count} Sahodaya database(s).");

        return self::SUCCESS;
    }
}
