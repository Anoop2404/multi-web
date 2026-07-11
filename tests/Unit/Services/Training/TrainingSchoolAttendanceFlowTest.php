<?php

namespace Tests\Unit\Services\Training;

use App\Models\SahodayaProfile;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Services\Training\TeacherTrainingEligibilityService;
use App\Services\Training\TrainingRegistrationLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingSchoolAttendanceFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: Tenant, 2: TrainingProgram} */
    private function seedProgram(array $programOverrides = []): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sahodaya',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'teacher_registration_enabled' => true,
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'School',
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'is_active' => true,
            'membership_status' => 'approved',
        ]);
        $program = TrainingProgram::create(array_merge([
            'tenant_id' => $sahodaya->id,
            'title' => 'Workshop',
            'status' => 'published',
            'fee_type' => 'none',
            'require_verified_teachers' => false,
            'allow_school_attendance' => true,
            'registration_open' => now()->subDay()->toDateString(),
            'registration_close' => now()->addDays(5)->toDateString(),
        ], $programOverrides));

        return [$sahodaya, $school, $program];
    }

    public function test_unverified_teacher_is_eligible_when_not_required(): void
    {
        [, $school, $program] = $this->seedProgram();
        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name' => 'Unverified',
            'email' => 'u@t.test',
            'status' => 'active',
            'verified_at' => null,
        ]);

        $this->assertTrue(app(TeacherTrainingEligibilityService::class)->isEligible($program, $teacher));
    }

    public function test_unverified_teacher_blocked_when_required(): void
    {
        [, $school, $program] = $this->seedProgram(['require_verified_teachers' => true]);
        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name' => 'Unverified',
            'email' => 'u2@t.test',
            'status' => 'active',
            'verified_at' => null,
        ]);

        $this->assertFalse(app(TeacherTrainingEligibilityService::class)->isEligible($program, $teacher));
        $this->assertNotNull(app(TeacherTrainingEligibilityService::class)->ineligibilityReason($program, $teacher));
    }

    public function test_fee_free_registration_auto_confirms(): void
    {
        [, , $program] = $this->seedProgram();

        $this->assertSame('confirmed', app(TrainingRegistrationLifecycle::class)->initialStatus($program));
    }

    public function test_qr_paid_registration_auto_confirms(): void
    {
        [, , $program] = $this->seedProgram([
            'fee_type' => 'flat',
            'fee_amount' => 100,
        ]);

        $this->assertSame('confirmed', app(TrainingRegistrationLifecycle::class)->initialStatus($program, 'qr'));
    }

    public function test_paid_school_registration_stays_registered(): void
    {
        [, , $program] = $this->seedProgram([
            'fee_type' => 'flat',
            'fee_amount' => 100,
        ]);

        $this->assertSame('registered', app(TrainingRegistrationLifecycle::class)->initialStatus($program));
    }

    public function test_unverified_confirmed_teacher_can_have_attendance(): void
    {
        [, $school, $program] = $this->seedProgram();
        $session = TrainingSession::create(['program_id' => $program->id, 'title' => 'Day 1']);
        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name' => 'Attend Me',
            'email' => 'a@t.test',
            'status' => 'active',
            'verified_at' => null,
        ]);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id' => $school->id,
            'status' => 'confirmed',
        ]);

        $this->assertTrue(app(TrainingRegistrationLifecycle::class)->canMarkAttendance($registration, $program));

        $attendance = TrainingAttendance::updateOrCreate(
            ['session_id' => $session->id, 'registration_id' => $registration->id],
            ['status' => 'present', 'marked_at' => now()]
        );

        $this->assertSame('present', $attendance->status);
    }

    public function test_paid_school_registration_cannot_mark_attendance_until_confirmed(): void
    {
        [, $school, $program] = $this->seedProgram([
            'fee_type' => 'flat',
            'fee_amount' => 100,
        ]);
        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name' => 'Waiting',
            'email' => 'wait@t.test',
            'status' => 'active',
        ]);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id' => $school->id,
            'status' => 'registered',
            'registration_source' => 'school',
        ]);

        $this->assertFalse(app(TrainingRegistrationLifecycle::class)->canMarkAttendance($registration, $program));
    }

    public function test_registered_status_has_no_attendance_bypass(): void
    {
        [, $school, $program] = $this->seedProgram(['fee_type' => 'none']);
        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name' => 'Legacy',
            'email' => 'legacy@t.test',
            'status' => 'active',
        ]);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id' => $school->id,
            'status' => 'registered',
            'registration_source' => 'qr',
        ]);

        $this->assertFalse(app(TrainingRegistrationLifecycle::class)->canMarkAttendance($registration, $program));
    }
}
