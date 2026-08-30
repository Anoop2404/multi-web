<?php

namespace Tests\Unit\Support;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Support\NavConfigDefaults;
use App\Support\SahodayaSiteTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SahodayaSiteTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_throws_when_the_free_plan_is_not_seeded(): void
    {
        $tenant = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'No Plan Sahodaya', 'is_active' => true]);

        $this->expectException(\RuntimeException::class);

        SahodayaSiteTemplate::apply($tenant);
    }

    public function test_apply_gives_a_fresh_tenant_a_free_subscription_and_the_full_section_set(): void
    {
        SubscriptionPlan::create(['name' => 'Free', 'slug' => 'free', 'price_inr' => 0, 'billing_period' => 'annual']);

        $tenant = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Fresh Sahodaya', 'is_active' => true]);

        SahodayaSiteTemplate::apply($tenant);

        $subscription = TenantSubscription::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame('free', $subscription->plan->slug);

        $sections = $tenant->sections()->get();
        $this->assertGreaterThanOrEqual(13, $sections->count());
        $this->assertTrue($sections->every(fn ($s) => $s->site_id !== null), 'Every seeded section should be site-scoped, not the legacy null-scoped fallback.');

        $sectionTypeAnchors = $sections->pluck('section_type')->map(fn ($t) => str_replace('_', '-', $t))->unique();
        $nav = NavConfigDefaults::pruneDeadAnchors(NavConfigDefaults::forSahodaya(), $sections);
        foreach ($nav['items'] as $item) {
            if (preg_match('/^\/#(.+)$/', $item['url'], $m)) {
                $this->assertTrue($sectionTypeAnchors->contains($m[1]), "Nav anchor #{$m[1]} has no matching section.");
            }
        }
    }

    public function test_apply_is_idempotent_and_does_not_duplicate_sections(): void
    {
        SubscriptionPlan::create(['name' => 'Free', 'slug' => 'free', 'price_inr' => 0, 'billing_period' => 'annual']);

        $tenant = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Idempotent Sahodaya', 'is_active' => true]);

        SahodayaSiteTemplate::apply($tenant);
        $firstCount = $tenant->sections()->count();

        SahodayaSiteTemplate::apply($tenant->fresh());
        $secondCount = $tenant->sections()->count();

        $this->assertSame($firstCount, $secondCount);
        $this->assertSame(1, TenantSubscription::where('tenant_id', $tenant->id)->count());
    }
}
