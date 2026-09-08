<?php

namespace Tests\Feature\Events;

use App\Models\FestClashRequest;
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
 * Coverage for the manual clash-request/appeal workflow — a school files a
 * FestClashRequest against two of their own schedule slots, and a Sahodaya admin
 * approves or rejects it. Previously had no automated test coverage at all, unlike the
 * automatic time-overlap detection (FestScheduleConflictServiceTest).
 */
class FestClashRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, schoolAdmin: User, sahodayaAdmin: User, event: FestEvent, participant: FestParticipant, scheduleA: FestSchedule, scheduleB: FestSchedule} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Clash Workflow Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CW', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Clash Workflow School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $sahodayaAdmin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Clash Workflow Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $schoolClass = SchoolClass::firstOrCreate(
            ['tenant_id' => $school->id, 'name' => '8'],
            ['display_order' => 1, 'is_active' => true],
        );
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'admission_number' => 'ADM1', 'reg_no' => 'REG1', 'name' => 'Clash Test Student', 'status' => 'active',
        ]);

        $itemA = FestEventItem::create(['event_id' => $event->id, 'title' => 'Recitation', 'participant_type' => 'individual', 'is_enabled' => true]);
        $itemB = FestEventItem::create(['event_id' => $event->id, 'title' => 'Elocution', 'participant_type' => 'individual', 'is_enabled' => true]);

        $registrationA = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $itemA->id, 'school_id' => $school->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registrationA->id, 'student_id' => $student->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        $registrationB = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $itemB->id, 'school_id' => $school->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);
        FestParticipant::create([
            'registration_id' => $registrationB->id, 'student_id' => $student->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        $scheduleA = FestSchedule::create([
            'event_id' => $event->id, 'item_id' => $itemA->id, 'participant_id' => $participant->id,
            'scheduled_at' => now()->addDay()->setTime(10, 0), 'stage' => 'Main Stage',
        ]);
        $scheduleB = FestSchedule::create([
            'event_id' => $event->id, 'item_id' => $itemB->id, 'participant_id' => $participant->id,
            'scheduled_at' => now()->addDay()->setTime(10, 15), 'stage' => 'Annex Hall',
        ]);

        return compact('sahodaya', 'school', 'schoolAdmin', 'sahodayaAdmin', 'event', 'participant', 'scheduleA', 'scheduleB');
    }

    public function test_school_can_file_a_clash_request_referencing_two_of_its_own_schedules(): void
    {
        ['school' => $school, 'schoolAdmin' => $schoolAdmin, 'event' => $event, 'participant' => $participant, 'scheduleA' => $scheduleA, 'scheduleB' => $scheduleB] = $this->fixture();

        $response = $this->actingAs($schoolAdmin)->post(route('school.kalotsav.clash-requests.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), [
            'participant_id' => $participant->id,
            'schedule_id_a' => $scheduleA->id,
            'schedule_id_b' => $scheduleB->id,
            'description' => 'Student is scheduled on two stages 15 minutes apart — cannot attend both.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('fest_clash_requests', [
            'event_id' => $event->id,
            'school_id' => $school->id,
            'participant_id' => $participant->id,
            'schedule_id_a' => $scheduleA->id,
            'schedule_id_b' => $scheduleB->id,
            'status' => 'pending',
        ]);
    }

    public function test_school_cannot_file_a_clash_request_for_a_participant_belonging_to_another_school(): void
    {
        ['school' => $school, 'schoolAdmin' => $schoolAdmin, 'sahodaya' => $sahodaya, 'event' => $event] = $this->fixture();

        $otherSchool = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Other School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $otherClass = SchoolClass::firstOrCreate(
            ['tenant_id' => $otherSchool->id, 'name' => '8'],
            ['display_order' => 1, 'is_active' => true],
        );
        $otherStudent = Student::create([
            'tenant_id' => $otherSchool->id, 'school_class_id' => $otherClass->id,
            'admission_number' => 'ADM2', 'reg_no' => 'REG2', 'name' => 'Other Student', 'status' => 'active',
        ]);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Other Item', 'participant_type' => 'individual', 'is_enabled' => true]);
        $otherRegistration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $otherSchool->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);
        $otherParticipant = FestParticipant::create([
            'registration_id' => $otherRegistration->id, 'student_id' => $otherStudent->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        $response = $this->actingAs($schoolAdmin)->post(route('school.kalotsav.clash-requests.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), [
            'participant_id' => $otherParticipant->id,
            'description' => 'Trying to file for someone else\'s student.',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('fest_clash_requests', ['participant_id' => $otherParticipant->id]);
    }

    public function test_sahodaya_admin_can_approve_a_pending_clash_request(): void
    {
        ['sahodaya' => $sahodaya, 'sahodayaAdmin' => $sahodayaAdmin, 'event' => $event, 'school' => $school, 'participant' => $participant, 'scheduleA' => $scheduleA, 'scheduleB' => $scheduleB] = $this->fixture();

        $clashRequest = FestClashRequest::create([
            'event_id' => $event->id, 'school_id' => $school->id, 'participant_id' => $participant->id,
            'schedule_id_a' => $scheduleA->id, 'schedule_id_b' => $scheduleB->id,
            'description' => 'Overlapping stages.', 'status' => 'pending',
        ]);

        $response = $this->actingAs($sahodayaAdmin)->post(route('sahodaya.events.clash-requests.approve', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'clashRequest' => $clashRequest->id,
        ]), ['resolution_note' => 'Moved Elocution to 10:45am.']);

        $response->assertSessionHas('success');

        $clashRequest->refresh();
        $this->assertSame('approved', $clashRequest->status);
        $this->assertSame('Moved Elocution to 10:45am.', $clashRequest->resolution_note);
        $this->assertNotNull($clashRequest->reviewed_at);
        $this->assertSame($sahodayaAdmin->id, $clashRequest->reviewed_by_user_id);
    }

    public function test_sahodaya_admin_can_reject_a_pending_clash_request(): void
    {
        ['sahodaya' => $sahodaya, 'sahodayaAdmin' => $sahodayaAdmin, 'event' => $event, 'school' => $school, 'participant' => $participant] = $this->fixture();

        $clashRequest = FestClashRequest::create([
            'event_id' => $event->id, 'school_id' => $school->id, 'participant_id' => $participant->id,
            'description' => 'Overlapping stages.', 'status' => 'pending',
        ]);

        $response = $this->actingAs($sahodayaAdmin)->post(route('sahodaya.events.clash-requests.reject', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'clashRequest' => $clashRequest->id,
        ]), ['resolution_note' => 'Schedules are 45 minutes apart, not a real clash.']);

        $response->assertSessionHas('success');
        $this->assertSame('rejected', $clashRequest->fresh()->status);
    }

    public function test_an_already_reviewed_clash_request_cannot_be_reviewed_again(): void
    {
        ['sahodaya' => $sahodaya, 'sahodayaAdmin' => $sahodayaAdmin, 'event' => $event, 'school' => $school, 'participant' => $participant] = $this->fixture();

        $clashRequest = FestClashRequest::create([
            'event_id' => $event->id, 'school_id' => $school->id, 'participant_id' => $participant->id,
            'description' => 'Overlapping stages.', 'status' => 'approved',
            'reviewed_at' => now(), 'reviewed_by_user_id' => $sahodayaAdmin->id,
        ]);

        $response = $this->actingAs($sahodayaAdmin)->post(route('sahodaya.events.clash-requests.reject', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'clashRequest' => $clashRequest->id,
        ]), ['resolution_note' => 'Changed my mind.']);

        $response->assertStatus(422);
        $this->assertSame('approved', $clashRequest->fresh()->status, 'an already-reviewed request must not be re-reviewable');
    }

    public function test_a_different_sahodayas_admin_cannot_review_this_clash_request(): void
    {
        ['event' => $event, 'school' => $school, 'participant' => $participant] = $this->fixture();

        $clashRequest = FestClashRequest::create([
            'event_id' => $event->id, 'school_id' => $school->id, 'participant_id' => $participant->id,
            'description' => 'Overlapping stages.', 'status' => 'pending',
        ]);

        $otherSahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Unrelated Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        $otherAdmin = User::factory()->create(['tenant_id' => $otherSahodaya->id, 'email_verified_at' => now()]);
        $otherAdmin->assignRole('sahodaya_admin');

        $response = $this->actingAs($otherAdmin)->post(route('sahodaya.events.clash-requests.approve', [
            'tenantId' => $otherSahodaya->id, 'event' => $event->id, 'clashRequest' => $clashRequest->id,
        ]), []);

        $response->assertStatus(403);
        $this->assertSame('pending', $clashRequest->fresh()->status);
    }
}
