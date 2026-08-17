<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SahodayaCredentialsHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_hub_loads_with_correct_counts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Hub Test Sahodaya', 'is_active' => true]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $schoolWithLogin = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'With Login',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        User::factory()->create(['tenant_id' => $schoolWithLogin->id]);

        $schoolWithoutLogin = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Without Login',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $class = SchoolClass::create(['tenant_id' => $schoolWithLogin->id, 'name' => 'Class A']);
        Student::create(['tenant_id' => $schoolWithLogin->id, 'school_class_id' => $class->id, 'name' => 'No Login Student', 'status' => 'active']);

        $response = $this->actingAs($admin)->get("/sahodaya-admin/{$sahodaya->id}/credentials");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('approvedSchoolsCount', 2)
            ->where('schoolsWithoutLoginCount', 1)
            ->where('studentsWithoutLoginCount', 1)
        );
    }
}
