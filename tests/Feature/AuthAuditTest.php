<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertStringContainsString('Invalid email or password', session('errors')->first('email'));

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

        $this->actingAs($admin)
            ->get('/admin/audit-logs?category=auth')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.category', 'auth')
                ->has('summary')
                ->has('categories')
            );
    }
}
