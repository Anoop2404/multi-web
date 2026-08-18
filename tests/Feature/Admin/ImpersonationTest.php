<?php

namespace Tests\Feature\Admin;

use App\Models\ImpersonationSession;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Full start -> consume -> act-as-target -> end cycle. This is the one FRD-13 business
 * rule ("support access through impersonation must always be logged") that had zero
 * existing precedent to build against — no other feature in this codebase juggles two
 * separate auth guards across a cross-host handoff, so this test is the primary proof
 * the design actually works, not just that the pieces compile.
 */
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithAdmin(): array
    {
        config(['tenancy.database_per_sahodaya' => false]);

        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Impersonation Test Sahodaya',
            'subdomain' => 'impersonation-test',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Target Sahodaya Admin',
            'email' => 'target-admin@example.com',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('sahodaya_admin');

        return [$tenant, $admin];
    }

    private function tenantHost(Tenant $tenant): string
    {
        return \App\Support\TenantDomainSync::subdomainFqdn($tenant->subdomain);
    }

    public function test_full_impersonation_cycle_start_consume_act_and_end(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$tenant, $target] = $this->makeTenantWithAdmin();

        $superadmin = PlatformUser::query()->create([
            'name' => 'Impersonation Test Super',
            'email' => 'impersonation-super@example.com',
            'username' => 'impersonation_super',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        // 1. Start — central host, platform guard.
        $startResponse = $this->actingAs($superadmin, 'platform')
            ->post("http://superadmin.test/admin/tenants/{$tenant->id}/impersonate/{$target->id}", [
                'reason' => 'Investigating a support ticket about missing registrations.',
            ], ['X-Inertia' => 'true']);

        // Inertia::location(): a real Inertia visit (X-Inertia header present, as the actual
        // frontend always sends) gets 409 + X-Inertia-Location so the JS client does a hard
        // window.location navigation instead of an XHR-based SPA transition.
        $startResponse->assertStatus(409);
        $location = $startResponse->headers->get('X-Inertia-Location');
        $this->assertNotNull($location, 'Expected an X-Inertia-Location header pointing at the tenant consume URL.');
        $this->assertStringContainsString('/impersonate/consume/', $location);

        $session = ImpersonationSession::sole();
        $this->assertSame($superadmin->id, $session->actor_platform_user_id);
        $this->assertSame($target->id, $session->target_user_id);
        $this->assertSame($tenant->id, $session->target_tenant_id);
        $this->assertSame('Investigating a support ticket about missing registrations.', $session->reason);
        $this->assertNotNull($session->consume_token);
        $this->assertNull($session->consumed_at);

        $startLog = \App\Models\AuditLog::where('action', 'impersonation.started')->sole();
        $this->assertSame($superadmin->id, $startLog->user_id);

        $token = Str::afterLast(rtrim($location, '/'), '/');

        // 2. Consume — tenant's own host, no prior auth (fresh browser-equivalent state).
        Auth::guard('platform')->logout();
        Auth::guard('web')->logout();

        $consumeResponse = $this->withHeader('Host', $this->tenantHost($tenant))
            ->get("http://{$this->tenantHost($tenant)}/impersonate/consume/{$token}");

        $consumeResponse->assertRedirect("/sahodaya-admin/{$tenant->id}");

        $session->refresh();
        $this->assertNotNull($session->consumed_at);
        $this->assertNull($session->consume_token, 'Token must be single-use — cleared after consumption.');
        $this->assertSame($target->id, Auth::guard('web')->id());

        $consumeLog = \App\Models\AuditLog::where('action', 'impersonation.consumed')->sole();
        $this->assertSame($target->id, $consumeLog->user_id);

        // 3. Act as target — the impersonated session should actually work.
        $this->withHeader('Host', $this->tenantHost($tenant))
            ->get("http://{$this->tenantHost($tenant)}/sahodaya-admin/{$tenant->id}")
            ->assertOk();

        // 4. Re-using the same (now-cleared) token must fail.
        $replay = $this->withHeader('Host', $this->tenantHost($tenant))
            ->get("http://{$this->tenantHost($tenant)}/impersonate/consume/{$token}");
        $replay->assertForbidden();

        // 5. End impersonation.
        $endResponse = $this->withHeader('Host', $this->tenantHost($tenant))
            ->post("http://{$this->tenantHost($tenant)}/impersonate/end");
        $endResponse->assertRedirect();

        $session->refresh();
        $this->assertNotNull($session->ended_at);
        $this->assertFalse($session->isActive());

        $endLog = \App\Models\AuditLog::where('action', 'impersonation.ended')->sole();
        $this->assertSame($target->id, $endLog->user_id);

        $this->assertNull(Auth::guard('web')->user(), 'web guard should be logged out after ending impersonation.');
    }

    public function test_impersonate_requires_a_reason(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$tenant, $target] = $this->makeTenantWithAdmin();

        $superadmin = PlatformUser::query()->create([
            'name' => 'Impersonation Test Super 2',
            'email' => 'impersonation-super-2@example.com',
            'username' => 'impersonation_super_2',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin, 'platform')
            ->post("http://superadmin.test/admin/tenants/{$tenant->id}/impersonate/{$target->id}", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, ImpersonationSession::count());
    }

    public function test_expired_token_cannot_be_consumed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$tenant, $target] = $this->makeTenantWithAdmin();

        $superadmin = PlatformUser::query()->create([
            'name' => 'Impersonation Test Super 3',
            'email' => 'impersonation-super-3@example.com',
            'username' => 'impersonation_super_3',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $session = ImpersonationSession::create([
            'actor_platform_user_id' => $superadmin->id,
            'target_user_id' => $target->id,
            'target_tenant_id' => $tenant->id,
            'reason' => 'Expired token test.',
            'consume_token' => 'expired-token-'.Str::random(20),
            'token_expires_at' => now()->subMinute(),
        ]);

        $this->withHeader('Host', $this->tenantHost($tenant))
            ->get("http://{$this->tenantHost($tenant)}/impersonate/consume/{$session->consume_token}")
            ->assertForbidden();

        $this->assertNull($session->fresh()->consumed_at);
    }
}
