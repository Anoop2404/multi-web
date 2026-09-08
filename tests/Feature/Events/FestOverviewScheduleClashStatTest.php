<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The automatic schedule-clash count (FestScheduleConflictService) previously only
 * surfaced inline on the Schedule page and the dedicated Schedule Clashes report — an
 * admin had to navigate into one of those to notice a clash existed at all. Now also
 * shown as a KPI tile on the event's own Overview/Dashboard page.
 */
class FestOverviewScheduleClashStatTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, event: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Overview Clash Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'OC', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Overview Clash Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        return compact('sahodaya', 'admin', 'event');
    }

    public function test_overview_reports_zero_clashes_when_schedule_is_clean(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $response = $this->actingAs($admin)->get(route('sahodaya.events.show', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('page')['props']['stats']['schedule_clashes']);
    }

    public function test_overview_counts_a_real_schedule_clash(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Overview Clash School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $schoolClass = SchoolClass::firstOrCreate(
            ['tenant_id' => $school->id, 'name' => '8'],
            ['display_order' => 1, 'is_active' => true],
        );
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'admission_number' => 'ADM1', 'reg_no' => 'REG1', 'name' => 'Overview Clash Student', 'status' => 'active',
        ]);

        $itemA = FestEventItem::create(['event_id' => $event->id, 'title' => 'Recitation', 'participant_type' => 'individual', 'is_enabled' => true]);
        $itemB = FestEventItem::create(['event_id' => $event->id, 'title' => 'Elocution', 'participant_type' => 'individual', 'is_enabled' => true]);

        $registrationA = FestRegistration::create(['event_id' => $event->id, 'item_id' => $itemA->id, 'school_id' => $school->id, 'status' => 'approved', 'submitted_at' => now()]);
        $participantA = FestParticipant::create(['registration_id' => $registrationA->id, 'student_id' => $student->id, 'participant_type' => 'student', 'participant_role' => 'performer']);

        $registrationB = FestRegistration::create(['event_id' => $event->id, 'item_id' => $itemB->id, 'school_id' => $school->id, 'status' => 'approved', 'submitted_at' => now()]);
        $participantB = FestParticipant::create(['registration_id' => $registrationB->id, 'student_id' => $student->id, 'participant_type' => 'student', 'participant_role' => 'performer']);

        FestSchedule::create(['event_id' => $event->id, 'item_id' => $itemA->id, 'participant_id' => $participantA->id, 'scheduled_at' => now()->addDay()->setTime(10, 0), 'stage' => 'Main Stage']);
        FestSchedule::create(['event_id' => $event->id, 'item_id' => $itemB->id, 'participant_id' => $participantB->id, 'scheduled_at' => now()->addDay()->setTime(10, 15), 'stage' => 'Annex Hall']);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.show', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $this->assertGreaterThan(0, $response->viewData('page')['props']['stats']['schedule_clashes']);
    }
}
