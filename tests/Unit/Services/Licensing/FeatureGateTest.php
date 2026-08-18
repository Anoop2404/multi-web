<?php

namespace Tests\Unit\Services\Licensing;

use App\Models\PlanFeature;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Models\TenantSubscription;
use App\Services\Licensing\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantOnPlan(?SubscriptionPlan $plan = null): Tenant
    {
        $tenant = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Gate Test', 'is_active' => true]);

        if ($plan) {
            TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'period_start' => now(),
                'period_end' => now()->addYear(),
                'status' => 'active',
            ]);
        }

        return $tenant;
    }

    public function test_unconfigured_tenant_defaults_to_allowed(): void
    {
        $tenant = $this->makeTenantOnPlan();

        $this->assertTrue((new FeatureGate)->allows($tenant, 'module.mcq'));
        $this->assertNull((new FeatureGate)->limit($tenant, 'limit.schools'));
    }

    public function test_plan_feature_is_respected(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Basic', 'slug' => 'basic-'.Str::random(6), 'price_inr' => 100, 'billing_period' => 'annual']);
        PlanFeature::create(['plan_id' => $plan->id, 'feature_key' => 'module.mcq', 'enabled' => false]);
        PlanFeature::create(['plan_id' => $plan->id, 'feature_key' => 'limit.schools', 'enabled' => true, 'limit_value' => 25]);

        $tenant = $this->makeTenantOnPlan($plan);

        $gate = new FeatureGate;
        $this->assertFalse($gate->allows($tenant, 'module.mcq'));
        $this->assertSame(25, $gate->limit($tenant, 'limit.schools'));
    }

    public function test_tenant_override_wins_over_plan(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Basic 2', 'slug' => 'basic2-'.Str::random(6), 'price_inr' => 100, 'billing_period' => 'annual']);
        PlanFeature::create(['plan_id' => $plan->id, 'feature_key' => 'module.mcq', 'enabled' => false]);

        $tenant = $this->makeTenantOnPlan($plan);
        TenantFeatureOverride::create(['tenant_id' => $tenant->id, 'feature_key' => 'module.mcq', 'enabled' => true]);

        $this->assertTrue((new FeatureGate)->allows($tenant, 'module.mcq'));
    }

    public function test_override_can_also_restrict_a_plan_allowed_feature(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Basic 3', 'slug' => 'basic3-'.Str::random(6), 'price_inr' => 100, 'billing_period' => 'annual']);
        PlanFeature::create(['plan_id' => $plan->id, 'feature_key' => 'module.sports', 'enabled' => true]);

        $tenant = $this->makeTenantOnPlan($plan);
        TenantFeatureOverride::create(['tenant_id' => $tenant->id, 'feature_key' => 'module.sports', 'enabled' => false]);

        $this->assertFalse((new FeatureGate)->allows($tenant, 'module.sports'));
    }
}
