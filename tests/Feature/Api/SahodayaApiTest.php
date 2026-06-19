<?php

namespace Tests\Feature\Api;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SahodayaApiTest extends TestCase
{
    use RefreshDatabase;

    private function sahodayaContext(): array
    {
        $this->seed([SahodayaMasterDataSeeder::class, RolesAndPermissionsSeeder::class]);

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
            'school_id'             => $school->id,
            'academic_year'         => AcademicYear::current(),
            'registration_status'   => 'payment_submitted',
            'membership_fee_amount' => 5000,
        ]);

        $payment = MembershipPayment::create([
            'school_id'          => $school->id,
            'academic_year'      => AcademicYear::current(),
            'registration_id'    => $registration->id,
            'amount'             => 5000,
            'payment_proof_path' => 'payments/'.$school->id.'/proof.png',
            'status'             => 'submitted',
        ]);

        $user = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $user->assignRole('sahodaya_admin');
        $token = $user->createToken('test')->plainTextToken;

        return compact('sahodaya', 'school', 'registration', 'payment', 'token');
    }

    private function auth(string $token): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_sahodaya_admin_can_view_school_details(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'token' => $token] = $this->sahodayaContext();

        $school->update([
            'application_payload' => [
                'school_email' => 'school@gmail.com',
                'phone' => '9876543210',
                'cbse_affiliation' => '930319',
            ],
        ]);

        $this->auth($token)
            ->getJson("/api/v1/sahodaya/{$sahodaya->id}/schools/{$school->id}")
            ->assertOk()
            ->assertJsonPath('data.school.name', 'Govt HS')
            ->assertJsonPath('data.school.has_login', false)
            ->assertJsonStructure([
                'data' => [
                    'school',
                    'detail_fields',
                    'registration',
                    'recent_payments',
                    'academic_year',
                ],
            ]);
    }

    public function test_sahodaya_admin_can_list_payment_due_registrations(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'registration' => $registration, 'payment' => $payment, 'token' => $token] = $this->sahodayaContext();

        $payment->delete();
        $registration->update([
            'registration_status'   => 'payment_pending',
            'membership_fee_amount' => 4500,
        ]);

        $this->auth($token)
            ->getJson("/api/v1/sahodaya/{$sahodaya->id}/payments?status=payment-due")
            ->assertOk()
            ->assertJsonPath('meta.active_status', 'payment-due')
            ->assertJsonPath('meta.status_counts.payment-due', 1)
            ->assertJsonPath('data.0.registration_status', 'payment_pending')
            ->assertJsonPath('data.0.school.name', 'Govt HS');
    }

    public function test_pending_membership_schools_without_payment_show_as_payment_due(): void
    {
        ['sahodaya' => $sahodaya, 'token' => $token] = $this->sahodayaContext();

        SahodayaProfile::where('tenant_id', $sahodaya->id)->update([
            'membership_fee_type'          => 'fixed',
            'fixed_membership_fee_amount'  => 4000,
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

        $this->auth($token)
            ->getJson("/api/v1/sahodaya/{$sahodaya->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.stats.payment_due', 2)
            ->assertJsonPath('data.stats.payment_due_amount', 8000);

        $this->auth($token)
            ->getJson("/api/v1/sahodaya/{$sahodaya->id}/payments?status=payment-due")
            ->assertOk()
            ->assertJsonPath('meta.status_counts.payment-due', 2)
            ->assertJsonPath('meta.summary.payment_due_amount', 8000)
            ->assertJsonPath('data.0.source', 'pending_membership')
            ->assertJsonPath('data.0.membership_fee_amount', 4000);
    }

    public function test_sahodaya_admin_can_list_payments(): void
    {
        ['sahodaya' => $sahodaya, 'token' => $token] = $this->sahodayaContext();

        $this->auth($token)
            ->getJson("/api/v1/sahodaya/{$sahodaya->id}/payments")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_sahodaya_admin_can_verify_payment_via_api(): void
    {
        ['sahodaya' => $sahodaya, 'payment' => $payment, 'registration' => $registration, 'token' => $token] = $this->sahodayaContext();

        $this->auth($token)
            ->postJson("/api/v1/sahodaya/{$sahodaya->id}/payments/{$payment->id}/verify", [
                'action' => 'verify',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $registration->refresh();
        $this->assertSame('completed', $registration->registration_status);
    }

    public function test_sahodaya_admin_cannot_access_other_tenant(): void
    {
        ['token' => $token] = $this->sahodayaContext();

        $this->auth($token)
            ->getJson('/api/v1/sahodaya/'.Str::uuid().'/dashboard')
            ->assertForbidden();
    }

    public function test_sahodaya_admin_can_stream_payment_proof(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'payment' => $payment, 'token' => $token] = $this->sahodayaContext();

        $absolute = base_path('storage/tenant'.$school->id.'/app/public/'.$payment->payment_proof_path);
        @mkdir(dirname($absolute), 0777, true);
        file_put_contents($absolute, 'proof-image');

        $this->auth($token)
            ->get("/api/v1/sahodaya/{$sahodaya->id}/payments/{$payment->id}/proof")
            ->assertOk();

        @unlink($absolute);
    }
}
