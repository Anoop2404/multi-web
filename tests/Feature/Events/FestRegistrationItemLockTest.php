<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestRegistrationBulkService;
use App\Services\Events\FestRegistrationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Every registration-write path (school: cancel/edit-roster; admin: cancel/
 * cancel-with-refund/substitute/add-participant/remove-participant/review)
 * previously checked only the event-wide results_published flag, never the
 * per-item FestEventItem::results_published_at — so once a single item's
 * results were published, its roster stayed fully editable until the WHOLE
 * event was published too. Confirmed as the likely cause of a real production
 * certificate discrepancy this session: a Choral Recitation team's
 * registration could still change after that item's own results were
 * published. See EventLifecycleGate::assertItemRosterNotFrozen().
 */
class FestRegistrationItemLockTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{event: FestEvent, item: FestEventItem, registration: FestRegistration, performer: FestParticipant, standby: FestParticipant, school: Tenant} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => 'lock-sahodaya-1', 'type' => 'sahodaya', 'name' => 'Lock Sahodaya',
        ]);
        $school = Tenant::create([
            'id' => 'lock-school-1', 'type' => 'school', 'parent_id' => 'lock-sahodaya-1', 'name' => 'Lock School',
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Lock Event', 'event_type' => 'kalolsavam',
            'status' => 'registration_open',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Choral Recitation', 'item_code' => 'LOCK1',
        ]);

        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);

        $performer = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => 5001,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);
        $standby = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => 5002,
            'participant_type' => 'student', 'participant_role' => 'standby',
        ]);

        return compact('event', 'item', 'registration', 'performer', 'standby', 'school');
    }

    public function test_school_and_admin_guard_methods_block_once_the_items_results_are_published(): void
    {
        $f = $this->fixture();
        $f['item']->update(['results_published_at' => now()]);
        $registration = $f['registration']->fresh();
        $service = app(FestRegistrationService::class);

        $this->assertFalse($service->canSchoolCancel($registration, $f['event']));
        $this->assertFalse($service->canSchoolEditRoster($registration, $f['event']));
        $this->assertFalse($service->canAdminCancel($registration, $f['event']));
        $this->assertFalse($service->canAdminCancelWithRefund($registration, $f['event']));
    }

    public function test_school_and_admin_guard_methods_allow_when_the_item_is_not_yet_published(): void
    {
        $f = $this->fixture();
        $registration = $f['registration']->fresh();
        $service = app(FestRegistrationService::class);

        $this->assertTrue($service->canSchoolCancel($registration, $f['event']));
        $this->assertTrue($service->canSchoolEditRoster($registration, $f['event']));
        $this->assertTrue($service->canAdminCancel($registration, $f['event']));
    }

    public function test_cancel_aborts_once_the_items_results_are_published(): void
    {
        $f = $this->fixture();
        $f['item']->update(['results_published_at' => now()]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("item's results are already published");

        app(FestRegistrationService::class)->cancel($f['registration']->fresh(), $f['event'], notify: false);
    }

    public function test_cancel_succeeds_when_the_item_is_not_yet_published(): void
    {
        $f = $this->fixture();

        app(FestRegistrationService::class)->cancel($f['registration']->fresh(), $f['event'], notify: false);

        $this->assertSame('withdrawn', $f['registration']->fresh()->status);
    }

    public function test_substitute_performer_aborts_once_the_items_results_are_published(): void
    {
        $f = $this->fixture();
        $f['item']->update(['results_published_at' => now()]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("item's results are already published");

        app(FestRegistrationService::class)->substitutePerformer($f['performer']->fresh(), $f['standby']->fresh());
    }

    public function test_substitute_performer_succeeds_when_the_item_is_not_yet_published(): void
    {
        $f = $this->fixture();

        app(FestRegistrationService::class)->substitutePerformer($f['performer']->fresh(), $f['standby']->fresh());

        $this->assertSame('standby', $f['performer']->fresh()->participant_role);
        $this->assertSame('performer', $f['standby']->fresh()->participant_role);
    }

    public function test_add_participant_aborts_once_the_items_results_are_published(): void
    {
        $f = $this->fixture();
        $f['item']->update(['results_published_at' => now()]);
        $schoolClass = SchoolClass::create(['tenant_id' => $f['school']->id, 'name' => 'Class 9']);
        $student = Student::create(['name' => 'New Standby', 'tenant_id' => $f['school']->id, 'school_class_id' => $schoolClass->id]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("item's results are already published");

        app(FestRegistrationService::class)->addParticipant($f['registration']->fresh(), $f['event'], $student, 'standby');
    }

    public function test_add_participant_succeeds_when_the_item_is_not_yet_published(): void
    {
        $f = $this->fixture();
        $schoolClass = SchoolClass::create(['tenant_id' => $f['school']->id, 'name' => 'Class 9']);
        $student = Student::create(['name' => 'New Standby', 'tenant_id' => $f['school']->id, 'school_class_id' => $schoolClass->id]);

        // Registration already has a standby — cap is 2, so this must still succeed.
        $participant = app(FestRegistrationService::class)->addParticipant($f['registration']->fresh(), $f['event'], $student, 'standby');

        $this->assertSame($student->id, $participant->student_id);
        $this->assertDatabaseHas('fest_participants', ['id' => $participant->id, 'registration_id' => $f['registration']->id]);
    }

    public function test_remove_participant_aborts_once_the_items_results_are_published(): void
    {
        $f = $this->fixture();
        $f['item']->update(['results_published_at' => now()]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("item's results are already published");

        app(FestRegistrationService::class)->removeParticipant($f['standby']->fresh(), $f['event']);
    }

    public function test_remove_participant_succeeds_when_the_item_is_not_yet_published(): void
    {
        $f = $this->fixture();

        app(FestRegistrationService::class)->removeParticipant($f['standby']->fresh(), $f['event']);

        $this->assertDatabaseMissing('fest_participants', ['id' => $f['standby']->id]);
        $this->assertDatabaseHas('fest_participants', ['id' => $f['performer']->id]);
    }

    public function test_allow_registration_for_item_aborts_once_item_results_are_published(): void
    {
        $f = $this->fixture();
        $f['item']->update(['results_published_at' => now()]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("item's results are already published");

        EventLifecycleGate::allowRegistrationForItem($f['event'], $f['item']->fresh());
    }

    public function test_allow_registration_for_item_allows_when_item_is_not_published(): void
    {
        $f = $this->fixture();

        EventLifecycleGate::allowRegistrationForItem($f['event'], $f['item']->fresh());

        $this->assertTrue(true);
    }

    public function test_allow_registration_review_aborts_once_item_results_are_published(): void
    {
        $f = $this->fixture();
        $f['item']->update(['results_published_at' => now()]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("item's results are already published");

        EventLifecycleGate::allowRegistrationReview($f['event'], false, $f['item']->fresh());
    }

    public function test_allow_registration_review_override_bypasses_the_item_level_freeze_too(): void
    {
        $f = $this->fixture();
        $f['item']->update(['results_published_at' => now()]);

        EventLifecycleGate::allowRegistrationReview($f['event'], true, $f['item']->fresh());

        $this->assertTrue(true);
    }

    public function test_allow_registration_review_allows_when_item_is_not_published(): void
    {
        $f = $this->fixture();

        EventLifecycleGate::allowRegistrationReview($f['event'], false, $f['item']->fresh());

        $this->assertTrue(true);
    }

    /**
     * approveMany()/rejectMany() are shared by three separate controllers (sahodaya-admin,
     * the fest-ops Portal, and the REST API) and iterate registrations that can each belong
     * to a different item — so the item-level freeze can't be a single check up front like
     * the event-level one; it has to run per registration inside the loop. These prove that
     * loop check actually skips (not silently ignores) a published item's registration while
     * leaving the rest of the batch unaffected.
     */
    public function test_bulk_approve_skips_a_registration_whose_item_is_published_but_approves_the_rest(): void
    {
        $f = $this->fixture();
        $f['item']->update(['results_published_at' => now()]);
        $f['registration']->update(['status' => 'submitted']);

        $itemB = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'LOCK2']);
        $registrationB = FestRegistration::create([
            'event_id' => $f['event']->id, 'item_id' => $itemB->id, 'school_id' => $f['school']->id, 'status' => 'submitted',
        ]);
        FestParticipant::create([
            'registration_id' => $registrationB->id, 'event_id' => $f['event']->id, 'student_id' => 5003,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        $result = app(FestRegistrationBulkService::class)->approveMany($f['event'], [$f['registration']->id, $registrationB->id]);

        $this->assertSame(1, $result['approved']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringContainsString("item's results are already published", $result['errors'][0]);
        $this->assertSame('submitted', $f['registration']->fresh()->status);
        $this->assertSame('approved', $registrationB->fresh()->status);
    }

    public function test_bulk_reject_skips_a_registration_whose_item_is_published_but_rejects_the_rest(): void
    {
        $f = $this->fixture();
        $f['item']->update(['results_published_at' => now()]);
        $f['registration']->update(['status' => 'submitted']);

        $itemB = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'LOCK3']);
        $registrationB = FestRegistration::create([
            'event_id' => $f['event']->id, 'item_id' => $itemB->id, 'school_id' => $f['school']->id, 'status' => 'submitted',
        ]);
        FestParticipant::create([
            'registration_id' => $registrationB->id, 'event_id' => $f['event']->id, 'student_id' => 5004,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        $result = app(FestRegistrationBulkService::class)->rejectMany($f['event'], [$f['registration']->id, $registrationB->id]);

        $this->assertSame(1, $result['rejected']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringContainsString("item's results are already published", $result['errors'][0]);
        $this->assertSame('submitted', $f['registration']->fresh()->status);
        $this->assertSame('rejected', $registrationB->fresh()->status);
    }
}
