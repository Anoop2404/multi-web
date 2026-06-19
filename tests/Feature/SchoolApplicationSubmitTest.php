<?php

namespace Tests\Feature;

use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SchoolApplicationForm;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolApplicationSubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_application_creates_user_with_gmail_and_prefix(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'subdomain' => 'test-sahodaya',
            'domain'    => 'test-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create(['tenant_id' => $sahodaya->id]);

        $response = $this->post('http://test-sahodaya.test/school-register', [
            'school_name'      => 'Demo School',
            'school_email'     => 'demo.school@gmail.com',
            'school_prefix'    => 'DEM',
            'cbse_affiliation' => '930319',
            'phone'            => '9876543210',
            'highest_class'    => 'Class 12',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $school = Tenant::where('name', 'Demo School')->first();
        $this->assertNotNull($school);
        $this->assertSame('DEM', $school->school_prefix);
        $this->assertSame('pending', $school->membership_status);
        $this->assertTrue($school->is_active);

        $user = User::where('email', 'demo.school@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('school_admin'));

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_non_gmail_rejected(): void
    {
        $this->assertFalse(SchoolApplicationForm::isGmailAddress('admin@school.edu'));
        $this->assertTrue(SchoolApplicationForm::isGmailAddress('admin@gmail.com'));
    }

    public function test_duplicate_school_prefix_rejected_on_setup(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'subdomain' => 'dup-sahodaya',
            'domain'    => 'dup-sahodaya.test',
            'is_active' => true,
        ]);

        Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'First School',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'AMU',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Second School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id]);
        $admin->assignRole('school_admin');

        $response = $this->actingAs($admin)->post("/school-admin/{$school->id}/setup/code", [
            'school_prefix' => 'AMU',
        ]);

        $response->assertSessionHasErrors('school_prefix');
        $this->assertNull($school->fresh()->school_prefix);
    }
}
