<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class BackfillTenantRole extends Command
{
    /**
     * php artisan tenants:backfill-role event_admin
     *
     * Ensures a role exists in every already-provisioned tenant database.
     * Needed when a new role is added to TenantUserCatalog after tenants
     * have already been created, since TenantRolesAndPermissionsSeeder only
     * runs automatically at provisioning time.
     */
    protected $signature = 'tenants:backfill-role {role : Role name to ensure exists, e.g. event_admin} {--guard=web}';

    protected $description = 'Create a missing Spatie role in every tenant database that does not already have it';

    public function handle(): int
    {
        $roleName = $this->argument('role');
        $guard = $this->option('guard');

        $tenants = Tenant::all();
        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($roleName, $guard, &$created, &$skipped) {
                    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

                    $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();

                    if ($role) {
                        $skipped++;
                        return;
                    }

                    Role::create(['name' => $roleName, 'guard_name' => $guard]);
                    $created++;
                });
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  tenant {$tenant->getTenantKey()}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Role '{$roleName}' created in {$created} tenant(s), already present in {$skipped}, failed in {$failed}.");

        return self::SUCCESS;
    }
}
