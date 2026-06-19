<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'sahodaya_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_admin', 'guard_name' => 'web']);

        $superadmin = User::firstOrCreate(
            ['email' => 'admin@sahodaya.test'],
            [
                'name' => 'Super Admin',
                'tenant_id' => null,
                'password' => bcrypt('password'),
            ]
        );

        $superadmin->assignRole('superadmin');
    }
}
