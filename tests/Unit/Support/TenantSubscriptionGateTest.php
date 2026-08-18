<?php

namespace Tests\Unit\Support;

use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Support\TenantSubscriptionGate;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantSubscriptionGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Gate Test Sahodaya',
            'is_active' => true,
        ]);
    }

    private function subscription(Tenant $tenant, string $status): TenantSubscription
    {
        return TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'period_start' => now()->subMonth(),
            'period_end' => now()->addMonth(),
            'status' => $status,
        ]);
    }

    public function test_tenant_with_no_subscription_record_is_not_blocked(): void
    {
        $tenant = $this->makeTenant();

        $result = TenantSubscriptionGate::check($tenant, Request::create('/', 'GET'));

        $this->assertNull($result);
    }

    public function test_suspended_subscription_blocks_the_request(): void
    {
        $tenant = $this->makeTenant();
        $this->subscription($tenant, 'suspended');

        $result = TenantSubscriptionGate::check($tenant, Request::create('/', 'GET'));

        $this->assertSame('suspended', $result);
    }

    public function test_readonly_subscription_blocks_writes_but_allows_reads(): void
    {
        $tenant = $this->makeTenant();
        $this->subscription($tenant, 'readonly');

        $getResult = TenantSubscriptionGate::check($tenant, Request::create('/', 'GET'));
        $postResult = TenantSubscriptionGate::check($tenant, Request::create('/', 'POST'));

        $this->assertNull($getResult);
        $this->assertSame('readonly', $postResult);
    }

    public function test_grace_subscription_is_not_blocked_but_flashes_a_warning(): void
    {
        $tenant = $this->makeTenant();
        $this->subscription($tenant, 'grace');

        $request = Request::create('/', 'GET');
        $request->setLaravelSession(app('session.store'));

        $result = TenantSubscriptionGate::check($tenant, $request);

        $this->assertNull($result);
        $this->assertStringContainsString('grace period', $request->session()->get('warning'));
    }

    public function test_active_subscription_is_not_blocked(): void
    {
        $tenant = $this->makeTenant();
        $this->subscription($tenant, 'active');

        $result = TenantSubscriptionGate::check($tenant, Request::create('/', 'POST'));

        $this->assertNull($result);
    }

    public function test_superadmin_bypasses_a_suspended_subscription(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $tenant = $this->makeTenant();
        $this->subscription($tenant, 'suspended');

        $superadmin = PlatformUser::query()->create([
            'name' => 'Platform Super',
            'email' => 'gate-super@example.com',
            'username' => 'gate_super',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $request = Request::create('/', 'GET');
        $request->setUserResolver(fn () => $superadmin);

        $result = TenantSubscriptionGate::check($tenant, $request);

        $this->assertNull($result);
    }
}
