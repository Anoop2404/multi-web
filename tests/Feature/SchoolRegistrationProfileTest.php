<?php

namespace Tests\Feature;

use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PortalVerifyEmail;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolRegistrationProfileTest extends TestCase
{
    use RefreshDatabase;

    private function schoolAdmin(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'is_active' => true,
        ]);

        SahodayaProfile::create(['tenant_id' => $sahodaya->id]);

        $school = Tenant::create([
            'id'                  => (string) Str::uuid(),
            'type'                => 'school',
            'name'                => 'Demo School',
            'parent_id'           => $sahodaya->id,
            'school_prefix'       => 'DEM',
            'membership_status'   => 'approved',
            'is_active'           => true,
            'application_payload' => [
                'school_email'     => 'demo.school@gmail.com',
                'phone'            => '9876543210',
                'cbse_affiliation' => '930319',
                'highest_class'    => 'Class 12',
            ],
        ]);

        $admin = User::factory()->create([
            'tenant_id'         => $school->id,
            'email'             => 'demo.school@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('old-password'),
        ]);
        $admin->assignRole('school_admin');

        return compact('sahodaya', 'school', 'admin');
    }

    public function test_school_can_view_registration_profile_page(): void
    {
        ['school' => $school, 'admin' => $admin] = $this->schoolAdmin();

        $response = $this->actingAs($admin)->get("/school-admin/{$school->id}/registration/profile");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('profileData')
            ->has('account')
            ->where('account.email', 'demo.school@gmail.com')
        );
    }

    public function test_school_can_update_registration_details(): void
    {
        ['school' => $school, 'admin' => $admin] = $this->schoolAdmin();

        $response = $this->actingAs($admin)->put("/school-admin/{$school->id}/registration/profile", [
            'section'       => 'school',
            'phone'         => '9999888877',
            'highest_class' => 'Class 10',
            'address'       => 'Main Road, Town',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payload = $school->fresh()->application_payload;
        $this->assertSame('9999888877', $payload['phone']);
        $this->assertSame('9999888877', $payload['contact_phone']);
        $this->assertSame('Class 10', $payload['highest_class']);
        $this->assertSame('Main Road, Town', $payload['address']);
    }

    public function test_school_can_update_principal_section_only(): void
    {
        ['school' => $school, 'admin' => $admin] = $this->schoolAdmin();

        $response = $this->actingAs($admin)->put("/school-admin/{$school->id}/registration/profile", [
            'section'         => 'principal',
            'principal_name'  => 'Dr. Jane Principal',
            'principal_email' => 'principal@school.edu',
            'principal_phone' => '9876500001',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payload = $school->fresh()->application_payload;
        $this->assertSame('Dr. Jane Principal', $payload['principal_name']);
        $this->assertSame('9876543210', $payload['phone']);
    }

    public function test_profile_update_requires_section(): void
    {
        ['school' => $school, 'admin' => $admin] = $this->schoolAdmin();

        $response = $this->actingAs($admin)->put("/school-admin/{$school->id}/registration/profile", [
            'phone' => '1111222233',
        ]);

        $response->assertSessionHasErrors('section');
    }

    public function test_school_can_change_password(): void
    {
        ['school' => $school, 'admin' => $admin] = $this->schoolAdmin();

        $response = $this->actingAs($admin)->put("/school-admin/{$school->id}/registration/account", [
            'name'             => 'Demo School Admin',
            'email'            => 'demo.school@gmail.com',
            'current_password' => 'old-password',
            'password'         => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue(Hash::check('new-secure-password', $admin->fresh()->password));
    }

    public function test_school_can_change_login_email(): void
    {
        Notification::fake();

        ['school' => $school, 'admin' => $admin] = $this->schoolAdmin();

        $response = $this->actingAs($admin)->put("/school-admin/{$school->id}/registration/account", [
            'name'  => 'Demo School',
            'email' => 'new.demo.school@gmail.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame('new.demo.school@gmail.com', $admin->fresh()->email);
        $this->assertNull($admin->fresh()->email_verified_at);
        $this->assertSame('new.demo.school@gmail.com', $school->fresh()->application_payload['school_email']);

        Notification::assertSentTo($admin->fresh(), PortalVerifyEmail::class);
    }

    public function test_non_gmail_login_email_rejected(): void
    {
        ['school' => $school, 'admin' => $admin] = $this->schoolAdmin();

        $response = $this->actingAs($admin)->put("/school-admin/{$school->id}/registration/account", [
            'name'  => 'Demo School',
            'email' => 'admin@school.edu',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
