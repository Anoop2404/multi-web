<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\PlatformState;
use App\Models\State\StateEventStaff;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemSchedule;
use App\Models\State\StateMealSession;
use App\Models\State\StateSahodaya;
use App\Models\State\StateVenue;
use App\Services\State\Fest\StateHospitalityService;
use App\Support\StateFestReportCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 10 of the State Kalotsav module — catering and duty rosters.
 *
 * Catering at State level is counted by Sahodaya, so the tests are about that unit: a contingent's
 * entitlement for a day, what it was actually handed, and the gap between the two.
 */
class StateHospitalityTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private StateFestEvent $event;

    private FestStateProgramItem $item;

    private StateSahodaya $sahodaya;

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
        $this->sahodaya = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Malappuram Sahodaya',
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);
    }

    private function hospitality(): StateHospitalityService
    {
        return app(StateHospitalityService::class);
    }

    private function competitor(string $name, ?FestStateProgramItem $item = null, string $school = 'St Joseph HSS'): StateFestParticipant
    {
        $item ??= $this->item;

        $registration = StateFestRegistration::create([
            'state_event_id' => $this->event->id, 'sahodaya_id' => $this->sahodaya->id,
            'sahodaya_name' => $this->sahodaya->name, 'school_id' => Str::slug($school), 'school_name' => $school,
            'item_id' => $item->id, 'item_code' => $item->item_code, 'status' => 'approved',
        ]);

        return StateFestParticipant::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'student_name' => $name, 'class_name' => 'Class 10',
        ]);
    }

    private function schedule(FestStateProgramItem $item, string $date, ?StateVenue $venue = null): StateItemSchedule
    {
        return StateItemSchedule::create([
            'state_event_id' => $this->event->id, 'state_id' => $this->state->id,
            'item_id' => $item->id, 'item_code' => $item->item_code,
            'scheduled_on' => $date, 'starts_at' => '10:00:00', 'venue_id' => $venue?->id,
        ]);
    }

    private function sitting(string $date = '2026-01-12', string $session = 'lunch', ?int $capacity = null): StateMealSession
    {
        return $this->hospitality()->saveSession($this->event, [
            'served_on' => $date, 'session' => $session, 'capacity' => $capacity,
        ]);
    }

    private function venue(string $name = 'Town Hall Stage'): StateVenue
    {
        return StateVenue::create([
            'state_event_id' => $this->event->id, 'state_id' => $this->state->id,
            'name' => $name, 'kind' => 'stage',
        ]);
    }

    private function staff(string $name = 'Ramesh', string $role = 'volunteer'): StateEventStaff
    {
        return StateEventStaff::create([
            'state_event_id' => $this->event->id, 'state_id' => $this->state->id,
            'name' => $name, 'role' => $role, 'phone' => '9400000000', 'is_active' => true,
        ]);
    }

    // ── Catering ───────────────────────────────────────────────────────────────────────────

    public function test_entitlement_counts_the_people_competing_that_day_plus_escorts(): void
    {
        $this->competitor('Anil');
        $this->competitor('Bindu');
        $this->schedule($this->item, '2026-01-12');

        $rows = $this->hospitality()->entitlement($this->event, $this->sitting());

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['participants']);
        $this->assertSame(2 + StateHospitalityService::ESCORT_ALLOWANCE, $rows[0]['entitled']);
    }

    public function test_one_person_entered_for_several_items_eats_one_meal(): void
    {
        $second = FestStateProgramItem::create([
            'state_program_id' => $this->event->state_program_id, 'title' => 'Classical Dance',
            'item_code' => 'CD01', 'qualify_count' => 3,
        ]);

        $this->competitor('Anil');
        $this->competitor('Anil', $second);
        $this->schedule($this->item, '2026-01-12');
        $this->schedule($second, '2026-01-12');

        $rows = $this->hospitality()->entitlement($this->event, $this->sitting());

        $this->assertSame(1, $rows[0]['participants']);
    }

    public function test_a_sitting_on_a_date_with_nothing_scheduled_entitles_nobody(): void
    {
        $this->competitor('Anil');
        $this->schedule($this->item, '2026-01-12');

        $rows = $this->hospitality()->entitlement($this->event, $this->sitting('2026-01-15'));

        $this->assertCount(0, $rows);
    }

    public function test_freezing_records_the_entitlement_against_the_sitting(): void
    {
        $this->competitor('Anil');
        $this->schedule($this->item, '2026-01-12');
        $session = $this->sitting();

        $result = $this->hospitality()->freezeEntitlement($this->event, $session);

        $this->assertSame(1, $result['rows']);
        $this->assertSame(1 + StateHospitalityService::ESCORT_ALLOWANCE, $result['meals']);
        $this->assertSame(
            1 + StateHospitalityService::ESCORT_ALLOWANCE,
            $this->hospitality()->entitlement($this->event, $session)[0]['recorded_entitled'],
        );
    }

    public function test_issuing_more_than_the_entitlement_is_recorded_as_a_variance_not_refused(): void
    {
        $this->competitor('Anil');
        $this->schedule($this->item, '2026-01-12');
        $session = $this->sitting();
        $this->hospitality()->freezeEntitlement($this->event, $session);

        $allocation = $this->hospitality()->issue($this->event, $session, [
            'sahodaya_id' => $this->sahodaya->id, 'issued_count' => 10,
        ]);

        // The counter's job is not to refuse hungry people; the State's job is to see the gap.
        $this->assertSame(10, $allocation->issued_count);
        $this->assertSame(10 - (1 + StateHospitalityService::ESCORT_ALLOWANCE), $allocation->variance());
    }

    public function test_issuing_twice_updates_the_same_row(): void
    {
        $this->competitor('Anil');
        $this->schedule($this->item, '2026-01-12');
        $session = $this->sitting();

        $this->hospitality()->issue($this->event, $session, ['sahodaya_id' => $this->sahodaya->id, 'issued_count' => 5]);
        $this->hospitality()->issue($this->event, $session, ['sahodaya_id' => $this->sahodaya->id, 'issued_count' => 8]);

        $summary = $this->hospitality()->cateringSummary($this->event);

        $this->assertSame(8, $summary['totals']['issued']);
        $this->assertSame(1, $summary['sessions'][0]['sahodayas']);
    }

    public function test_a_second_sitting_of_the_same_kind_on_one_date_is_refused(): void
    {
        $this->sitting('2026-01-12', 'lunch');

        $this->expectException(ValidationException::class);
        $this->sitting('2026-01-12', 'lunch');
    }

    public function test_a_sitting_over_its_capacity_is_flagged(): void
    {
        $this->competitor('Anil');
        $this->competitor('Bindu');
        $this->schedule($this->item, '2026-01-12');
        $session = $this->sitting(capacity: 2);
        $this->hospitality()->freezeEntitlement($this->event, $session);

        $this->assertTrue($this->hospitality()->sessions($this->event)[0]['over_capacity']);
    }

    // ── Volunteers and officials ───────────────────────────────────────────────────────────

    public function test_a_duty_names_the_person_the_place_and_the_session(): void
    {
        $staff = $this->staff();
        $venue = $this->venue();

        $this->hospitality()->assignDuty($this->event, [
            'staff_id' => $staff->id, 'duty_on' => '2026-01-12', 'session' => 'morning',
            'venue_id' => $venue->id, 'duty' => 'Stage manager',
        ]);

        $roster = $this->hospitality()->roster($this->event);

        $this->assertCount(1, $roster);
        $this->assertSame('Ramesh', $roster[0]['staff']);
        $this->assertSame('Town Hall Stage', $roster[0]['venue']);
        $this->assertSame('Stage manager', $roster[0]['duty']);
    }

    public function test_the_same_person_cannot_be_in_two_places_in_one_session(): void
    {
        $staff = $this->staff();

        $this->hospitality()->assignDuty($this->event, [
            'staff_id' => $staff->id, 'duty_on' => '2026-01-12', 'session' => 'morning',
            'venue_id' => $this->venue('Stage A')->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->hospitality()->assignDuty($this->event, [
            'staff_id' => $staff->id, 'duty_on' => '2026-01-12', 'session' => 'morning',
            'venue_id' => $this->venue('Stage B')->id,
        ]);
    }

    public function test_a_full_day_duty_blocks_a_session_duty_on_the_same_day(): void
    {
        $staff = $this->staff();

        $this->hospitality()->assignDuty($this->event, [
            'staff_id' => $staff->id, 'duty_on' => '2026-01-12', 'session' => 'full_day',
        ]);

        $this->expectException(ValidationException::class);
        $this->hospitality()->assignDuty($this->event, [
            'staff_id' => $staff->id, 'duty_on' => '2026-01-12', 'session' => 'afternoon',
        ]);
    }

    public function test_a_scheduled_stage_with_nobody_rostered_is_reported(): void
    {
        $venue = $this->venue();
        $this->schedule($this->item, '2026-01-12', $venue);

        $uncovered = $this->hospitality()->uncoveredVenues($this->event);

        $this->assertCount(1, $uncovered);
        $this->assertSame('Town Hall Stage', $uncovered[0]['venue']);
        $this->assertSame('2026-01-12', $uncovered[0]['date']);
    }

    public function test_rostering_somebody_clears_the_uncovered_warning(): void
    {
        $venue = $this->venue();
        $this->schedule($this->item, '2026-01-12', $venue);

        $this->hospitality()->assignDuty($this->event, [
            'staff_id' => $this->staff()->id, 'duty_on' => '2026-01-12',
            'session' => 'morning', 'venue_id' => $venue->id,
        ]);

        $this->assertCount(0, $this->hospitality()->uncoveredVenues($this->event));
    }

    public function test_a_duty_can_be_removed(): void
    {
        $duty = $this->hospitality()->assignDuty($this->event, [
            'staff_id' => $this->staff()->id, 'duty_on' => '2026-01-12', 'session' => 'morning',
        ]);

        $this->hospitality()->removeDuty($this->event, $duty->id);

        $this->assertCount(0, $this->hospitality()->roster($this->event));
    }

    public function test_the_catering_and_roster_reports_are_in_the_catalog(): void
    {
        foreach (['catering-summary', 'duty-roster'] as $id) {
            $definition = StateFestReportCatalog::find($id);

            $this->assertNotNull($definition, "{$id} is missing from the catalog.");
            $this->assertTrue($definition['available']);
        }
    }

    public function test_the_catering_report_totals_its_own_rows(): void
    {
        $this->competitor('Anil');
        $this->schedule($this->item, '2026-01-12');
        $session = $this->sitting();
        $this->hospitality()->freezeEntitlement($this->event, $session);
        $this->hospitality()->issue($this->event, $session, ['sahodaya_id' => $this->sahodaya->id, 'issued_count' => 5]);

        $built = app(\App\Services\State\Reports\StateReportDataService::class)
            ->build('catering-summary', $this->event);

        $this->assertSame('Total', end($built['rows'])[1]);
        $this->assertSame('5', end($built['rows'])[6]);
    }
}
