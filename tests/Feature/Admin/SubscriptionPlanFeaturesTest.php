<?php

namespace Tests\Feature\Admin;

use App\Models\PlanFeature;
use App\Models\PlatformUser;
use App\Models\SubscriptionPlan;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionPlanFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_update_plan_features(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superadmin = PlatformUser::query()->create([
            'name' => 'Plan Features Super',
            'email' => 'plan-features-super@example.com',
            'username' => 'plan_features_super',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $plan = SubscriptionPlan::create(['name' => 'Pro', 'slug' => 'pro-'.Str::random(6), 'price_inr' => 5000, 'billing_period' => 'annual']);

        $this->actingAs($superadmin, 'platform')
            ->put("http://superadmin.test/admin/billing/plans/{$plan->id}/features", [
                'features' => [
                    'module.mcq' => ['enabled' => true],
                    'module.sports' => ['enabled' => false],
                    'limit.schools' => ['enabled' => true, 'limit_value' => 50],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plan_features', ['plan_id' => $plan->id, 'feature_key' => 'module.mcq', 'enabled' => true]);
        $this->assertDatabaseHas('plan_features', ['plan_id' => $plan->id, 'feature_key' => 'module.sports', 'enabled' => false]);
        $this->assertDatabaseHas('plan_features', ['plan_id' => $plan->id, 'feature_key' => 'limit.schools', 'enabled' => true, 'limit_value' => 50]);
        $this->assertSame(3, PlanFeature::where('plan_id', $plan->id)->count());
    }

    public function test_unknown_feature_keys_are_ignored(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superadmin = PlatformUser::query()->create([
            'name' => 'Plan Features Super 2',
            'email' => 'plan-features-super-2@example.com',
            'username' => 'plan_features_super_2',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $plan = SubscriptionPlan::create(['name' => 'Pro 2', 'slug' => 'pro2-'.Str::random(6), 'price_inr' => 5000, 'billing_period' => 'annual']);

        $this->actingAs($superadmin, 'platform')
            ->put("http://superadmin.test/admin/billing/plans/{$plan->id}/features", [
                'features' => ['not.a.real.key' => ['enabled' => true]],
            ])
            ->assertRedirect();

        $this->assertSame(0, PlanFeature::where('plan_id', $plan->id)->count());
    }
}
