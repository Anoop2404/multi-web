<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestEventItem;
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
 * A Sahodaya admin creating a wildcard entry directly (on a school's behalf) is
 * meant to behave like a school's own wildcard request PLUS its later Sahodaya
 * approval, collapsed into one action — see FestAppealController::storeWildcard()
 * for why (no one else is left to review something the Sahodaya admin themselves
 * originated). FestAppealWildcardResolutionTest covers the existing two-step path
 * (school submits pending, Sahodaya later resolves); this covers the new
 * single-step one.
 */
class FestSahodayaCreatedWildcardTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Wildcard Create Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'WCS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Wildcard Origin School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Wildcard Create Kalotsav', 'event_type' => 'kalolsavam',
            'appeals_open' => true,
        ]);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'WCSS1', 'participant_type' => 'individual', 'is_enabled' => true]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10', 'display_order' => 1, 'is_active' => true]);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'admission_number' => 'WCADM1', 'reg_no' => 'WCREG1', 'name' => 'Wildcard Create Student', 'status' => 'active',
        ]);

        return [$admin, $sahodaya, $school, $event, $item, $student];
    }

    public function test_sahodaya_admin_creates_and_immediately_grants_a_wildcard_slot(): void
    {
        [$admin, $sahodaya, $school, $event, $item, $student] = $this->fixture();

        $response = $this->actingAs($admin)->post(route('sahodaya.events.appeals.store-wildcard', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'school_id'   => $school->id,
            'appeal_type' => FestAppeal::TYPE_SAHODAYA_WILDCARD,
            'student_id'  => $student->id,
            'item_id'     => $item->id,
            'reason'      => 'Missed the normal selection window.',
        ]);

        $response->assertRedirect();

        $appeal = FestAppeal::where('event_id', $event->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('approved', $appeal->status, 'must be granted immediately, never left pending');
        $this->assertSame($admin->id, $appeal->submitted_by_user_id);
        $this->assertSame($admin->id, $appeal->resolved_by_user_id);
        $this->assertNotNull($appeal->granted_registration_id);

        $registration = FestRegistration::find($appeal->granted_registration_id);
        $this->assertNotNull($registration);
        $this->assertSame($student->tenant_id, $registration->origin_school_id);
        $this->assertNotSame($school->id, $registration->school_id, 'granted under the appeal-pool placeholder school, not the real one, matching the existing two-step grant');
    }

    public function test_rejects_a_school_id_that_does_not_belong_to_this_sahodaya(): void
    {
        [$admin, $sahodaya, , $event, $item, $student] = $this->fixture();

        $otherSahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Other Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        $foreignSchool = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $otherSahodaya->id,
            'name' => 'Foreign School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('sahodaya.events.appeals.store-wildcard', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'school_id'   => $foreignSchool->id,
            'appeal_type' => FestAppeal::TYPE_SAHODAYA_WILDCARD,
            'student_id'  => $student->id,
            'item_id'     => $item->id,
            'reason'      => 'Should not be allowed.',
        ])->assertNotFound();

        $this->assertSame(0, FestAppeal::where('event_id', $event->id)->count());
    }

    public function test_rejects_a_student_who_does_not_belong_to_the_selected_school(): void
    {
        [$admin, $sahodaya, $school, $event, $item] = $this->fixture();

        $otherSchool = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Other School In Same Sahodaya', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);
        $otherSchoolClass = SchoolClass::create(['tenant_id' => $otherSchool->id, 'name' => '10', 'display_order' => 1, 'is_active' => true]);
        $mismatchedStudent = Student::create([
            'tenant_id' => $otherSchool->id, 'school_class_id' => $otherSchoolClass->id,
            'admission_number' => 'WCADM2', 'reg_no' => 'WCREG2', 'name' => 'Mismatched Student', 'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('sahodaya.events.appeals.store-wildcard', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'school_id'   => $school->id,
            'appeal_type' => FestAppeal::TYPE_SAHODAYA_WILDCARD,
            'student_id'  => $mismatchedStudent->id,
            'item_id'     => $item->id,
            'reason'      => 'Student belongs to a different school than selected.',
        ])->assertStatus(422);

        $this->assertSame(0, FestAppeal::where('event_id', $event->id)->count(), 'nothing should be created when the school/student mismatch is caught');
    }

    public function test_blocks_creation_when_appeals_are_closed_for_the_event(): void
    {
        [$admin, $sahodaya, $school, $event, $item, $student] = $this->fixture();
        $event->update(['appeals_open' => false]);

        $this->actingAs($admin)->post(route('sahodaya.events.appeals.store-wildcard', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'school_id'   => $school->id,
            'appeal_type' => FestAppeal::TYPE_SAHODAYA_WILDCARD,
            'student_id'  => $student->id,
            'item_id'     => $item->id,
            'reason'      => 'Appeals are closed.',
        ])->assertStatus(422);

        $this->assertSame(0, FestAppeal::where('event_id', $event->id)->count());
    }

    public function test_the_wildcard_students_lookup_endpoint_scopes_to_the_selected_school(): void
    {
        [$admin, $sahodaya, $school, $event, , $student] = $this->fixture();

        $otherSchool = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Another School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);
        $otherSchoolClass = SchoolClass::create(['tenant_id' => $otherSchool->id, 'name' => '10', 'display_order' => 1, 'is_active' => true]);
        Student::create([
            'tenant_id' => $otherSchool->id, 'school_class_id' => $otherSchoolClass->id,
            'admission_number' => 'WCADM3', 'reg_no' => 'WCREG3', 'name' => 'Other School Student', 'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->getJson(route('sahodaya.events.appeals.wildcard-students', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]).'?school_id='.$school->id);

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->all();
        $this->assertSame([$student->name], $names, 'must only return the selected school\'s own students');
    }
}
