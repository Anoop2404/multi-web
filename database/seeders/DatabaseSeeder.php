<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            NotificationTemplatesSeeder::class,
            SkinPresetsSeeder::class,
            SahodayaMasterDataSeeder::class,
            DemoTenantsSeeder::class,
        ]);
    }
}
