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
    private function createWildcard($admin, $sahodaya, $school, $event, $item, $student)
    {
        return $this->actingAs($admin)->postJson(route('sahodaya.events.appeals.store-wildcard', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['school_id' => $school->id, 'appeal_type' => FestAppeal::TYPE_SAHODAYA_WILDCARD,
            'student_id' => $student->id, 'item_id' => $item->id, 'reason' => 'Wildcard request']);
    }

    public function test_item_lookup_filters_category_gender_and_disabled_items(): void
    {
        [$admin, $sahodaya, , $event, $item, $student] = $this->fixture();
        $student->update(['gender' => 'female']);
        $item->update(['class_group' => 'category_3', 'gender' => 'female']);
        foreach ([['category_2', 'female', true], ['category_3', 'male', true], ['category_3', 'female', false]] as [$category, $gender, $enabled]) {
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'class_group' => $category,
                'gender' => $gender, 'participant_type' => 'individual', 'is_enabled' => $enabled]);
        }
        $open = FestEventItem::create(['event_id' => $event->id, 'title' => 'Open group', 'class_group' => 'category_5',
            'gender' => 'mixed', 'participant_type' => 'group', 'is_enabled' => true]);
        $response = $this->actingAs($admin)->getJson(route('sahodaya.events.appeals.wildcard-items', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]).'?student_id='.$student->id.'&appeal_type=sahodaya_wildcard')->assertOk();
        $this->assertEqualsCanonicalizing([$item->id, $open->id], collect($response->json('items'))->pluck('id')->all());
        $response->assertJsonPath('student.gender', 'female');
    }

    public function test_direct_submission_cannot_bypass_category_gender_or_disabled_item_checks(): void
    {
        [$admin, $sahodaya, $school, $event, $item, $student] = $this->fixture();
        $student->update(['gender' => 'female']);
        $item->update(['class_group' => 'category_2', 'gender' => 'female']);
        $this->createWildcard($admin, $sahodaya, $school, $event, $item, $student)->assertUnprocessable()->assertJsonValidationErrors('item_id');
        $item->update(['class_group' => 'category_3', 'gender' => 'male']);
        $this->createWildcard($admin, $sahodaya, $school, $event, $item, $student)->assertUnprocessable();
        $item->update(['gender' => 'female', 'is_enabled' => false]);
        $this->createWildcard($admin, $sahodaya, $school, $event, $item, $student)->assertUnprocessable();
        $this->assertSame(0, FestAppeal::count());
    }

    public function test_a_withdrawn_wildcard_can_be_requested_again_without_erasing_history(): void
    {
        [$admin, $sahodaya, $school, $event, $item, $student] = $this->fixture();
        $this->createWildcard($admin, $sahodaya, $school, $event, $item, $student)->assertRedirect();
        $original = FestAppeal::firstOrFail();
        $this->createWildcard($admin, $sahodaya, $school, $event, $item, $student)->assertUnprocessable();
        $original->grantedRegistration->update(['status' => 'withdrawn']);
        $this->createWildcard($admin, $sahodaya, $school, $event, $item, $student)->assertRedirect();
        $this->assertSame(2, FestAppeal::count());
        $this->assertSame('withdrawn', $original->grantedRegistration->fresh()->status);
        $this->assertSame(1, FestRegistration::where('item_id', $item->id)->active()->count());
    }

    public function test_a_withdrawn_normal_entry_does_not_block_a_wildcard(): void
    {
        [$admin, $sahodaya, $school, $event, $item, $student] = $this->fixture();
        $old = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'withdrawn']);
        \App\Models\FestParticipant::create(['registration_id' => $old->id, 'student_id' => $student->id]);
        $this->createWildcard($admin, $sahodaya, $school, $event, $item, $student)->assertRedirect();
        $this->assertSame('withdrawn', $old->fresh()->status);
        $this->assertSame(1, FestRegistration::where('item_id', $item->id)->active()->count());
    }

    public function test_admin_can_correct_an_existing_wildcard_item_and_preserve_participant_id(): void
    {
        [$admin, $sahodaya, $school, $event, $item, $student] = $this->fixture();
        $this->createWildcard($admin, $sahodaya, $school, $event, $item, $student)->assertRedirect();
        $appeal = FestAppeal::firstOrFail();
        $participantId = $appeal->grantedRegistration->participants()->firstOrFail()->id;
        $item->update(['class_group' => 'category_2']);
        $correct = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'class_group' => 'category_3',
            'participant_type' => 'individual', 'is_enabled' => true]);
        $url = route('sahodaya.events.appeals.correct-item', ['tenantId' => $sahodaya->id, 'event' => $event->id, 'appeal' => $appeal->id]);
        $this->postJson($url, ['item_id' => $correct->id, 'reason' => 'Wrong category was selected'])->assertRedirect();
        $appeal->refresh();
        $this->assertSame($correct->id, $appeal->item_id);
        $this->assertSame($correct->id, $appeal->grantedRegistration->item_id);
        $this->assertSame($participantId, $appeal->grantedRegistration->participants()->firstOrFail()->id);
        $this->assertStringContainsString('Wrong category was selected', $appeal->resolution_note);
        $locked = FestEventItem::create(['event_id' => $event->id, 'title' => 'Published item',
            'participant_type' => 'individual', 'is_enabled' => true, 'results_published_at' => now()]);
        $this->postJson($url, ['item_id' => $locked->id, 'reason' => 'Cannot move now'])->assertUnprocessable();
        $this->assertSame($correct->id, $appeal->fresh()->item_id);
    }

    public function test_correction_cannot_move_an_entry_with_marks_or_a_state_submission(): void
    {
        [$admin, $sahodaya, $school, $event, $item, $student] = $this->fixture();
        $this->createWildcard($admin, $sahodaya, $school, $event, $item, $student)->assertRedirect();
        $appeal = FestAppeal::firstOrFail();
        $participant = $appeal->grantedRegistration->participants()->firstOrFail();
        $other = FestEventItem::create(['event_id' => $event->id, 'title' => 'Other item', 'participant_type' => 'individual', 'is_enabled' => true]);
        \App\Models\FestMark::create(['event_id' => $event->id, 'item_id' => $item->id,
            'registration_id' => $appeal->granted_registration_id, 'participant_id' => $participant->id, 'score' => 90]);
        $url = route('sahodaya.events.appeals.correct-item', ['tenantId' => $sahodaya->id, 'event' => $event->id, 'appeal' => $appeal->id]);
        $this->postJson($url, ['item_id' => $other->id, 'reason' => 'Already scored'])->assertUnprocessable();
        $this->assertSame($item->id, $appeal->fresh()->item_id);
        $appeal->update(['appeal_type' => FestAppeal::TYPE_STATE_WILDCARD, 'granted_state_reference' => 'sent-to-state']);
        $this->postJson($url, ['item_id' => $other->id, 'reason' => 'Already sent'])->assertUnprocessable();
        $this->assertSame($item->id, $appeal->fresh()->item_id);
    }

}
