<?php

namespace Tests\Feature\Events;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestRegistrationBatch;
use App\Models\FestSchoolEventFee;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestEventPhaseService;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestRegistrationBatchFeeService;
use App\Services\Events\FestRegistrationCreateService;
use App\Services\Events\FestSchoolEventFeeService;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Reproduces MCS's real phased_regional_billing config (kalolsavam_composite: ₹400 student
 * reg, 1 free item, ₹50 extra item, ₹4000 flat school reg) across LEVEL_1/LEVEL_2 batches —
 * confirms the whole-event quota engine and once-per-event school fee described in
 * docs plan "Fix phase-wise (registration-batch) billing for MCS Kalotsav".
 */
class FestPhasedBatchCompositeBillingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, root: FestEvent, level1: FestRegistrationBatch, level2: FestRegistrationBatch, digiItem: FestEventItem, sargadharaItem: FestEventItem} */
    private function mcsFixture(array $feeSettingsOverrides = []): array
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'MCS Composite Batch Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'MCB',
            'student_data_mode' => 'full_records',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'name' => 'Composite Batch School',
            'domain' => Str::uuid().'.test',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'MCS Kalotsav Composite Batch Test',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'conductor_level' => 'sahodaya',
            'status' => 'registration_open',
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'phase_mode_enabled' => true,
            'conduct_mode' => 'partitioned',
            'fee_settings' => array_merge([
                'fee_model' => 'kalolsavam_composite',
                'per_student_amount' => 400,
                'included_items_per_student' => 1,
                'extra_item_fee' => 50,
                'school_registration_flat' => 4000,
            ], $feeSettingsOverrides),
        ]);

        $level1 = FestRegistrationBatch::create([
            'event_id' => $root->id,
            'code' => 'LEVEL_1',
            'name' => 'Level 1',
            'sort_order' => 1,
            'status' => 'registration_open',
        ]);
        $level2 = FestRegistrationBatch::create([
            'event_id' => $root->id,
            'code' => 'LEVEL_2',
            'name' => 'Level 2',
            'sort_order' => 2,
            'status' => 'registration_open',
        ]);

        $phaseService = app(FestEventPhaseService::class);
        $digiPhase = $phaseService->createPhase($root, [
            'name' => 'Digi Fest', 'code' => 'DIGI', 'sort_order' => 1,
            'registration_batch_id' => $level1->id, 'is_regional' => false,
        ]);
        $sargadharaPhase = $phaseService->createPhase($root, [
            'name' => 'Sargadhara', 'code' => 'SARGADHARA', 'sort_order' => 2,
            'registration_batch_id' => $level2->id, 'is_regional' => false,
        ]);

        $digiItem = FestEventItem::create([
            'event_id' => $root->id, 'phase_id' => $digiPhase->id,
            'title' => 'Digi Item', 'participant_type' => 'individual', 'is_enabled' => true,
            'is_mandatory' => false, 'quota_eligible' => false,
        ]);
        $sargadharaItem = FestEventItem::create([
            'event_id' => $root->id, 'phase_id' => $sargadharaPhase->id,
            'title' => 'Sargadhara Item', 'participant_type' => 'individual', 'is_enabled' => true,
            'is_mandatory' => false, 'quota_eligible' => false,
        ]);

        return compact('sahodaya', 'school', 'root', 'level1', 'level2', 'digiItem', 'sargadharaItem');
    }

    private function makeStudent(Tenant $school, string $name = 'Student'): Student
    {
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10', 'is_active' => true]);

        return Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => $name,
            'gender' => 'male',
            'dob' => '2012-01-01',
            'status' => 'active',
            'verified_at' => now(),
        ]);
    }

    private function register(FestEvent $root, FestEventItem $item, Tenant $school, Student $student): FestRegistration
    {
        $registration = FestRegistration::create([
            'event_id' => $root->id,
            'item_id' => $item->id,
            'school_id' => $school->id,
            'status' => 'approved',
        ]);
        FestParticipant::create([
            'registration_id' => $registration->id,
            'student_id' => $student->id,
            'participant_role' => 'performer',
        ]);

        return $registration;
    }

    public function test_quota_and_school_fee_are_tracked_once_across_both_levels_combined(): void
    {
        ['school' => $school, 'root' => $root, 'digiItem' => $digiItem, 'sargadharaItem' => $sargadharaItem] = $this->mcsFixture();
        $student = $this->makeStudent($school);

        $this->register($root, $digiItem, $school, $student);
        $this->register($root, $sargadharaItem, $school, $student);

        $fees = app(FestRegistrationBatchFeeService::class)->recalculateAll($root, $school->id)
            ->keyBy('registrationBatch.code');

        // Digi Item is the student's first item overall — covered by the ₹400 student
        // registration fee, attributed to LEVEL_1 (its own phase).
        $this->assertSame(4000.0, (float) $fees['LEVEL_1']->school_registration_fee);
        $this->assertSame(400.0, (float) $fees['LEVEL_1']->participation_fee);
        $this->assertSame(4400.0, (float) $fees['LEVEL_1']->total_due);

        // Sargadhara Item is the student's SECOND item overall (even though it's a
        // different level/phase) — beyond the whole-event quota of 1, billed at the ₹50
        // extra item fee, not the school fee again and not a fresh free item.
        $this->assertSame(0.0, (float) $fees['LEVEL_2']->school_registration_fee);
        $this->assertSame(50.0, (float) $fees['LEVEL_2']->participation_fee);
        $this->assertSame(50.0, (float) $fees['LEVEL_2']->total_due);

        $rollup = FestSchoolEventFee::where('event_id', $root->id)
            ->where('school_id', $school->id)
            ->whereNull('registration_batch_id')
            ->first();
        $this->assertSame(4450.0, (float) $rollup->total_due);
    }

    public function test_prior_batch_payment_gate_off_by_default_allows_level2_registration(): void
    {
        ['school' => $school, 'root' => $root, 'digiItem' => $digiItem, 'sargadharaItem' => $sargadharaItem] = $this->mcsFixture();
        $student = $this->makeStudent($school);

        // No LEVEL_1 payment made at all — succeeds because require_prior_batch_payment
        // defaults to false (zero behavior change for every other Sahodaya).
        $registration = app(FestRegistrationCreateService::class)
            ->createForSchool($root, $sargadharaItem, $school, [$student->id]);

        $this->assertNotNull($registration->id);
    }

    public function test_prior_batch_payment_gate_blocks_level2_until_level1_fully_paid(): void
    {
        ['school' => $school, 'root' => $root, 'digiItem' => $digiItem, 'sargadharaItem' => $sargadharaItem] = $this->mcsFixture([
            'require_prior_batch_payment' => true,
        ]);
        $student = $this->makeStudent($school);
        // Give LEVEL_1 a real fee to owe by registering something in it first.
        $this->register($root, $digiItem, $school, $student);
        app(FestRegistrationBatchFeeService::class)->recalculateAll($root, $school->id);

        $this->expectException(ValidationException::class);
        app(FestRegistrationCreateService::class)
            ->createForSchool($root, $sargadharaItem, $school, [$student->id]);
    }

    public function test_prior_batch_payment_gate_allows_level2_once_level1_fully_paid(): void
    {
        ['school' => $school, 'root' => $root, 'digiItem' => $digiItem, 'sargadharaItem' => $sargadharaItem, 'level1' => $level1] = $this->mcsFixture([
            'require_prior_batch_payment' => true,
        ]);
        $student = $this->makeStudent($school);
        $this->register($root, $digiItem, $school, $student);

        $level1Fee = app(FestRegistrationBatchFeeService::class)->recalculateBatch($root, $school->id, $level1);
        FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $level1Fee->id,
            'file_path' => 'test/receipt.pdf',
            'payment_date' => now()->toDateString(),
            'amount' => $level1Fee->total_due,
            'status' => 'approved',
        ]);
        $level1Fee->refreshPaidState();

        $registration = app(FestRegistrationCreateService::class)
            ->createForSchool($root, $sargadharaItem, $school, [$student->id]);

        $this->assertNotNull($registration->id);
    }

    public function test_sync_rollup_folds_pre_conversion_receipt_into_combined_amount_paid_without_double_counting(): void
    {
        ['school' => $school, 'root' => $root, 'digiItem' => $digiItem] = $this->mcsFixture();
        $student = $this->makeStudent($school);
        $this->register($root, $digiItem, $school, $student);

        // Simulate a pre-batch-conversion payment: an approved receipt directly on the
        // rollup row (feeable_id = rollup row's own id), from before phase billing existed.
        $rollup = FestSchoolEventFee::firstOrCreate([
            'event_id' => $root->id, 'school_id' => $school->id,
            'registration_batch_id' => null, 'phase_id' => null, 'head_id' => null,
        ]);
        FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $rollup->id,
            'file_path' => 'test/receipt.pdf',
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
            'status' => 'approved',
        ]);

        app(FestRegistrationBatchFeeService::class)->recalculateAll($root, $school->id);

        $rollup = $rollup->fresh();
        // 1000 (old, own receipt) + 0 (no batch-level payment yet) = 1000, not silently
        // dropped back to 0 by the batch-row sum alone.
        $this->assertSame(1000.0, (float) $rollup->amount_paid);
    }

    public function test_force_recalculation_updates_total_due_on_a_paid_invoice_without_touching_amount_paid(): void
    {
        ['school' => $school, 'root' => $root, 'digiItem' => $digiItem, 'sargadharaItem' => $sargadharaItem, 'level1' => $level1] = $this->mcsFixture();
        $student = $this->makeStudent($school);
        $this->register($root, $digiItem, $school, $student);

        $fee = app(FestRegistrationBatchFeeService::class)->recalculateBatch($root, $school->id, $level1);
        $this->assertSame(4400.0, (float) $fee->total_due);
        FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'file_path' => 'test/receipt.pdf',
            'payment_date' => now()->toDateString(),
            'amount' => 4400,
            'status' => 'approved',
        ]);
        $fee->refreshPaidState();
        $this->assertSame(4400.0, (float) $fee->fresh()->amount_paid);

        // A second registration changes what LEVEL_1's true total should be, but the
        // immutability guard normally blocks this once amount_paid > 0.
        $this->register($root, $sargadharaItem, $school, $student);
        FestEventItem::whereKey($sargadharaItem->id)->update(['phase_id' => $level1->phases()->first()?->id]);

        $unforced = app(FestRegistrationBatchFeeService::class)->recalculateBatch($root, $school->id, $level1);
        $this->assertSame(4400.0, (float) $unforced->total_due, 'Immutability guard should still apply without force.');

        $forced = app(FestRegistrationBatchFeeService::class)->recalculateBatch($root, $school->id, $level1, force: true);
        $this->assertNotSame(4400.0, (float) $forced->total_due);
        $this->assertSame(4400.0, (float) $forced->amount_paid, 'amount_paid must stay untouched by a forced recalculation.');
    }
}
