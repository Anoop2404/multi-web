<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression test for WF-04 (functional audit, 2026-08-11/12): MCQ
 * registration previously had only a non-unique index on
 * (exam_id, school_id), no unique constraint on (exam_id, student_id), and a
 * plain check-then-create with no transaction/lock in
 * McqRegistrationController::store() — a real double-submit race that could
 * create two registration rows for the same student+exam.
 */
class McqRegistrationDuplicateRaceTest extends TestCase
{
    use RefreshDatabase;

    private function setUpExam(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Race Sahodaya',
            'domain'    => 'mcq-race.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'                    => $sahodaya->id,
            'prefix'                       => 'RC',
            'student_data_mode'            => 'counts_only',
            'require_student_verification' => false,
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Race School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $admin->assignRole('school_admin');

        $exam = McqExam::create([
            'tenant_id' => $sahodaya->id,
            'title'     => 'Race MCQ',
            'exam_type' => 'assessment',
            'status'    => 'published',
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $school->id,
            'name'      => 'Class 10',
            'is_active' => true,
        ]);

        $student = Student::create([
            'tenant_id'        => $school->id,
            'school_class_id'  => $class->id,
            'name'             => 'Race Student',
            'status'           => 'active',
        ]);

        return compact('sahodaya', 'school', 'admin', 'exam', 'student');
    }

    public function test_database_rejects_a_second_registration_row_for_the_same_student_and_exam(): void
    {
        // This bypasses the controller entirely and proves the invariant is
        // enforced at the database level, not just by application logic that
        // a future change could accidentally weaken or race around.
        ['exam' => $exam, 'school' => $school, 'student' => $student] = $this->setUpExam();

        McqRegistration::create([
            'exam_id'    => $exam->id,
            'student_id' => $student->id,
            'school_id'  => $school->id,
            'status'     => 'registered',
        ]);

        $this->expectException(QueryException::class);

        McqRegistration::create([
            'exam_id'    => $exam->id,
            'student_id' => $student->id,
            'school_id'  => $school->id,
            'status'     => 'registered',
        ]);
    }

    public function test_double_submitting_the_registration_endpoint_never_creates_two_rows(): void
    {
        ['exam' => $exam, 'school' => $school, 'admin' => $admin, 'student' => $student] = $this->setUpExam();

        $first = $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/mcq/{$exam->id}/register", ['student_id' => $student->id]);
        $first->assertRedirect();
        $first->assertSessionHas('success');

        // Simulates a double-click / duplicate tab submit of the same form.
        $second = $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/mcq/{$exam->id}/register", ['student_id' => $student->id]);
        $second->assertRedirect();
        $second->assertSessionHas('success', 'Student is already registered for this exam.');

        $rows = McqRegistration::where('exam_id', $exam->id)->where('student_id', $student->id)->get();
        $this->assertCount(1, $rows);
    }
}
