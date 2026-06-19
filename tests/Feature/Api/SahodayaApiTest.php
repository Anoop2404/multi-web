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
