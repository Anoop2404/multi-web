<?php

namespace Database\Seeders;

use App\Models\PlanFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Baseline plans for the "FRD-13 §9 Feature Management" entitlement system
 * (SubscriptionPlan/PlanFeature/TenantSubscription/FeatureGate) — specifically the
 * Free/Premium website design tier. Idempotent, safe to re-run.
 */
class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $free = SubscriptionPlan::updateOrCreate(
            ['slug' => 'free'],
            ['name' => 'Free', 'price_inr' => 0, 'billing_period' => 'annual', 'grace_period_days' => 0, 'is_active' => true]
        );

        $premium = SubscriptionPlan::updateOrCreate(
            ['slug' => 'premium'],
            ['name' => 'Premium', 'price_inr' => 4999, 'billing_period' => 'annual', 'grace_period_days' => 14, 'is_active' => true]
        );

        PlanFeature::updateOrCreate(['plan_id' => $free->id, 'feature_key' => 'module.website'], ['enabled' => true]);
        PlanFeature::updateOrCreate(['plan_id' => $free->id, 'feature_key' => 'module.website_premium'], ['enabled' => false]);

        PlanFeature::updateOrCreate(['plan_id' => $premium->id, 'feature_key' => 'module.website'], ['enabled' => true]);
        PlanFeature::updateOrCreate(['plan_id' => $premium->id, 'feature_key' => 'module.website_premium'], ['enabled' => true]);
    }
}
