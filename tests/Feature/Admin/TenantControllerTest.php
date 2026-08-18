<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_show_keeps_school_login_username_separate_from_email(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $school->id,
            'name' => 'School Admin',
            'email' => 'contact@example.com',
            'username' => 'testschool1',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->get("/admin/tenants/{$school->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenants/Show', false)
                ->where('schoolAdmins.0.email', 'contact@example.com')
                ->where('schoolAdmins.0.username', 'testschool1')
            );
    }

    public function test_superadmin_can_save_a_separate_school_login_username(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->post("/admin/tenants/{$school->id}/school-admin", [
                'name' => 'School Admin',
                'email' => 'contact@example.com',
                'username' => 'testschool1',
                'password' => 'Password123!',
            ])
            ->assertRedirect();

        $created = User::query()
            ->where('tenant_id', $school->id)
            ->where('email', 'contact@example.com')
            ->firstOrFail();

        $this->assertSame('testschool1', $created->username);
    }

    public function test_superadmin_defaults_blank_school_login_username_to_email(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->post("/admin/tenants/{$school->id}/school-admin", [
                'name' => 'School Admin',
                'email' => 'contact@example.com',
                'username' => '',
                'password' => 'Password123!',
            ])
            ->assertRedirect();

        $created = User::query()
            ->where('tenant_id', $school->id)
            ->where('email', 'contact@example.com')
            ->firstOrFail();

        $this->assertSame('contact@example.com', $created->username);
    }

    public function test_superadmin_cannot_create_school_login_when_email_is_already_used_as_another_username(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $existing = User::factory()->create([
            'tenant_id' => $school->id,
            'name' => 'Existing Login',
            'email' => 'existing@example.com',
            'username' => 'contact@example.com',
            'email_verified_at' => now(),
        ]);
        $existing->assignRole('school_admin');

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->post("/admin/tenants/{$school->id}/school-admin", [
                'name' => 'New School Admin',
                'email' => 'contact@example.com',
                'username' => '',
                'password' => 'Password123!',
            ])
            ->assertSessionHasErrors([
                'email' => 'That email or login is already used by an existing account.',
            ]);

        $this->assertSame(1, User::where('tenant_id', $school->id)->count());
    }

    public function test_superadmin_can_lookup_existing_school_login_by_email(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $existing = User::factory()->create([
            'tenant_id' => $school->id,
            'name' => 'Existing Login',
            'email' => 'existing@example.com',
            'username' => 'contact@example.com',
            'email_verified_at' => now(),
        ]);
        $existing->assignRole('school_admin');

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->get("/admin/tenants/{$school->id}?login_lookup=contact@example.com")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenants/Show', false)
                ->where('loginLookup.query', 'contact@example.com')
                ->where('loginLookup.searched', true)
                ->where('loginLookup.matches.0.email', 'existing@example.com')
                ->where('loginLookup.matches.0.username', 'contact@example.com')
            );
    }

    public function test_creating_a_tenant_writes_an_audit_log_entry(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->post('/admin/tenants', [
                'type' => 'sahodaya',
                'name' => 'Audited Sahodaya',
                'database_name' => 'audited_sahodaya_db',
            ])
            ->assertRedirect();

        $tenant = Tenant::where('name', 'Audited Sahodaya')->firstOrFail();

        $log = AuditLog::where('action', 'tenant.created')->where('subject_id', $tenant->id)->first();
        $this->assertNotNull($log, 'Expected a tenant.created audit log entry.');
        $this->assertSame('platform', $log->category);
        $this->assertSame($superadmin->id, $log->user_id);
    }

    public function test_updating_a_tenant_writes_an_audit_log_entry_with_changes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Original Name',
            'is_active' => true,
        ]);

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->put("/admin/tenants/{$sahodaya->id}", [
                'type' => 'sahodaya',
                'name' => 'Renamed Sahodaya',
            ])
            ->assertRedirect();

        $log = AuditLog::where('action', 'tenant.updated')->where('subject_id', $sahodaya->id)->first();
        $this->assertNotNull($log, 'Expected a tenant.updated audit log entry.');
        $this->assertArrayHasKey('name', $log->properties['changes'] ?? []);
        $this->assertSame('Renamed Sahodaya', $log->properties['changes']['name']);
    }

    public function test_deleting_a_tenant_writes_an_audit_log_entry(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'To Be Deleted',
            'is_active' => true,
        ]);
        $tenantId = $sahodaya->id;

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->delete("/admin/tenants/{$tenantId}")
            ->assertRedirect();

        $this->assertNull(Tenant::find($tenantId));

        $log = AuditLog::where('action', 'tenant.deleted')->where('subject_id', $tenantId)->first();
        $this->assertNotNull($log, 'Expected a tenant.deleted audit log entry.');
    }

    public function test_rejecting_school_membership_writes_an_audit_log_entry(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Parent Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Applicant School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'pending',
            'is_active' => true,
        ]);

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->post("/admin/tenants/{$school->id}/reject-membership", [
                'reason' => 'Incomplete documentation.',
            ])
            ->assertRedirect();

        $log = AuditLog::where('action', 'tenant.membership_rejected')->where('subject_id', $school->id)->first();
        $this->assertNotNull($log, 'Expected a tenant.membership_rejected audit log entry.');
        $this->assertSame('Incomplete documentation.', $log->properties['reason']);
    }
}
