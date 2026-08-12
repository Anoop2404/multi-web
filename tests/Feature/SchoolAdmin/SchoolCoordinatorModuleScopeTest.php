<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression test for PERM-03 (functional audit, 2026-08-11/12): five school
 * "coordinator" roles (finance/mcq/training/sports/kalotsavam) were missing
 * from TenantUserCatalog::schoolWriteGatedRoles(), so EnsureSchoolAdmin never
 * marked them as staff and SchoolAdminController's write-permission gate
 * never ran for them — a school_finance_coordinator (permissions:
 * finance.view, fest.finance only) could write to any school-admin module by
 * direct URL, same as a full school_admin, with only client-side nav hiding
 * the other modules.
 */
class SchoolCoordinatorModuleScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchool(): Tenant
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Coordinator Scope Sahodaya',
            'domain'    => 'coord-scope.test',
            'is_active' => true,
        ]);

        return Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Coordinator Scope School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);
    }

    private function makeCoordinator(Tenant $school, string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $user->assignRole($role);
        // Mirrors TenantUserProvisioner::defaultPermissionsForRoles() — the
        // permissions a coordinator actually receives when provisioned
        // through the real admin UI.
        $user->syncPermissions(TenantUserCatalog::mergedDefaultPermissions([$role], 'school'));

        return $user;
    }

    public function test_finance_coordinator_cannot_write_to_the_news_module(): void
    {
        $school = $this->makeSchool();
        $coordinator = $this->makeCoordinator($school, 'school_finance_coordinator');

        $response = $this->actingAs($coordinator)->post(route('school.news.store', ['tenantId' => $school->id]), [
            'title' => 'Should be blocked',
            'body'  => 'Should be blocked',
        ]);

        $response->assertForbidden();
    }

    public function test_mcq_coordinator_cannot_write_to_the_news_module(): void
    {
        $school = $this->makeSchool();
        $coordinator = $this->makeCoordinator($school, 'school_mcq_coordinator');

        $response = $this->actingAs($coordinator)->post(route('school.news.store', ['tenantId' => $school->id]), [
            'title' => 'Should be blocked',
            'body'  => 'Should be blocked',
        ]);

        $response->assertForbidden();
    }

    public function test_school_admin_is_unaffected_and_can_still_write_to_the_news_module(): void
    {
        $school = $this->makeSchool();
        $admin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $admin->assignRole('school_admin');

        $response = $this->actingAs($admin)->post(route('school.news.store', ['tenantId' => $school->id]), [
            'title' => 'Allowed',
            'body'  => 'Allowed',
        ]);

        // Not blocked by the write-permission gate (full admins are exempt via
        // schoolManagementRoles()) — whatever status the controller/validation
        // returns next, it must not be 403.
        $response->assertStatus($response->status());
        $this->assertNotSame(403, $response->status());
    }

    public function test_read_access_is_unaffected_for_coordinators(): void
    {
        $school = $this->makeSchool();
        $coordinator = $this->makeCoordinator($school, 'school_training_coordinator');

        // GET requests are never blocked by the write-permission gate
        // (SchoolAdminController::__construct only checks non-GET methods).
        $response = $this->actingAs($coordinator)->get(route('school.news.index', ['tenantId' => $school->id]));

        $this->assertNotSame(403, $response->status());
    }
}
