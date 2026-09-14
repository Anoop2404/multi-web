<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\TestCase;

/**
 * A school could upload a second Talent Search batch-fee proof while an earlier one was
 * still awaiting review — FeeReceipt::supersedePriorForFeeable() silently marked the prior
 * (possibly for a much larger amount) 'superseded' with no warning. Fixed two ways: (1) a
 * school genuinely paying in installments (e.g. ₹1000 now, ₹500 later) gets both receipts
 * kept, reviewable independently, capped so their combined total can't exceed what's due
 * (McqSchoolFee::claimableBalance()); (2) a true duplicate (same amount, nothing left
 * unclaimed) is still rejected outright, matching the equivalent fix on the Fest/Kalotsav
 * side (FestSchoolEventFeeService::attachPayment() and friends).
 */
class McqSchoolFeeDuplicateSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_installment_proofs_coexist_and_are_both_reviewable(): void
    {
        Storage::fake(\App\Support\TenantStorage::SHARED_DISK);
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Installment Fee Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'IFS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Installment Fee School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $admin->assignRole('school_admin');

        $exam = McqExam::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Installment Fee MCQ', 'exam_type' => 'assessment',
            'status' => 'published', 'fee_type' => 'flat', 'fee_amount' => 1500,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10', 'is_active' => true]);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Installment Fee Student',
            'gender' => 'male', 'status' => 'active',
        ]);
        McqRegistration::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'school_id' => $school->id, 'status' => 'registered']);

        McqSchoolFee::create([
            'exam_id' => $exam->id, 'school_id' => $school->id,
            'student_count' => 1, 'total_due' => 1500, 'amount_paid' => 0, 'status' => 'pending',
        ]);

        $first = $this->actingAs($admin)->post("/school-admin/{$school->id}/mcq/{$exam->id}/school-payment", [
            'payment_proof' => [UploadedFile::fake()->create('proof-1000.pdf', 100, 'application/pdf')],
            'amount' => 1000,
        ]);
        $first->assertSessionHasNoErrors();

        $second = $this->actingAs($admin)->post("/school-admin/{$school->id}/mcq/{$exam->id}/school-payment", [
            'payment_proof' => [UploadedFile::fake()->create('proof-500.pdf', 100, 'application/pdf')],
            'amount' => 500,
        ]);
        $second->assertSessionHasNoErrors('The second, smaller installment must be accepted while the first is still pending review.');

        $fee = McqSchoolFee::where('exam_id', $exam->id)->where('school_id', $school->id)->first();
        $receipts = $fee->receipts()->orderBy('id')->get();
        $this->assertCount(2, $receipts, 'Both installments must survive as separate receipts, not one superseding the other.');
        $this->assertSame('uploaded', $receipts[0]->status, 'The first installment must not be silently superseded by the second.');
        $this->assertSame('uploaded', $receipts[1]->status);
        $this->assertSame(1000.0, (float) $receipts[0]->amount);
        $this->assertSame(500.0, (float) $receipts[1]->amount);
        $this->assertSame(0.0, $fee->claimableBalance(), 'Nothing should remain claimable once pending proofs cover the full amount due.');

        // Now fully claimed — a third submission must be rejected, same as a true duplicate.
        $third = $this->actingAs($admin)->post("/school-admin/{$school->id}/mcq/{$exam->id}/school-payment", [
            'payment_proof' => [UploadedFile::fake()->create('proof-extra.pdf', 100, 'application/pdf')],
        ]);
        $third->assertStatus(422);
        $this->assertCount(2, $fee->receipts()->get(), 'A third submission must not create a receipt once the full balance is already claimed.');
    }

    public function test_uploading_a_second_proof_for_the_same_full_amount_while_the_first_is_pending_is_rejected(): void
    {
        Storage::fake(\App\Support\TenantStorage::SHARED_DISK);
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Dup Fee Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'DFS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Dup Fee School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $admin->assignRole('school_admin');

        $exam = McqExam::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Dup Fee MCQ', 'exam_type' => 'assessment',
            'status' => 'published', 'fee_type' => 'flat', 'fee_amount' => 100,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10', 'is_active' => true]);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Dup Fee Student',
            'gender' => 'male', 'status' => 'active',
        ]);
        McqRegistration::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'school_id' => $school->id, 'status' => 'registered']);

        McqSchoolFee::create([
            'exam_id' => $exam->id, 'school_id' => $school->id,
            'student_count' => 1, 'total_due' => 100, 'amount_paid' => 0, 'status' => 'pending',
        ]);

        $firstUpload = $this->actingAs($admin)->post("/school-admin/{$school->id}/mcq/{$exam->id}/school-payment", [
            'payment_proof' => [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')],
        ]);
        $firstUpload->assertSessionHasNoErrors();
        $this->assertSame('proof_uploaded', McqSchoolFee::where('exam_id', $exam->id)->where('school_id', $school->id)->first()->status);

        $secondUpload = $this->actingAs($admin)->post("/school-admin/{$school->id}/mcq/{$exam->id}/school-payment", [
            'payment_proof' => [UploadedFile::fake()->create('proof2.pdf', 100, 'application/pdf')],
        ]);
        $secondUpload->assertStatus(422);

        $fee = McqSchoolFee::where('exam_id', $exam->id)->where('school_id', $school->id)->first();
        $this->assertSame(1, $fee->receipts()->count(), 'The second submission must be rejected before it can supersede the first pending proof.');
        $this->assertSame('uploaded', $fee->receipts()->first()->status, 'The original pending proof must survive untouched, not get silently superseded.');
    }

    /**
     * A school paying in installments can have TWO receipts 'uploaded' at once.
     * McqSchoolFeeService::reject() didn't re-point fee_receipt_id after rejecting one of
     * them, so the admin queue's Approve/Reject buttons (keyed off feeReceipt->status)
     * stayed hidden behind the just-rejected receipt even though the sibling installment
     * was still genuinely awaiting review. Confirms rejecting one leaves the other
     * reviewable.
     */
    public function test_rejecting_one_installment_does_not_hide_a_still_pending_sibling_installment(): void
    {
        Storage::fake(\App\Support\TenantStorage::SHARED_DISK);
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

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');
        $sahodayaAdmin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $exam = McqExam::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Installment Reject MCQ', 'exam_type' => 'assessment',
            'status' => 'published', 'fee_type' => 'flat', 'fee_amount' => 1500,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10', 'is_active' => true]);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Installment Reject Student',
            'gender' => 'male', 'status' => 'active',
        ]);
        McqRegistration::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'school_id' => $school->id, 'status' => 'registered']);

        McqSchoolFee::create([
            'exam_id' => $exam->id, 'school_id' => $school->id,
            'student_count' => 1, 'total_due' => 1500, 'amount_paid' => 0, 'status' => 'pending',
        ]);

        $this->actingAs($schoolAdmin)->post("/school-admin/{$school->id}/mcq/{$exam->id}/school-payment", [
            'payment_proof' => [UploadedFile::fake()->create('proof-1000.pdf', 100, 'application/pdf')],
            'amount' => 1000,
        ])->assertSessionHasNoErrors();
        $this->actingAs($schoolAdmin)->post("/school-admin/{$school->id}/mcq/{$exam->id}/school-payment", [
            'payment_proof' => [UploadedFile::fake()->create('proof-500.pdf', 100, 'application/pdf')],
            'amount' => 500,
        ])->assertSessionHasNoErrors();

        $fee = McqSchoolFee::where('exam_id', $exam->id)->where('school_id', $school->id)->first();
        [$olderReceipt, $newerReceipt] = $fee->receipts()->orderBy('id')->get()->all();

        // McqPaymentsController::reject() targets "the latest uploaded receipt" when given
        // no explicit receipt id — same as the admin queue's plain Reject button.
        $this->actingAs($sahodayaAdmin)->post("/sahodaya-admin/{$sahodaya->id}/mcq/payments/{$fee->id}/reject", [
            'rejection_reason' => 'Amount mismatch',
        ])->assertSessionHasNoErrors();

        $this->assertSame('rejected', $newerReceipt->fresh()->status);
        $this->assertSame('uploaded', $olderReceipt->fresh()->status, 'The sibling installment must not be touched by rejecting the other one.');

        $freshFee = $fee->fresh();
        $this->assertSame(
            'proof_uploaded',
            $freshFee->status,
            'The fee must stay proof_uploaded while another installment is still genuinely pending review.'
        );
        $this->assertSame($olderReceipt->id, $freshFee->fee_receipt_id, 'fee_receipt_id must re-point to the still-pending sibling, or the admin queue\'s Approve/Reject buttons stay hidden behind the rejected receipt.');
    }
}
