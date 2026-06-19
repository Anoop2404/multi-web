<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenancyDatabase;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenancyAssetUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_sahodaya_admin_page_uses_global_vite_assets_not_tenancy_assets(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Asset Sahodaya',
            'subdomain' => 'asset-sahodaya',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('sahodaya_admin');

        if (TenancyDatabase::enabled()) {
            TenancyDatabase::initializeForTenant($sahodaya);
        }

        $response = $this->actingAs($admin)->get("/sahodaya-admin/{$sahodaya->id}/schools");

        $response->assertOk();
        $response->assertDontSee('/tenancy/assets/', false);

        $html = $response->getContent();
        $usesBuiltAssets = str_contains($html, '/build/assets/');
        $usesViteDev = str_contains($html, '@vite/client') || str_contains($html, ':5173/');
        $this->assertTrue($usesBuiltAssets || $usesViteDev);
    }
}
