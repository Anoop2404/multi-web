<?php

use App\Models\FestEventStaff;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Backfills two gaps left by the 2026-08-06 change in
 * FestEventStaffController@store that started assigning the 'region_admin'
 * role (instead of 'fest_ops') to staff assigned the 'region_admin' duty:
 *
 *  1. The 'region_admin' role only previously existed if someone had
 *     manually run `php artisan db:seed --class=RolesAndPermissionsSeeder`
 *     (or TenantRolesAndPermissionsSeeder) against this tenant database.
 *     Seeders never run automatically, so on any tenant DB where that was
 *     never done, FestEventStaffController@store's `assignRole('region_admin')`
 *     call throws Spatie\Permission\Exceptions\RoleDoesNotExist.
 *  2. Nothing revoked the old, broader 'fest_ops' role from admins who were
 *     assigned the region_admin duty *before* the fix shipped, so they kept
 *     unscoped access to every event in the Sahodaya.
 *
 * Step 1 below must be kept in sync with the role/permission definitions in
 * database/seeders/RolesAndPermissionsSeeder.php and
 * database/seeders/TenantRolesAndPermissionsSeeder.php — if either seeder's
 * region_admin role or permission list changes, mirror the change here too.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // --- 1. Ensure the role (and every permission it can be given) exists ---
        // Mirrors RolesAndPermissionsSeeder::run() / TenantRolesAndPermissionsSeeder::run().
        Role::firstOrCreate(['name' => 'region_admin', 'guard_name' => 'web']);

        foreach (TenantUserCatalog::allPermissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // --- 2. Backfill users who were given the region_admin duty under the old code path ---
        $regionAdminUserIds = FestEventStaff::query()
            ->where('duty', 'region_admin')
            ->distinct()
            ->pluck('user_id');

        foreach ($regionAdminUserIds as $userId) {
            $user = User::find($userId);

            if (! $user || ! $user->hasRole('fest_ops')) {
                // Either the user no longer exists, or they never held (or already lost)
                // the stale 'fest_ops' role for this duty — nothing to backfill/revoke.
                continue;
            }

            if (! $user->hasRole('region_admin')) {
                $user->assignRole('region_admin');
            }

            // Grant the write permissions this duty needs, same as
            // FestEventStaffController@store does for new assignments.
            $user->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('region_admin'));

            // Only revoke 'fest_ops' if the user has no OTHER FestEventStaff duty that
            // legitimately grants it. Per FestEventStaffController@store, every duty
            // except 'marks' and 'region_admin' results in the user being assigned
            // 'fest_ops' — so if this user also coordinates, say, a 'stage' or
            // 'registration' duty on any event, they still need 'fest_ops' and must
            // keep it. Revoking here is scoped strictly to users whose *only*
            // fest_ops-granting context was the (now superseded) region_admin duty.
            $hasOtherFestOpsGrantingDuty = FestEventStaff::query()
                ->where('user_id', $userId)
                ->whereNotIn('duty', ['region_admin', 'marks'])
                ->exists();

            if (! $hasOtherFestOpsGrantingDuty) {
                $user->removeRole('fest_ops');
            }
        }
    }

    public function down(): void
    {
        // Best-effort reversal, scoped to the exact same population identified in up():
        // users currently on the region_admin duty who now hold 'region_admin' but not
        // 'fest_ops'. This can't distinguish "granted by this migration" from "granted
        // legitimately afterwards by the controller for a fresh assignment", so this is
        // intended for rolling back shortly after running up(), not as a long-term-safe
        // undo. The role/permission definitions themselves (step 1 of up()) are left in
        // place, matching this repo's other role-seeding migrations (see
        // 2026_08_21_000001_ensure_state_roles_exist.php) — roles/permissions are additive
        // and safe to keep even if this migration is rolled back.
        $regionAdminUserIds = FestEventStaff::query()
            ->where('duty', 'region_admin')
            ->distinct()
            ->pluck('user_id');

        foreach ($regionAdminUserIds as $userId) {
            $user = User::find($userId);

            if (! $user || ! $user->hasRole('region_admin') || $user->hasRole('fest_ops')) {
                continue;
            }

            $user->assignRole('fest_ops');
            $user->removeRole('region_admin');
        }
    }
};
