<?php

namespace Tests\Feature\Admin;

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
}
