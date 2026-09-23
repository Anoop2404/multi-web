<?php

namespace Tests\Feature\State;

use App\Models\ExternalSahodaya;
use App\Models\FestStateProgram;
use App\Models\PlatformState;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\TenantProvisioningChecklist;
use App\Models\User;
use App\Services\State\SahodayaPromotionService;
use App\Services\State\ExternalIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 2 of docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md.
 *
 * The suite runs with TENANCY_DATABASE_PER_SAHODAYA=false (see phpunit.xml), so these cover the
 * tenant/subdomain/link/admin/checklist half of promotion and the idempotency guarantees. The
 * database-creation half needs a real Postgres server and is covered by the manual UAT steps in
 * §8.3 B of the plan.
 */
class SahodayaPromotionTest extends TestCase
{
    use RefreshDatabase;

    private FestStateProgram $program;

    private PlatformState $state;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        // SahodayaSiteTemplate::apply() gives every new Sahodaya a free TenantSubscription and
        // throws without the plan, so promotion genuinely depends on this being seeded.
        Artisan::call('db:seed', ['--class' => 'SubscriptionPlanSeeder']);

        $this->state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);

        $this->program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'state_id'       => $this->state->id,
            'event_type'     => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);
    }

    private function externalSahodaya(array $overrides = []): ExternalSahodaya
    {
        return app(ExternalIntakeService::class)->createSahodaya($this->program, array_merge([
            'name'          => 'Idukki Sahodaya',
            'district'      => 'IDUKKI',
            'contact_name'  => 'Secretary',
            'contact_email' => 'secretary@idukkisahodaya.test',
            'source'        => 'seeded',
        ], $overrides));
    }

    public function test_promotion_creates_a_linked_tenant_with_subdomain_profile_and_admin_login(): void
    {
        $ext = $this->externalSahodaya();

        $tenant = app(SahodayaPromotionService::class)->promote($ext);
        $ext->refresh();

        $this->assertSame('sahodaya', $tenant->type);
        $this->assertSame('idukki', $tenant->subdomain);
        $this->assertSame($this->state->id, $tenant->state_id);
        $this->assertTrue($tenant->is_active);

        // The link back is what makes every later run idempotent, and what the access-code portal
        // checks before allowing a write.
        $this->assertSame($tenant->id, $ext->tenant_id);
        $this->assertSame(ExternalSahodaya::PROMOTION_READY, $ext->promotion_status);
        $this->assertNotNull($ext->promoted_at);
        $this->assertNull($ext->promotion_error);
        $this->assertTrue($ext->isPromoted());

        // The host is only published once the tenant is active — see the ordering note in promote().
        $this->assertSame(['idukki'], $tenant->domains->pluck('domain')->all());

        $profile = SahodayaProfile::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($profile, 'Promotion must leave a SahodayaProfile behind.');
        // "Sahodaya" is stripped before initials are taken, leaving a single word → first 3 letters.
        $this->assertSame('IDU', $profile->prefix);

        $admin = User::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($admin);
        $this->assertSame('idukki', $admin->username);
        $this->assertSame('secretary@idukkisahodaya.test', $admin->email);
        $this->assertTrue($admin->hasRole('sahodaya_admin'));
        $this->assertTrue($admin->must_change_password);

        $steps = TenantProvisioningChecklist::where('tenant_id', $tenant->id)->pluck('step_key')->all();
        $this->assertContains('tenant_created', $steps);
        $this->assertContains('portal_admin_created', $steps);
    }

    public function test_promoting_an_already_promoted_sahodaya_is_refused_rather_than_duplicating_it(): void
    {
        $ext = $this->externalSahodaya();
        $first = app(SahodayaPromotionService::class)->promote($ext);

        $this->expectException(RuntimeException::class);

        try {
            app(SahodayaPromotionService::class)->promote($ext->fresh());
        } finally {
            // The important assertion: no second tenant, and no second subdomain claim.
            $this->assertSame(1, Tenant::where('subdomain', 'idukki')->count());
            $this->assertSame(1, Tenant::where('id', $first->id)->count());
        }
    }

    public function test_retry_reuses_the_linked_tenant_instead_of_creating_another(): void
    {
        $ext = $this->externalSahodaya();
        $first = app(SahodayaPromotionService::class)->promote($ext);

        $again = app(SahodayaPromotionService::class)->retry($ext->fresh());

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Tenant::where('type', 'sahodaya')->count());
        $this->assertSame(1, User::where('tenant_id', $first->id)->count(), 'Retry must not issue a second admin account.');
    }

    public function test_appeal_pool_rows_are_never_promoted(): void
    {
        $appeal = app(ExternalIntakeService::class)->ensureAppealSahodaya($this->program);

        $this->assertFalse($appeal->isPromotable());
        $this->assertStringContainsString('Appeal pool', (string) app(SahodayaPromotionService::class)->blockingReason($appeal));

        $this->expectException(RuntimeException::class);
        app(SahodayaPromotionService::class)->promote($appeal);
    }

    public function test_disabled_sahodayas_are_blocked_until_reactivated(): void
    {
        $ext = $this->externalSahodaya();
        $ext->forceFill(['status' => 'disabled'])->save();

        $this->assertNotNull(app(SahodayaPromotionService::class)->blockingReason($ext));

        $ext->forceFill(['status' => 'active'])->save();
        $this->assertNull(app(SahodayaPromotionService::class)->blockingReason($ext->fresh()));
    }

    public function test_colliding_names_get_distinct_subdomains(): void
    {
        // The official list repeats names across districts, so this is the common case, not an edge.
        $first = $this->externalSahodaya([
            'name' => 'Central Sahodaya', 'district' => 'Ernakulam', 'contact_email' => 'a@ernakulam.test',
        ]);
        $second = $this->externalSahodaya([
            'name' => 'Central Sahodaya', 'district' => 'Thrissur', 'contact_email' => 'b@thrissur.test',
        ]);

        $service = app(SahodayaPromotionService::class);
        $a = $service->promote($first);
        $b = $service->promote($second);

        $this->assertNotSame($a->subdomain, $b->subdomain);
        $this->assertSame('central', $a->subdomain);
        $this->assertStringContainsString('thrissur', $b->subdomain);
    }

    public function test_a_reserved_subdomain_is_never_handed_out(): void
    {
        config(['tenancy.reserved_subdomains' => ['admin', 'idukki']]);

        $ext = $this->externalSahodaya();
        $tenant = app(SahodayaPromotionService::class)->promote($ext);

        $this->assertNotSame('idukki', $tenant->subdomain);
    }

    public function test_a_non_email_contact_does_not_become_a_login(): void
    {
        // One seeded row carries a postal address in contact_email; writing that into users.email
        // would create an account that cannot be emailed or password-reset.
        $ext = $this->externalSahodaya([
            'name'          => 'Conclave Sahodaya',
            'contact_email' => 'kottayam 686141',
            'contact_name'  => null,
        ]);

        $this->assertNull(app(SahodayaPromotionService::class)->adminEmail($ext));

        $tenant = app(SahodayaPromotionService::class)->promote($ext);

        $this->assertSame(0, User::where('tenant_id', $tenant->id)->whereNotNull('email')->count());
    }

    public function test_a_contact_email_already_used_by_another_tenant_does_not_abort_the_promotion(): void
    {
        // users.email is unique per database, not per tenant. With a dedicated database per
        // Sahodaya this never collides; on a shared database two Sahodayas listing the same contact
        // address on the master list would. The promotion must still complete — only the login is
        // deferred, because the central users.email is NOT NULL so there is no username-only
        // fallback here (there is inside a dedicated tenant database).
        $first = $this->externalSahodaya(['name' => 'First Sahodaya', 'contact_email' => 'shared@example.test']);
        $second = $this->externalSahodaya(['name' => 'Second Sahodaya', 'contact_email' => 'shared@example.test']);

        $service = app(SahodayaPromotionService::class);
        $a = $service->promote($first);
        $b = $service->promote($second);

        $this->assertSame('shared@example.test', User::where('tenant_id', $a->id)->value('email'));
        $this->assertSame(0, User::where('tenant_id', $b->id)->count());

        $this->assertTrue($b->is_active, 'The tenant itself must still be usable.');
        $this->assertSame(ExternalSahodaya::PROMOTION_READY, $second->fresh()->promotion_status);
        $this->assertFalse(
            TenantProvisioningChecklist::where('tenant_id', $b->id)
                ->where('step_key', 'portal_admin_created')
                ->exists(),
            'The owed login must stay visible on the checklist.'
        );
    }

    public function test_a_row_with_no_contact_details_still_yields_a_working_tenant(): void
    {
        $ext = $this->externalSahodaya([
            'name'          => 'Nameless Sahodaya',
            'contact_email' => null,
            'contact_name'  => null,
            'contact_phone' => null,
        ]);

        $tenant = app(SahodayaPromotionService::class)->promote($ext);

        $this->assertTrue($tenant->is_active);
        $this->assertSame(0, User::where('tenant_id', $tenant->id)->count());

        // Not marked complete, so the state admin can see the login is still owed.
        $this->assertFalse(
            TenantProvisioningChecklist::where('tenant_id', $tenant->id)
                ->where('step_key', 'portal_admin_created')
                ->exists()
        );
    }

    public function test_plan_previews_without_writing_anything(): void
    {
        $ext = $this->externalSahodaya();

        $plan = app(SahodayaPromotionService::class)->plan($ext);

        $this->assertTrue($plan['promotable']);
        $this->assertSame('idukki', $plan['subdomain']);
        $this->assertSame('secretary@idukkisahodaya.test', $plan['admin_email']);
        $this->assertSame(0, Tenant::count(), 'plan() must not create anything.');
        $this->assertNull($ext->fresh()->tenant_id);
    }

    public function test_plan_respects_subdomains_claimed_earlier_in_the_same_batch(): void
    {
        $ext = $this->externalSahodaya(['district' => 'Ernakulam']);

        $plan = app(SahodayaPromotionService::class)->plan($ext, ['idukki']);

        $this->assertNotSame('idukki', $plan['subdomain']);
    }

    public function test_tenants_are_scopable_to_a_state_and_fail_closed_without_one(): void
    {
        $ext = $this->externalSahodaya();
        app(SahodayaPromotionService::class)->promote($ext);

        $this->assertSame(1, Tenant::forState($this->state->id)->count());
        $this->assertSame(0, Tenant::forState(null)->count(), 'A state user with no state must see nothing.');
    }
}
