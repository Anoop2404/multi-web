<?php

namespace Tests\Feature\State;

use App\Http\Controllers\Admin\AuthController;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Signing in on the dedicated State domain (config/state.php, routes/state.php).
 *
 * Two things were broken. The login page branched only on "is this a central host", so the State
 * domain fell through to the Sahodaya membership login — a state admin arriving at their own domain
 * was told to "manage membership, schools, and registrations" and offered a school-login link. And
 * homeFor() sent state users to an absolute central-domain URL, so signing in ON the State domain
 * immediately redirected them OFF it, to a host where their session cookie does not exist
 * (SESSION_DOMAIN is null, so cookies are host-only) — landing them back on a login page.
 */
class StateDomainLoginTest extends TestCase
{
    use RefreshDatabase;

    private function stateUser(): PlatformUser
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);

        $user = PlatformUser::create([
            'name' => 'State Admin', 'email' => 'sa@example.test', 'username' => 'sa',
            'password' => 'password', 'state_id' => $state->id, 'email_verified_at' => now(),
        ]);
        $user->assignRole('state_admin');

        return $user;
    }

    private function onHost(string $host, callable $callback): mixed
    {
        $request = Request::create("http://{$host}/login", 'GET');
        app()->instance('request', $request);

        return $callback();
    }

    public function test_the_state_domain_serves_the_platform_login_not_the_sahodaya_one(): void
    {
        // Asserted on the page object rather than assertInertia()->component(), whose file-existence
        // check resolves against a page path that does not include the admin bundle's Admin/ prefix.
        $response = $this->get('http://'.config('state.domain').'/login')->assertOk();

        $this->assertSame('Auth/SuperadminLogin', $response->viewData('page')['component']);
    }

    public function test_a_state_user_signing_in_on_the_state_domain_stays_on_it(): void
    {
        $user = $this->stateUser();

        $home = $this->onHost(config('state.domain'), fn () => AuthController::homeFor($user));

        $this->assertSame('/', $home, 'A relative path keeps the user on the host — and the port — they signed in on.');
    }

    public function test_the_same_user_signing_in_centrally_still_goes_to_the_central_state_dashboard(): void
    {
        $user = $this->stateUser();

        $home = $this->onHost('superadmin.test', fn () => AuthController::homeFor($user));

        $this->assertStringContainsString('/admin/state-dashboard', (string) $home);
    }

    public function test_a_state_user_can_open_the_forced_password_change_page(): void
    {
        $user = $this->stateUser();

        $response = $this->actingAs($user, 'platform')
            ->get('http://superadmin.test/change-password')
            ->assertOk();

        $page = $response->viewData('page');

        $this->assertSame('Auth/ChangePassword', $page['component']);
        $this->assertSame('Kerala', $page['props']['organizationName']);
        $this->assertSame('State Admin', $page['props']['roleLabel']);
    }

    public function test_a_superadmin_on_the_state_domain_also_stays_on_it(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $super = PlatformUser::create([
            'name' => 'Super', 'email' => 'su@example.test', 'username' => 'su',
            'password' => 'password', 'email_verified_at' => now(),
        ]);
        $super->assignRole('superadmin');

        $this->assertSame('/', $this->onHost(config('state.domain'), fn () => AuthController::homeFor($super)));
        $this->assertStringContainsString('/admin', (string) $this->onHost('superadmin.test', fn () => AuthController::homeFor($super)));
    }

    public function test_the_state_host_is_recognised_only_when_it_matches_the_configured_domain(): void
    {
        $this->assertTrue(AuthController::isStateHost(config('state.domain')));
        $this->assertTrue(AuthController::isStateHost(strtoupper(config('state.domain'))));
        $this->assertFalse(AuthController::isStateHost('superadmin.test'));
        $this->assertFalse(AuthController::isStateHost(null));
    }
}
