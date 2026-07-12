<?php

namespace Tests\Feature;

use App\Models\FeeReceipt;
use App\Models\LedgerTransaction;
use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger\FeeReceiptReversalService;
use App\Services\Ledger\LedgerService;
use App\Services\Membership\FeeReceiptService;
use App\Support\AcademicYear;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeeReceiptReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_reversal_posts_compensating_ledger_and_marks_receipt_reversed(): void
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Reversal Sahodaya',
            'domain'    => 'reversal-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'REV',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Reversal School',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'RS',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $user = User::factory()->create();

        $registration = Registration::create([
            'school_id'             => $school->id,
            'academic_year'         => AcademicYear::current(),
            'registration_status'   => 'approved',
            'membership_fee_amount' => 500,
        ]);

        $payment = MembershipPayment::create([
            'school_id'          => $school->id,
            'academic_year'      => AcademicYear::current(),
            'registration_id'    => $registration->id,
            'amount'             => 500,
            'payment_proof_path' => 'payments/test/proof.png',
            'status'             => 'verified',
            'verified_by_user_id'=> $user->id,
            'verified_at'        => now(),
        ]);

        $receipt = app(FeeReceiptService::class)->createForMembershipPayment($payment);
        $receipt->update([
            'status'      => FeeReceipt::STATUS_APPROVED,
            'payment_date'=> now()->toDateString(),
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        app(LedgerService::class)->postFeeReceipt($receipt->fresh(), $sahodaya->id);
        $this->assertSame(2, LedgerTransaction::where('reference_type', FeeReceipt::class)->count());

        $reversed = app(FeeReceiptReversalService::class)->reverse($receipt->fresh(), $user, 'Chargeback');

        $this->assertSame(FeeReceipt::STATUS_REVERSED, $reversed->status);
        $this->assertSame(2, LedgerTransaction::where('reference_type', FeeReceipt::REVERSAL_REFERENCE)->count());

        $netDebit = (float) LedgerTransaction::where('entry_type', 'debit')->sum('amount');
        $netCredit = (float) LedgerTransaction::where('entry_type', 'credit')->sum('amount');
        $this->assertEquals($netDebit, $netCredit);

        $payment->refresh();
        $this->assertSame('rejected', $payment->status);
    }
}
