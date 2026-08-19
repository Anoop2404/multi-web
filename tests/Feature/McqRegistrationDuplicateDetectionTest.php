<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McqRegistrationDuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_prevents_duplicate_student_registrations(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Dup Test Sahodaya',
            'domain'    => 'dup-mcq.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix'    => 'DT',
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Dup Test School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $exam = McqExam::create([
            'tenant_id' => $sahodaya->id,
            'title'     => 'Dup MCQ Exam',
            'exam_type' => 'assessment',
            'status'    => 'published',
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $school->id,
            'name'      => 'Class 4',
            'is_active' => true,
        ]);

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => 'VEDITH KOTHERI',
            'status'          => 'active',
        ]);

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

    public function test_targeted_cancellation_by_registration_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Dup Test Sahodaya 2',
            'domain'    => 'dup-mcq2.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix'    => 'DT2',
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Dup Test School 2',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $admin->assignRole('school_admin');

        $exam = McqExam::create([
            'tenant_id' => $sahodaya->id,
            'title'     => 'Dup MCQ Exam 2',
            'exam_type' => 'assessment',
            'status'    => 'published',
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $school->id,
            'name'      => 'Class 4',
            'is_active' => true,
        ]);

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => 'VEDITH KOTHERI',
            'status'          => 'active',
        ]);

        $reg = McqRegistration::create([
            'exam_id'    => $exam->id,
            'student_id' => $student->id,
            'school_id'  => $school->id,
            'status'     => 'registered',
        ]);

        $cancelResponse = $this->actingAs($admin)->post("/school-admin/{$school->id}/mcq/{$exam->id}/cancel", [
            'registration_id' => $reg->id,
            'student_id'      => $student->id,
        ]);

        $cancelResponse->assertRedirect();
        $this->assertSame('cancelled', $reg->fresh()->status);
    }
}
