<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Models\State\StateSahodaya;
use App\Support\StateFestPermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 2 of the State Kalotsav module — the workspace shell and its permission matrix.
 *
 * The module replaces "state_admin may write, state_staff may only GET" with capabilities, because
 * that pair cannot express the separations the State actually needs: a mark operator who must never
 * publish, a certificate operator who must never alter a result.
 */
class StateFestWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private StateFestEvent $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Artisan::call('state:migrate');

        $this->state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
        $program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'state_id' => $this->state->id,
            'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published',
        ]);
        $this->event = StateFestEvent::create([
            'state_program_id' => $program->id, 'state_id' => $this->state->id,
            'name' => 'State Finals 2026', 'slug' => 'state-finals-2026', 'status' => 'active',
        ]);
    }

    private function user(string $role, ?string $stateId = null): PlatformUser
    {
        $user = PlatformUser::create([
            'name' => $role, 'email' => $role.'@example.test', 'username' => $role,
            'password' => 'password', 'state_id' => $stateId ?? $this->state->id, 'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function workspace(PlatformUser $user)
    {
        return $this->actingAs($user, 'platform')
            ->get("http://superadmin.test/admin/state/fest/{$this->event->id}");
    }

    public function test_the_permission_matrix_keeps_operators_apart(): void
    {
        $mark = StateFestPermissions::forRole('state_mark_operator');
        $this->assertContains(StateFestPermissions::MARKS, $mark);
        $this->assertNotContains(StateFestPermissions::PUBLISH, $mark, 'A mark operator must never publish.');
        $this->assertNotContains(StateFestPermissions::RESULTS, $mark, 'A mark operator must not alter results.');

        $certificates = StateFestPermissions::forRole('state_certificate_operator');
        $this->assertContains(StateFestPermissions::CERTIFICATES, $certificates);
        $this->assertNotContains(StateFestPermissions::RESULTS, $certificates, 'A certificate operator must not alter results.');
        $this->assertNotContains(StateFestPermissions::MARKS, $certificates);

        $reportUser = StateFestPermissions::forRole('state_report_user');
        $this->assertSame([StateFestPermissions::VIEW, StateFestPermissions::REPORTS], $reportUser, 'Report users are read-only.');

        $this->assertSame(StateFestPermissions::all(), StateFestPermissions::forRole('state_admin'));
        $this->assertSame([], StateFestPermissions::forRole('not_a_state_role'), 'Unknown roles hold nothing.');
    }

    public function test_the_roles_are_seeded_with_exactly_those_permissions(): void
    {
        foreach (StateFestPermissions::roleMatrix() as $role => $expected) {
            $held = \App\Models\PlatformRole::where('name', $role)->sole()
                ->permissions->pluck('name')
                ->filter(fn ($p) => str_starts_with($p, 'state.fest.'))
                ->values()->all();

            $this->assertEqualsCanonicalizing($expected, $held, "Role {$role} was seeded with the wrong permissions.");
        }
    }

    public function test_a_state_admin_opens_the_workspace_with_every_capability(): void
    {
        $page = $this->workspace($this->user('state_admin'))->assertOk()->viewData('page');

        $this->assertSame('State/Fest/Overview', $page['component']);
        $this->assertSame('State Finals 2026', $page['props']['event']['name']);
        $this->assertEqualsCanonicalizing(StateFestPermissions::all(), $page['props']['permissions']);
    }

    public function test_an_operator_is_handed_only_its_own_capabilities(): void
    {
        // The sidebar filters itself on these, so a mark operator is never shown Results or Publish.
        $page = $this->workspace($this->user('state_mark_operator'))->assertOk()->viewData('page');

        $this->assertEqualsCanonicalizing(
            StateFestPermissions::forRole('state_mark_operator'),
            $page['props']['permissions'],
        );
    }

    public function test_the_capability_middleware_refuses_a_user_missing_the_capability(): void
    {
        // Tested directly rather than through a route, because every route built so far needs only
        // "view", which all State roles hold — the separations that matter are on capabilities the
        // later phases will gate on.
        $operator = $this->user('state_mark_operator');
        $middleware = new \App\Http\Middleware\EnsureStateFestPermission();

        $request = \Illuminate\Http\Request::create('/admin/state/fest/x', 'POST');
        $request->setUserResolver(fn () => $operator);

        $passed = new \Symfony\Component\HttpFoundation\Response('ok');

        $this->assertSame(
            $passed,
            $middleware->handle($request, fn () => $passed, 'marks'),
            'A mark operator may enter marks.',
        );

        try {
            $middleware->handle($request, fn () => $passed, 'publish');
            $this->fail('A mark operator must not be allowed to publish.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            // The message names the capability rather than just refusing, because these roles exist
            // precisely so an operator can be told what they are not trusted with.
            $this->assertStringContainsString('Publish results publicly', $e->getMessage());
        }
    }

    public function test_a_user_with_no_state_role_cannot_reach_the_workspace(): void
    {
        $stranger = PlatformUser::create([
            'name' => 'Stranger', 'email' => 'stranger@example.test', 'username' => 'stranger',
            'password' => 'password', 'email_verified_at' => now(),
        ]);

        $this->workspace($stranger)->assertForbidden();
    }

    public function test_another_states_event_is_refused(): void
    {
        $other = PlatformState::create(['code' => 'TN', 'name' => 'Tamil Nadu', 'is_active' => true]);

        $this->workspace($this->user('state_admin', $other->id))->assertForbidden();
    }

    public function test_the_overview_counts_sahodayas_by_canonical_identity_not_by_source_key(): void
    {
        $managed = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Malappuram Sahodaya',
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);
        $promoted = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Kasaragod Sahodaya',
            'tenant_id' => (string) Str::uuid(), 'external_sahodaya_id' => (string) Str::uuid(),
            'origin' => StateSahodaya::ORIGIN_EXTERNAL,
        ]);

        // The promoted Sahodaya has registrations from both sides of its promotion. They must count
        // as one Sahodaya, and it must still be reported as having arrived from outside.
        $this->registration($managed->id, 'St Joseph HSS');
        $this->registration($promoted->id, 'Govt HSS');
        $this->registration($promoted->id, 'Model HSS');

        $metrics = $this->workspace($this->user('state_admin'))->viewData('page')['props']['metrics'];

        $this->assertSame(2, $metrics['participation']['sahodayas']);
        $this->assertSame(1, $metrics['participation']['managed']);
        $this->assertSame(1, $metrics['participation']['from_outside']);
        $this->assertSame(3, $metrics['participation']['schools']);
        $this->assertSame(3, $metrics['participation']['registrations']);
    }

    private function registration(string $sahodayaId, string $schoolName): void
    {
        StateFestRegistration::create([
            'state_event_id' => $this->event->id,
            'sahodaya_id'    => $sahodayaId,
            'sahodaya_name'  => 'Snapshot Name',
            'school_id'      => Str::slug($schoolName),
            'school_name'    => $schoolName,
            'item_code'      => 'LM01',
            'status'         => 'approved',
        ]);
    }

    public function test_the_overview_reports_intake_and_scrutiny_progress(): void
    {
        $intake = StateQualifierIntake::create([
            'state_program_id' => $this->event->state_program_id, 'state_id' => $this->state->id,
            'source_tenant_id' => 'tenant-a', 'source_event_id' => 1,
            'idempotency_key' => 'k1', 'status' => 'received', 'payload' => [],
        ]);
        foreach (['pending', 'pending', 'approved'] as $i => $status) {
            StateQualifierEntry::create([
                'intake_id' => $intake->id, 'school_id' => 's'.$i,
                'student_name' => 'P'.$i, 'status' => $status,
            ]);
        }

        $metrics = $this->workspace($this->user('state_admin'))->viewData('page')['props']['metrics'];

        $this->assertSame(1, $metrics['intake']['submissions']);
        $this->assertSame(1, $metrics['intake']['awaiting_scrutiny']);
        $this->assertSame(3, $metrics['intake']['entries']);
        $this->assertSame(2, $metrics['intake']['entries_pending']);
        $this->assertSame(1, $metrics['intake']['entries_approved']);
    }
}
