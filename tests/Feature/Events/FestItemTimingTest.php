<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestEventPhase;
use App\Models\FestSchedule;
use App\Models\FestStage;
use App\Models\FestVenue;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestItemScheduleService;
use App\Services\Events\FestScheduleConflictService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Per-item timing: an item's total stage time is either a fixed block (e.g. offstage
 * "Pencil Drawing — 1 Hr" regardless of headcount) or duration_minutes charged per
 * registered participant/team (e.g. onstage "Recitation — 5 mts" each, one after another).
 * Feeds FestScheduleConflictService and drives auto-sequencing across items on a stage.
 */
class FestItemTimingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, admin: User, event: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Timing Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'TIM', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Timing Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Timing School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        return compact('sahodaya', 'school', 'admin', 'event');
    }

    private function registerStudents(Tenant $school, FestEvent $event, FestEventItem $item, int $count): void
    {
        $schoolClass = SchoolClass::firstOrCreate(
            ['tenant_id' => $school->id, 'name' => '8'],
            ['display_order' => 1, 'is_active' => true],
        );

        for ($i = 0; $i < $count; $i++) {
            $student = Student::create([
                'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
                'admission_number' => "ADM{$item->id}-{$i}", 'reg_no' => "REG{$item->id}-{$i}",
                'name' => "Student {$item->id}-{$i}", 'status' => 'active',
            ]);
            $registration = FestRegistration::create([
                'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id,
                'status' => 'approved', 'submitted_at' => now(),
            ]);
            FestParticipant::create([
                'registration_id' => $registration->id, 'student_id' => $student->id,
                'participant_type' => 'student', 'participant_role' => 'performer',
            ]);
        }
    }

    public function test_fixed_timing_mode_ignores_participant_count(): void
    {
        ['school' => $school, 'event' => $event] = $this->fixture();

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Pencil Drawing', 'participant_type' => 'individual',
            'is_enabled' => true, 'timing_mode' => 'fixed', 'duration_minutes' => 60, 'calling_buffer_minutes' => 5,
        ]);
        $this->registerStudents($school, $event, $item, 5);

        $this->assertSame(65, $item->fresh()->estimatedDurationMinutes());
    }

    public function test_per_participant_timing_mode_multiplies_by_registered_count(): void
    {
        ['school' => $school, 'event' => $event] = $this->fixture();

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Recitation', 'participant_type' => 'individual',
            'is_enabled' => true, 'timing_mode' => 'per_participant', 'duration_minutes' => 5, 'calling_buffer_minutes' => 2,
        ]);
        $this->registerStudents($school, $event, $item, 3);

        $this->assertSame(17, $item->fresh()->estimatedDurationMinutes());
    }

    public function test_bulk_item_schedule_save_persists_timing_fields(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Elocution', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.items.bulk', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'rows' => [[
                'item_id' => $item->id,
                'timing_mode' => 'per_participant',
                'duration_minutes' => 5,
                'calling_buffer_minutes' => 3,
            ]],
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertSame('per_participant', $item->timing_mode);
        $this->assertSame(5, $item->duration_minutes);
        $this->assertSame(3, $item->calling_buffer_minutes);
    }

    public function test_bulk_item_schedule_save_can_assign_a_plain_venue_without_a_stage(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Pencil Drawing', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);
        $venue = FestVenue::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'name' => 'Classroom Block A', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.items.bulk', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'rows' => [[
                'item_id' => $item->id,
                'venue_id' => $venue->id,
            ]],
        ]);

        $response->assertRedirect();
        $schedule = FestSchedule::where('item_id', $item->id)->whereNull('participant_id')->firstOrFail();
        $this->assertSame($venue->id, $schedule->venue_id);
        $this->assertNull($schedule->stage_id);
    }

    /**
     * A regional phase (e.g. "Sargadhara" split by region) gets its own separate child
     * FestEvent per region (FestPhaseTopologyService::syncLeaf() + FestItemSyncService)
     * rather than one shared item list filtered by region — so this page exposes a plain
     * Phase filter (for phases that stay on this one event, e.g. Prelims/Finals) plus the
     * standard scopedChildEventOptions() switcher for jumping to a region's own page.
     */
    public function test_item_schedule_page_exposes_a_phase_filter_and_defers_regions_to_the_child_event_switcher(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $phase = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Finals', 'sort_order' => 1]);

        FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Recitation', 'participant_type' => 'individual',
            'is_enabled' => true, 'phase_id' => $phase->id,
        ]);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.schedule.items', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertSame('Finals', $props['phases'][0]['name']);
        $this->assertArrayNotHasKey('allowed_regions', $props['phases'][0]);
        $this->assertSame($phase->id, $props['rows'][0]['phase_id']);
        $this->assertArrayHasKey('childEvents', $props);
    }

    public function test_auto_sequence_cascades_start_times_across_items_on_a_stage(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $stage = FestStage::create(['event_id' => $event->id, 'name' => 'Main Stage', 'sort_order' => 1, 'is_active' => true]);

        $itemA = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Recitation', 'participant_type' => 'individual',
            'is_enabled' => true, 'timing_mode' => 'per_participant', 'duration_minutes' => 5,
        ]);
        $this->registerStudents($school, $event, $itemA, 3); // 15 min

        $itemB = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Elocution', 'participant_type' => 'individual',
            'is_enabled' => true, 'timing_mode' => 'per_participant', 'duration_minutes' => 10,
        ]);
        $this->registerStudents($school, $event, $itemB, 2); // 20 min

        $start = Carbon::parse('2027-01-10 09:00:00');

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.items.auto-sequence', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'item_ids' => [$itemA->id, $itemB->id],
            'start_at' => $start->toDateTimeString(),
            'stage_id' => $stage->id,
        ]);

        $response->assertRedirect();

        $scheduleA = FestSchedule::where('item_id', $itemA->id)->whereNull('participant_id')->firstOrFail();
        $scheduleB = FestSchedule::where('item_id', $itemB->id)->whereNull('participant_id')->firstOrFail();

        $this->assertTrue($scheduleA->scheduled_at->equalTo($start));
        $this->assertTrue($scheduleB->scheduled_at->equalTo($start->copy()->addMinutes(15)));
        $this->assertSame($stage->id, $scheduleA->stage_id);
        $this->assertSame($stage->id, $scheduleB->stage_id);
    }

    public function test_conflict_detection_uses_estimated_duration_not_a_flat_fallback(): void
    {
        ['school' => $school, 'event' => $event] = $this->fixture();

        $itemA = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Recitation', 'participant_type' => 'individual',
            'is_enabled' => true, 'timing_mode' => 'per_participant', 'duration_minutes' => 5,
        ]);
        $this->registerStudents($school, $event, $itemA, 4); // 20 min total

        $itemB = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Elocution', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);
        $student = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => SchoolClass::firstOrCreate(['tenant_id' => $school->id, 'name' => '8'], ['display_order' => 1, 'is_active' => true])->id,
            'admission_number' => 'SHARED1', 'reg_no' => 'SHARED1', 'name' => 'Shared Student', 'status' => 'active',
        ]);
        $regA = FestRegistration::create(['event_id' => $event->id, 'item_id' => $itemA->id, 'school_id' => $school->id, 'status' => 'approved', 'submitted_at' => now()]);
        $participantA = FestParticipant::create(['registration_id' => $regA->id, 'student_id' => $student->id, 'participant_type' => 'student', 'participant_role' => 'performer']);
        $regB = FestRegistration::create(['event_id' => $event->id, 'item_id' => $itemB->id, 'school_id' => $school->id, 'status' => 'approved', 'submitted_at' => now()]);
        $participantB = FestParticipant::create(['registration_id' => $regB->id, 'student_id' => $student->id, 'participant_type' => 'student', 'participant_role' => 'performer']);

        // Item A (20 min, per-participant) starts 10:00 → ends 10:20. Item B starts 10:15,
        // inside that window — a real clash that the old flat "duration_minutes ?? 60"
        // fallback would also have caught, but only estimatedDurationMinutes() proves the
        // per-participant total (20 min) was actually used rather than a hardcoded 5.
        FestSchedule::create(['event_id' => $event->id, 'item_id' => $itemA->id, 'participant_id' => $participantA->id, 'scheduled_at' => now()->addDay()->setTime(10, 0)]);
        FestSchedule::create(['event_id' => $event->id, 'item_id' => $itemB->id, 'participant_id' => $participantB->id, 'scheduled_at' => now()->addDay()->setTime(10, 15)]);

        $clashes = (new FestScheduleConflictService($event))->detectAll();

        $this->assertCount(1, $clashes);
    }
}
