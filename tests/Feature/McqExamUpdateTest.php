<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McqExamUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_exam_succeeds_when_hall_tickets_exist_and_reg_no_is_unchanged(): void
    {
        [$sahodaya, $school, $user] = $this->seedSetup();

        $exam = McqExam::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'Original Exam Title',
            'exam_type'          => 'assessment',
            'status'             => 'published',
            'next_hall_ticket_no'=> 500,
            'fee_type'           => 'flat',
            'fee_amount'         => 100,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 10']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Test Student']);

        McqRegistration::create([
            'exam_id'        => $exam->id,
            'school_id'      => $school->id,
            'student_id'     => $student->id,
            'hall_ticket_no' => '500',
            'status'         => 'registered',
        ]);

        $response = $this->actingAs($user)->put("/sahodaya-admin/{$sahodaya->id}/mcq-exams/{$exam->id}", [
            'title'               => 'Updated Exam Title',
            'status'              => 'published',
            'fee_amount'          => 100,
            'next_hall_ticket_no' => 500,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('Updated Exam Title', $exam->fresh()->title);
    }

    public function test_updating_exam_without_next_hall_ticket_no_in_payload_succeeds_when_hall_tickets_exist(): void
    {
        [$sahodaya, $school, $user] = $this->seedSetup();

        $exam = McqExam::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'Original Exam Title',
            'exam_type'          => 'assessment',
            'status'             => 'published',
            'next_hall_ticket_no'=> 500,
            'fee_type'           => 'flat',
            'fee_amount'         => 100,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 10']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Test Student']);

        McqRegistration::create([
            'exam_id'        => $exam->id,
            'school_id'      => $school->id,
            'student_id'     => $student->id,
            'hall_ticket_no' => '500',
            'status'         => 'registered',
        ]);

        $response = $this->actingAs($user)->put("/sahodaya-admin/{$sahodaya->id}/mcq-exams/{$exam->id}", [
            'title'      => 'Updated Exam Title',
            'status'     => 'published',
            'fee_amount' => 100,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('Updated Exam Title', $exam->fresh()->title);
        $this->assertEquals(500, $exam->fresh()->next_hall_ticket_no);
    }

    public function test_changing_next_hall_ticket_no_fails_when_hall_tickets_exist(): void
    {
        [$sahodaya, $school, $user] = $this->seedSetup();

        $exam = McqExam::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'Original Exam Title',
            'exam_type'          => 'assessment',
            'status'             => 'published',
            'next_hall_ticket_no'=> 500,
            'fee_type'           => 'flat',
            'fee_amount'         => 100,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 10']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Test Student']);

        McqRegistration::create([
            'exam_id'        => $exam->id,
            'school_id'      => $school->id,
            'student_id'     => $student->id,
            'hall_ticket_no' => '500',
            'status'         => 'registered',
        ]);

        $response = $this->actingAs($user)->put("/sahodaya-admin/{$sahodaya->id}/mcq-exams/{$exam->id}", [
            'title'               => 'Updated Exam Title',
            'status'              => 'published',
            'fee_amount'          => 100,
            'next_hall_ticket_no' => 999,
        ]);

        $response->assertStatus(422);
    }

    /** @return array{0: Tenant, 1: Tenant, 2: User} */
    private function seedSetup(): array
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        $sahodaya = Tenant::create([
            'id'        => $sahodayaId,
            'name'      => 'Test Sahodaya',
            'type'      => 'sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'        => $schoolId,
            'name'      => 'Test School',
            'type'      => 'school',
            'parent_id' => $sahodayaId,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $sahodayaId,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('sahodaya_admin');

        return [$sahodaya, $school, $user];
    }
}
