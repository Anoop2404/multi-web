<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Original roles — never rename or remove (hardcoded in 30+ files)
        Role::firstOrCreate(['name' => 'superadmin',    'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'sahodaya_admin','guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_admin',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_principal', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_vice_principal', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_event_coordinator', 'guard_name' => 'web']);

        // Phase 8 — operational module roles (additive)
        Role::firstOrCreate(['name' => 'state_admin',            'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'state_staff',            'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'sahodaya_staff',         'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_staff',           'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'mark_entry_admin',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'mark_entry_coordinator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'judge',                  'guard_name' => 'web']);
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

        foreach (TenantUserCatalog::allPermissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superadmin = User::firstOrCreate(
            ['email' => 'admin@sahodaya.test'],
            [
                'name' => 'Super Admin',
                'tenant_id' => null,
                'password' => bcrypt('password'),
            ]
        );

        $superadmin->assignRole('superadmin');
        $superadmin->syncPermissions(TenantUserCatalog::allPermissions());
    }
}
