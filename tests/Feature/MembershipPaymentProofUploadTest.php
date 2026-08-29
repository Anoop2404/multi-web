<?php

namespace Tests\Feature;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Membership\PaymentDueResolver;
use App\Support\AcademicYear;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class MembershipPaymentProofUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_verified_proof_upload_syncs_registration_and_leaves_payment_due_tabs(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Central Sahodaya',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'                   => $sahodaya->id,
            'membership_fee_type'         => 'fixed',
            'fixed_membership_fee_amount' => 4000,
            'student_data_mode'           => 'counts_only',
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

        $academicYear = AcademicYear::forSahodaya($sahodaya->id);

        $registration = Registration::create([
            'school_id'             => $school->id,
            'academic_year'         => $academicYear,
            'reg_no'                => 'MALCS/27/13',
            'membership_fee_amount' => 4000,
            'amount_paid'           => 0,
            'registration_status'   => 'payment_pending',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->post(
            "/sahodaya-admin/{$sahodaya->id}/schools/{$school->id}/payment-proof",
            [
                'amount'            => 4000,
                'status'            => 'verified',
                'payment_reference' => 'NEFT12345',
                'notes'             => '',
                'proof'             => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
            ],
        );

        $response->assertSessionHasNoErrors();

        $registration->refresh();
        $this->assertSame('completed', $registration->registration_status);
        $this->assertEquals(4000.0, (float) $registration->amount_paid);

        $payment = MembershipPayment::where('school_id', $school->id)->firstOrFail();
        $this->assertSame('verified', $payment->status);
        $this->assertSame($registration->id, $payment->registration_id);
        $this->assertNotNull($payment->verified_at);

        $resolver = app(PaymentDueResolver::class);

        // Fully paid: must not appear under "Payment Not Done"...
        $due = $resolver->items($sahodaya->id, [$school->id], $academicYear);
        $this->assertCount(0, $due);

        // ...nor under "Partial Payment" (regression for the 'completed' rows
        // that used to slip through partialItems()'s stale status filter).
        $partial = $resolver->partialItems($sahodaya->id, [$school->id], $academicYear);
        $this->assertCount(0, $partial);
    }

    public function test_admin_submitted_proof_upload_moves_school_out_of_payment_not_done(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Central Sahodaya',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'                   => $sahodaya->id,
            'membership_fee_type'         => 'fixed',
            'fixed_membership_fee_amount' => 4000,
            'student_data_mode'           => 'counts_only',
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'ALFOUZ JAMIA ENGLISH SCHOOL',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'AJES',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $academicYear = AcademicYear::forSahodaya($sahodaya->id);

        $registration = Registration::create([
            'school_id'             => $school->id,
            'academic_year'         => $academicYear,
            'reg_no'                => 'MALCS/27/99',
            'membership_fee_amount' => 4000,
            'amount_paid'           => 0,
            'registration_status'   => 'payment_pending',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $resolver = app(PaymentDueResolver::class);
        $this->assertCount(1, $resolver->items($sahodaya->id, [$school->id], $academicYear));

        $response = $this->actingAs($admin)->post(
            "/sahodaya-admin/{$sahodaya->id}/schools/{$school->id}/payment-proof",
            [
                'amount'            => 4000,
                'status'            => 'submitted',
                'payment_reference' => 'NEFT99999',
                'notes'             => '',
                'proof'             => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
            ],
        );

        $response->assertSessionHasNoErrors();

        $registration->refresh();
        $this->assertSame('payment_submitted', $registration->registration_status);
        $this->assertEquals(0.0, (float) $registration->amount_paid);

        $payment = MembershipPayment::where('school_id', $school->id)->firstOrFail();
        $this->assertSame('submitted', $payment->status);
        $this->assertSame($registration->id, $payment->registration_id);

        $this->assertCount(0, $resolver->items($sahodaya->id, [$school->id], $academicYear));
    }
}
