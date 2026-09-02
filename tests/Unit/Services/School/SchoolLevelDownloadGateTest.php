<?php

namespace Tests\Unit\Services\School;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestRegistrationBatch;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestIdCardService;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestRegistrationBatchFeeService;
use App\Services\School\SchoolDocumentDownloadGateService;
use App\Support\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Events on the phased_regional_billing workflow invoice each registration level
 * (Level 1, Level 2, …) separately, and each is approved on its own. Downloads scoped
 * to one level must therefore be gated on that level alone — FestSchoolEventFeeService::
 * isPaid() only clears once EVERY level is paid, which used to leave a school with an
 * approved Level 1 invoice staring at "Payment pending" on the ID cards page.
 */
class SchoolLevelDownloadGateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: FestEvent, 2: FestRegistrationBatch, 3: FestRegistrationBatch, 4: FestEventItem, 5: FestEventItem} */
    private function makeLevelledEvent(): array
    {
        $sahodaya = Tenant::create([
            'id' => 'sahodaya-level-gate',
            'name' => 'Sahodaya Level Gate Test',
            'type' => 'sahodaya',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        $school = Tenant::create([
            'id' => 'school-level-gate',
            'parent_id' => $sahodaya->id,
            'name' => 'School Level Gate Test',
            'type' => 'school',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        Registration::create([
            'school_id' => $school->id,
            'academic_year' => AcademicYear::forSahodaya($sahodaya->id),
            'registration_status' => 'completed',
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Levelled Kalotsavam',
            'event_type' => 'kalolsavam',
            'status' => 'registration_open',
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'phase_mode_enabled' => true,
            'fee_settings' => [
                'fee_model' => 'per_item',
                'per_item_amount' => 100,
                'require_verified_students' => false,
            ],
        ]);

        $levelOne = FestRegistrationBatch::create([
            'event_id' => $event->id,
            'code' => 'level_1',
            'name' => 'Level 1',
            'sort_order' => 1,
            'status' => 'registration_open',
        ]);
        $levelTwo = FestRegistrationBatch::create([
            'event_id' => $event->id,
            'code' => 'level_2',
            'name' => 'Level 2',
            'sort_order' => 2,
            'status' => 'registration_open',
        ]);

        $phaseOne = FestEventPhase::create([
            'event_id' => $event->id,
            'registration_batch_id' => $levelOne->id,
            'name' => 'Digi Fest',
            'code' => 'digi',
            'sort_order' => 1,
        ]);
        $phaseTwo = FestEventPhase::create([
            'event_id' => $event->id,
            'registration_batch_id' => $levelTwo->id,
            'name' => 'Off Stage',
            'code' => 'off',
            'sort_order' => 2,
        ]);

        $itemOne = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Level 1 Item',
            'participant_type' => 'single',
            'is_enabled' => true,
            'phase_id' => $phaseOne->id,
        ]);
        $itemTwo = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Level 2 Item',
            'participant_type' => 'single',
            'is_enabled' => true,
            'phase_id' => $phaseTwo->id,
        ]);

        $schoolClass = SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => 'Class 10',
            'class_number' => 10,
        ]);

        foreach ([[$itemOne, 'Level One Student'], [$itemTwo, 'Level Two Student']] as [$item, $name]) {
            $student = Student::create([
                'tenant_id' => $school->id,
                'school_class_id' => $schoolClass->id,
                'name' => $name,
                'status' => 'active',
                'verification_status' => 'verified',
                'eligible_kalolsav' => true,
            ]);

            $registration = FestRegistration::create([
                'event_id' => $event->id,
                'item_id' => $item->id,
                'school_id' => $school->id,
                'status' => 'approved',
            ]);

            FestParticipant::create([
                'registration_id' => $registration->id,
                'student_id' => $student->id,
                'participant_role' => 'performer',
            ]);
        }

        return [$school, $event->fresh(), $levelOne, $levelTwo, $itemOne, $itemTwo];
    }

    public function test_paid_level_unlocks_its_own_downloads_while_another_level_is_unpaid(): void
    {
        [$school, $event, $levelOne, $levelTwo] = $this->makeLevelledEvent();

        $batchFees = app(FestRegistrationBatchFeeService::class);
        $fees = $batchFees->recalculateAll($event, $school->id);

        $feeOne = $fees->firstWhere('registration_batch_id', $levelOne->id);
        $this->assertNotNull($feeOne);
        $this->assertGreaterThan(0, (float) $feeOne->total_due);

        // Level 1 invoice paid and approved; Level 2 untouched. Recorded as an approved
        // receipt, since every recalculation re-derives amount_paid from those.
        \App\Models\FeeReceipt::create([
            'feeable_type' => \App\Models\FestSchoolEventFee::class,
            'feeable_id' => $feeOne->id,
            'amount' => $feeOne->total_due,
            'status' => 'approved',
            'file_path' => 'fest-payments/level-1-proof.pdf',
        ]);
        $feeOne->refreshPaidState();
        $this->assertTrue($feeOne->fresh()->isFullyPaid());

        $gate = app(SchoolDocumentDownloadGateService::class);

        $this->assertTrue($batchFees->isBatchPaid($event, $school->id, $levelOne->id));
        $this->assertFalse($batchFees->isBatchPaid($event, $school->id, $levelTwo->id));

        $this->assertTrue($gate->festEventFeeCleared($event, $school, null, null, $levelOne->id));
        $this->assertFalse($gate->festEventFeeCleared($event, $school, null, null, $levelTwo->id));

        // The whole-event aggregate is still unpaid — that's exactly why the level-scoped
        // check has to exist.
        $this->assertFalse($gate->festEventFeeCleared($event, $school));

        $payloadOne = $gate->payload($school, $event, null, null, null, $levelOne->id);
        $this->assertFalse($payloadOne['blocked']);
        $this->assertNull($payloadOne['reason']);

        $payloadTwo = $gate->payload($school, $event, null, null, null, $levelTwo->id);
        $this->assertTrue($payloadTwo['blocked']);
        $this->assertStringContainsString('Level 2', $payloadTwo['reason']);

        $gate->assertFestEventFeeForDownloads($event, $school, null, null, $levelOne->id);

        try {
            $gate->assertFestEventFeeForDownloads($event, $school, null, null, $levelTwo->id);
            $this->fail('Expected HttpException 422 was not thrown for the unpaid level');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
            $this->assertStringContainsString('Level 2', $e->getMessage());
        }
    }

    public function test_id_cards_are_scoped_to_the_selected_level(): void
    {
        [$school, $event, $levelOne, $levelTwo] = $this->makeLevelledEvent();

        $service = app(FestIdCardService::class);
        $base = ['school_id' => $school->id, 'school_downloads' => true, 'scope' => 'event'];

        $all = $service->cards($event, 'student', $base);
        $this->assertCount(2, $all);

        $levelOneCards = $service->cards($event, 'student', $base + ['registration_batch_id' => $levelOne->id]);
        $this->assertCount(1, $levelOneCards);
        $this->assertEquals('Level One Student', $levelOneCards[0]['name']);

        $levelTwoCards = $service->cards($event, 'student', $base + ['registration_batch_id' => $levelTwo->id]);
        $this->assertCount(1, $levelTwoCards);
        $this->assertEquals('Level Two Student', $levelTwoCards[0]['name']);
    }
}
