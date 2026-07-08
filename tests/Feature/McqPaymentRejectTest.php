<?php

namespace Tests\Feature;

use App\Models\FeeReceipt;
use App\Models\McqExam;
use App\Models\McqSchoolFee;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mcq\McqSchoolFeeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class McqPaymentRejectTest extends TestCase
{
    use RefreshDatabase;

    public function test_sahodaya_can_reject_uploaded_mcq_batch_fee(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'domain'    => 'mcq-reject.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'TS',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Test School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        Role::findByName('sahodaya_admin', 'web');
        $admin->assignRole('sahodaya_admin');

        $exam = McqExam::create([
            'tenant_id'  => $sahodaya->id,
            'title'      => 'Math MCQ',
            'exam_type'  => 'assessment',
            'status'     => 'published',
            'fee_type'   => 'flat',
            'fee_amount' => 50,
        ]);

        $schoolFee = McqSchoolFee::create([
            'exam_id'       => $exam->id,
            'school_id'     => $school->id,
            'student_count' => 2,
            'total_due'     => 100,
            'status'        => 'proof_uploaded',
        ]);

        $receipt = FeeReceipt::create([
            'feeable_type' => McqSchoolFee::class,
            'feeable_id'   => $schoolFee->id,
            'file_path'    => 'mcq-payments/test/proof.png',
            'amount'       => 100,
            'status'       => 'uploaded',
            'payment_date' => now()->toDateString(),
        ]);

        $schoolFee->update(['fee_receipt_id' => $receipt->id]);

        app(McqSchoolFeeService::class)->reject($schoolFee->fresh(), $admin->id, 'Amount mismatch');

        $receipt->refresh();
        $schoolFee->refresh();

        $this->assertSame('rejected', $receipt->status);
        $this->assertSame('Amount mismatch', $receipt->rejection_reason);
        $this->assertSame('pending', $schoolFee->status);
    }
}
