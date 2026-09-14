<?php

namespace Tests\Feature\Events;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestRegistrationBatch;
use App\Models\FestSchoolEventFee;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestPhasedWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A school reported being unable to re-upload a payment proof after the admin rejected
 * it. FestSchoolEventFeeController::reject() (the legacy aggregate action) explicitly
 * resets the fee's own status to 'rejected' after refreshPaidState() runs — but
 * rejectReceipt() (the newer, per-receipt action used by the proof-history modal, added
 * so a school with several uploaded/superseded/approved proofs isn't left to the legacy
 * action's "guess the latest" behavior) only calls refreshPaidState() and never applies
 * that same override. Reproduces the exact flow to confirm whether the fee status is
 * left in a state that still blocks a school from uploading again.
 */
class FestSchoolFeeReuploadAfterRejectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_can_still_upload_after_admin_rejects_via_the_receipt_modal(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Reupload Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RUS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Reupload School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Reupload Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'fee_settings' => ['fee_model' => 'per_item'],
        ]);

        $fee = FestSchoolEventFee::create([
            'event_id' => $event->id, 'school_id' => $school->id,
            'total_due' => 500, 'amount_paid' => 0, 'status' => 'proof_uploaded',
        ]);
        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'file_path' => 'fest/receipts/first-attempt.jpg',
            'amount' => 500,
            'status' => 'uploaded',
        ]);
        $fee->update(['fee_receipt_id' => $receipt->id]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.school-fees.receipts.reject', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
            'schoolEventFee' => $fee->id,
            'feeReceipt' => $receipt->id,
        ]), ['rejection_reason' => 'Screenshot is unreadable']);
        $response->assertSessionHasNoErrors();

        $freshFee = $fee->fresh();
        $this->assertSame('rejected', $receipt->fresh()->status);
        $this->assertContains(
            $freshFee->status,
            ['pending', 'partial', 'rejected'],
            "Fee status is '{$freshFee->status}' after rejection — the school-facing upload button and the server-side guard both only allow re-upload for pending/partial/rejected, so this status leaves the school stuck with no way to resubmit."
        );
    }

    /**
     * The actual production bug: FestSchoolEventFeeController::reject() — the action behind
     * the "Reject" button on the cross-event "Fest Payments Queue" dashboard widget, the
     * most common admin rejection workflow — wrapped its state changes in DB::transaction()
     * but the closure's use() clause omitted $event despite the closure body referencing it
     * (to call demoteSiblingApprovals()). Every rejection through this route threw
     * "Undefined variable $event" and silently rolled back the whole transaction, leaving
     * the fee stuck at 'proof_uploaded' forever — exactly matching a school's real complaint
     * that they could not re-upload after a rejection.
     */
    public function test_school_can_still_upload_after_admin_rejects_via_the_payments_queue(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Queue Reject Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'QRS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Queue Reject School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Queue Reject Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'fee_settings' => ['fee_model' => 'per_item'],
        ]);

        $fee = FestSchoolEventFee::create([
            'event_id' => $event->id, 'school_id' => $school->id,
            'total_due' => 500, 'amount_paid' => 0, 'status' => 'proof_uploaded',
        ]);
        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'file_path' => 'fest/receipts/queue-first-attempt.jpg',
            'amount' => 500,
            'status' => 'uploaded',
        ]);
        $fee->update(['fee_receipt_id' => $receipt->id]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.school-fees.reject', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
            'schoolEventFee' => $fee->id,
        ]), ['rejection_reason' => 'Amount mismatch']);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $freshFee = $fee->fresh();
        $this->assertSame('rejected', $receipt->fresh()->status);
        $this->assertContains(
            $freshFee->status,
            ['pending', 'partial', 'rejected'],
            "Fee status is '{$freshFee->status}' after reject() — the school-facing upload button and the server-side guard both only allow re-upload for pending/partial/rejected, so this status leaves the school stuck with no way to resubmit."
        );
    }

    /**
     * Same reproduction, but for a phased_regional_billing event — the batch/level-scoped
     * fee record PhasedRegionBillingPanel.vue actually reads for its own "Upload Payment
     * Proof" button, going through FestRegistrationBatchFeeService::syncRollup() as well
     * (called via FestSchoolEventFeeController::syncBatchRollup() after every reject).
     */
    public function test_school_can_still_upload_for_a_phased_batch_level_after_rejection(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Reupload Batch Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RBS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Reupload Batch School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Reupload Batch Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'conductor_level' => 'sahodaya', 'status' => 'registration_open',
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'fee_settings' => [
                'fee_model' => 'kalolsavam_composite',
                'per_student_amount' => 400,
                'included_items_per_student' => 1,
                'extra_item_fee' => 50,
                'school_registration_flat' => 4000,
            ],
        ]);
        $level1 = FestRegistrationBatch::create([
            'event_id' => $root->id, 'code' => 'LEVEL_1', 'name' => 'Level 1', 'sort_order' => 1,
        ]);
        $digiPhase = FestEventPhase::create(['event_id' => $root->id, 'name' => 'Digi Fest', 'code' => 'DIGI', 'sort_order' => 1, 'registration_batch_id' => $level1->id]);

        // Real registration/participation so recalculateAll() (triggered by the rollup
        // resync after reject) computes a genuine non-zero total_due, instead of wiping it
        // to 0 and masking the bug behind isFullyPaid()'s "nothing due = approved" branch.
        $digiItem = FestEventItem::create([
            'event_id' => $root->id, 'phase_id' => $digiPhase->id,
            'title' => 'Digi Item', 'participant_type' => 'individual', 'is_enabled' => true,
            'is_mandatory' => false, 'quota_eligible' => false,
        ]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10', 'is_active' => true]);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Reupload Student',
            'gender' => 'male', 'dob' => '2012-01-01', 'status' => 'active', 'verified_at' => now(),
        ]);
        $registration = FestRegistration::create([
            'event_id' => $root->id, 'item_id' => $digiItem->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);
        FestParticipant::create([
            'registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer',
        ]);

        $level1Fee = app(\App\Services\Events\FestRegistrationBatchFeeService::class)
            ->recalculateBatch($root, $school->id, $level1);
        $this->assertGreaterThan(0, $level1Fee->total_due, 'Sanity check: the fixture must produce a real non-zero fee, or this test proves nothing.');

        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $level1Fee->id,
            'file_path' => 'fest/receipts/level1-first-attempt.jpg',
            'amount' => $level1Fee->total_due,
            'status' => 'uploaded',
        ]);
        $level1Fee->update(['fee_receipt_id' => $receipt->id, 'status' => 'proof_uploaded']);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.school-fees.receipts.reject', [
            'tenantId' => $sahodaya->id,
            'event' => $root->id,
            'schoolEventFee' => $level1Fee->id,
            'feeReceipt' => $receipt->id,
        ]), ['rejection_reason' => 'Amount does not match']);
        $response->assertSessionHasNoErrors();

        $freshLevel1Fee = $level1Fee->fresh();
        $this->assertSame('rejected', $receipt->fresh()->status);
        $this->assertContains(
            $freshLevel1Fee->status,
            ['pending', 'partial', 'rejected'],
            "Level 1 fee status is '{$freshLevel1Fee->status}' after rejection — PhasedRegionBillingPanel.vue's Upload Payment Proof button only shows for pending/partial/rejected, so this status leaves the school stuck with no way to resubmit for this level."
        );
    }

    /**
     * A school paying in installments (see claimableBalance()) can have TWO receipts
     * 'uploaded' at once. FestSchoolEventFeeController::reject() used to unconditionally
     * stamp the whole fee 'rejected' after rejecting one of them — even when the other
     * installment was still genuinely awaiting review — which hid it from the admin
     * queue's Pending filter (status === 'proof_uploaded' AND feeReceipt.status ===
     * 'uploaded'). Confirms rejecting one installment leaves the other one's pending
     * status, and the admin's ability to review it, intact.
     */
    public function test_rejecting_one_installment_does_not_hide_a_still_pending_sibling_installment(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Installment Reject Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'IRS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Installment Reject School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Installment Reject Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'fee_settings' => ['fee_model' => 'per_item'],
        ]);

        $fee = FestSchoolEventFee::create([
            'event_id' => $event->id, 'school_id' => $school->id,
            'total_due' => 500, 'amount_paid' => 0, 'status' => 'proof_uploaded',
        ]);
        $olderReceipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class, 'feeable_id' => $fee->id,
            'file_path' => 'fest/receipts/installment-300.jpg', 'amount' => 300, 'status' => 'uploaded',
        ]);
        $newerReceipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class, 'feeable_id' => $fee->id,
            'file_path' => 'fest/receipts/installment-200.jpg', 'amount' => 200, 'status' => 'uploaded',
        ]);
        $fee->update(['fee_receipt_id' => $newerReceipt->id]);

        // reject() targets "the latest uploaded receipt" when given no explicit receipt id
        // — same as the admin queue's plain Reject button.
        $response = $this->actingAs($admin)->post(route('sahodaya.events.school-fees.reject', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
            'schoolEventFee' => $fee->id,
        ]), ['rejection_reason' => 'Amount mismatch']);
        $response->assertSessionHasNoErrors();

        $this->assertSame('rejected', $newerReceipt->fresh()->status);
        $this->assertSame('uploaded', $olderReceipt->fresh()->status, 'The sibling installment must not be touched by rejecting the other one.');

        $freshFee = $fee->fresh();
        $this->assertSame(
            'proof_uploaded',
            $freshFee->status,
            'The fee must stay proof_uploaded (not be force-stamped rejected) while another installment is still genuinely pending review.'
        );
        $this->assertSame($olderReceipt->id, $freshFee->fee_receipt_id, 'fee_receipt_id must re-point to the still-pending sibling, or the admin queue\'s Approve/Reject buttons stay hidden behind the rejected receipt.');
    }
}
