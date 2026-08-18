<?php

namespace Tests\Feature\Middleware;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantSubscriptionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_sahodaya_admin_panel_is_blocked_for_the_tenant_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Suspended Sahodaya',
            'is_active' => true,
        ]);

        TenantSubscription::create([
            'tenant_id' => $sahodaya->id,
            'period_start' => now()->subMonth(),
            'period_end' => now()->addMonth(),
            'status' => 'suspended',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('sahodaya_admin');

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/")
            ->assertForbidden();
    }

    public function test_active_sahodaya_admin_panel_is_reachable_for_the_tenant_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Active Sahodaya',
            'is_active' => true,
        ]);

        TenantSubscription::create([
            'tenant_id' => $sahodaya->id,
            'period_start' => now()->subMonth(),
            'period_end' => now()->addMonth(),
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('sahodaya_admin');

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/")
            ->assertSuccessful();
    }
}
