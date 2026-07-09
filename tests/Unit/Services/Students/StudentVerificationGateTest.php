<?php

namespace Tests\Unit\Services\Students;

use App\Models\McqExam;
use App\Models\SahodayaProfile;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Students\StudentVerificationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentVerificationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcq_exam_inherits_cluster_default_when_no_override(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'gate-mcq-inherit.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'require_student_verification' => false,
        ]);

        $exam = new McqExam([
            'tenant_id' => $sahodaya->id,
            'settings_json' => [],
        ]);

        $gate = app(StudentVerificationGate::class);

        $this->assertFalse($gate->requiredForMcq($exam));
        $this->assertTrue($gate->isEligible(new Student(['verified_at' => null]), null, null, $exam));
    }

    public function test_mcq_exam_override_requires_verified_students(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'gate-mcq-required.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'require_student_verification' => false,
        ]);

        $exam = new McqExam([
            'tenant_id' => $sahodaya->id,
            'settings_json' => ['require_verified_students' => true],
        ]);

        $gate = app(StudentVerificationGate::class);
        $unverified = new Student(['verified_at' => null]);

        $this->assertTrue($gate->requiredForMcq($exam));
        $this->assertFalse($gate->isEligible($unverified, null, null, $exam));
        $this->assertSame(
            'Student must be verified before registration.',
            $gate->ineligibilityReason($unverified, null, null, $exam),
        );
    }

    public function test_mcq_exam_override_allows_unverified_students(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'gate-mcq-optional.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'require_student_verification' => true,
        ]);

        $exam = new McqExam([
            'tenant_id' => $sahodaya->id,
            'settings_json' => ['require_verified_students' => false],
        ]);

        $gate = app(StudentVerificationGate::class);

        $this->assertFalse($gate->requiredForMcq($exam));
        $this->assertTrue($gate->isEligible(new Student(['verified_at' => null]), null, null, $exam));
    }
}
