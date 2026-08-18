<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateFestEvent;
use App\Models\State\StateQualifierIntake;
use App\Models\StateRemittance;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The platform's own gap analysis flagged this as the single most serious finding
 * across the entire audit: state_admin/state_staff had no state assignment and no
 * ownership check anywhere, so any state admin could read/write every other
 * state's fest programs, qualifiers, and remittances. These tests are the direct
 * proof that the fix (states table + StateScope + EnsureStateAdmin) actually closes it.
 */
class StateCrossIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeStateAdmin(PlatformState $state): User
    {
        $admin = User::factory()->create([
            'tenant_id' => null,
            'must_change_password' => false,
            'state_id' => $state->id,
        ]);
        $admin->assignRole('state_admin');

        return $admin;
    }

    public function test_state_admin_cannot_list_another_states_programs(): void
    {
        $stateA = PlatformState::create(['code' => 'AA', 'name' => 'State A']);
        $stateB = PlatformState::create(['code' => 'BB', 'name' => 'State B']);

        $programA = FestStateProgram::create(['state_id' => $stateA->id, 'title' => 'Program A', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state']]);
        FestStateProgram::create(['state_id' => $stateB->id, 'title' => 'Program B', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state']]);

        $adminA = $this->makeStateAdmin($stateA);

        $this->actingAs($adminA)
            ->get('/admin/state-programs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StatePrograms/Index', false)
                ->has('programs', 1)
                ->where('programs.0.id', $programA->id));
    }

    public function test_state_admin_cannot_open_another_states_program_directly(): void
    {
        $stateA = PlatformState::create(['code' => 'AA', 'name' => 'State A']);
        $stateB = PlatformState::create(['code' => 'BB', 'name' => 'State B']);

        $programB = FestStateProgram::create(['state_id' => $stateB->id, 'title' => 'Program B', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state']]);

        $adminA = $this->makeStateAdmin($stateA);

        $this->actingAs($adminA)
            ->get("/admin/state-programs/{$programB->id}")
            ->assertForbidden();
    }

    public function test_state_admin_cannot_publish_another_states_program(): void
    {
        $stateA = PlatformState::create(['code' => 'AA', 'name' => 'State A']);
        $stateB = PlatformState::create(['code' => 'BB', 'name' => 'State B']);

        $programB = FestStateProgram::create(['state_id' => $stateB->id, 'title' => 'Program B', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'draft']);

        $adminA = $this->makeStateAdmin($stateA);

        $this->actingAs($adminA)
            ->post("/admin/state-programs/{$programB->id}/publish")
            ->assertForbidden();

        $this->assertSame('draft', $programB->fresh()->status);
    }

    public function test_state_admin_cannot_see_or_verify_another_states_remittances(): void
    {
        $stateA = PlatformState::create(['code' => 'AA', 'name' => 'State A']);
        $stateB = PlatformState::create(['code' => 'BB', 'name' => 'State B']);

        $sahodaya = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Cross Isolation Sahodaya', 'is_active' => true]);

        FestStateProgram::create(['state_id' => $stateA->id, 'title' => 'Program A', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state']]);
        $remittanceB = StateRemittance::create([
            'state_id' => $stateB->id,
            'sahodaya_id' => $sahodaya->id,
            'title' => 'Remittance B',
            'amount' => 1000,
            'status' => 'submitted',
        ]);

        $adminA = $this->makeStateAdmin($stateA);

        $this->actingAs($adminA)
            ->get('/admin/state-remittances')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StateRemittances/Index', false)
                ->has('remittances.data', 0));

        $this->actingAs($adminA)
            ->post("/admin/state-remittances/{$remittanceB->id}/verify")
            ->assertForbidden();

        $this->assertSame('submitted', $remittanceB->fresh()->status);
    }

    public function test_state_admin_cannot_review_another_states_qualifier_intake(): void
    {
        $stateA = PlatformState::create(['code' => 'AA', 'name' => 'State A']);
        $stateB = PlatformState::create(['code' => 'BB', 'name' => 'State B']);

        $programB = FestStateProgram::create(['state_id' => $stateB->id, 'title' => 'Program B', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state']]);
        $intakeB = StateQualifierIntake::create([
            'state_program_id' => $programB->id,
            'state_id' => $stateB->id,
            'source_tenant_id' => 'some-sahodaya',
            'source_event_id' => 1,
            'idempotency_key' => 'cross-isolation-test-'.Str::uuid(),
            'status' => 'received',
            'payload' => [],
        ]);

        $adminA = $this->makeStateAdmin($stateA);

        $this->actingAs($adminA)
            ->get("/admin/state-workspace/qualifiers/{$intakeB->id}")
            ->assertForbidden();

        $this->actingAs($adminA)
            ->post("/admin/state-workspace/qualifiers/{$intakeB->id}/approve")
            ->assertForbidden();
    }

    public function test_state_admin_cannot_publish_results_for_another_states_fest_event(): void
    {
        $stateA = PlatformState::create(['code' => 'AA', 'name' => 'State A']);
        $stateB = PlatformState::create(['code' => 'BB', 'name' => 'State B']);

        $eventB = StateFestEvent::create([
            'state_program_id' => (string) Str::uuid(),
            'state_id' => $stateB->id,
            'name' => 'State B Finals',
            'status' => 'draft',
        ]);

        $adminA = $this->makeStateAdmin($stateA);

        $this->actingAs($adminA)
            ->get("/admin/state-workspace/fest/{$eventB->id}")
            ->assertForbidden();

        $this->actingAs($adminA)
            ->post("/admin/state-workspace/fest/{$eventB->id}/publish-results")
            ->assertForbidden();
    }

    public function test_state_admin_with_no_state_assigned_sees_nothing(): void
    {
        $stateA = PlatformState::create(['code' => 'AA', 'name' => 'State A']);
        FestStateProgram::create(['state_id' => $stateA->id, 'title' => 'Program A', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state']]);

        $unassigned = User::factory()->create(['tenant_id' => null, 'must_change_password' => false, 'state_id' => null]);
        $unassigned->assignRole('state_admin');

        $this->actingAs($unassigned)
            ->get('/admin/state-programs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StatePrograms/Index', false)
                ->has('programs', 0));
    }

    public function test_superadmin_sees_every_states_programs(): void
    {
        $stateA = PlatformState::create(['code' => 'AA', 'name' => 'State A']);
        $stateB = PlatformState::create(['code' => 'BB', 'name' => 'State B']);

        FestStateProgram::create(['state_id' => $stateA->id, 'title' => 'Program A', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state']]);
        FestStateProgram::create(['state_id' => $stateB->id, 'title' => 'Program B', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state']]);

        $superadmin = PlatformUser::query()->create([
            'name' => 'Cross Isolation Super',
            'email' => 'cross-isolation-super@example.com',
            'username' => 'cross_isolation_super',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin, 'platform')
            ->get('http://superadmin.test/admin/state-programs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StatePrograms/Index', false)
                ->has('programs', 2));
    }
}
