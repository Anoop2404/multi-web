<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PortalVerifyEmail;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_link_uses_sahodaya_portal_domain(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Sahodaya',
            'domain'    => 'malappuramcentralsahodaya.org',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Demo School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => null,
        ]);
        $admin->assignRole('school_admin');

        $admin->sendEmailVerificationNotification();

        Notification::assertSentTo($admin, PortalVerifyEmail::class, function (PortalVerifyEmail $notification) use ($admin) {
            $url = $notification->toMail($admin)->viewData['verificationUrl'] ?? '';

            return str_contains($url, 'malappuramcentralsahodaya.org/email/verify/');
        });
    }

    public function test_verify_refreshes_session_and_opens_school_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'subdomain' => 'verify-sahodaya',
            'domain'    => 'verify-sahodaya.test',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Verify School',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'VER',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => null,
        ]);
        $admin->assignRole('school_admin');

        URL::forceRootUrl('http://verify-sahodaya.test');

        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $admin->id, 'hash' => sha1($admin->email)],
        );

        URL::forceRootUrl(config('app.url'));

        $this->get($verifyUrl)
            ->assertRedirect("/school-admin/{$school->id}");

        $this->assertTrue($admin->fresh()->hasVerifiedEmail());
        $this->assertAuthenticatedAs($admin->fresh());

        $this->get("/school-admin/{$school->id}")
            ->assertOk();
    }

    public function test_session_user_refreshes_after_email_verified_in_database(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Session School',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => null,
        ]);
        $admin->assignRole('school_admin');

        $this->actingAs($admin)
            ->get(route('verification.notice'))
            ->assertOk();

        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->get("/school-admin/{$school->id}")
            ->assertOk();
    }
}
