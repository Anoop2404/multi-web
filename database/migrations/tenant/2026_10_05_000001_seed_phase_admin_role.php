<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\TenantUserCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ensures the new 'phase_admin' role (and current permission list) exists in every
 * tenant database. Mirrors 2026_08_15_000001_seed_region_admin_role_and_revoke_stale_fest_ops.php's
 * step 1 — seeders never run automatically against already-provisioned tenants, so without
 * this, FestEventStaffController@store's `assignRole('phase_admin')` call would throw
 * Spatie\Permission\Exceptions\RoleDoesNotExist on any tenant database that predates this
 * change.
 *
 * No backfill-of-existing-assignments step is needed here (unlike the region_admin
 * migration this mirrors): 'phase_admin' is a brand new duty — TenantUserCatalog didn't
 * recognize it before this change, so no FestEventStaff row anywhere can already have
 * duty='phase_admin' to backfill.
 *
 * Must be kept in sync with database/seeders/RolesAndPermissionsSeeder.php and
 * database/seeders/TenantRolesAndPermissionsSeeder.php — if either seeder's phase_admin
 * role or permission list changes, mirror the change here too.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'phase_admin', 'guard_name' => 'web']);

        foreach (TenantUserCatalog::allPermissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function down(): void
    {
        // Roles/permissions are additive and safe to keep even if this migration is rolled
        // back — matching this repo's other role-seeding migrations (see the region_admin
        // migration this one mirrors, and 2026_08_21_000001_ensure_state_roles_exist.php).
    }
};
