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
            'schedule_ids' => [$scheduleA->id, $scheduleB->id],
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
        $stored = FestClashRequest::where('participant_id', $participant->id)->sole();
        $this->assertSame([$scheduleA->id, $scheduleB->id], $stored->schedule_ids);
    }

    public function test_a_clash_request_needs_at_least_two_clashing_slots(): void
    {
        ['school' => $school, 'schoolAdmin' => $schoolAdmin, 'event' => $event, 'participant' => $participant, 'scheduleA' => $scheduleA] = $this->fixture();

        $response = $this->actingAs($schoolAdmin)->post(route('school.kalotsav.clash-requests.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), [
            'participant_id' => $participant->id,
            'schedule_ids' => [$scheduleA->id],
            'description' => 'Only one slot picked.',
        ]);

        $response->assertSessionHasErrors('schedule_ids');
        $this->assertDatabaseMissing('fest_clash_requests', ['participant_id' => $participant->id]);
    }

    public function test_a_three_way_clash_can_be_reported_in_one_request(): void
    {
        ['school' => $school, 'schoolAdmin' => $schoolAdmin, 'event' => $event, 'participant' => $participant, 'scheduleA' => $scheduleA, 'scheduleB' => $scheduleB] = $this->fixture();

        $itemC = FestEventItem::create(['event_id' => $event->id, 'title' => 'Mono Act', 'participant_type' => 'individual', 'is_enabled' => true]);
        $registrationC = FestRegistration::create(['event_id' => $event->id, 'item_id' => $itemC->id, 'school_id' => $school->id, 'status' => 'approved', 'submitted_at' => now()]);
        FestParticipant::create(['registration_id' => $registrationC->id, 'student_id' => $participant->student_id, 'participant_type' => 'student', 'participant_role' => 'performer']);
        $scheduleC = FestSchedule::create(['event_id' => $event->id, 'item_id' => $itemC->id, 'participant_id' => $participant->id, 'scheduled_at' => now()->addDay()->setTime(10, 5), 'stage' => 'Open Air Stage']);

        $response = $this->actingAs($schoolAdmin)->post(route('school.kalotsav.clash-requests.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), [
            'participant_id' => $participant->id,
            'schedule_ids' => [$scheduleA->id, $scheduleB->id, $scheduleC->id],
            'description' => 'Student is scheduled on three stages within the same 20 minutes.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $stored = FestClashRequest::where('participant_id', $participant->id)->sole();
        $this->assertCount(3, $stored->schedule_ids);
        $this->assertEqualsCanonicalizing([$scheduleA->id, $scheduleB->id, $scheduleC->id], $stored->schedule_ids);
        $this->assertCount(3, $stored->schedules());

        // The Sahodaya review page shows every one of the three clashing items, not just two.
        $sahodayaAdmin = User::factory()->create(['tenant_id' => $event->tenant_id, 'email_verified_at' => now()]);
        $sahodayaAdmin->assignRole('sahodaya_admin');
        $page = $this->actingAs($sahodayaAdmin)->get(route('sahodaya.events.clash-requests.index', ['tenantId' => $event->tenant_id, 'event' => $event->id]));
        $rowSchedules = collect($page->viewData('page')['props']['requests']['data'])->firstWhere('id', $stored->id)['schedules'];
        $this->assertCount(3, $rowSchedules);
        $this->assertEqualsCanonicalizing(['Recitation', 'Elocution', 'Mono Act'], collect($rowSchedules)->pluck('item_title')->all());
    }

    /**
     * The printable clash form carries the SCHOOL'S OWN Sahodaya's name (not a hardcoded
     * one), and a request with 3 clashing slots prints all 3 item boxes, not just 2.
     */
    public function test_the_printable_clash_form_is_branded_with_its_own_sahodaya_and_prints_every_clashing_item(): void
    {
        config(['services.pdf_converter.url' => 'https://pdf.example.test/generate-pdf']);
        \Illuminate\Support\Facades\Http::fake(['pdf.example.test/*' => \Illuminate\Support\Facades\Http::response('%PDF-1.4 fake', 200)]);

        ['school' => $school, 'schoolAdmin' => $schoolAdmin, 'sahodaya' => $sahodaya, 'event' => $event, 'participant' => $participant, 'scheduleA' => $scheduleA, 'scheduleB' => $scheduleB] = $this->fixture();

        $itemC = FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Song', 'participant_type' => 'individual', 'is_enabled' => true]);
        $registrationC = FestRegistration::create(['event_id' => $event->id, 'item_id' => $itemC->id, 'school_id' => $school->id, 'status' => 'approved', 'submitted_at' => now()]);
        FestParticipant::create(['registration_id' => $registrationC->id, 'student_id' => $participant->student_id, 'participant_type' => 'student', 'participant_role' => 'performer']);
        $scheduleC = FestSchedule::create(['event_id' => $event->id, 'item_id' => $itemC->id, 'participant_id' => $participant->id, 'scheduled_at' => now()->addDay()->setTime(10, 10), 'stage' => 'Green Room']);

        $clashRequest = FestClashRequest::create([
            'event_id' => $event->id, 'school_id' => $school->id, 'participant_id' => $participant->id,
            'schedule_id_a' => $scheduleA->id, 'schedule_id_b' => $scheduleB->id,
            'schedule_ids' => [$scheduleA->id, $scheduleB->id, $scheduleC->id],
            'description' => 'Three-way clash.', 'status' => 'pending',
        ]);

        // Blank form, no id — an admin filling it in by hand at the venue.
        $this->actingAs($schoolAdmin)->get(route('school.kalotsav.clash-requests.print-form', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]).'?preview=1')->assertOk();
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains((string) $r->data()['html'], $sahodaya->name) && ($r->data()['landscape'] ?? false) === true);

        // Pre-filled from the request — every one of the 3 clashing items, not just 2.
        $this->actingAs($schoolAdmin)->get(route('school.kalotsav.clash-requests.print-form', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]).'?clash_request='.$clashRequest->id)->assertOk();
        \Illuminate\Support\Facades\Http::assertSent(function ($r) {
            $html = (string) $r->data()['html'];

            return str_contains($html, 'Recitation') && str_contains($html, 'Elocution') && str_contains($html, 'Group Song') && ($r->data()['landscape'] ?? false) === true;
        });
    }

    public function test_school_cannot_file_a_clash_request_for_a_participant_belonging_to_another_school(): void
    {
        ['school' => $school, 'schoolAdmin' => $schoolAdmin, 'sahodaya' => $sahodaya, 'event' => $event, 'scheduleA' => $scheduleA, 'scheduleB' => $scheduleB] = $this->fixture();

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
            // Valid, existing slots (this school's own), so validation passes and the
            // request reaches — and is stopped by — the participant-ownership check.
            'schedule_ids' => [$scheduleA->id, $scheduleB->id],
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

    public function test_school_can_render_blank_and_prefilled_clash_form_pdf(): void
    {
        ['event' => $event, 'school' => $school, 'schoolAdmin' => $schoolAdmin, 'participant' => $participant, 'scheduleA' => $scheduleA, 'scheduleB' => $scheduleB] = $this->fixture();

        // 1. Blank form
        $blankResponse = $this->actingAs($schoolAdmin)->get(
            "/school-admin/{$school->id}/kalotsav/events/{$event->id}/clash-requests/print-form?preview=1"
        );
        $blankResponse->assertOk();

        // 2. Pre-filled form
        $clashRequest = FestClashRequest::create([
            'event_id'             => $event->id,
            'school_id'            => $school->id,
            'participant_id'       => $participant->id,
            'schedule_ids'         => [$scheduleA->id, $scheduleB->id],
            'description'          => 'Overlapping stages.',
            'status'               => 'pending',
            'requested_by_user_id' => $schoolAdmin->id,
        ]);

        $prefilledResponse = $this->actingAs($schoolAdmin)->get(
            "/school-admin/{$school->id}/kalotsav/events/{$event->id}/clash-requests/print-form?clash_request={$clashRequest->id}&preview=1"
        );
        $prefilledResponse->assertOk();

        // 3. Pre-filled directly by student_id and schedule_ids (even with 3+ overlapping items)
        $itemC = FestEventItem::create(['event_id' => $event->id, 'title' => 'Essay Writing', 'participant_type' => 'individual', 'is_enabled' => true]);
        $registrationC = FestRegistration::create(['event_id' => $event->id, 'item_id' => $itemC->id, 'school_id' => $school->id, 'status' => 'approved', 'submitted_at' => now()]);
        FestParticipant::create(['registration_id' => $registrationC->id, 'student_id' => $participant->student_id, 'participant_type' => 'student', 'participant_role' => 'performer']);
        $scheduleC = FestSchedule::create([
            'event_id' => $event->id, 'item_id' => $itemC->id, 'participant_id' => $participant->id,
            'scheduled_at' => now()->addDay()->setTime(10, 20), 'stage' => 'Hall C',
        ]);

        $studentClashResponse = $this->actingAs($schoolAdmin)->get(
            "/school-admin/{$school->id}/kalotsav/events/{$event->id}/clash-requests/print-form?student_id={$participant->student_id}&schedule_ids={$scheduleA->id},{$scheduleB->id}&preview=1&raw_html=1"
        );
        $studentClashResponse->assertOk();
        $html = $studentClashResponse->getContent();
        $this->assertStringContainsString('Recitation', $html);
        $this->assertStringContainsString('Elocution', $html);
        $this->assertStringContainsString('Essay Writing', $html);
        $this->assertStringContainsString('10:00 AM – 11:00 AM', $html);
        $this->assertStringContainsString('10:15 AM – 11:15 AM', $html);
    }
}
