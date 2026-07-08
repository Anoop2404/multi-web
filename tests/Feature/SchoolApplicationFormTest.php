<?php

namespace Tests\Feature;

use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Support\SchoolApplicationForm;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolApplicationFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_form_respects_disabled_fields(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'subdomain' => 'test-sahodaya',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'application_form_config' => [
                'website'        => ['enabled' => false, 'required' => false],
                'district'       => ['enabled' => false, 'required' => false],
                'password'       => ['enabled' => false, 'required' => false],
                'cbse_affiliation' => ['enabled' => false, 'required' => false],
            ],
        ]);

        $fields = SchoolApplicationForm::resolve(SahodayaProfile::first());

        $this->assertFalse($fields['website']['enabled']);
        $this->assertFalse($fields['password']['enabled']);
        $this->assertTrue($fields['school_email']['enabled']);
        $this->assertTrue($fields['school_prefix']['enabled']);
        $this->assertTrue($fields['cbse_affiliation']['enabled']);
        $this->assertTrue($fields['cbse_affiliation']['required']);
    }

    public function test_principal_and_leadership_hidden_by_default_on_public_form(): void
    {
        $fields = SchoolApplicationForm::resolve(null);

        foreach ([
            'principal_name', 'principal_email', 'principal_phone',
            'vice_principal_name', 'vice_principal_email', 'vice_principal_phone',
            'event_coordinator_name', 'event_coordinator_email', 'event_coordinator_phone',
        ] as $key) {
            $this->assertFalse($fields[$key]['enabled'], "{$key} should be hidden on public registration by default");
            $this->assertFalse($fields[$key]['required'], "{$key} should not be required on public registration by default");
        }
    }

    public function test_school_prefix_must_be_unique_within_sahodaya(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'subdomain' => 'test-sahodaya',
            'is_active' => true,
        ]);

        Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Existing School',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'GHS',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $this->assertTrue(SchoolApplicationForm::prefixIsTaken($sahodaya, 'GHS'));
        $this->assertTrue(SchoolApplicationForm::prefixIsTaken($sahodaya, 'ghs'));
        $this->assertFalse(SchoolApplicationForm::prefixIsTaken($sahodaya, 'AMU'));

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['school_prefix' => 'GHS'],
            ['school_prefix' => SchoolApplicationForm::schoolPrefixRules($sahodaya)],
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('school_prefix', $validator->errors()->toArray());
    }

    public function test_affiliation_number_must_be_unique_within_sahodaya(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'subdomain' => 'test-sahodaya',
            'is_active' => true,
        ]);

        Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Existing School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
            'application_payload' => [
                'cbse_affiliation' => '930319',
            ],
        ]);

        $this->assertTrue(SchoolApplicationForm::affiliationIsTaken($sahodaya, '930319'));
        $this->assertTrue(SchoolApplicationForm::affiliationIsTaken($sahodaya, '930 319'));
        $this->assertFalse(SchoolApplicationForm::affiliationIsTaken($sahodaya, '930320'));
    }

    public function test_rejected_school_affiliation_can_be_reused(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'subdomain' => 'test-sahodaya',
            'is_active' => true,
        ]);

        Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Rejected School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'rejected',
            'is_active'         => false,
            'application_payload' => [
                'cbse_affiliation' => '930319',
            ],
        ]);

        $this->assertFalse(SchoolApplicationForm::affiliationIsTaken($sahodaya, '930319'));
    }

    public function test_registration_form_config_can_be_saved_from_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'subdomain' => 'test-sahodaya',
            'is_active' => true,
        ]);

        SahodayaProfile::create(['tenant_id' => $sahodaya->id]);

        $admin = \App\Models\User::factory()->create([
            'tenant_id' => $sahodaya->id,
        ]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->put("/sahodaya-admin/{$sahodaya->id}/membership/application-form", [
            'fields' => [
                'website'  => ['enabled' => true, 'required' => false],
                'district' => ['enabled' => true, 'required' => true],
            ],
        ]);

        $response->assertRedirect();

        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->first();
        $this->assertTrue($profile->application_form_config['district']['required']);
        $this->assertTrue($profile->application_form_config['website']['enabled']);
    }
}
