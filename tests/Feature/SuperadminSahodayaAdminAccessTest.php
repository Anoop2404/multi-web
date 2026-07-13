<?php

namespace Tests\Feature;

use App\Models\PlatformUser;
use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperadminSahodayaAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_superadmin_can_open_sahodaya_admin_on_central_host(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Access Test Sahodaya',
            'subdomain' => 'access-test',
            'is_active' => true,
        ]);

        $superadmin = PlatformUser::query()->create([
            'name'              => 'Platform Super',
            'email'             => 'platform-super@example.com',
            'username'          => 'platform_super',
            'password'          => 'password',
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $response = $this->actingAs($superadmin, 'platform')
            ->get('http://superadmin.test/sahodaya-admin/'.$sahodaya->id);

        $response->assertOk();
    }

    public function test_session_defaults_to_central_connection(): void
    {
        $this->assertSame('central', config('session.connection'));
    }
}
