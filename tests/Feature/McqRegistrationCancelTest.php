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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McqRegistrationCancelTest extends TestCase
{
    use RefreshDatabase;

    private function setUpExam(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Cancel Sahodaya',
            'domain'    => 'mcq-cancel.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'                    => $sahodaya->id,
            'prefix'                       => 'CS',
            'student_data_mode'            => 'counts_only',
            'require_student_verification' => false,
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Cancel School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $admin->assignRole('school_admin');

        $exam = McqExam::create([
            'tenant_id'  => $sahodaya->id,
            'title'      => 'Cancel MCQ',
            'exam_type'  => 'assessment',
            'status'     => 'published',
            'fee_type'   => 'flat',
            'fee_amount' => 50,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $school->id,
            'name'      => 'Class 10',
            'is_active' => true,
        ]);

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => 'Cancel Student',
            'status'          => 'active',
        ]);

        return compact('sahodaya', 'school', 'admin', 'exam', 'student');
    }

    public function test_cancel_marks_registration_cancelled_and_reregister_reuses_same_id(): void
    {
        ['school' => $school, 'admin' => $admin, 'exam' => $exam, 'student' => $student] = $this->setUpExam();

        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/mcq/{$exam->id}/register", ['student_id' => $student->id])
            ->assertRedirect();

        $original = McqRegistration::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('registered', $original->status);

        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/mcq/{$exam->id}/cancel", ['student_id' => $student->id])
            ->assertRedirect();

        $original->refresh();
        $this->assertSame('cancelled', $original->status);
        $this->assertNotNull($original->cancelled_at);

        // Re-register the cancelled student.
        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/mcq/{$exam->id}/register", ['student_id' => $student->id])
            ->assertRedirect();

        // Only one registration row exists and it reuses the original id.
        $rows = McqRegistration::where('exam_id', $exam->id)->where('student_id', $student->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($original->id, $rows->first()->id);
        $this->assertSame('registered', $rows->first()->status);
        $this->assertNull($rows->first()->cancelled_at);
    }

    public function test_cancel_excludes_student_from_batch_fee_count(): void
    {
        ['school' => $school, 'admin' => $admin, 'exam' => $exam, 'student' => $student] = $this->setUpExam();

        $other = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $student->school_class_id,
            'name'            => 'Other Student',
            'status'          => 'active',
        ]);

        foreach ([$student->id, $other->id] as $id) {
            $this->actingAs($admin)
                ->post("/school-admin/{$school->id}/mcq/{$exam->id}/register", ['student_id' => $id])
                ->assertRedirect();
        }

        $fee = \App\Models\McqSchoolFee::where('exam_id', $exam->id)->where('school_id', $school->id)->firstOrFail();
        $this->assertSame(2, (int) $fee->student_count);

        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/mcq/{$exam->id}/cancel", ['student_id' => $student->id])
            ->assertRedirect();

        $fee->refresh();
        $this->assertSame(1, (int) $fee->student_count);
        $this->assertEquals(50, (float) $fee->total_due);
    }
}
