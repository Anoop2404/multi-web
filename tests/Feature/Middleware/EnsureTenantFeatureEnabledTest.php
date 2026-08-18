<?php

namespace Tests\Feature\Middleware;

use App\Models\PlanFeature;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnsureTenantFeatureEnabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->get('/__test/feature-gated/{tenantId}', function () {
            return 'ok';
        })->middleware('feature:module.mcq')->name('test.feature-gated');
    }

    public function test_module_blocked_by_plan_returns_404(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $plan = SubscriptionPlan::create(['name' => 'No MCQ', 'slug' => 'no-mcq-'.Str::random(6), 'price_inr' => 100, 'billing_period' => 'annual']);
        PlanFeature::create(['plan_id' => $plan->id, 'feature_key' => 'module.mcq', 'enabled' => false]);

        $tenant = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Gated Tenant', 'is_active' => true]);
        TenantSubscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
            'period_start' => now(), 'period_end' => now()->addYear(), 'status' => 'active',
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('sahodaya_admin');

        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->get("/__test/feature-gated/{$tenant->id}")
            ->assertNotFound();
    }

    public function test_module_allowed_by_plan_passes_through(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $plan = SubscriptionPlan::create(['name' => 'With MCQ', 'slug' => 'with-mcq-'.Str::random(6), 'price_inr' => 100, 'billing_period' => 'annual']);
        PlanFeature::create(['plan_id' => $plan->id, 'feature_key' => 'module.mcq', 'enabled' => true]);

        $tenant = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Allowed Tenant', 'is_active' => true]);
        TenantSubscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
            'period_start' => now(), 'period_end' => now()->addYear(), 'status' => 'active',
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('sahodaya_admin');

        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->get("/__test/feature-gated/{$tenant->id}")
            ->assertOk()
            ->assertSee('ok');
    }
}
