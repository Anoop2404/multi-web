<?php

namespace Tests\Feature\Api;

use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PortalVerifyEmail;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_returns_fields_for_sahodaya_domain(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'domain'    => 'api-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create(['tenant_id' => $sahodaya->id]);

        $this->getJson('http://api-sahodaya.test/api/v1/public/school-register')
            ->assertOk()
            ->assertJsonPath('data.tenant_name', 'Test Sahodaya')
            ->assertJsonStructure([
                'data' => ['fields', 'highest_class_options', 'two_step'],
            ]);
    }

    public function test_submit_creates_pending_school_via_api(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'domain'    => 'api-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create(['tenant_id' => $sahodaya->id]);

        $this->postJson('http://api-sahodaya.test/api/v1/public/school-register', [
            'school_name'              => 'API Demo School',
            'school_email'             => 'api.demo.school@gmail.com',
            'school_prefix'            => 'ADS',
            'cbse_affiliation'         => '930320',
            'phone'                    => '9876543210',
            'highest_class'            => 'Class 12',
            'principal_name'           => 'Principal Demo',
            'principal_email'          => 'principal.demo@gmail.com',
            'principal_phone'          => '9876543211',
            'vice_principal_name'      => 'VP Demo',
            'vice_principal_email'     => 'vp.demo@gmail.com',
            'vice_principal_phone'     => '9876543212',
            'event_coordinator_name'   => 'EC Demo',
            'event_coordinator_email'  => 'ec.demo@gmail.com',
            'event_coordinator_phone'  => '9876543213',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'api.demo.school@gmail.com');

        $school = Tenant::where('name', 'API Demo School')->first();
        $this->assertNotNull($school);
        $this->assertSame('pending', $school->membership_status);

        $user = User::where('email', 'api.demo.school@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('school_admin'));

        Notification::assertSentTo($user, PortalVerifyEmail::class);
    }

    public function test_validate_rejects_duplicate_school_prefix(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'domain'    => 'api-sahodaya.test',
            'is_active' => true,
        ]);

        Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Existing School',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'TEST3',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $this->postJson('http://api-sahodaya.test/api/v1/public/school-register/validate', [
            'field' => 'school_prefix',
            'value' => 'TEST3',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['school_prefix']);
    }
}
