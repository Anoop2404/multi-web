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

    public function test_class_timings_save_render_per_candidate_and_can_be_removed(): void
    {
        [$sahodaya, $school, $user] = $this->seedSetup();
        $exam = McqExam::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Class timings', 'status' => 'draft',
            'scheduled_at' => '2026-11-03 09:00:00', 'duration_minutes' => 60,
            'settings_json' => ['hall_ticket' => ['layout' => 'stse_three_up'], 'school_centre_codes' => [$school->id => 'STSE-001']],
        ]);
        $url = "/sahodaya-admin/{$sahodaya->id}/mcq-exams/{$exam->id}";
        $rows = [
            ['class_name' => 'LKG', 'scheduled_at' => '2026-11-03T10:00', 'duration_minutes' => 45],
            ['class_name' => '10', 'scheduled_at' => '2026-11-04T13:30', 'duration_minutes' => 90],
        ];
        $this->actingAs($user)->put($url, ['title' => $exam->title, 'status' => 'draft', 'class_schedules' => $rows])->assertSessionHasNoErrors();
        $exam->refresh();
        $this->assertSame($rows, $exam->settings_json['class_schedules']);
        $this->assertSame('STSE-001', $exam->settings_json['school_centre_codes'][$school->id]);
        $this->assertSame('13:30', $exam->scheduleForClass('Class 10')['scheduled_at']->format('H:i'));
        $this->assertSame('09:00', $exam->scheduleForClass('UKG')['scheduled_at']->format('H:i'));

        $registrations = collect();
        foreach (['LKG', 'Class 10'] as $index => $className) {
            $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => $className]);
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Candidate '.$index]);
            $registration = McqRegistration::create(['exam_id' => $exam->id, 'school_id' => $school->id,
                'student_id' => $student->id, 'hall_ticket_no' => (string) (10000 + $index), 'status' => 'registered']);
            $registrations->push($registration);
            $html = view('mcq.hall-ticket', compact('registration'))->render();
            $this->assertStringContainsString($index === 0 ? '10:00 AM' : '01:30 PM', $html);
            $this->assertStringContainsString($index === 0 ? '10:45 AM' : '03:00 PM', $html);
        }
        $html = view('mcq.hall-tickets-bulk', compact('exam', 'registrations'))->render();
        foreach (['10:00 AM', '10:45 AM', '01:30 PM', '03:00 PM'] as $time) {
            $this->assertStringContainsString($time, $html);
        }
        $this->actingAs($user)->put($url, ['title' => $exam->title, 'status' => 'draft', 'class_schedules' => []])->assertSessionHasNoErrors();
        $this->assertSame('09:00', $exam->fresh()->scheduleForClass('LKG')['scheduled_at']->format('H:i'));
    }

    public function test_invalid_class_timing_is_rejected(): void
    {
        [$sahodaya, $school, $user] = $this->seedSetup();
        $exam = McqExam::create(['tenant_id' => $sahodaya->id, 'title' => 'Timings', 'status' => 'draft']);
        $this->actingAs($user)->put("/sahodaya-admin/{$sahodaya->id}/mcq-exams/{$exam->id}", [
            'title' => $exam->title, 'status' => 'draft',
            'class_schedules' => [['class_name' => 'LKG', 'scheduled_at' => 'invalid', 'duration_minutes' => 0]],
        ])->assertSessionHasErrors(['class_schedules.0.scheduled_at', 'class_schedules.0.duration_minutes']);
        $this->assertEmpty($exam->fresh()->settings_json['class_schedules'] ?? []);
    }

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
