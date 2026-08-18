<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantProvisioningChecklistService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantProvisioningChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function actingSuperadmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        return $superadmin;
    }

    private function statusFor(Tenant $tenant): array
    {
        return (new TenantProvisioningChecklistService)->statusFor($tenant);
    }

    public function test_creating_a_sahodaya_completes_only_the_tenant_created_step(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $superadmin = $this->actingSuperadmin();

        $this->actingAs($superadmin)
            ->post('/admin/tenants', [
                'type' => 'sahodaya',
                'name' => 'Checklist Sahodaya',
                'database_name' => 'checklist_sahodaya_db',
            ])
            ->assertRedirect();

        $tenant = Tenant::where('name', 'Checklist Sahodaya')->firstOrFail();
        $status = $this->statusFor($tenant);

        $this->assertTrue($status['steps']['tenant_created']['completed']);
        $this->assertFalse($status['steps']['database_configured']['completed']);
        $this->assertFalse($status['steps']['database_migrated']['completed']);
        $this->assertFalse($status['steps']['portal_admin_created']['completed']);
        $this->assertFalse($status['steps']['logo_uploaded']['completed']);
        $this->assertFalse($status['complete']);
        $this->assertSame(4, $status['pending_count']);
    }

    public function test_creating_a_school_only_has_three_applicable_checklist_steps(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $superadmin = $this->actingSuperadmin();

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Parent Sahodaya',
            'is_active' => true,
        ]);

        $this->actingAs($superadmin)
            ->post('/admin/tenants', [
                'type' => 'school',
                'name' => 'Checklist School',
                'parent_id' => $sahodaya->id,
            ])
            ->assertRedirect();

        $tenant = Tenant::where('name', 'Checklist School')->firstOrFail();
        $status = $this->statusFor($tenant);

        $this->assertSame(['tenant_created', 'portal_admin_created', 'logo_uploaded'], array_keys($status['steps']));
        $this->assertTrue($status['steps']['tenant_created']['completed']);
        $this->assertSame(2, $status['pending_count']);
    }

    public function test_uploading_a_logo_marks_logo_uploaded_step_complete(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        Storage::fake('shared');
        $superadmin = $this->actingSuperadmin();

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Logo School',
            'is_active' => true,
        ]);

        $this->actingAs($superadmin)
            ->post("/admin/tenants/{$school->id}/logo", [
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertRedirect();

        $this->assertTrue($this->statusFor($school)['steps']['logo_uploaded']['completed']);
    }

    public function test_creating_a_school_admin_marks_portal_admin_created_step_complete(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $superadmin = $this->actingSuperadmin();

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Admin School',
            'is_active' => true,
        ]);

        $this->actingAs($superadmin)
            ->post("/admin/tenants/{$school->id}/school-admin", [
                'name' => 'School Admin',
                'email' => 'admin@example.com',
                'password' => 'Password123!',
            ])
            ->assertRedirect();

        $this->assertTrue($this->statusFor($school)['steps']['portal_admin_created']['completed']);
    }

    public function test_updating_an_existing_school_admin_does_not_recomplete_the_step(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $superadmin = $this->actingSuperadmin();

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Existing Admin School',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $school->id,
            'email' => 'existing-admin@example.com',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        // Step was never explicitly marked (the admin above bypassed the controller) —
        // updating that existing account must not silently mark it complete either.
        $this->actingAs($superadmin)
            ->post("/admin/tenants/{$school->id}/school-admin", [
                'user_id' => $admin->id,
                'name' => 'Renamed Admin',
                'email' => 'existing-admin@example.com',
            ])
            ->assertRedirect();

        $this->assertFalse($this->statusFor($school)['steps']['portal_admin_created']['completed']);
    }

    public function test_tenant_show_exposes_setup_checklist_prop(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $superadmin = $this->actingSuperadmin();

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Show Page School',
            'is_active' => true,
        ]);
        (new TenantProvisioningChecklistService)->markComplete($school, 'tenant_created');

        $this->actingAs($superadmin)
            ->get("/admin/tenants/{$school->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenants/Show', false)
                ->where('setupChecklist.complete', false)
                ->where('setupChecklist.pending_count', 2)
                ->where('setupChecklist.steps.tenant_created.completed', true)
            );
    }

    public function test_tenant_index_flags_tenants_with_incomplete_setup(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $superadmin = $this->actingSuperadmin();

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Incomplete School',
            'is_active' => true,
        ]);
        (new TenantProvisioningChecklistService)->markComplete($school, 'tenant_created');

        $this->actingAs($superadmin)
            ->get('/admin/schools')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenants/Index', false)
                ->where('tenants.data.0.setup_incomplete', true)
            );
    }

    public function test_tenant_index_does_not_flag_tenants_with_complete_setup(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        Storage::fake('shared');
        $superadmin = $this->actingSuperadmin();

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Complete School',
            'is_active' => true,
        ]);
        (new TenantProvisioningChecklistService)->markComplete($school, 'tenant_created');

        $this->actingAs($superadmin)->post("/admin/tenants/{$school->id}/school-admin", [
            'name' => 'School Admin',
            'email' => 'complete-admin@example.com',
            'password' => 'Password123!',
        ])->assertRedirect();

        $this->actingAs($superadmin)->post("/admin/tenants/{$school->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect();

        $this->assertTrue($this->statusFor($school)['complete']);

        $this->actingAs($superadmin)
            ->get('/admin/schools')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenants/Index', false)
                ->where('tenants.data.0.setup_incomplete', false)
            );
    }
}
