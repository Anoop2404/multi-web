<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemSchedule;
use App\Models\State\StateSahodaya;
use App\Models\State\StateVenue;
use App\Services\State\Fest\StateChestNumberService;
use App\Services\State\Fest\StateScheduleService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 5 of the State Kalotsav module — schedule, clashes and chest numbers.
 */
class StateScheduleTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private FestStateProgram $program;

    private StateFestEvent $event;

    private StateSahodaya $sahodaya;

    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Artisan::call('state:migrate');

        $this->state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
        $this->program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'state_id' => $this->state->id,
            'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published',
        ]);
        $this->event = StateFestEvent::create([
            'state_program_id' => $this->program->id, 'state_id' => $this->state->id,
            'name' => 'State Finals', 'slug' => 'finals', 'status' => 'active',
        ]);
        $this->sahodaya = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Malappuram Sahodaya',
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);
    }

    private function item(string $code, array $attrs = []): FestStateProgramItem
    {
        return FestStateProgramItem::create(array_merge([
            'state_program_id' => $this->program->id, 'title' => "Item {$code}", 'item_code' => $code,
            'qualify_count' => 2, 'duration_minutes' => 60,
        ], $attrs));
    }

    private function schedule(FestStateProgramItem $item, string $date, string $start, ?string $venueId = null, ?int $duration = 60): StateItemSchedule
    {
        return app(StateScheduleService::class)->save($this->event, $item, [
            'scheduled_on' => $date, 'starts_at' => $start, 'venue_id' => $venueId, 'duration_minutes' => $duration,
        ]);
    }

    private function registration(FestStateProgramItem $item, array $names, array $attrs = []): StateFestRegistration
    {
        $registration = StateFestRegistration::create(array_merge([
            'state_event_id' => $this->event->id, 'sahodaya_id' => $this->sahodaya->id,
            'sahodaya_name' => 'Malappuram Sahodaya', 'school_id' => 'sjh', 'school_name' => 'St Joseph HSS',
            'item_id' => $item->id, 'item_code' => $item->item_code, 'status' => 'approved',
        ], $attrs));

        foreach ($names as $name) {
            $isStandby = str_starts_with($name, '*');
            StateFestParticipant::create([
                'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
                'student_name' => ltrim($name, '*'), 'class_name' => 'Class 10', 'is_standby' => $isStandby,
            ]);
        }

        return $registration;
    }

    public function test_day_counts_deduplicate_students_per_day_and_overall_with_school_names(): void
    {
        $one = $this->item('A');
        $two = $this->item('B');
        $three = $this->item('C');
        $this->schedule($one, '2026-10-20', '09:00');
        $this->schedule($two, '2026-10-20', '11:00');
        $this->schedule($three, '2026-10-21', '09:00');
        foreach ([$one, $two, $three] as $item) {
            $registration = $this->registration($item, ['Same pupil', '*Standby']);
            $registration->participants()->where('student_name', 'Same pupil')->update(['roll_number' => '123']);
        }
        $this->registration($one, ['Another pupil'])->participants()->update(['roll_number' => '456']);
        $this->registration($one, ['Rejected'], ['status' => 'rejected']);
        $this->registration($this->item('D'), ['Unscheduled'])->participants()->update(['roll_number' => '789']);
        $summary = app(\App\Services\Events\FestDayParticipationService::class)->state($this->event);
        $this->assertSame([2, 1], array_column($summary['days'], 'unique_participants'));
        $this->assertSame(2, $summary['overall']['unique_participants']);
        $this->assertSame(4, $summary['overall']['participant_entries']);
        $this->assertSame(1, $summary['unscheduled']['unique_participants']);
        $this->assertSame(3, $summary['event_total']['unique_participants']);
        $school = $summary['overall']['schools'][0];
        $this->assertSame('St Joseph HSS', $school['name']);
        $this->assertSame(2, $school['count']);
        $this->assertSame(['Another pupil', 'Same pupil'], array_column($school['students'], 'name'));
    }

    public function test_day_counts_flag_records_without_identity_instead_of_merging_names(): void
    {
        $item = $this->item('A');
        $this->schedule($item, '2026-10-20', '09:00');
        $this->registration($item, ['Same name', 'Same name']);
        $summary = app(\App\Services\Events\FestDayParticipationService::class)->state($this->event);
        $this->assertSame(2, $summary['overall']['unique_participants']);
        $this->assertSame(2, $summary['overall']['unidentified_participants']);
    }

    public function test_legacy_roll_identity_is_reconciled_only_when_unambiguous(): void
    {
        $one = $this->item('A');
        $two = $this->item('B');
        $this->schedule($one, '2026-10-20', '09:00');
        $this->schedule($two, '2026-10-20', '11:00');
        $first = $this->registration($one, ['Pupil']);
        $first->participants()->firstOrFail()->update(['roll_number' => '123', 'meta' => ['source_student_id' => '42']]);
        $this->registration($two, ['Pupil'])->participants()->update(['roll_number' => '123']);
        $service = app(\App\Services\Events\FestDayParticipationService::class);
        $this->assertSame(1, $service->state($this->event)['overall']['unique_participants']);
        $this->registration($two, ['Another pupil'])->participants()->firstOrFail()->update(['roll_number' => '123', 'meta' => ['source_student_id' => '43']]);
        $summary = $service->state($this->event);
        $this->assertSame(3, $summary['overall']['unique_participants']);
        $this->assertSame(1, $summary['overall']['unidentified_participants']);
    }

    public function test_legacy_records_without_school_ids_keep_distinct_school_name_groups(): void
    {
        $item = $this->item('A');
        $this->schedule($item, '2026-10-20', '09:00');
        foreach (['Alpha School', 'Beta School'] as $school) {
            $registration = $this->registration($item, ['Same name'], ['school_id' => '', 'school_name' => $school]);
            $registration->participants()->firstOrFail()->update(['meta' => ['source_student_id' => '42']]);
        }
        $summary = app(\App\Services\Events\FestDayParticipationService::class)->state($this->event);
        $this->assertSame(2, $summary['overall']['school_count']);
        $this->assertSame(2, $summary['overall']['unidentified_participants']);
        $this->assertSame(['Alpha School', 'Beta School'], array_column($summary['overall']['schools'], 'name'));
    }

    public function test_schedule_reports_require_schedule_permission(): void
    {
        $url = "http://superadmin.test/admin/state/fest/{$this->event->id}/schedule/day-participants";
        $this->actingAs($this->user(), 'platform')->get($url.'?format=xlsx')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($this->user('state_mark_operator'), 'platform')->get($url.'?format=xlsx')->assertForbidden();
    }

    private function user(string $role = 'state_admin'): PlatformUser
    {
        if (isset($this->users[$role])) {
            return $this->users[$role];
        }

        $user = PlatformUser::create([
            'name' => 'Registrar', 'email' => $role.'@example.test', 'username' => $role,
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $this->users[$role] = $user;
    }

    // ── Clashes ────────────────────────────────────────────────────────────────────────────

    public function test_a_participant_entered_for_two_overlapping_items_is_a_clash(): void
    {
        $a = $this->item('LM01');
        $b = $this->item('FD01');
        $this->schedule($a, '2026-01-20', '10:00');
        $this->schedule($b, '2026-01-20', '10:30');

        $this->registration($a, ['Athira Menon']);
        $this->registration($b, ['Athira Menon']);

        $clashes = app(StateScheduleService::class)->clashes($this->event);

        $this->assertCount(1, $clashes['participant']);
        $this->assertSame('Athira Menon', $clashes['participant'][0]['name']);
        // Both names travel with the clash so the office knows who to call.
        $this->assertSame('Malappuram Sahodaya', $clashes['participant'][0]['sahodaya']);
        $this->assertSame('St Joseph HSS', $clashes['participant'][0]['school']);
    }

    public function test_items_that_merely_touch_are_not_a_clash(): void
    {
        // Finishing at 11:00 and starting at 11:00 is a schedule, not a conflict.
        $a = $this->item('LM01');
        $b = $this->item('FD01');
        $this->schedule($a, '2026-01-20', '10:00', null, 60);
        $this->schedule($b, '2026-01-20', '11:00', null, 60);

        $this->registration($a, ['Athira Menon']);
        $this->registration($b, ['Athira Menon']);

        $this->assertSame([], app(StateScheduleService::class)->clashes($this->event)['participant']);
    }

    public function test_the_same_name_on_different_days_is_not_a_clash(): void
    {
        $a = $this->item('LM01');
        $b = $this->item('FD01');
        $this->schedule($a, '2026-01-20', '10:00');
        $this->schedule($b, '2026-01-21', '10:00');

        $this->registration($a, ['Athira Menon']);
        $this->registration($b, ['Athira Menon']);

        $this->assertSame([], app(StateScheduleService::class)->clashes($this->event)['participant']);
    }

    public function test_a_standby_does_not_clash_because_they_are_not_competing(): void
    {
        $a = $this->item('LM01');
        $b = $this->item('GD01', ['participant_type' => 'group']);
        $this->schedule($a, '2026-01-20', '10:00');
        $this->schedule($b, '2026-01-20', '10:15');

        $this->registration($a, ['Athira Menon']);
        $this->registration($b, ['*Athira Menon', 'Other One', 'Other Two']);

        $this->assertSame([], app(StateScheduleService::class)->clashes($this->event)['participant']);
    }

    public function test_a_team_clash_is_distinguished_from_an_individual_one(): void
    {
        // Moving one child is easy; moving a team of six is a different conversation.
        $a = $this->item('LM01');
        $b = $this->item('GD01', ['participant_type' => 'group']);
        $this->schedule($a, '2026-01-20', '10:00');
        $this->schedule($b, '2026-01-20', '10:15');

        $this->registration($a, ['Athira Menon']);
        $this->registration($b, ['Athira Menon', 'Second Member']);

        $clash = app(StateScheduleService::class)->clashes($this->event)['participant'][0];
        $this->assertSame('team', $clash['kind']);
    }

    public function test_two_items_on_one_stage_at_once_is_a_clash(): void
    {
        $stage = StateVenue::create([
            'id' => (string) Str::uuid(), 'state_event_id' => $this->event->id, 'name' => 'Main Stage', 'kind' => 'stage',
        ]);
        $this->schedule($this->item('LM01'), '2026-01-20', '10:00', $stage->id);
        $this->schedule($this->item('FD01'), '2026-01-20', '10:30', $stage->id);

        $venueClashes = app(StateScheduleService::class)->clashes($this->event)['venue'];

        $this->assertCount(1, $venueClashes);
        $this->assertSame('Main Stage', $venueClashes[0]['venue']);
    }

    public function test_unscheduled_items_cannot_clash(): void
    {
        $a = $this->item('LM01');
        $b = $this->item('FD01');
        $this->registration($a, ['Athira Menon']);
        $this->registration($b, ['Athira Menon']);

        $clashes = app(StateScheduleService::class)->clashes($this->event);

        $this->assertSame([], $clashes['participant']);
        $this->assertSame([], $clashes['venue']);
    }

    // ── Chest numbers ──────────────────────────────────────────────────────────────────────

    public function test_numbers_are_assigned_in_a_block_per_sahodaya(): void
    {
        $other = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Kasaragod Sahodaya',
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);

        $item = $this->item('LM01');
        $this->registration($item, ['A One', 'A Two']);
        $this->registration($item, ['B One'], ['sahodaya_id' => $other->id, 'sahodaya_name' => 'Kasaragod Sahodaya']);

        $result = app(StateChestNumberService::class)->assignMissing($this->event, ['start' => 100, 'block_size' => 50]);

        $this->assertSame(3, $result['assigned']);
        // Blocks are handed out in Sahodaya name order, so Kasaragod takes the first one. A
        // contingent's numbers stay contiguous, so one sheet covers it.
        $this->assertSame(['from' => 100, 'to' => 100], $result['blocks']['Kasaragod Sahodaya']);
        $this->assertSame(['from' => 150, 'to' => 151], $result['blocks']['Malappuram Sahodaya']);
    }

    public function test_an_existing_number_is_never_reissued(): void
    {
        // Renumbering after a sheet is printed is the failure this guards against.
        $item = $this->item('LM01');
        $registration = $this->registration($item, ['Already Numbered', 'Needs One']);
        $first = $registration->participants()->first();
        $first->forceFill(['chest_number' => '777'])->save();

        $result = app(StateChestNumberService::class)->assignMissing($this->event, ['start' => 1]);

        $this->assertSame(1, $result['assigned']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame('777', $first->fresh()->chest_number);
    }

    public function test_assigning_skips_numbers_already_taken(): void
    {
        $item = $this->item('LM01');
        $registration = $this->registration($item, ['Has One', 'Needs One']);
        $registration->participants()->first()->forceFill(['chest_number' => '1'])->save();

        app(StateChestNumberService::class)->assignMissing($this->event, ['start' => 1]);

        $numbers = $registration->participants()->pluck('chest_number')->sort()->values()->all();
        $this->assertSame(['1', '2'], $numbers);
    }

    public function test_standbys_are_not_numbered(): void
    {
        // They are not on the stage; they inherit a number only when substituted in.
        $item = $this->item('GD01', ['participant_type' => 'group']);
        $this->registration($item, ['Competing', '*Standby']);

        $result = app(StateChestNumberService::class)->assignMissing($this->event);

        $this->assertSame(1, $result['assigned']);
    }

    public function test_a_manual_number_that_duplicates_another_is_refused(): void
    {
        $item = $this->item('LM01');
        $registration = $this->registration($item, ['One', 'Two']);
        [$a, $b] = $registration->participants()->orderBy('id')->get()->all();
        $a->forceFill(['chest_number' => '55'])->save();

        try {
            app(StateChestNumberService::class)->setNumber($this->event, $b->id, '55');
            $this->fail('A duplicate chest number must be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already belongs to One', collect($e->errors())->flatten()->first());
        }

        $this->assertNull($b->fresh()->chest_number);
    }

    public function test_the_register_puts_unnumbered_participants_last(): void
    {
        // The register is used to find gaps, so they belong where they are obvious.
        $item = $this->item('LM01');
        $registration = $this->registration($item, ['Numbered', 'Unnumbered']);
        $registration->participants()->first()->forceFill(['chest_number' => '10'])->save();

        $register = app(StateChestNumberService::class)->register($this->event);

        $this->assertSame('10', $register->first()['chest_number']);
        $this->assertNull($register->last()['chest_number']);
    }

    // ── Screens ────────────────────────────────────────────────────────────────────────────

    public function test_the_phase_five_screens_render_and_are_gated(): void
    {
        $item = $this->item('LM01');
        $this->schedule($item, '2026-01-20', '10:00');
        $this->registration($item, ['Athira']);

        $url = "http://superadmin.test/admin/state/fest/{$this->event->id}";
        $admin = $this->user();

        foreach (['schedule', 'clashes', 'green-room', 'chest-numbers'] as $path) {
            $this->actingAs($admin, 'platform')->get("{$url}/{$path}")->assertOk();
        }

        // Scheduling is its own capability; a mark operator holds none of it.
        $this->actingAs($this->user('state_mark_operator'), 'platform')->get("{$url}/schedule")->assertForbidden();
    }

    public function test_the_green_room_lists_entries_in_performance_order(): void
    {
        $item = $this->item('LM01');
        $this->schedule($item, '2026-01-20', '10:00');
        $r1 = $this->registration($item, ['Second On']);
        $r2 = $this->registration($item, ['First On']);
        $r1->participants()->first()->forceFill(['chest_number' => '20'])->save();
        $r2->participants()->first()->forceFill(['chest_number' => '10'])->save();

        $slot = app(StateScheduleService::class)->greenRoom($this->event, '2026-01-20')->first();

        $this->assertSame('10', $slot['entries'][0]['chest_number']);
        $this->assertSame('First On', $slot['entries'][0]['participants']);
        $this->assertSame('Malappuram Sahodaya', $slot['entries'][0]['sahodaya']);
        $this->assertSame('St Joseph HSS', $slot['entries'][0]['school']);
    }

    public function test_the_schedule_reports_are_now_available(): void
    {
        $item = $this->item('LM01');
        $this->schedule($item, '2026-01-20', '10:00');

        foreach (['item-schedule', 'schedule-clashes'] as $report) {
            $this->assertTrue(
                \App\Support\StateFestReportCatalog::isAvailable($report),
                "{$report} should be available once scheduling exists.",
            );

            $this->actingAs($this->user(), 'platform')
                ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/reports/{$report}")
                ->assertOk();
        }
    }
}
