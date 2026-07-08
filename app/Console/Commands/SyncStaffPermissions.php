<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class SyncStaffPermissions extends Command
{
    protected $signature = 'permissions:sync-staff {--force : Overwrite existing staff permissions}';

    protected $description = 'Backfill default Spatie permissions on staff and coordinator users';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $updated = 0;

        $rolesToSync = array_merge(
            ['school_staff'],
            TenantUserCatalog::sahodayaPermissionRoles(),
        );

        $syncInContext = function () use ($force, $rolesToSync, &$updated) {
            User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', $rolesToSync))
                ->each(function (User $user) use ($force, &$updated) {
                    if (! $force && $user->permissions()->exists()) {
                        return;
                    }

                    $tenantType = $user->hasRole('school_staff') ? 'school' : 'sahodaya';
                    $defaults = TenantUserCatalog::mergedDefaultPermissions(
                        $user->getRoleNames()->all(),
                        $tenantType,
                    );

                    if ($defaults === []) {
                        return;
                    }

                    $user->syncPermissions($defaults);
                    $updated++;
                });
        };

        if (! config('tenancy.database_per_sahodaya', true)) {
            $syncInContext();
        } else {
            Tenant::query()->where('type', 'sahodaya')->orderBy('name')->each(function (Tenant $sahodaya) use ($syncInContext) {
                TenancyDatabase::withTenantDatabase($sahodaya, $syncInContext);
            });
        }

        $this->info("Synced permissions on {$updated} staff user(s).");

        return self::SUCCESS;
    }
}
