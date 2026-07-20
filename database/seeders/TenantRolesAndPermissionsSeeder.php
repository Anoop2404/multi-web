<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\TenantUserCatalog;
use Illuminate\Database\Seeder;

class TenantRolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            return;
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'sahodaya_admin', 'school_admin', 'school_principal', 'school_vice_principal',
            'school_event_coordinator', 'school_finance_coordinator', 'school_training_coordinator',
            'school_mcq_coordinator', 'school_kalotsavam_coordinator', 'school_sports_coordinator',
            'sahodaya_staff', 'school_staff', 'mark_entry_admin', 'mark_entry_coordinator',
            'judge', 'student', 'teacher', 'exam_controller', 'exam_staff', 'group_admin',
            'house_admin', 'fest_ops', 'registration_coordinator', 'sahodaya_finance',
            'certificate_collector', 'data_entry', 'event_coordinator', 'event_admin',
        ] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        foreach (TenantUserCatalog::allPermissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
