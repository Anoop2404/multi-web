<?php

namespace Tests\Feature;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MembershipSchoolFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_schools_index_filters_by_no_proof_payment_status_without_query_exception(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Malabar Sahodaya',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        \Spatie\Permission\Models\Role::create(['name' => 'sahodaya_admin', 'guard_name' => 'web']);

        $adminUser = User::factory()->create([
            'tenant_id' => $sahodaya->id,
            'is_active' => true,
        ]);
        $adminUser->assignRole('sahodaya_admin');

        $schoolWithPayment = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'name' => 'School With Payment',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $schoolWithoutPayment = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'name' => 'School Without Payment',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $academicYear = AcademicYear::forSahodaya($sahodaya->id);

        MembershipPayment::create([
            'school_id' => $schoolWithPayment->id,
            'academic_year' => $academicYear,
            'amount' => 5000,
            'payment_proof_path' => 'payments/proof.pdf',
            'status' => 'submitted',
            'payment_method' => 'NEFT',
        ]);

        $response = $this->actingAs($adminUser)
            ->get(route('sahodaya.schools.index', [
                'tenantId' => $sahodaya->id,
                'payment_status' => 'no_proof',
            ]));

        $response->assertStatus(200);

        $inertiaData = $response->inertiaProps();
        $schoolNames = collect($inertiaData['schools']['data'])->pluck('name')->all();

        $this->assertContains('SCHOOL WITHOUT PAYMENT', $schoolNames);
        $this->assertNotContains('SCHOOL WITH PAYMENT', $schoolNames);

        // Test payment_pending filter
        $pendingResponse = $this->actingAs($adminUser)
            ->get(route('sahodaya.schools.index', [
                'tenantId' => $sahodaya->id,
                'payment_status' => 'payment_pending',
            ]));
        $pendingResponse->assertStatus(200);
        $pendingNames = collect($pendingResponse->inertiaProps()['schools']['data'])->pluck('name')->all();
        $this->assertContains('SCHOOL WITH PAYMENT', $pendingNames);
        $this->assertNotContains('SCHOOL WITHOUT PAYMENT', $pendingNames);
    }

    public function test_create_school_login_provisions_user_account(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Wayanad Sahodaya',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        \Spatie\Permission\Models\Role::create(['name' => 'sahodaya_admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'school_admin', 'guard_name' => 'web']);

        $adminUser = User::factory()->create([
            'tenant_id' => $sahodaya->id,
            'is_active' => true,
        ]);
        $adminUser->assignRole('sahodaya_admin');

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'name' => 'St Marys High School',
            'membership_status' => 'pending',
            'is_active' => true,
            'application_payload' => [
                'school_email' => 'stmarys@example.com',
                'school_name'  => 'St Marys High School',
            ],
        ]);

        $this->assertFalse(User::where('tenant_id', $school->id)->exists());

        $response = $this->actingAs($adminUser)
            ->post(route('sahodaya.schools.create-login', [
                'tenantId' => $sahodaya->id,
                'school'   => $school->id,
            ]), [
                'email' => 'stmarys@example.com',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user = User::where('tenant_id', $school->id)->first();
        $this->assertNotNull($user);
        $this->assertEquals('stmarys@example.com', $user->email);
        $this->assertTrue($user->hasRole('school_admin'));
    }
}
