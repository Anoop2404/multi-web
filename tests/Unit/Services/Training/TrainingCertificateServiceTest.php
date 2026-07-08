<?php

namespace Tests\Unit\Services\Training;

use App\Models\CertificateTemplate;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Services\Training\TrainingCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrainingCertificateServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: Tenant} */
    private function seedTenants(): array
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        $sahodaya = Tenant::create([
            'id'        => $sahodayaId,
            'name'      => 'Malappuram Central Sahodaya',
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

        return [$sahodaya, $school];
    }

    public function test_requires_at_least_one_present_day(): void
    {
        [$sahodaya, $school] = $this->seedTenants();
        $teacher = Teacher::create(['tenant_id' => $school->id, 'name' => 'Teacher A', 'status' => 'active']);

        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id,
            'title'     => 'Synergy 26',
            'status'    => 'ongoing',
            'fee_type'  => 'none',
        ]);
        $session = TrainingSession::create(['program_id' => $program->id, 'title' => 'Day 1']);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id'  => $school->id,
            'status'     => 'confirmed',
        ]);

        $service = app(TrainingCertificateService::class);

        try {
            $service->assertEligible($registration);
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
            // expected
        }

        TrainingAttendance::create([
            'session_id'      => $session->id,
            'registration_id' => $registration->id,
            'status'          => 'present',
        ]);

        $service->assertEligible($registration->fresh());
    }

    public function test_conducted_on_reflects_only_present_days(): void
    {
        [$sahodaya, $school] = $this->seedTenants();
        $teacher = Teacher::create([
            'tenant_id'   => $school->id,
            'name'        => 'Jane Teacher',
            'designation' => 'PGT',
            'status'      => 'active',
        ]);

        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id,
            'title'     => "SYNERGY'26 – Teacher Training Programme",
            'venue'     => 'St. Alphonsa Public School, Oorakam',
            'status'    => 'completed',
            'fee_type'  => 'none',
        ]);
        $day1 = TrainingSession::create([
            'program_id'   => $program->id,
            'title'        => 'Day 1',
            'scheduled_at' => '2026-07-11 09:00:00',
        ]);
        $day2 = TrainingSession::create([
            'program_id'   => $program->id,
            'title'        => 'Day 2',
            'scheduled_at' => '2026-07-12 09:00:00',
        ]);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id'  => $school->id,
            'status'     => 'confirmed',
        ]);

        TrainingAttendance::create([
            'session_id'      => $day1->id,
            'registration_id' => $registration->id,
            'status'          => 'present',
        ]);
        TrainingAttendance::create([
            'session_id'      => $day2->id,
            'registration_id' => $registration->id,
            'status'          => 'absent',
        ]);

        CertificateTemplate::create([
            'tenant_id'        => $sahodaya->id,
            'event_type'       => 'training',
            'certificate_type' => 'participation',
            'title'            => 'Certificate of Participation',
            'body'             => CertificateTemplate::defaultTrainingBody(),
            'is_active'        => true,
        ]);

        $service = app(TrainingCertificateService::class);
        $registration->load(['program', 'teacher', 'school']);
        $fields = $service->resolveFieldValues($registration, $sahodaya);

        $this->assertSame('11 July 2026', $fields['conducted_on']);
        $this->assertSame('1', $fields['days_attended']);
        $this->assertSame('2', $fields['total_days']);
        $this->assertSame('St. Alphonsa Public School, Oorakam', $fields['venue']);
        $this->assertSame('Jane Teacher', $fields['recipient_name']);
        $this->assertSame('PGT', $fields['designation']);
    }
}
