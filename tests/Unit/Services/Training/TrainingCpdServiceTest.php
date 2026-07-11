<?php

namespace Tests\Unit\Services\Training;

use App\Models\AcademicYearRecord;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Services\Training\TrainingCpdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingCpdServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: Tenant, 2: Teacher, 3: TrainingProgram, 4: TrainingRegistration, 5: int} */
    private function seedPresentAttendance(int $durationMinutes = 120, string $regStatus = 'confirmed'): array
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        $sahodaya = Tenant::create([
            'id' => $sahodayaId,
            'name' => 'CPD Sahodaya',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id' => $schoolId,
            'name' => 'CPD School',
            'type' => 'school',
            'parent_id' => $sahodayaId,
            'is_active' => true,
        ]);

        $year = AcademicYearRecord::create([
            'label' => '2025-26',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
            'status' => 'active',
        ]);

        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name' => 'Teacher CPD',
            'status' => 'active',
        ]);

        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id,
            'academic_year_id' => $year->id,
            'title' => 'CPD Programme',
            'status' => 'completed',
            'fee_type' => 'none',
        ]);

        $session = TrainingSession::create([
            'program_id' => $program->id,
            'title' => 'Day 1',
            'duration_minutes' => $durationMinutes,
            'scheduled_at' => '2025-07-01 09:00:00',
        ]);

        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id' => $school->id,
            'status' => $regStatus,
        ]);

        TrainingAttendance::create([
            'session_id' => $session->id,
            'registration_id' => $registration->id,
            'status' => 'present',
        ]);

        return [$sahodaya, $school, $teacher, $program, $registration, $year->id];
    }

    public function test_hours_for_teacher_sums_present_session_durations(): void
    {
        [, , $teacher, , , $yearId] = $this->seedPresentAttendance(90);
        $service = app(TrainingCpdService::class);

        $this->assertSame(1.5, $service->hoursForTeacher($teacher, $yearId));
    }

    public function test_ignores_absent_and_unconfirmed_registrations(): void
    {
        [$sahodaya, $school, $teacher, $program, $registration, $yearId] = $this->seedPresentAttendance(120);

        $day2 = TrainingSession::create([
            'program_id' => $program->id,
            'title' => 'Day 2',
            'duration_minutes' => 60,
        ]);
        TrainingAttendance::create([
            'session_id' => $day2->id,
            'registration_id' => $registration->id,
            'status' => 'absent',
        ]);

        $otherTeacher = Teacher::create([
            'tenant_id' => $school->id,
            'name' => 'Unconfirmed Teacher',
            'status' => 'active',
        ]);
        $otherReg = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $otherTeacher->id,
            'school_id' => $school->id,
            'status' => 'registered',
        ]);
        TrainingAttendance::create([
            'session_id' => $day2->id,
            'registration_id' => $otherReg->id,
            'status' => 'present',
        ]);

        $service = app(TrainingCpdService::class);

        $this->assertSame(2.0, $service->hoursForTeacher($teacher, $yearId));
        $this->assertSame(0.0, $service->hoursForTeacher($otherTeacher, $yearId));
        $this->assertSame(2.0, $service->totalHoursForSahodaya($sahodaya->id, $yearId));
    }

    public function test_summary_for_school_and_sahodaya(): void
    {
        [$sahodaya, $school, $teacher, , , $yearId] = $this->seedPresentAttendance(180);
        $service = app(TrainingCpdService::class);

        $schoolSummary = $service->summaryForSchool($school->id, $yearId);
        $this->assertCount(1, $schoolSummary);
        $this->assertSame($teacher->id, $schoolSummary->first()['teacher_id']);
        $this->assertSame(3.0, $schoolSummary->first()['hours']);

        $sahodayaSummary = $service->summaryForSahodaya($sahodaya->id, $yearId);
        $this->assertCount(1, $sahodayaSummary);
        $this->assertSame($school->id, $sahodayaSummary->first()['school_id']);
        $this->assertSame(3.0, $sahodayaSummary->first()['hours']);
        $this->assertSame(1, $sahodayaSummary->first()['teachers']);
    }

    public function test_filters_by_academic_year(): void
    {
        [, , $teacher, $program, , $yearId] = $this->seedPresentAttendance(60);

        $otherYear = AcademicYearRecord::create([
            'label' => '2024-25',
            'start_date' => '2024-06-01',
            'end_date' => '2025-05-31',
            'status' => 'closed',
        ]);
        $program->update(['academic_year_id' => $otherYear->id]);

        $service = app(TrainingCpdService::class);

        $this->assertSame(0.0, $service->hoursForTeacher($teacher, $yearId));
        $this->assertSame(1.0, $service->hoursForTeacher($teacher, $otherYear->id));
    }
}
