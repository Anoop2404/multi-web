<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\PlanFeature;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\WebsiteSite;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteBuilderPremiumGatingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;

    private User $admin;

    private User $superadmin;

    private WebsiteSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Gating Test Sahodaya',
            'subdomain' => 'gating-test',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['tenant_id' => $this->sahodaya->id]);
        $this->admin->assignRole('sahodaya_admin');

        // Applying/publishing a template is platform-assigned (superadmin only) —
        // see SiteBuilderApiController::assertSuperAdmin().
        $this->superadmin = User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]);
        $this->superadmin->assignRole('superadmin');

        $this->site = WebsiteSite::ensurePrimary($this->sahodaya->id);
    }

    private function subscribeTo(SubscriptionPlan $plan): void
    {
        TenantSubscription::create([
            'tenant_id' => $this->sahodaya->id,
            'plan_id' => $plan->id,
            'period_start' => now(),
            'period_end' => now()->addYears(50),
            'status' => 'active',
        ]);
    }

    public function test_free_tenant_is_shown_the_premium_experience_as_locked(): void
    {
        $free = SubscriptionPlan::create(['name' => 'Free', 'slug' => 'free-'.Str::random(6), 'price_inr' => 0, 'billing_period' => 'annual']);
        PlanFeature::create(['plan_id' => $free->id, 'feature_key' => 'module.website_premium', 'enabled' => false]);
        $this->subscribeTo($free);

        $response = $this->actingAs($this->admin)
            ->getJson("/sahodaya-admin/{$this->sahodaya->id}/site-builder/api/experiences")
            ->assertOk();

        $experiences = collect($response->json('experiences'));
        $this->assertTrue($experiences->firstWhere('key', 'sahodaya-premium')['locked']);
        $this->assertFalse($experiences->firstWhere('key', 'network-directory')['locked']);
    }

    public function test_sahodaya_admin_cannot_apply_any_experience_at_all(): void
    {
        // Applying a template is platform-assigned now — a regular Sahodaya admin is
        // blocked regardless of plan, even for a template that isn't premium-gated.
        $this->actingAs($this->admin)
            ->postJson("/sahodaya-admin/{$this->sahodaya->id}/site-builder/api/experience/draft", [
                'site_id' => $this->site->id,
                'template_key' => 'network-directory',
            ])
            ->assertStatus(403);

        $this->assertNull($this->site->fresh()->draft_template_json);
    }

    public function test_free_tenant_cannot_apply_the_premium_experience(): void
    {
        $free = SubscriptionPlan::create(['name' => 'Free', 'slug' => 'free-'.Str::random(6), 'price_inr' => 0, 'billing_period' => 'annual']);
        PlanFeature::create(['plan_id' => $free->id, 'feature_key' => 'module.website_premium', 'enabled' => false]);
        $this->subscribeTo($free);

        $this->actingAs($this->superadmin)
            ->postJson("/sahodaya-admin/{$this->sahodaya->id}/site-builder/api/experience/draft", [
                'site_id' => $this->site->id,
                'template_key' => 'sahodaya-premium',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['template_key']);

        $this->assertNull($this->site->fresh()->draft_template_json);
    }

    public function test_free_tenant_can_still_apply_the_free_experience(): void
    {
        $free = SubscriptionPlan::create(['name' => 'Free', 'slug' => 'free-'.Str::random(6), 'price_inr' => 0, 'billing_period' => 'annual']);
        PlanFeature::create(['plan_id' => $free->id, 'feature_key' => 'module.website_premium', 'enabled' => false]);
        $this->subscribeTo($free);

        $this->actingAs($this->superadmin)
            ->postJson("/sahodaya-admin/{$this->sahodaya->id}/site-builder/api/experience/draft", [
                'site_id' => $this->site->id,
                'template_key' => 'network-directory',
            ])
            ->assertOk()
            ->assertJson(['saved' => true]);
    }

    public function test_premium_plan_tenant_can_apply_the_premium_experience(): void
    {
        $premium = SubscriptionPlan::create(['name' => 'Premium', 'slug' => 'premium-'.Str::random(6), 'price_inr' => 4999, 'billing_period' => 'annual']);
        PlanFeature::create(['plan_id' => $premium->id, 'feature_key' => 'module.website_premium', 'enabled' => true]);
        $this->subscribeTo($premium);

        $response = $this->actingAs($this->admin)
            ->getJson("/sahodaya-admin/{$this->sahodaya->id}/site-builder/api/experiences")
            ->assertOk();
        $this->assertFalse(collect($response->json('experiences'))->firstWhere('key', 'sahodaya-premium')['locked']);

        $this->actingAs($this->superadmin)
            ->postJson("/sahodaya-admin/{$this->sahodaya->id}/site-builder/api/experience/draft", [
                'site_id' => $this->site->id,
                'template_key' => 'sahodaya-premium',
            ])
            ->assertOk()
            ->assertJson(['saved' => true]);
    }

    public function test_tenant_feature_override_unlocks_premium_regardless_of_plan(): void
    {
        $free = SubscriptionPlan::create(['name' => 'Free', 'slug' => 'free-'.Str::random(6), 'price_inr' => 0, 'billing_period' => 'annual']);
        PlanFeature::create(['plan_id' => $free->id, 'feature_key' => 'module.website_premium', 'enabled' => false]);
        $this->subscribeTo($free);
        TenantFeatureOverride::create(['tenant_id' => $this->sahodaya->id, 'feature_key' => 'module.website_premium', 'enabled' => true]);

        $this->actingAs($this->superadmin)
            ->postJson("/sahodaya-admin/{$this->sahodaya->id}/site-builder/api/experience/draft", [
                'site_id' => $this->site->id,
                'template_key' => 'sahodaya-premium',
            ])
            ->assertOk()
            ->assertJson(['saved' => true]);
    }
}
