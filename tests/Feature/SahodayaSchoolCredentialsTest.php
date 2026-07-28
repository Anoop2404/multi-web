<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PortalVerifyEmail;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class SahodayaSchoolCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sahodaya_admin_can_update_school_email_and_login_email(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Demo School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
            'application_payload'=> [
                'school_email' => 'old@example.com',
                'contact_email' => 'old@example.com',
            ],
        ]);

        $sahodayaAdmin = User::factory()->create([
            'tenant_id'         => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $schoolAdmin = User::factory()->create([
            'tenant_id'         => $school->id,
            'email'             => 'old@example.com',
            'email_verified_at' => now(),
        ]);
        $schoolAdmin->assignRole('school_admin');

        $this->actingAs($sahodayaAdmin)
            ->put("/sahodaya-admin/{$sahodaya->id}/schools/{$school->id}/email", [
                'email' => 'new@example.com',
            ])
            ->assertRedirect();

        $updatedSchool = Tenant::findOrFail($school->id);
        $updatedAdmin = User::query()->where('tenant_id', $school->id)->firstOrFail();

        $this->assertSame('new@example.com', $updatedSchool->application_payload['school_email']);
        $this->assertSame('new@example.com', $updatedSchool->application_payload['contact_email']);
        $this->assertSame('new@example.com', $updatedAdmin->email);
        Notification::assertSentTo($updatedAdmin, PortalVerifyEmail::class);
    }

    public function test_sahodaya_admin_can_reset_school_password(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Reset School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $sahodayaAdmin = User::factory()->create([
            'tenant_id'         => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $schoolAdmin = User::factory()->create([
            'tenant_id'         => $school->id,
            'email'             => 'reset@example.com',
            'email_verified_at' => now(),
            'plain_password'    => null,
        ]);
        $schoolAdmin->assignRole('school_admin');

        $this->actingAs($sahodayaAdmin)
            ->post("/sahodaya-admin/{$sahodaya->id}/schools/{$school->id}/reset-password")
            ->assertRedirect();

        $updatedAdmin = User::query()->where('tenant_id', $school->id)->firstOrFail();

        $this->assertTrue((bool) $updatedAdmin->must_change_password);
        $this->assertNotEmpty($updatedAdmin->plain_password);
        $this->assertTrue(Hash::check($updatedAdmin->plain_password, $updatedAdmin->password));
    }
}
