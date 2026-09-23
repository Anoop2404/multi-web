<?php

namespace Tests\Feature\External;

use App\Models\ExternalSahodaya;
use App\Models\ExternalSchool;
use App\Models\FestStateProgram;
use App\Models\PlatformState;
use App\Services\State\ExternalIntakeService;
use App\Services\State\SahodayaPromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Cutover behaviour for the access-code portal once a Sahodaya has been promoted to a tenant
 * (docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md §5.3).
 *
 * The risk being closed: promotion gives a Sahodaya its own database, but the access code stays
 * valid and was already printed in circulars. Without this, the same Sahodaya could keep typing a
 * roster here while its schools type another one in the new portal, with nothing to reconcile the
 * two and no signal to the State about which to believe.
 *
 * The code deliberately keeps working — it redirects rather than 404s, because a dead link tells a
 * coordinator nothing about where their roster went.
 */
class PromotedPortalLockdownTest extends TestCase
{
    use RefreshDatabase;

    private FestStateProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        Artisan::call('db:seed', ['--class' => 'SubscriptionPlanSeeder']);
        Artisan::call('state:migrate');

        $state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);

        $this->program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'state_id'       => $state->id,
            'event_type'     => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);
    }

    private function sahodaya(string $name = 'Idukki Sahodaya'): ExternalSahodaya
    {
        return app(ExternalIntakeService::class)->createSahodaya($this->program, [
            'name'          => $name,
            'district'      => 'IDUKKI',
            'contact_email' => strtolower(str_replace(' ', '', $name)).'@example.test',
            'source'        => 'seeded',
        ]);
    }

    public function test_an_unpromoted_sahodaya_portal_still_works_exactly_as_before(): void
    {
        $ext = $this->sahodaya();

        $this->get("/state/external/{$ext->access_code}")
            ->assertOk()
            ->assertSee('Coordinator portal', false);

        $this->post("/state/external/{$ext->access_code}/schools", ['name' => 'St Joseph HSS'])
            ->assertRedirect();

        $this->assertSame(1, $ext->schools()->where('name', 'St Joseph HSS')->count());
    }

    public function test_a_promoted_sahodayas_portal_shows_the_moved_page_instead_of_the_roster(): void
    {
        $ext = $this->sahodaya();
        $tenant = app(SahodayaPromotionService::class)->promote($ext);

        // The code still resolves — it is already printed on circulars.
        $this->get("/state/external/{$ext->fresh()->access_code}")
            ->assertOk()
            ->assertSee('now runs on the platform', false)
            ->assertSee($tenant->subdomain, false)
            ->assertDontSee('Coordinator portal', false);
    }

    public function test_every_write_path_on_a_promoted_sahodaya_is_refused(): void
    {
        $ext = $this->sahodaya();
        app(SahodayaPromotionService::class)->promote($ext);
        $code = $ext->fresh()->access_code;
        $before = $ext->schools()->count();

        $this->post("/state/external/{$code}/schools", ['name' => 'Should Not Exist'])
            ->assertRedirect("/state/external/{$code}");
        $this->post("/state/external/{$code}/submit")
            ->assertRedirect("/state/external/{$code}");
        $this->post("/state/external/{$code}/register-item", ['entry_id' => 1, 'item_code' => 'LM01'])
            ->assertRedirect("/state/external/{$code}");

        $this->assertSame($before, $ext->schools()->count(), 'No write may land after promotion.');
        $this->assertSame(0, ExternalSchool::where('name', 'Should Not Exist')->count());
    }

    public function test_a_school_under_a_promoted_sahodaya_cannot_log_in_or_write(): void
    {
        $ext = $this->sahodaya();
        $school = app(ExternalIntakeService::class)->addSchool($ext, ['name' => 'St Joseph HSS']);

        // Works before promotion — this is the control, so the assertion below means something.
        $this->get("/state/external/school/{$school->access_code}")
            ->assertRedirect(route('state.external.school.show'));
        $this->post(route('state.external.school.logout'));

        app(SahodayaPromotionService::class)->promote($ext);

        $this->get("/state/external/school/{$school->fresh()->access_code}")
            ->assertRedirect(route('state.external.school.login'))
            ->assertSessionHasErrors('username');
    }

    public function test_promotion_state_is_keyed_on_the_tenant_link_not_the_status_string(): void
    {
        // promotion_status is progress reporting for the UI; the foreign key is the fact. A stale
        // status must never be able to re-open the portal on a Sahodaya that has a tenant.
        $ext = $this->sahodaya();
        app(SahodayaPromotionService::class)->promote($ext);

        $ext->fresh()->forceFill(['promotion_status' => ExternalSahodaya::PROMOTION_FAILED])->save();

        $this->get("/state/external/{$ext->fresh()->access_code}")
            ->assertOk()
            ->assertSee('now runs on the platform', false);
    }
}
