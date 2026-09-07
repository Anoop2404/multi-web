<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestEventItem;
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
 * A school admin submits a wildcard appeal (no existing FestParticipant to
 * reference — that's the whole point) by picking a student and an item
 * directly, instead of the normal participant_id dispute flow.
 */
class FestEventPortalWildcardAppealTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Wildcard Submit Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'WS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Wildcard Submit School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Submit Kalotsav', 'event_type' => 'kalolsavam', 'appeals_open' => true]);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1', 'participant_type' => 'individual', 'is_enabled' => true]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10', 'display_order' => 1, 'is_active' => true]);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'admission_number' => 'ADM1', 'reg_no' => 'REG1', 'name' => 'Wildcard Submit Student', 'status' => 'active',
        ]);

        return compact('sahodaya', 'school', 'schoolAdmin', 'event', 'item', 'student');
    }

    public function test_school_admin_can_submit_a_sahodaya_wildcard_appeal_for_a_student_without_a_participant(): void
    {
        ['school' => $school, 'schoolAdmin' => $schoolAdmin, 'event' => $event, 'item' => $item, 'student' => $student] = $this->fixture();

        $this->actingAs($schoolAdmin)->post(route('school.fest.appeals.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), [
            'appeal_type' => 'sahodaya_wildcard',
            'student_id'  => $student->id,
            'item_id'     => $item->id,
            'reason'      => 'Not selected during school-level trials',
        ])->assertRedirect();

        $this->assertDatabaseHas('fest_appeals', [
            'event_id' => $event->id, 'appeal_type' => 'sahodaya_wildcard',
            'student_id' => $student->id, 'item_id' => $item->id, 'status' => 'pending',
            'participant_id' => null,
        ]);
    }

    public function test_a_second_wildcard_appeal_for_the_same_student_and_item_is_rejected_while_one_is_active(): void
    {
        ['school' => $school, 'schoolAdmin' => $schoolAdmin, 'event' => $event, 'item' => $item, 'student' => $student] = $this->fixture();

        $submit = fn () => $this->actingAs($schoolAdmin)->post(route('school.fest.appeals.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), [
            'appeal_type' => 'sahodaya_wildcard',
            'student_id'  => $student->id,
            'item_id'     => $item->id,
            'reason'      => 'Not selected',
        ]);

        $submit()->assertRedirect();
        $submit()->assertStatus(422);

        $this->assertSame(1, FestAppeal::where('event_id', $event->id)->count());
    }

    public function test_a_school_admin_cannot_submit_a_wildcard_appeal_for_another_schools_student(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin, 'event' => $event, 'item' => $item] = $this->fixture();

        $otherSchool = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Other School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);
        $otherClass = SchoolClass::create(['tenant_id' => $otherSchool->id, 'name' => '10', 'display_order' => 1, 'is_active' => true]);
        $otherStudent = Student::create([
            'tenant_id' => $otherSchool->id, 'school_class_id' => $otherClass->id,
            'admission_number' => 'ADM2', 'reg_no' => 'REG2', 'name' => 'Other Student', 'status' => 'active',
        ]);

        $this->actingAs($schoolAdmin)->post(route('school.fest.appeals.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), [
            'appeal_type' => 'sahodaya_wildcard',
            'student_id'  => $otherStudent->id,
            'item_id'     => $item->id,
            'reason'      => 'Not selected',
        ])->assertForbidden();
    }
}
