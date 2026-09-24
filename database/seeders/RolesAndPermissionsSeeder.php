<?php

namespace Database\Seeders;

use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use App\Models\PlatformUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\StateFestPermissions;
use App\Support\TenantUserCatalog;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Platform roles (central database only)
        PlatformRole::firstOrCreate(['name' => 'superadmin',    'guard_name' => 'web']);
        PlatformRole::firstOrCreate(['name' => 'state_admin',   'guard_name' => 'web']);
        PlatformRole::firstOrCreate(['name' => 'state_staff',   'guard_name' => 'web']);

        // Tenant roles (single-DB test/local setups still seed on the default connection)
        Role::firstOrCreate(['name' => 'superadmin',    'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'sahodaya_admin','guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_admin',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_principal', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_vice_principal', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_event_coordinator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_finance_coordinator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_training_coordinator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_mcq_coordinator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_kalotsavam_coordinator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_sports_coordinator', 'guard_name' => 'web']);

        // Phase 8 — operational module roles (additive)
        Role::firstOrCreate(['name' => 'state_admin',            'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'state_staff',            'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'sahodaya_staff',         'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_staff',           'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'mark_entry_admin',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'mark_entry_coordinator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'judge',                  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'state_judge',             'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student',                'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher',                'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'exam_controller',        'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'exam_staff',             'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'group_admin',            'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'house_admin',            'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'fest_ops',               'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'registration_coordinator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'sahodaya_finance',         'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'certificate_collector',    'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'data_entry',               'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'event_coordinator',        'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'event_admin',               'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'region_admin',              'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'phase_admin',                'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'training_admin',            'guard_name' => 'web']);

        foreach (TenantUserCatalog::allPermissions() as $permission) {
            PlatformPermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // State Kalotsav module permissions — separate from the tenant catalog on purpose, because a
        // State officer's authority is not a tenant role. The matrix is what lets a mark operator be
        // barred from publishing and a certificate operator from altering results, distinctions the
        // old state_admin/state_staff pair could not express.
        foreach (StateFestPermissions::all() as $permission) {
            PlatformPermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (StateFestPermissions::roleMatrix() as $roleName => $permissions) {
            PlatformRole::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'])
                ->givePermissionTo($permissions);
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'])
                ->givePermissionTo($permissions);
        }

        $superadmin = PlatformUser::firstOrCreate(
            ['email' => 'admin@sahodaya.test'],
            [
                'name' => 'Super Admin',
                'tenant_id' => null,
                'password' => bcrypt('password'),
            ]
        );

        $superadmin->assignRole('superadmin');
        $superadmin->syncPermissions(array_merge(TenantUserCatalog::allPermissions(), StateFestPermissions::all()));
    }
}
