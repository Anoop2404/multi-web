<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestRegistrationBatch;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestRegistrationBatchFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FestRegistrationBatch::student_registration_fee — a per-batch override for the
 * per-student charge, additive on top of school_base_fee, independent per batch (see
 * the migration docblock). Modeled on Wayanad Sahodaya's real requirement: ₹250 per
 * student, charged once per phase (each phase is its own batch here), on top of a
 * category-tiered school base fee that must NOT double up across phases.
 */
class FestBatchStudentRegistrationFeeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Batch Student Fee Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BSF', 'student_data_mode' => 'counts_only']);
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Batch Student Fee Test School', 'domain' => Str::uuid().'.test', 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Wayanad-style Kalotsav', 'event_type' => 'kalotsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'workflow_mode' => FestPhasedWorkflowService::MODE, 'phase_mode_enabled' => true, 'conduct_mode' => 'partitioned',
            'fee_type' => 'none', 'fee_settings' => ['fee_model' => 'item_catalog'],
        ]);

        $batch1 = FestRegistrationBatch::create(['event_id' => $root->id, 'code' => 'PHASE_1', 'name' => 'Phase 1', 'sort_order' => 1, 'school_base_fee' => 0, 'student_registration_fee' => 250]);
        $batch2 = FestRegistrationBatch::create(['event_id' => $root->id, 'code' => 'PHASE_2', 'name' => 'Phase 2', 'sort_order' => 2, 'school_base_fee' => 0, 'student_registration_fee' => 250]);

        $phase1 = FestEventPhase::create(['event_id' => $root->id, 'name' => 'Phase 1', 'code' => 'P1', 'registration_batch_id' => $batch1->id, 'sort_order' => 1]);
        $phase2 = FestEventPhase::create(['event_id' => $root->id, 'name' => 'Phase 2', 'code' => 'P2', 'registration_batch_id' => $batch2->id, 'sort_order' => 2]);

        $item1 = FestEventItem::create(['event_id' => $root->id, 'title' => 'Phase 1 Item', 'item_code' => 'P1-01', 'phase_id' => $phase1->id, 'fee_amount' => 0, 'is_enabled' => true]);
        $item2 = FestEventItem::create(['event_id' => $root->id, 'title' => 'Phase 2 Item', 'item_code' => 'P2-01', 'phase_id' => $phase2->id, 'fee_amount' => 0, 'is_enabled' => true]);

        return [$root, $school->id, $batch1, $batch2, $item1, $item2];
    }

    public function test_student_registered_in_both_phases_is_charged_250_on_each_batch(): void
    {
        [$root, $schoolId, $batch1, $batch2, $item1, $item2] = $this->fixture();

        $reg1 = FestRegistration::create(['event_id' => $root->id, 'item_id' => $item1->id, 'school_id' => $schoolId, 'status' => 'approved', 'submitted_at' => now()]);
        FestParticipant::create(['registration_id' => $reg1->id, 'student_id' => 1, 'participant_type' => 'student']);

        $reg2 = FestRegistration::create(['event_id' => $root->id, 'item_id' => $item2->id, 'school_id' => $schoolId, 'status' => 'approved', 'submitted_at' => now()]);
        FestParticipant::create(['registration_id' => $reg2->id, 'student_id' => 1, 'participant_type' => 'student']);

        $service = app(FestRegistrationBatchFeeService::class);
        $fee1 = $service->recalculateBatch($root, $schoolId, $batch1);
        $fee2 = $service->recalculateBatch($root, $schoolId, $batch2);

        $this->assertSame(250.0, (float) $fee1->total_due);
        $this->assertSame(250.0, (float) $fee2->total_due);
        $this->assertSame(500.0, (float) $fee1->total_due + (float) $fee2->total_due);
        $this->assertTrue($fee1->lines->contains(fn ($l) => $l->line_type === 'student_registration' && (float) $l->amount === 250.0));
    }

    public function test_a_batch_without_its_own_rate_still_bills_exactly_as_before(): void
    {
        [$root, $schoolId, , , $item1] = $this->fixture();

        // A THIRD batch that never opted into its own per-student rate (student_registration_fee
        // left null) -- existing behavior (zero contribution) must be completely unaffected.
        $legacyBatch = FestRegistrationBatch::create(['event_id' => $root->id, 'code' => 'LEGACY', 'name' => 'Legacy', 'sort_order' => 3, 'school_base_fee' => 100]);
        $legacyPhase = FestEventPhase::create(['event_id' => $root->id, 'name' => 'Legacy Phase', 'code' => 'LP', 'registration_batch_id' => $legacyBatch->id, 'sort_order' => 3]);
        $legacyItem = FestEventItem::create(['event_id' => $root->id, 'title' => 'Legacy Item', 'item_code' => 'LP-01', 'phase_id' => $legacyPhase->id, 'fee_amount' => 30, 'is_enabled' => true]);

        $reg = FestRegistration::create(['event_id' => $root->id, 'item_id' => $legacyItem->id, 'school_id' => $schoolId, 'status' => 'approved', 'submitted_at' => now()]);
        FestParticipant::create(['registration_id' => $reg->id, 'student_id' => 1, 'participant_type' => 'student']);

        $fee = app(FestRegistrationBatchFeeService::class)->recalculateBatch($root, $schoolId, $legacyBatch);

        // 100 base + 30 item, no student_registration line at all.
        $this->assertSame(130.0, (float) $fee->total_due);
        $this->assertFalse($fee->lines->contains(fn ($l) => $l->line_type === 'student_registration'));
    }
}
