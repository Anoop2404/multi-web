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
 * Regression test for a real production bug: rejecting a registration left its
 * participant's chest_no populated in the database (fest_participants_event_head_chest_unique
 * still enforces it), while the Chest Numbers admin list filters out rejected registrations
 * — so the number looked free on screen but a fresh assignment attempt 500'd on a raw
 * unique-constraint violation. FestRegistrationService::cancel() already clears chest_no on
 * withdrawal; reject() (single and bulk) never did.
 */
class FestRegistrationRejectFreesChestNumberTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, event: FestEvent, item: FestEventItem, registration: FestRegistration, participant: FestParticipant} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Reject Chest Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RCT', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Reject Chest Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Reject Chest School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $schoolClass = SchoolClass::firstOrCreate(
            ['tenant_id' => $school->id, 'name' => '8'],
            ['display_order' => 1, 'is_active' => true],
        );

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Reject Chest Item', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        // Still 'submitted' — reject() only ever targets a still-pending registration,
        // but its participant already carries a chest_no (e.g. manually set/imported
        // before someone noticed it needed rejecting instead of approving).
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id,
            'status' => 'submitted', 'submitted_at' => now(),
        ]);

        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'admission_number' => 'ADMR', 'reg_no' => 'REGR', 'name' => 'Reject Chest Student', 'status' => 'active',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'student_id' => $student->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
            'chest_no' => 117, 'chest_head_id' => $item->id, 'event_id' => $event->id,
        ]);

        return compact('sahodaya', 'admin', 'event', 'item', 'registration', 'participant');
    }

    public function test_rejecting_a_single_registration_clears_its_participants_chest_number(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'registration' => $registration, 'participant' => $participant] = $this->fixture();

        $response = $this->actingAs($admin)->post(route('sahodaya.events.registrations.reject', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'registration' => $registration->id,
        ]), ['rejection_reason' => 'Duplicate entry']);

        $response->assertSessionHasNoErrors();

        $participant->refresh();
        $this->assertNull($participant->chest_no);

        // The number must be genuinely reusable now, not just nulled in isolation.
        $this->assertDatabaseMissing('fest_participants', [
            'event_id' => $event->id, 'chest_head_id' => $participant->chest_head_id, 'chest_no' => 117,
        ]);
    }

    public function test_bulk_rejecting_clears_the_participants_chest_number(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'registration' => $registration, 'participant' => $participant] = $this->fixture();

        $response = $this->actingAs($admin)->post(route('sahodaya.events.registrations.bulk-reject', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['registration_ids' => [$registration->id], 'rejection_reason' => 'Duplicate entry']);

        $response->assertSessionHasNoErrors();

        $participant->refresh();
        $this->assertNull($participant->chest_no);
    }
}
