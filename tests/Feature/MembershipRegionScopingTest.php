<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\SchoolClass;
use App\Models\SchoolRegionAssignment;
use App\Models\StaffRegionAssignment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MembershipRegionScopingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;
    private Tenant $schoolInRegion;
    private Tenant $schoolOutsideRegion;
    private Region $region;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Region Test Sahodaya', 'is_active' => true,
        ]);

        $this->schoolInRegion = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'In-Region School',
            'parent_id' => $this->sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $this->schoolOutsideRegion = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Outside-Region School',
            'parent_id' => $this->sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $this->region = Region::create([
            'tenant_id' => $this->sahodaya->id, 'name' => 'North Zone', 'code' => 'north', 'is_active' => true, 'sort_order' => 1,
        ]);

        SchoolRegionAssignment::create([
            'tenant_id' => $this->sahodaya->id,
            'region_id' => $this->region->id,
            'school_id' => $this->schoolInRegion->id,
            'academic_year' => AcademicYear::forSahodaya($this->sahodaya->id),
        ]);
    }

    private function regionScopedStaff(): User
    {
        $staff = User::factory()->create(['tenant_id' => $this->sahodaya->id, 'email_verified_at' => now()]);
        $staff->assignRole('sahodaya_staff');
        $staff->givePermissionTo('membership.view', 'membership.manage');

        StaffRegionAssignment::create([
            'tenant_id' => $this->sahodaya->id,
            'user_id'   => $staff->id,
            'region_id' => $this->region->id,
        ]);

        return $staff;
    }

    private function fullSahodayaAdmin(): User
    {
        $admin = User::factory()->create(['tenant_id' => $this->sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        return $admin;
    }

    public function test_region_scoped_staff_only_sees_schools_in_their_region(): void
    {
        $staff = $this->regionScopedStaff();

        $response = $this->actingAs($staff)->get("/sahodaya-admin/{$this->sahodaya->id}/schools");
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->has('schools.data', 1)
            ->where('schools.data.0.id', $this->schoolInRegion->id)
        );
    }

    public function test_unscoped_sahodaya_admin_sees_all_schools(): void
    {
        $admin = $this->fullSahodayaAdmin();

        $response = $this->actingAs($admin)->get("/sahodaya-admin/{$this->sahodaya->id}/schools");
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page->has('schools.data', 2));
    }

    public function test_region_scoped_staff_cannot_view_students_of_a_school_outside_their_region(): void
    {
        $staff = $this->regionScopedStaff();

        $this->actingAs($staff)
            ->get("/sahodaya-admin/{$this->sahodaya->id}/schools/{$this->schoolOutsideRegion->id}/students")
            ->assertNotFound();

        $this->actingAs($staff)
            ->get("/sahodaya-admin/{$this->sahodaya->id}/schools/{$this->schoolInRegion->id}/students")
            ->assertOk();
    }

    public function test_region_scoped_staff_cannot_view_a_student_profile_outside_their_region(): void
    {
        $staff = $this->regionScopedStaff();

        $outsideClass = SchoolClass::create(['tenant_id' => $this->schoolOutsideRegion->id, 'name' => 'Class A']);
        $insideClass = SchoolClass::create(['tenant_id' => $this->schoolInRegion->id, 'name' => 'Class A']);

        $outsideStudent = Student::create([
            'tenant_id' => $this->schoolOutsideRegion->id, 'school_class_id' => $outsideClass->id,
            'name' => 'Outside Student', 'status' => 'active',
        ]);
        $insideStudent = Student::create([
            'tenant_id' => $this->schoolInRegion->id, 'school_class_id' => $insideClass->id,
            'name' => 'Inside Student', 'status' => 'active',
        ]);

        $this->actingAs($staff)
            ->get("/sahodaya-admin/{$this->sahodaya->id}/students/{$outsideStudent->id}")
            ->assertNotFound();

        $this->actingAs($staff)
            ->get("/sahodaya-admin/{$this->sahodaya->id}/students/{$insideStudent->id}")
            ->assertOk();
    }

    public function test_sahodaya_admin_can_assign_a_staff_member_to_a_region(): void
    {
        $admin = $this->fullSahodayaAdmin();
        $staff = User::factory()->create(['tenant_id' => $this->sahodaya->id]);

        $this->actingAs($admin)
            ->put("/sahodaya-admin/{$this->sahodaya->id}/users/{$staff->id}", [
                'name'  => $staff->name,
                'email' => $staff->email,
                'roles' => ['sahodaya_staff'],
                'region_ids' => [$this->region->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('staff_region_assignments', [
            'tenant_id' => $this->sahodaya->id,
            'user_id'   => $staff->id,
            'region_id' => $this->region->id,
        ]);
    }
}
