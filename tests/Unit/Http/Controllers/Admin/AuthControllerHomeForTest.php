<?php

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AuthController;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthControllerHomeForTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_school_domain_coordinators_receive_program_home_urls(): void
    {
        $schoolId = (string) Str::uuid();

        $cases = [
            'school_sports_coordinator' => "/school-admin/{$schoolId}/sports",
            'school_kalotsavam_coordinator' => "/school-admin/{$schoolId}/kalotsav",
            'school_mcq_coordinator' => "/school-admin/{$schoolId}/mcq",
            'school_training_coordinator' => "/school-admin/{$schoolId}/training",
            'school_finance_coordinator' => "/school-admin/{$schoolId}/payments",
        ];

        foreach ($cases as $roleName => $expectedUrl) {
            $user = User::factory()->create(['tenant_id' => $schoolId]);
            $user->assignRole(Role::findByName($roleName, 'web'));

            $this->assertSame($expectedUrl, AuthController::homeFor($user), "Failed for role {$roleName}");
        }
    }

    public function test_central_superadmin_routes_to_admin_dashboard_in_dedicated_database_mode(): void
    {
        config(['tenancy.database_per_sahodaya' => true]);

        $user = User::factory()->create(['tenant_id' => null]);
        PlatformUser::findOrFail($user->id)->assignRole('superadmin');
        $user = User::findOrFail($user->id);

        $this->assertTrue($user->isSuperAdmin());
        $this->assertSame(route('admin.dashboard'), AuthController::homeFor($user));
    }
}
