<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
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
 * The Mark Entry page's "Reg No." column read participant.student.fest_registration_id
 * and participant.event_reg_id (MarkEntry.vue's participantRegNo()) — neither field has
 * ever existed on Student or FestParticipant, so it silently fell back to showing the
 * raw internal participant.id for every row instead of a real registration number.
 * Confirms the "Fest ID" column now reads the field the rest of the platform (Chest
 * Number report, ID cards, certificates, etc.) already calls fest_id: level_registration_number.
 */
class FestMarkEntryFestIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_entry_page_includes_the_participants_fest_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Mark Entry Fest ID Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'MEF', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Mark Entry Fest ID School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Mark Entry Fest ID Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Recitation', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '8']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Fest ID Student', 'reg_no' => 'STU/1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create([
            'registration_id' => $registration->id, 'student_id' => $student->id,
            'participant_type' => 'student', 'event_id' => $event->id, 'participant_role' => 'performer',
            'level_registration_number' => 'MEF-0001',
        ]);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.marks.index', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'item_id' => $item->id,
        ]));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $participant = $props['registrations'][0]['participants'][0];
        $this->assertSame('MEF-0001', $participant['level_registration_number']);
    }
}
