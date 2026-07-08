<?php

namespace App\Console\Commands;

use App\Models\PersonalAccessToken;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenancyDatabase;
use Database\Seeders\TenantRolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateTenantUsersFromCentral extends Command
{
    protected $signature = 'users:migrate-to-tenant-databases
                            {--tenant= : Migrate a single Sahodaya tenant UUID}
                            {--dry-run : Preview counts without writing}
                            {--purge-central : Remove copied users from the central database}
                            {--seed-roles : Seed tenant roles and permissions before copying users}';

    protected $description = 'Copy portal users from the central database into each Sahodaya tenant database (preserving user IDs).';

    public function handle(): int
    {
        if (! TenancyDatabase::enabled()) {
            $this->warn('TENANCY_DATABASE_PER_SAHODAYA=false — users already share one database. Nothing to migrate.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $purge = (bool) $this->option('purge-central');
        $seedRoles = (bool) $this->option('seed-roles');

        $query = Tenant::query()->where('type', 'sahodaya')->orderBy('name');
        if ($tenantId = $this->option('tenant')) {
            $query->where('id', $tenantId);
        }

        $sahodayas = $query->get();
        if ($sahodayas->isEmpty()) {
            $this->error('No Sahodaya tenants matched.');

            return self::FAILURE;
        }

        $central = DB::connection(config('tenancy.database.central_connection', 'central'));
        $totalUsers = 0;
        $failedTenants = 0;

        foreach ($sahodayas as $sahodaya) {
            $tenantIds = array_merge([$sahodaya->id], TenancyDatabase::schoolIdsFor($sahodaya->id));
            $users = $central->table('users')
                ->whereIn('tenant_id', $tenantIds)
                ->orderBy('id')
                ->get();

            $this->line("Sahodaya {$sahodaya->name} ({$sahodaya->id}): {$users->count()} central user(s)");

            if ($users->isEmpty()) {
                continue;
            }

            if ($dryRun) {
                $totalUsers += $users->count();

                continue;
            }

            TenancyDatabase::withTenantDatabase($sahodaya, function () use (
                $sahodaya,
                $users,
                $central,
                $seedRoles,
                $purge,
                &$totalUsers,
                &$failedTenants,
            ) {
                $missingTables = $this->missingTenantAuthTables();
                if ($missingTables !== []) {
                    $failedTenants++;
                    $this->error(
                        '  → missing tenant auth table(s): '.implode(', ', $missingTables)
                        .'. Run php artisan sahodaya:provision-databases --tenant='.$sahodaya->id.' --create --seed before migrating users.'
                    );

                    return;
                }

                if ($seedRoles) {
                    (new TenantRolesAndPermissionsSeeder)->run();
                }

                $roleMap = $this->buildRoleMap($central);
                $userIds = $users->pluck('id')->all();
                $userColumns = $this->userColumns();

                foreach ($users as $row) {
                    $payload = [];
                    foreach ($userColumns as $column) {
                        if (property_exists($row, $column)) {
                            $payload[$column] = $row->{$column};
                        }
                    }

                    DB::table('users')->updateOrInsert(['id' => $row->id], $payload);
                }

                $this->copyModelRoles($central, $roleMap, $userIds);
                $this->copyModelPermissions($central, $userIds);
                $this->copyAccessTokens($central, $userIds);
                $this->resetUsersSequence();

                $totalUsers += count($userIds);
                $this->info("  → copied {$users->count()} user(s) into {$sahodaya->getInternal('db_name')}");

                if ($purge) {
                    $this->purgeCentralUsers($central, $userIds);
                    $this->info('  → removed migrated users from central');
                }
            });
        }

        if ($failedTenants > 0) {
            $this->error("Aborted for {$failedTenants} tenant(s) because required tenant auth tables are missing.");

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Dry run complete. {$totalUsers} user(s) would be migrated.");
        } else {
            $this->info("Done. Migrated {$totalUsers} user(s).");
            if (! $purge) {
                $this->comment('Central copies remain. Re-run with --purge-central after verifying logins.');
            }
            $this->comment('All users must sign in again (sessions invalidated).');
        }

        return self::SUCCESS;
    }

    /** @return array<int, int> */
    private function buildRoleMap($central): array
    {
        $centralRoles = $central->table('roles')->pluck('id', 'name');
        $tenantRoles = Role::query()->pluck('id', 'name');
        $map = [];

        foreach ($centralRoles as $name => $centralId) {
            if (isset($tenantRoles[$name])) {
                $map[(int) $centralId] = (int) $tenantRoles[$name];
            }
        }

        return $map;
    }

    /** @param  list<int>  $userIds */
    private function copyModelRoles($central, array $roleMap, array $userIds): void
    {
        $rows = $central->table('model_has_roles')
            ->whereIn('model_id', $userIds)
            ->whereIn('model_type', [User::class, 'App\\Models\\User'])
            ->get();

        foreach ($rows as $row) {
            $tenantRoleId = $roleMap[(int) $row->role_id] ?? null;
            if (! $tenantRoleId) {
                continue;
            }

            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id'    => $tenantRoleId,
                    'model_type' => User::class,
                    'model_id'   => $row->model_id,
                ],
                [],
            );
        }
    }

    /** @param  list<int>  $userIds */
    private function copyModelPermissions($central, array $userIds): void
    {
        $permissionMap = $central->table('permissions')->pluck('id', 'name');
        $tenantPermissions = DB::table('permissions')->pluck('id', 'name');

        $rows = $central->table('model_has_permissions')
            ->whereIn('model_id', $userIds)
            ->whereIn('model_type', [User::class, 'App\\Models\\User'])
            ->get();

        foreach ($rows as $row) {
            $permissionName = $permissionMap[(int) $row->permission_id] ?? null;
            $tenantPermissionId = $permissionName ? ($tenantPermissions[$permissionName] ?? null) : null;
            if (! $tenantPermissionId) {
                continue;
            }

            DB::table('model_has_permissions')->updateOrInsert(
                [
                    'permission_id' => $tenantPermissionId,
                    'model_type'    => User::class,
                    'model_id'      => $row->model_id,
                ],
                [],
            );
        }
    }

    /** @param  list<int>  $userIds */
    private function copyAccessTokens($central, array $userIds): void
    {
        $rows = $central->table('personal_access_tokens')
            ->whereIn('tokenable_id', $userIds)
            ->whereIn('tokenable_type', [User::class, 'App\\Models\\User'])
            ->get();

        foreach ($rows as $row) {
            $payload = (array) $row;
            unset($payload['id']);
            $payload['tokenable_type'] = User::class;

            DB::table('personal_access_tokens')->updateOrInsert(
                ['token' => $row->token],
                $payload,
            );
        }
    }

    private function resetUsersSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), COALESCE((SELECT MAX(id) FROM users), 1))");
    }

    /** @return list<string> */
    private function missingTenantAuthTables(): array
    {
        $tableNames = config('permission.table_names');
        $required = array_filter([
            'users',
            $tableNames['roles'] ?? 'roles',
            $tableNames['permissions'] ?? 'permissions',
            $tableNames['model_has_roles'] ?? 'model_has_roles',
            $tableNames['model_has_permissions'] ?? 'model_has_permissions',
            'personal_access_tokens',
        ]);

        return array_values(array_filter(
            $required,
            fn (string $table) => ! \Illuminate\Support\Facades\Schema::hasTable($table),
        ));
    }

    /** @param  list<int>  $userIds */
    private function purgeCentralUsers($central, array $userIds): void
    {
        $central->table('personal_access_tokens')
            ->whereIn('tokenable_id', $userIds)
            ->whereIn('tokenable_type', [User::class, 'App\\Models\\User'])
            ->delete();

        $central->table('model_has_roles')
            ->whereIn('model_id', $userIds)
            ->whereIn('model_type', [User::class, 'App\\Models\\User'])
            ->delete();

        $central->table('model_has_permissions')
            ->whereIn('model_id', $userIds)
            ->whereIn('model_type', [User::class, 'App\\Models\\User'])
            ->delete();

        $central->table('users')->whereIn('id', $userIds)->delete();
    }

    /** @return list<string> */
    private function userColumns(): array
    {
        return [
            'id',
            'tenant_id',
            'school_house_id',
            'name',
            'email',
            'username',
            'email_verified_at',
            'password',
            'must_change_password',
            'portal_welcome_seen',
            'last_login_at',
            'created_by_user_id',
            'group_classes',
            'remember_token',
            'created_at',
            'updated_at',
        ];
    }
}
