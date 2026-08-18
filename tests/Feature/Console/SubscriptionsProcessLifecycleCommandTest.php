<?php

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionsProcessLifecycleCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        return Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Lifecycle Test Tenant', 'is_active' => true]);
    }

    public function test_overdue_active_subscription_enters_grace(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Plan', 'slug' => 'plan-'.Str::random(6), 'price_inr' => 100, 'billing_period' => 'annual', 'grace_period_days' => 14]);
        $subscription = TenantSubscription::create([
            'tenant_id' => $this->makeTenant()->id,
            'plan_id' => $plan->id,
            'period_start' => now()->subYear(),
            'period_end' => now()->subDay(),
            'status' => 'active',
        ]);

        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();

        $this->assertSame('grace', $subscription->fresh()->status);
        $this->assertNotNull(AuditLog::where('action', 'subscription.entered_grace')->first());
    }

    public function test_active_subscription_not_yet_overdue_is_untouched(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Plan 2', 'slug' => 'plan2-'.Str::random(6), 'price_inr' => 100, 'billing_period' => 'annual']);
        $subscription = TenantSubscription::create([
            'tenant_id' => $this->makeTenant()->id,
            'plan_id' => $plan->id,
            'period_start' => now()->subMonth(),
            'period_end' => now()->addMonth(),
            'status' => 'active',
        ]);

        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();

        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_grace_subscription_past_grace_period_is_suspended(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Plan 3', 'slug' => 'plan3-'.Str::random(6), 'price_inr' => 100, 'billing_period' => 'annual', 'grace_period_days' => 14]);
        $subscription = TenantSubscription::create([
            'tenant_id' => $this->makeTenant()->id,
            'plan_id' => $plan->id,
            'period_start' => now()->subYear(),
            'period_end' => now()->subDays(20), // 20 days overdue > 14-day grace
            'status' => 'grace',
        ]);

        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('suspended', $subscription->status);
        $this->assertNotNull($subscription->suspended_at);
        $this->assertStringContainsString('Automatic', $subscription->suspended_reason);
        $this->assertNotNull(AuditLog::where('action', 'subscription.auto_suspended')->first());
    }

    public function test_grace_subscription_still_within_grace_period_is_untouched(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Plan 4', 'slug' => 'plan4-'.Str::random(6), 'price_inr' => 100, 'billing_period' => 'annual', 'grace_period_days' => 14]);
        $subscription = TenantSubscription::create([
            'tenant_id' => $this->makeTenant()->id,
            'plan_id' => $plan->id,
            'period_start' => now()->subYear(),
            'period_end' => now()->subDays(5), // only 5 days overdue, within 14-day grace
            'status' => 'grace',
        ]);

        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();

        $this->assertSame('grace', $subscription->fresh()->status);
        $this->assertNull($subscription->fresh()->suspended_at);
    }

    public function test_readonly_and_suspended_subscriptions_are_left_alone(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Plan 5', 'slug' => 'plan5-'.Str::random(6), 'price_inr' => 100, 'billing_period' => 'annual']);

        $readonly = TenantSubscription::create([
            'tenant_id' => $this->makeTenant()->id,
            'plan_id' => $plan->id,
            'period_start' => now()->subYear(), 'period_end' => now()->subMonth(),
            'status' => 'readonly',
        ]);

        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();

        $this->assertSame('readonly', $readonly->fresh()->status);
    }
}
