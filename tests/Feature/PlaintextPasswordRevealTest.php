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

class PlaintextPasswordRevealTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_tenants_show_does_not_ship_plaintext_password_but_reveal_endpoint_returns_it(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superadmin = User::factory()->create(['tenant_id' => null]);
        $superadmin->assignRole('superadmin');

        $sahodaya = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Reveal Test Sahodaya', 'is_active' => true]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'plain_password' => 'SuperSecret123']);
        $admin->assignRole('sahodaya_admin');

        $show = $this->actingAs($superadmin)->get("/admin/tenants/{$sahodaya->id}");
        $show->assertOk();
        $show->assertInertia(fn ($page) => $page
            ->has('sahodayaAdmins.0', fn ($a) => $a
                ->where('has_password', true)
                ->missing('password')
                ->etc()
            )
        );

        $reveal = $this->actingAs($superadmin)
            ->getJson("/admin/tenants/{$sahodaya->id}/portal-admin/{$admin->id}/reveal-password");
        $reveal->assertOk();
        $reveal->assertJson(['password' => 'SuperSecret123']);
    }

    public function test_sahodaya_student_profile_does_not_ship_plaintext_password_but_reveal_endpoint_returns_it(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Reveal Sahodaya 2', 'is_active' => true]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Reveal School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class A']);
        $studentUser = User::factory()->create(['tenant_id' => $school->id, 'plain_password' => 'StudentSecret456']);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Reveal Student',
            'status' => 'active', 'user_id' => $studentUser->id, 'reg_no' => 'RS001',
        ]);

        $show = $this->actingAs($admin)->get("/sahodaya-admin/{$sahodaya->id}/students/{$student->id}");
        $show->assertOk();
        $show->assertInertia(fn ($page) => $page
            ->has('student', fn ($s) => $s->missing('portal_password')->etc())
        );

        $reveal = $this->actingAs($admin)
            ->getJson("/sahodaya-admin/{$sahodaya->id}/students/{$student->id}/reveal-portal-password");
        $reveal->assertOk();
        $reveal->assertJson(['password' => 'StudentSecret456']);
    }

    public function test_school_admin_teacher_list_does_not_ship_plaintext_password_but_reveal_endpoint_returns_it(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Reveal Sahodaya 3', 'is_active' => true]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Reveal School 2',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
            'school_prefix' => 'RS2',
        ]);
        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id]);
        $schoolAdmin->assignRole('school_admin');

        $teacherUser = User::factory()->create(['tenant_id' => $school->id, 'plain_password' => 'TeacherSecret789']);
        $teacher = \App\Models\Teacher::create([
            'tenant_id' => $school->id, 'name' => 'Reveal Teacher', 'status' => 'active', 'user_id' => $teacherUser->id,
        ]);

        $index = $this->actingAs($schoolAdmin)->get("/school-admin/{$school->id}/teachers");
        $index->assertOk();
        $index->assertInertia(fn ($page) => $page
            ->has('teachers.data.0', fn ($t) => $t->where('has_portal_password', true)->missing('portal_password')->etc())
        );

        $reveal = $this->actingAs($schoolAdmin)
            ->getJson("/school-admin/{$school->id}/teachers/{$teacher->id}/reveal-portal-password");
        $reveal->assertOk();
        $reveal->assertJson(['password' => 'TeacherSecret789']);
    }
}
