<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DevPassTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_sahodaya_user_can_login_with_valid_dev_pass_token(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('auth.dev_pass_token', 'dev-secret-pass-123');

        $tenant = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Sahodaya',
            'subdomain' => 'malappuram-dev-test',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id'         => $tenant->id,
            'username'          => 'sahodayauser01',
            'email'             => 'sahodayauser01@example.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('realpassword123'),
        ]);
        $user->assignRole('sahodaya_admin');

        $response = $this->post('http://malappuram-dev-test.sahodaya.test/login', [
            'email'    => 'sahodayauser01',
            'password' => 'dev-secret-pass-123',
        ]);

        $response->assertRedirect("/sahodaya-admin/{$tenant->id}");
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_user_cannot_login_with_invalid_dev_pass_token(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('auth.dev_pass_token', 'dev-secret-pass-123');

        $tenant = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Sahodaya',
            'subdomain' => 'malappuram-dev-test2',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id'         => $tenant->id,
            'username'          => 'sahodayauser02',
            'email'             => 'sahodayauser02@example.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('realpassword123'),
        ]);
        $user->assignRole('sahodaya_admin');

        $response = $this->post('http://malappuram-dev-test2.sahodaya.test/login', [
            'email'    => 'sahodayauser02',
            'password' => 'wrong-pass-token',
        ]);

        $this->assertGuest('web');
        $response->assertSessionHasErrors('email');
    }
}
