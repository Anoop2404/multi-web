<?php

namespace Tests\Feature\Api;

use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function schoolAdmin(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'KNR Sahodaya',
            'domain'    => 'knr-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix'    => 'KNR',
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Govt HS',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'GHS',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $user = User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('school_admin');

        return compact('sahodaya', 'school', 'user');
    }

    public function test_school_admin_can_login_and_receive_token(): void
    {
        ['school' => $school, 'user' => $user] = $this->schoolAdmin();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'       => $user->email,
            'password'    => 'password',
            'device_name' => 'PHPUnit',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role', 'school_admin')
            ->assertJsonPath('data.tenant_id', $school->id)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_unverified_school_admin_cannot_login(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'KNR Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Govt HS',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $user = User::factory()->unverified()->create(['tenant_id' => $school->id]);
        $user->assignRole('school_admin');

        $this->postJson('/api/v1/auth/login', [
            'email'       => $user->email,
            'password'    => 'password',
            'device_name' => 'PHPUnit',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_me_returns_profile_with_token(): void
    {
        ['school' => $school, 'user' => $user] = $this->schoolAdmin();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $school->id)
            ->assertJsonPath('data.role', 'school_admin');
    }

    public function test_logout_revokes_token(): void
    {
        ['user' => $user] = $this->schoolAdmin();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertNull(PersonalAccessToken::findToken($token));
    }
}
