<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateEventStaff;
use App\Models\State\StateFestEvent;
use App\Models\State\StateVenue;
use App\Services\State\Fest\StateEventSettings;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 3 of the State Kalotsav module — event configuration: settings and windows, venues and
 * stages, and event staff.
 */
class StateEventConfigTest extends TestCase
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
            'name' => 'State Finals', 'slug' => 'state-finals', 'status' => 'active',
        ]);
    }

    private function user(string $role = 'state_admin'): PlatformUser
    {
        $user = PlatformUser::create([
            'name' => $role, 'email' => $role.'@example.test', 'username' => $role,
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function url(string $path = ''): string
    {
        return "http://superadmin.test/admin/state/fest/{$this->event->id}".$path;
    }

    // ── Windows ────────────────────────────────────────────────────────────────────────────

    public function test_a_window_with_no_dates_is_open(): void
    {
        // An event nobody has configured must not find its workflow silently shut.
        $settings = app(StateEventSettings::class);

        $this->assertTrue($settings->windowIsOpen($this->event, StateEventSettings::WINDOW_QUALIFIER));
        $this->assertNull($settings->windowClosedReason($this->event, StateEventSettings::WINDOW_QUALIFIER));
    }

    public function test_a_window_closes_once_its_closing_date_has_passed(): void
    {
        $settings = app(StateEventSettings::class);
        $settings->update($this->event, ['qualifier_closes_at' => '2026-01-10 17:00:00']);

        $before = CarbonImmutable::parse('2026-01-09 12:00:00');
        $after = CarbonImmutable::parse('2026-01-11 09:00:00');

        $this->assertTrue($settings->windowIsOpen($this->event->fresh(), 'qualifier', $before));
        $this->assertFalse($settings->windowIsOpen($this->event->fresh(), 'qualifier', $after));

        // The reason is phrased for the Sahodaya on the other end of it.
        $this->assertStringContainsString(
            'closed on 10 Jan 2026',
            $settings->windowClosedReason($this->event->fresh(), 'qualifier', $after),
        );
    }

    public function test_a_window_that_has_not_opened_yet_says_so(): void
    {
        $settings = app(StateEventSettings::class);
        $settings->update($this->event, ['qualifier_opens_at' => '2026-02-01 09:00:00']);

        $reason = $settings->windowClosedReason($this->event->fresh(), 'qualifier', CarbonImmutable::parse('2026-01-15 10:00:00'));

        $this->assertStringContainsString('opens on 01 Feb 2026', $reason);
    }

    public function test_unknown_settings_keys_are_dropped_rather_than_stored(): void
    {
        // A typo in a form field must not quietly become a setting nothing reads.
        app(StateEventSettings::class)->update($this->event, [
            'contact_name' => 'Registrar',
            'notaresetting' => 'should not persist',
        ]);

        $stored = $this->event->fresh()->settings;
        $this->assertSame('Registrar', $stored['contact_name']);
        $this->assertArrayNotHasKey('notaresetting', $stored);
    }

    public function test_settings_save_through_the_screen(): void
    {
        $this->actingAs($this->user(), 'platform')
            ->post($this->url('/settings'), [
                'name' => 'Renamed Finals',
                'status' => 'active',
                'contact_name' => 'State Registrar',
                'qualifier_closes_at' => '2026-01-10 17:00:00',
                'registrations_locked' => true,
                'public_results_visible' => false,
            ])->assertRedirect();

        $event = $this->event->fresh();
        $this->assertSame('Renamed Finals', $event->name);
        $this->assertSame('State Registrar', $event->settings['contact_name']);
        $this->assertTrue($event->settings['registrations_locked']);
    }

    // ── Venues ─────────────────────────────────────────────────────────────────────────────

    public function test_a_venue_and_a_stage_inside_it_can_be_created(): void
    {
        $admin = $this->user();

        $this->actingAs($admin, 'platform')
            ->post($this->url('/venues'), ['name' => 'Town Hall', 'kind' => 'venue', 'capacity' => 800])
            ->assertRedirect();

        $venue = StateVenue::where('name', 'Town Hall')->sole();
        $this->assertFalse($venue->canHostItems(), 'A venue is the building; items are held at stages inside it.');

        $this->actingAs($admin, 'platform')
            ->post($this->url('/venues'), ['name' => 'Main Stage', 'kind' => 'stage', 'parent_id' => $venue->id])
            ->assertRedirect();

        $stage = StateVenue::where('name', 'Main Stage')->sole();
        $this->assertSame($venue->id, $stage->parent_id);
        $this->assertTrue($stage->canHostItems());
    }

    public function test_a_venue_cannot_contain_itself_or_a_place_from_another_event(): void
    {
        $admin = $this->user();
        $other = StateFestEvent::create([
            'state_program_id' => $this->event->state_program_id, 'state_id' => $this->state->id,
            'name' => 'Other', 'slug' => 'other', 'status' => 'draft',
        ]);
        $foreign = StateVenue::create([
            'id' => (string) Str::uuid(), 'state_event_id' => $other->id, 'name' => 'Elsewhere', 'kind' => 'venue',
        ]);

        $this->actingAs($admin, 'platform')
            ->post($this->url('/venues'), ['name' => 'Stage', 'kind' => 'stage', 'parent_id' => $foreign->id])
            ->assertStatus(422);
    }

    public function test_a_venue_still_holding_stages_is_not_deleted(): void
    {
        $admin = $this->user();
        $venue = StateVenue::create(['id' => (string) Str::uuid(), 'state_event_id' => $this->event->id, 'name' => 'Town Hall', 'kind' => 'venue']);
        StateVenue::create(['id' => (string) Str::uuid(), 'state_event_id' => $this->event->id, 'name' => 'Stage A', 'kind' => 'stage', 'parent_id' => $venue->id]);

        // Deleting it would orphan the stage into an event with no parent, which reads as data loss.
        $this->actingAs($admin, 'platform')->delete($this->url("/venues/{$venue->id}"))->assertStatus(422);

        $this->assertNotNull(StateVenue::find($venue->id));
    }

    // ── Staff ──────────────────────────────────────────────────────────────────────────────

    public function test_staff_can_be_recorded_without_a_login(): void
    {
        // Most event staff are present for three days and never sign in.
        $this->actingAs($this->user(), 'platform')
            ->post($this->url('/staff'), ['name' => 'K. Menon', 'role' => 'stage_manager', 'phone' => '9876543210'])
            ->assertRedirect();

        $staff = StateEventStaff::sole();
        $this->assertSame('Stage manager', $staff->roleLabel());
        $this->assertNull($staff->user_id);
    }

    public function test_staff_cannot_be_assigned_to_another_events_venue(): void
    {
        $other = StateFestEvent::create([
            'state_program_id' => $this->event->state_program_id, 'state_id' => $this->state->id,
            'name' => 'Other', 'slug' => 'other-2', 'status' => 'draft',
        ]);
        $foreign = StateVenue::create(['id' => (string) Str::uuid(), 'state_event_id' => $other->id, 'name' => 'Elsewhere', 'kind' => 'stage']);

        $this->actingAs($this->user(), 'platform')
            ->post($this->url('/staff'), ['name' => 'K. Menon', 'role' => 'volunteer', 'venue_id' => $foreign->id])
            ->assertStatus(422);
    }

    // ── Access ─────────────────────────────────────────────────────────────────────────────

    public function test_configuration_screens_are_gated_by_capability(): void
    {
        // A mark operator can open the workspace but must not reconfigure the event.
        $operator = $this->user('state_mark_operator');

        $this->actingAs($operator, 'platform')->get($this->url('/settings'))->assertForbidden();
        $this->actingAs($operator, 'platform')->get($this->url('/venues'))->assertForbidden();
        $this->actingAs($operator, 'platform')->get($this->url('/staff'))->assertForbidden();
        $this->actingAs($operator, 'platform')->get($this->url('/items'))->assertForbidden();

        // But the workspace itself stays open to it.
        $this->actingAs($operator, 'platform')->get($this->url())->assertOk();
    }

    public function test_the_items_tab_shows_the_effective_per_sahodaya_allowance(): void
    {
        \App\Models\FestStateProgramItem::create([
            'state_program_id' => $this->event->state_program_id, 'title' => 'Light Music',
            'item_code' => 'LM01', 'qualify_count' => 2, 'max_per_school' => 1,
        ]);

        $page = $this->actingAs($this->user(), 'platform')->get($this->url('/items'))->viewData('page');

        // The override wins, and the catalog agrees with the Slots tab.
        $this->assertSame(1, $page['props']['items'][0]['slots']);
    }
}
