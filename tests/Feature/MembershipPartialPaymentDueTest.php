<?php

namespace Tests\Feature;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Membership\PaymentDueResolver;
use App\Support\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MembershipPartialPaymentDueTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_due_resolver_returns_remaining_balance_for_partial_payment(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Wayanad Sahodaya',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'membership_fee_type' => 'fixed',
            'fixed_membership_fee_amount' => 35000,
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'name' => 'Amrita Vidyalayam, Pulpally',
            'membership_status' => 'approved',
            'school_prefix' => 'AMP',
            'is_active' => true,
        ]);

        $academicYear = AcademicYear::forSahodaya($sahodaya->id);

        $registration = Registration::create([
            'school_id' => $school->id,
            'academic_year' => $academicYear,
            'reg_no' => 'WS/27/5',
            'membership_fee_amount' => 35000,
            'amount_paid' => 5000,
            'registration_status' => 'payment_pending',
        ]);

        $payment1 = MembershipPayment::create([
            'school_id' => $school->id,
            'academic_year' => $academicYear,
            'registration_id' => $registration->id,
            'amount' => 5000,
            'payment_proof_path' => 'payments/proof1.pdf',
            'status' => 'verified',
            'payment_method' => 'NEFT',
            'transaction_ref' => 'NEFT12345',
        ]);

        $resolver = app(PaymentDueResolver::class);
        $items = $resolver->items($sahodaya->id, [$school->id], $academicYear);

        $this->assertCount(1, $items);
        $item = $items->first();

        // Should return remaining balance due (35000 - 5000 = 30000)
        $this->assertEquals(30000.0, $item['membership_fee_amount']);
        $this->assertEquals(35000.0, $item['total_fee_amount']);
        $this->assertEquals(5000.0, $item['amount_paid']);
        $this->assertEquals(30000.0, $registration->outstandingBalance());

        // Test partialItems resolver
        $partialItems = $resolver->partialItems($sahodaya->id, [$school->id], $academicYear);
        $this->assertCount(1, $partialItems);
        $partialItem = $partialItems->first();
        $this->assertEquals(30000.0, $partialItem['membership_fee_amount']);
        $this->assertCount(1, $partialItem['school_payments']);
        $this->assertEquals(5000.0, $partialItem['school_payments'][0]['amount']);
        $this->assertEquals('verified', $partialItem['school_payments'][0]['status']);
    }
}
