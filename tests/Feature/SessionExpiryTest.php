<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_request_without_session_redirects_to_login_with_location(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Session Sahodaya',
            'subdomain' => 'session-sahodaya',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
        ])->get("/sahodaya-admin/{$sahodaya->id}");

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', route('login').'?session=expired');
    }

    public function test_login_page_shows_session_expired_flag(): void
    {
        $response = $this->get('/login?session=expired');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/SuperadminLogin', false)
            ->where('sessionExpired', true)
        );
    }
}
