<?php

namespace Tests\Feature\State;

use App\Models\ExternalSahodaya;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateSahodaya;
use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 11 of the State Kalotsav module — the cutover.
 *
 * Three things have to hold before the old stack can be retired: the directory can be seeded from the
 * platform's own records rather than only from submissions, the module's ranking agrees with what is
 * already stored, and old links keep working once the switch is flipped.
 */
class StateModuleCutoverTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private StateFestEvent $event;

    private FestStateProgramItem $item;

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
            'name' => 'State Finals', 'slug' => 'finals', 'status' => 'active',
        ]);
        $this->item = FestStateProgramItem::create([
            'state_program_id' => $program->id, 'title' => 'Light Music', 'item_code' => 'LM01', 'qualify_count' => 3,
        ]);
    }

    private ?PlatformUser $admin = null;

    private function admin(): PlatformUser
    {
        return $this->admin ??= tap(PlatformUser::create([
            'name' => 'Registrar', 'email' => 'registrar@example.test', 'username' => 'registrar',
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]), fn ($u) => $u->assignRole('state_admin'));
    }

    private function competitor(string $name, float $score, ?int $position = null): StateFestParticipant
    {
        $sahodaya = StateSahodaya::first() ?? StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Malappuram Sahodaya',
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);

        $registration = StateFestRegistration::create([
            'state_event_id' => $this->event->id, 'sahodaya_id' => $sahodaya->id,
            'sahodaya_name' => $sahodaya->name, 'school_id' => 'sj', 'school_name' => 'St Joseph HSS',
            'item_id' => $this->item->id, 'item_code' => 'LM01', 'status' => 'approved',
        ]);

        $participant = StateFestParticipant::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'student_name' => $name, 'class_name' => 'Class 10', 'chest_number' => (string) random_int(100, 999),
        ]);

        StateFestMark::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'participant_id' => $participant->id, 'score' => $score, 'grade' => 'A',
            'position' => $position, 'status' => 'aggregated',
        ]);

        return $participant;
    }

    // ── Directory backfill ─────────────────────────────────────────────────────────────────

    public function test_all_seeds_a_directory_row_for_every_known_sahodaya(): void
    {
        ExternalSahodaya::create([
            'state_program_id' => $this->event->state_program_id, 'state_id' => $this->state->id,
            'name' => 'Thrissur Sahodaya', 'access_code' => 'THR123', 'status' => 'active',
        ]);
        Tenant::create([
            'id' => (string) Str::uuid(), 'name' => 'Malappuram Sahodaya', 'type' => 'sahodaya',
            'state_id' => $this->state->id,
        ]);

        Artisan::call('state:backfill-sahodaya-directory', ['--all' => true]);

        // Both are in the directory before anyone has submitted anything — which is the point: the
        // Sahodaya filter, the slot matrix and catering all need the full list up front.
        $this->assertSame(2, StateSahodaya::count());
        $this->assertNotNull(StateSahodaya::where('name', 'Thrissur Sahodaya')->first());
        $this->assertNotNull(StateSahodaya::where('name', 'Malappuram Sahodaya')->first());
    }

    public function test_a_promoted_sahodaya_gets_one_row_carrying_both_identities(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(), 'name' => 'Thrissur Sahodaya', 'type' => 'sahodaya',
            'state_id' => $this->state->id,
        ]);
        ExternalSahodaya::create([
            'state_program_id' => $this->event->state_program_id, 'state_id' => $this->state->id,
            'name' => 'Thrissur Sahodaya', 'access_code' => 'THR123', 'status' => 'active',
            'tenant_id' => $tenant->id,
        ]);

        Artisan::call('state:backfill-sahodaya-directory', ['--all' => true]);

        // Not two rows: the same Sahodaya promoted onto the platform is one identity.
        $this->assertSame(1, StateSahodaya::count());
        $row = StateSahodaya::first();
        $this->assertSame($tenant->id, $row->tenant_id);
        $this->assertNotNull($row->external_sahodaya_id);
    }

    public function test_the_backfill_is_safe_to_run_twice(): void
    {
        ExternalSahodaya::create([
            'state_program_id' => $this->event->state_program_id, 'state_id' => $this->state->id,
            'name' => 'Thrissur Sahodaya', 'access_code' => 'THR123', 'status' => 'active',
        ]);

        Artisan::call('state:backfill-sahodaya-directory', ['--all' => true]);
        Artisan::call('state:backfill-sahodaya-directory', ['--all' => true]);

        $this->assertSame(1, StateSahodaya::count());
    }

    // ── Verification ───────────────────────────────────────────────────────────────────────

    public function test_verification_passes_when_stored_rankings_match_the_module(): void
    {
        $this->competitor('First', 95, 1);
        $this->competitor('Second', 90, 2);
        $this->competitor('Third', 85, 3);

        $exit = Artisan::call('state:verify-module', ['--event' => $this->event->id]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('matches what the module computes', Artisan::output());
    }

    public function test_verification_fails_when_a_stored_ranking_disagrees_with_the_module(): void
    {
        // Stored back to front: the higher score is recorded second. A set comparison would call this
        // identical, which is exactly the bug this check exists to catch.
        $this->competitor('Higher score', 95, 2);
        $this->competitor('Lower score', 90, 1);

        $exit = Artisan::call('state:verify-module', ['--event' => $this->event->id]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('ranked differently', Artisan::output());
    }

    public function test_verification_names_registrations_with_no_canonical_sahodaya(): void
    {
        $registration = StateFestRegistration::create([
            'state_event_id' => $this->event->id, 'sahodaya_id' => null,
            'school_id' => 'sj', 'school_name' => 'St Joseph HSS',
            'item_id' => $this->item->id, 'item_code' => 'LM01', 'status' => 'approved',
        ]);
        StateFestParticipant::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'student_name' => 'Orphan', 'class_name' => 'Class 10', 'chest_number' => '401',
        ]);

        Artisan::call('state:verify-module', ['--event' => $this->event->id]);
        $output = Artisan::output();

        $this->assertStringContainsString('no canonical Sahodaya', $output);
        // The fix is named, not just the problem.
        $this->assertStringContainsString('state:backfill-sahodaya-directory', $output);
    }

    public function test_verification_writes_nothing(): void
    {
        $this->competitor('First', 95, 1);

        $before = StateFestMark::first()->only(['position', 'score', 'grade']);
        Artisan::call('state:verify-module', ['--event' => $this->event->id]);

        $this->assertSame($before, StateFestMark::first()->only(['position', 'score', 'grade']));
    }

    // ── Route switch ───────────────────────────────────────────────────────────────────────

    public function test_both_stacks_stay_reachable_before_the_switch(): void
    {
        config(['state.module_switched' => false]);

        $this->actingAs($this->admin(), 'platform')
            ->get("http://superadmin.test/admin/state-workspace/fest/{$this->event->id}")
            ->assertOk();
    }

    public function test_after_the_switch_an_old_link_redirects_into_the_module(): void
    {
        config(['state.module_switched' => true]);

        $this->actingAs($this->admin(), 'platform')
            ->get("http://superadmin.test/admin/state-workspace/fest/{$this->event->id}")
            ->assertRedirect("/admin/state/fest/{$this->event->id}");

        $this->actingAs($this->admin(), 'platform')
            ->get("http://superadmin.test/admin/state-workspace/fest/{$this->event->id}/attendance")
            ->assertRedirect("/admin/state/fest/{$this->event->id}/attendance");
    }

    public function test_the_old_event_list_redirects_to_the_latest_event(): void
    {
        config(['state.module_switched' => true]);

        $this->actingAs($this->admin(), 'platform')
            ->get('http://superadmin.test/admin/state-workspace/fest')
            ->assertRedirect("/admin/state/fest/{$this->event->id}");
    }

    public function test_a_screen_the_module_does_not_have_is_left_alone_rather_than_redirected_somewhere_wrong(): void
    {
        config(['state.module_switched' => true]);

        // Qualifiers are not under the fest prefix and have no one-to-one module equivalent, so the
        // old screen keeps working instead of dumping the operator on an overview.
        $this->actingAs($this->admin(), 'platform')
            ->get('http://superadmin.test/admin/state-workspace/qualifiers')
            ->assertOk();
    }

    public function test_a_post_is_never_redirected(): void
    {
        config(['state.module_switched' => true]);

        // A redirected POST loses its body, so a form submitted against the old stack mid-cutover
        // would silently do nothing.
        $this->actingAs($this->admin(), 'platform')
            ->post("http://superadmin.test/admin/state-workspace/fest/{$this->event->id}/assign-chest-numbers")
            // Whatever it redirects back to, it is not the module: the old handler ran.
            ->assertRedirect()
            ->assertDontSee("/admin/state/fest/{$this->event->id}");
    }
}
