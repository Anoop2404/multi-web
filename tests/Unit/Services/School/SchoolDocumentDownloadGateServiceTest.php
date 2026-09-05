<?php

namespace Tests\Unit\Services\School;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestRegistrationCreateService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\School\SchoolDocumentDownloadGateService;
use App\Support\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SchoolDocumentDownloadGateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_are_blocked_until_event_fee_is_paid_and_approved(): void
    {
        $sahodaya = Tenant::create([
            'id' => 'sahodaya-dl-gate',
            'name' => 'Sahodaya DL Gate Test',
            'type' => 'sahodaya',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        $year = AcademicYear::forSahodaya($sahodaya->id);

        $school = Tenant::create([
            'id' => 'school-dl-gate',
            'parent_id' => $sahodaya->id,
            'name' => 'School DL Gate Test',
            'type' => 'school',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        // Annual Sahodaya membership completed
        Registration::create([
            'school_id' => $school->id,
            'academic_year' => $year,
            'registration_status' => 'completed',
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Download Gating Test Event',
            'event_type' => 'kalotsav',
            'status' => 'registration_open',
            'approval_policy' => 'auto',
            'fee_settings' => [
                'fee_model' => 'per_item',
                'per_item_amount' => 100,
                'require_verified_students' => false,
            ],
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Folk Dance',
            'participant_type' => 'single',
            'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => 'Class 10',
            'class_number' => 10,
        ]);

        $student = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $schoolClass->id,
            'name' => 'Jane Student',
            'status' => 'active',
            'verification_status' => 'verified',
            'eligible_kalolsav' => true,
        ]);

        // 1. Create registration (auto-approved)
        $createService = app(FestRegistrationCreateService::class);
        $registration = $createService->createForSchool($event, $item, $school, [$student->id]);

        $this->assertEquals('approved', $registration->status);

        // 2. Test download gate BEFORE payment - should be blocked
        $downloadGate = app(SchoolDocumentDownloadGateService::class);
        $this->assertFalse($downloadGate->festEventFeeCleared($event, $school));

        $payload = $downloadGate->payload($school, $event);
        $this->assertTrue($payload['blocked']);
        $this->assertStringContainsString('Event fee payment is pending', $payload['reason']);

        try {
            $downloadGate->assertFestEventFeeForDownloads($event, $school);
            $this->fail('Expected HttpException 422 was not thrown');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
            $this->assertStringContainsString('Event fee payment is pending', $e->getMessage());
        }

        // 3. Mark event fee as paid and approved
        $fee = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $school->id)
            ->first();

        if (! $fee) {
            $fee = app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);
        }

        $fee->update([
            'amount_paid' => $fee->total_due,
            'status' => 'approved',
        ]);

        // 4. Test download gate AFTER payment approval - should be unlocked
        $this->assertTrue($downloadGate->festEventFeeCleared($event, $school));

        $payloadAfter = $downloadGate->payload($school, $event);
        $this->assertFalse($payloadAfter['blocked']);
        $this->assertNull($payloadAfter['reason']);

        // Should not throw any exception
        $downloadGate->assertFestEventFeeForDownloads($event, $school);
    }

    /**
     * The membership-fee reason string used to be a bare "...is pending." fragment, relying
     * on Vue templates (ReportIdCards.vue, ExamDetail.vue) to append a generic "Complete
     * membership and event fee payment..." sentence — which was wrong whenever, like here,
     * the event/exam fee was never even reached because membership already blocked. Now the
     * reason itself is a complete, self-contained sentence with its own call-to-action.
     */
    public function test_membership_reason_is_a_complete_sentence_that_does_not_mention_the_event_fee(): void
    {
        $sahodaya = Tenant::create([
            'id' => 'sahodaya-dl-gate-membership',
            'name' => 'Sahodaya DL Gate Membership Test',
            'type' => 'sahodaya',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        $school = Tenant::create([
            'id' => 'school-dl-gate-membership',
            'parent_id' => $sahodaya->id,
            'name' => 'School DL Gate Membership Test',
            'type' => 'school',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        // No Registration/MembershipPayment row at all — membership fee not cleared.
        $downloadGate = app(SchoolDocumentDownloadGateService::class);
        $payload = $downloadGate->payload($school);

        $this->assertTrue($payload['blocked']);
        $this->assertStringContainsString('Sahodaya membership fee payment is pending', $payload['reason']);
        $this->assertStringNotContainsString('event fee', $payload['reason']);
    }

    /**
     * Regression test for the bug where a school that fully paid Phase 1 of a per-phase
     * Kalotsavam event was still blocked from downloading Phase 1 ID cards, because the
     * download gate only ever ran the whole-event aggregate check (isPaid(), which requires
     * every phase paid) — there was no phase-scoped equivalent of the per-head $headId
     * parameter. Per FestSchoolEventFeeService::recalculateForPhase()'s own docblock, phases
     * are independently payable, so Phase 1 downloads must not wait on Phase 2's payment.
     */
    public function test_paid_phase_unlocks_downloads_for_that_phase_even_while_another_phase_is_unpaid(): void
    {
        $sahodaya = Tenant::create([
            'id' => 'sahodaya-dl-gate-phase',
            'name' => 'Sahodaya DL Gate Phase Test',
            'type' => 'sahodaya',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        $year = AcademicYear::forSahodaya($sahodaya->id);

        $school = Tenant::create([
            'id' => 'school-dl-gate-phase',
            'parent_id' => $sahodaya->id,
            'name' => 'School DL Gate Phase Test',
            'type' => 'school',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        Registration::create([
            'school_id' => $school->id,
            'academic_year' => $year,
            'registration_status' => 'completed',
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Phased Download Gating Test Event',
            'event_type' => 'kalolsavam',
            'status' => 'registration_open',
            'phase_mode_enabled' => true,
            'fee_settings' => [
                'fee_model' => 'per_item',
                'per_item_amount' => 100,
                'require_verified_students' => false,
            ],
        ]);

        $phaseOne = FestEventPhase::create([
            'event_id' => $event->id,
            'name' => 'Phase 1',
            'code' => 'P1',
            'sort_order' => 1,
        ]);
        $phaseTwo = FestEventPhase::create([
            'event_id' => $event->id,
            'name' => 'Phase 2',
            'code' => 'P2',
            'sort_order' => 2,
        ]);

        $itemPhaseOne = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Phase 1 Item',
            'participant_type' => 'single',
            'is_enabled' => true,
            'phase_id' => $phaseOne->id,
        ]);
        $itemPhaseTwo = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Phase 2 Item',
            'participant_type' => 'single',
            'is_enabled' => true,
            'phase_id' => $phaseTwo->id,
        ]);

        $registrationOne = FestRegistration::create([
            'event_id' => $event->id,
            'item_id' => $itemPhaseOne->id,
            'school_id' => $school->id,
            'status' => 'approved',
        ]);
        FestParticipant::create(['registration_id' => $registrationOne->id, 'participant_role' => 'performer']);

        $registrationTwo = FestRegistration::create([
            'event_id' => $event->id,
            'item_id' => $itemPhaseTwo->id,
            'school_id' => $school->id,
            'status' => 'approved',
        ]);
        FestParticipant::create(['registration_id' => $registrationTwo->id, 'participant_role' => 'performer']);

        $feeService = app(FestSchoolEventFeeService::class);
        $feeOne = $feeService->recalculateForPhase($event->fresh(), $school->id, $phaseOne);
        $feeService->recalculateForPhase($event->fresh(), $school->id, $phaseTwo);

        // Phase 1 fully paid and approved; Phase 2 left untouched.
        $feeOne->update(['amount_paid' => $feeOne->total_due, 'status' => 'approved']);

        $downloadGate = app(SchoolDocumentDownloadGateService::class);

        // Phase-scoped: Phase 1 is clear, Phase 2 is still pending.
        $this->assertTrue($downloadGate->festEventFeeCleared($event, $school, null, $phaseOne->id));
        $this->assertFalse($downloadGate->festEventFeeCleared($event, $school, null, $phaseTwo->id));

        $downloadGate->assertFestEventFeeForDownloads($event, $school, null, $phaseOne->id);

        try {
            $downloadGate->assertFestEventFeeForDownloads($event, $school, null, $phaseTwo->id);
            $this->fail('Expected HttpException 422 was not thrown for the unpaid phase');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }

        // A bundle spanning every phase (no phaseId given) still correctly requires
        // everything paid, since it isn't scoped to just Phase 1.
        $this->assertFalse($downloadGate->festEventFeeCleared($event, $school));
    }

    /**
     * A phase (a "level" in the Sahodaya's own terminology) with no fee configured — e.g. no
     * item on it carries a fee — must never block its own ID card downloads, even while a
     * different, genuinely-billed phase on the same event is still unpaid. isFullyPaid()
     * already treats total_due <= 0 as paid; this locks in that the phase-scoped gate reaches
     * that check instead of falling through to the whole-event aggregate (which would report
     * unpaid because of the OTHER phase).
     */
    public function test_phase_with_no_fee_due_never_blocks_its_own_downloads(): void
    {
        $sahodaya = Tenant::create([
            'id' => 'sahodaya-dl-gate-free-phase',
            'name' => 'Sahodaya DL Gate Free Phase Test',
            'type' => 'sahodaya',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        $year = AcademicYear::forSahodaya($sahodaya->id);

        $school = Tenant::create([
            'id' => 'school-dl-gate-free-phase',
            'parent_id' => $sahodaya->id,
            'name' => 'School DL Gate Free Phase Test',
            'type' => 'school',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        Registration::create([
            'school_id' => $school->id,
            'academic_year' => $year,
            'registration_status' => 'completed',
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Free Phase Download Gating Test Event',
            'event_type' => 'kalolsavam',
            'status' => 'registration_open',
            'phase_mode_enabled' => true,
            // item_catalog (unlike per_item, which bills a flat rate per item count and
            // ignores each item's own fee_amount) resolves each item's fee via
            // FestItemFeeResolver::amountForItem(), which lets an item's own fee_amount
            // override win outright — see that method's very first check. That's what
            // makes one item genuinely free while another on the same event is billed.
            'fee_settings' => [
                'fee_model' => 'item_catalog',
                'require_verified_students' => false,
            ],
        ]);

        $billedPhase = FestEventPhase::create([
            'event_id' => $event->id,
            'name' => 'Billed Level',
            'code' => 'BL',
            'sort_order' => 1,
        ]);
        $freePhase = FestEventPhase::create([
            'event_id' => $event->id,
            'name' => 'Free Level',
            'code' => 'FL',
            'sort_order' => 2,
        ]);

        $billedItem = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Billed Level Item',
            'participant_type' => 'single',
            'is_enabled' => true,
            'phase_id' => $billedPhase->id,
            'fee_amount' => 100,
        ]);
        $freeItem = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Free Level Item',
            'participant_type' => 'single',
            'is_enabled' => true,
            'phase_id' => $freePhase->id,
            'fee_amount' => 0,
        ]);

        FestParticipant::create([
            'registration_id' => FestRegistration::create([
                'event_id' => $event->id,
                'item_id' => $billedItem->id,
                'school_id' => $school->id,
                'status' => 'approved',
            ])->id,
            'participant_role' => 'performer',
        ]);
        FestParticipant::create([
            'registration_id' => FestRegistration::create([
                'event_id' => $event->id,
                'item_id' => $freeItem->id,
                'school_id' => $school->id,
                'status' => 'approved',
            ])->id,
            'participant_role' => 'performer',
        ]);

        $feeService = app(FestSchoolEventFeeService::class);
        $billedFee = $feeService->recalculateForPhase($event->fresh(), $school->id, $billedPhase);
        $freeFee = $feeService->recalculateForPhase($event->fresh(), $school->id, $freePhase);

        $this->assertGreaterThan(0, (float) $billedFee->total_due, 'sanity: the billed phase actually owes money');

        $downloadGate = app(SchoolDocumentDownloadGateService::class);

        // The unpaid billed phase blocks its own downloads...
        $this->assertFalse($downloadGate->festEventFeeCleared($event, $school, null, $billedPhase->id));
        // ...but the free phase is never blocked, regardless of the billed phase's state.
        $this->assertTrue($downloadGate->festEventFeeCleared($event, $school, null, $freePhase->id));
        $downloadGate->assertFestEventFeeForDownloads($event, $school, null, $freePhase->id);
    }

    /**
     * fee_settings.id_card_allowed_with_pending_fees is an event-level escape hatch, off by
     * default, that only ID card downloads may opt into (via $documentType = 'id_card') —
     * every other document type (admit cards, hall tickets, etc.) must keep enforcing the
     * fee gate exactly as before regardless of this setting.
     */
    public function test_id_card_pending_fees_flag_only_unlocks_id_card_downloads(): void
    {
        $sahodaya = Tenant::create([
            'id' => 'sahodaya-dl-gate-idcard-flag',
            'name' => 'Sahodaya DL Gate ID Card Flag Test',
            'type' => 'sahodaya',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        $year = AcademicYear::forSahodaya($sahodaya->id);

        $school = Tenant::create([
            'id' => 'school-dl-gate-idcard-flag',
            'parent_id' => $sahodaya->id,
            'name' => 'School DL Gate ID Card Flag Test',
            'type' => 'school',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        Registration::create([
            'school_id' => $school->id,
            'academic_year' => $year,
            'registration_status' => 'completed',
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'ID Card Pending Fee Flag Test Event',
            'event_type' => 'kalotsav',
            'status' => 'registration_open',
            'approval_policy' => 'auto',
            'fee_settings' => [
                'fee_model' => 'per_item',
                'per_item_amount' => 100,
                'require_verified_students' => false,
                // The flag under test — off means the default, unconditional block.
                'id_card_allowed_with_pending_fees' => false,
            ],
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Folk Dance',
            'participant_type' => 'single',
            'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => 'Class 10',
            'class_number' => 10,
        ]);

        $student = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $schoolClass->id,
            'name' => 'Jane Student',
            'status' => 'active',
            'verification_status' => 'verified',
            'eligible_kalolsav' => true,
        ]);

        app(FestRegistrationCreateService::class)->createForSchool($event, $item, $school, [$student->id]);

        $downloadGate = app(SchoolDocumentDownloadGateService::class);

        // Flag off (default): both document types stay blocked, same as today.
        $this->assertFalse($downloadGate->festEventFeeCleared($event, $school, documentType: 'default'));
        $this->assertFalse($downloadGate->festEventFeeCleared($event, $school, documentType: 'id_card'));
        try {
            $downloadGate->assertFestEventFeeForDownloads($event, $school, documentType: 'id_card');
            $this->fail('Expected HttpException 422 was not thrown');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }

        $event->update(['fee_settings' => array_merge($event->fee_settings, ['id_card_allowed_with_pending_fees' => true])]);
        $event->refresh();

        // Flag on: only the 'id_card' document type is unlocked, fee still pending.
        $this->assertFalse($downloadGate->festEventFeeCleared($event, $school, documentType: 'default'));
        $this->assertTrue($downloadGate->festEventFeeCleared($event, $school, documentType: 'id_card'));
        $downloadGate->assertFestEventFeeForDownloads($event, $school, documentType: 'id_card');

        $payload = $downloadGate->payload($school, $event, documentType: 'id_card');
        $this->assertFalse($payload['blocked']);

        // The unrelated admit-cards/hall-ticket path (documentType left at 'default') must
        // still enforce the gate -- the flag is scoped to ID cards only, not a blanket
        // "ignore this event's fees" switch.
        try {
            $downloadGate->assertFestEventFeeForDownloads($event, $school, documentType: 'default');
            $this->fail('Expected HttpException 422 was not thrown for the non-id_card document type');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }
    }
}
