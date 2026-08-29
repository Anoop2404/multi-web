<?php

namespace Tests\Feature;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FixMembershipRegistrationCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_relinks_orphaned_verified_payment_and_resyncs_registration(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Central Sahodaya',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Ideal International School, Irimbiliyam',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'IIS',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $registration = Registration::create([
            'school_id'             => $school->id,
            'academic_year'         => '2026-27',
            'reg_no'                => 'MALCS/27/13',
            'membership_fee_amount' => 4000,
            'amount_paid'           => 0,
            'registration_status'   => 'payment_pending',
        ]);

        $payment = MembershipPayment::create([
            'school_id'          => $school->id,
            'academic_year'      => '2026-27',
            'registration_id'    => null,
            'amount'             => 4000,
            'payment_proof_path' => 'membership/payment_proofs/proof.pdf',
            'status'             => 'verified',
        ]);

        $this->artisan('membership:fix-registration-cache', [
            'reg_no'      => 'MALCS/27/13',
            '--sahodaya'  => $sahodaya->id,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $registration->refresh();
        $payment->refresh();

        $this->assertSame('completed', $registration->registration_status);
        $this->assertEquals(4000.0, (float) $registration->amount_paid);
        $this->assertSame($registration->id, $payment->registration_id);
    }

    public function test_dry_run_reports_without_saving(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Central Sahodaya',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Dry Run School',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'DRS',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $registration = Registration::create([
            'school_id'             => $school->id,
            'academic_year'         => '2026-27',
            'reg_no'                => 'MALCS/27/99',
            'membership_fee_amount' => 4000,
            'amount_paid'           => 0,
            'registration_status'   => 'payment_pending',
        ]);

        MembershipPayment::create([
            'school_id'          => $school->id,
            'academic_year'      => '2026-27',
            'registration_id'    => null,
            'amount'             => 4000,
            'payment_proof_path' => 'membership/payment_proofs/proof.pdf',
            'status'             => 'verified',
        ]);

        $this->artisan('membership:fix-registration-cache', [
            'reg_no'     => 'MALCS/27/99',
            '--sahodaya' => $sahodaya->id,
            '--dry-run'  => true,
        ])->assertExitCode(0);

        $registration->refresh();
        $this->assertSame('payment_pending', $registration->registration_status);
        $this->assertEquals(0.0, (float) $registration->amount_paid);
    }
}
