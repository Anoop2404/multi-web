<?php

namespace Tests\Feature;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Support\AcademicYear;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function cluster(): array
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'KNR Sahodaya',
            'domain'    => 'knr-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'KNR',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Govt HS',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'GHS',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $registration = Registration::create([
            'school_id'            => $school->id,
            'academic_year'        => AcademicYear::current(),
            'registration_status'  => 'payment_submitted',
            'membership_fee_amount'=> 5000,
        ]);

        $payment = MembershipPayment::create([
            'school_id'          => $school->id,
            'academic_year'      => AcademicYear::current(),
            'registration_id'    => $registration->id,
            'amount'             => 5000,
            'payment_proof_path' => 'payments/'.$school->id.'/proof.png',
            'status'             => 'submitted',
        ]);

        return compact('sahodaya', 'school', 'registration', 'payment');
    }

    public function test_sahodaya_admin_can_reject_payment(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'payment' => $payment, 'registration' => $registration] = $this->cluster();

        $admin = \App\Models\User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->post(
            "/sahodaya-admin/{$sahodaya->id}/membership/payments/{$payment->id}/verify",
            ['action' => 'reject', 'reason' => 'Invalid transaction reference'],
        );

        $response->assertRedirect();

        $payment->refresh();
        $registration->refresh();

        $this->assertSame('rejected', $payment->status);
        $this->assertSame('Invalid transaction reference', $payment->rejection_reason);
        $this->assertSame('payment_rejected', $registration->registration_status);
    }

    public function test_sahodaya_admin_can_verify_payment(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'payment' => $payment, 'registration' => $registration] = $this->cluster();

        $admin = \App\Models\User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->post(
            "/sahodaya-admin/{$sahodaya->id}/membership/payments/{$payment->id}/verify",
            ['action' => 'verify'],
        );

        $response->assertRedirect();

        $payment->refresh();
        $registration->refresh();

        $this->assertSame('verified', $payment->status);
        $this->assertSame('completed', $registration->registration_status);
    }

    public function test_sahodaya_admin_can_stream_local_payment_proof(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'school' => $school, 'payment' => $payment] = $this->cluster();

        $absolute = base_path('storage/tenant'.$school->id.'/app/public/'.$payment->payment_proof_path);
        @mkdir(dirname($absolute), 0777, true);
        file_put_contents($absolute, 'proof-image');

        $admin = \App\Models\User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/membership/payments/{$payment->id}/proof")
            ->assertOk();

        @unlink($absolute);
    }

    public function test_web_dashboard_shows_pending_schools_as_payment_due(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya] = $this->cluster();

        SahodayaProfile::where('tenant_id', $sahodaya->id)->update([
            'membership_fee_type'         => 'fixed',
            'fixed_membership_fee_amount' => 4000,
        ]);

        Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Pending School A',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'PSA',
            'membership_status' => 'pending',
            'is_active'         => false,
        ]);

        Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Pending School B',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'PSB',
            'membership_status' => 'pending',
            'is_active'         => false,
        ]);

        $admin = \App\Models\User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.payment_due', 2)
                ->where('stats.payment_due_amount', 8000));

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/membership/payments?status=payment-due")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.payment_due', 2)
                ->where('summary.payment_due_amount', 8000)
                ->has('paymentDue.data', 2));
    }
}
