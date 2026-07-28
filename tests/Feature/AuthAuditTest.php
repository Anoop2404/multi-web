<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_shows_error_and_writes_audit_log(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('superadmin');

        $response = $this->from('/login')->post('/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Invalid', session('errors')->first('email'));
        $this->assertStringContainsString('password', strtolower(session('errors')->first('email')));

        $this->assertDatabaseHas('audit_logs', [
            'action'   => 'login.failed',
            'category' => 'auth',
        ]);

        $log = AuditLog::where('action', 'login.failed')->first();
        $this->assertSame($user->email, $log->properties['email']);
        $this->assertSame('invalid_credentials', $log->properties['reason']);
    }

    public function test_successful_login_and_logout_are_audited(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('superadmin');

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertRedirect('/admin/dashboard');

        $this->assertDatabaseHas('audit_logs', [
            'action'   => 'login',
            'category' => 'auth',
            'user_id'  => $user->id,
        ]);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertDatabaseHas('audit_logs', [
            'action'   => 'logout',
            'category' => 'auth',
            'user_id'  => $user->id,
        ]);
    }

    public function test_audit_report_page_groups_by_category(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('superadmin');

        AuditLog::create([
            'category'    => 'auth',
            'action'      => 'login.failed',
            'description' => 'Failed login attempt',
            'properties'  => ['email' => 'test@example.com', 'reason' => 'invalid_credentials'],
        ]);

        AuditLog::create([
            'category'    => 'fest',
            'action'      => 'fest.registration.approved',
            'description' => 'Fest registration #1 approved',
            'user_id'     => $admin->id,
        ]);

        $this->actingAs($admin, 'platform')
            ->get('http://superadmin.test/admin/audit-logs?category=auth')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.category', 'auth')
                ->has('summary')
                ->has('categories')
            );
    }

    public function test_forgot_password_reset_updates_password_without_clearing_login_identity(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'username'          => 'demo.user',
            'plain_password'    => 'OldTemp123',
        ]);

        $oldPassword = 'OldTemp123';
        $newPassword = 'NewTemp123!';
        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertRedirect('/portal/login');
        $response->assertSessionHas('success', 'Password reset. You can sign in now.');

        $fresh = $user->fresh();

        $this->assertSame('demo.user', $fresh->username);
        $this->assertSame($user->email, $fresh->email);
        $this->assertNull($fresh->plain_password);
        $this->assertFalse(Hash::check($oldPassword, $fresh->password));
        $this->assertTrue(Hash::check($newPassword, $fresh->password));
        $this->assertFalse((bool) $fresh->must_change_password);
    }
}
