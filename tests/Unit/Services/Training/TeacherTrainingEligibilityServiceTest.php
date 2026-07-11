<?php

namespace Tests\Unit\Services\Training;

use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolRegionAssignment;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Services\Training\TeacherTrainingEligibilityService;
use App\Support\Training\TrainingProgramEligibilityConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeacherTrainingEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: Tenant, 2: TrainingProgram, 3: Teacher} */
    private function seedContext(array $programOverrides = [], array $teacherOverrides = []): array
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
            'registration_open' => now()->subDay()->toDateString(),
            'registration_close' => now()->addDays(5)->toDateString(),
        ], $programOverrides));

        $teacher = Teacher::create(array_merge([
            'tenant_id' => $school->id,
            'name' => 'Eligible Teacher',
            'email' => 'eligible@t.test',
            'status' => 'active',
            'teaching_type_id' => 1,
            'subject_ids' => [10, 20],
            'designation_id' => 5,
            'experience_years' => 5,
        ], $teacherOverrides));

        return [$sahodaya, $school, $program, $teacher];
    }

    public function test_normalize_extends_legacy_keys(): void
    {
        $normalized = TrainingProgramEligibilityConfig::normalize([
            'teaching_type_ids' => ['1', 2],
            'subject_ids' => [3],
            'designation_exclude' => [9],
            'min_experience_years' => 2,
            'prior_training_required' => true,
            'prior_training_program_id' => 44,
            'region_ids' => [7],
        ]);

        $this->assertSame([1, 2], $normalized['teaching_type_ids']);
        $this->assertSame([3], $normalized['subject_ids']);
        $this->assertSame([9], $normalized['excluded_designation_ids']);
        $this->assertSame(2, $normalized['min_experience_years']);
        $this->assertTrue($normalized['prior_training']['required']);
        $this->assertSame(44, $normalized['prior_training']['program_id']);
        $this->assertSame([7], $normalized['region_ids']);
    }

    public function test_teaching_type_and_subject_still_enforced(): void
    {
        [, , $program, $teacher] = $this->seedContext([
            'eligibility_config' => [
                'teaching_type_ids' => [1],
                'subject_ids' => [10],
            ],
        ]);

        $service = app(TeacherTrainingEligibilityService::class);
        $this->assertTrue($service->isEligible($program, $teacher));

        $teacher->update(['teaching_type_id' => 99]);
        $this->assertFalse($service->isEligible($program, $teacher->fresh()));
        $this->assertStringContainsString('teaching category', (string) $service->ineligibilityReason($program, $teacher->fresh()));

        $teacher->update(['teaching_type_id' => 1, 'subject_ids' => [99]]);
        $this->assertFalse($service->isEligible($program, $teacher->fresh()));
        $this->assertStringContainsString('subject', (string) $service->ineligibilityReason($program, $teacher->fresh()));
    }

    public function test_excluded_designation_blocks(): void
    {
        [, , $program, $teacher] = $this->seedContext([
            'eligibility_config' => [
                'excluded_designation_ids' => [5],
            ],
        ]);

        $service = app(TeacherTrainingEligibilityService::class);
        $this->assertFalse($service->isEligible($program, $teacher));
        $this->assertStringContainsString('designation', (string) $service->ineligibilityReason($program, $teacher));

        $teacher->update(['designation_id' => 6]);
        $this->assertTrue($service->isEligible($program, $teacher->fresh()));
    }

    public function test_min_experience_years(): void
    {
        [, , $program, $teacher] = $this->seedContext([
            'eligibility_config' => [
                'min_experience_years' => 3,
            ],
        ], [
            'experience_years' => 2,
        ]);

        $service = app(TeacherTrainingEligibilityService::class);
        $this->assertFalse($service->isEligible($program, $teacher));
        $this->assertStringContainsString('3 year', (string) $service->ineligibilityReason($program, $teacher));

        $teacher->update(['experience_years' => 3]);
        $this->assertTrue($service->isEligible($program, $teacher->fresh()));
    }

    public function test_prior_training_any_and_specific(): void
    {
        [$sahodaya, $school, $program, $teacher] = $this->seedContext([
            'eligibility_config' => [
                'prior_training' => ['required' => true, 'program_id' => null],
            ],
        ]);

        $service = app(TeacherTrainingEligibilityService::class);
        $this->assertFalse($service->isEligible($program, $teacher));

        $prior = TrainingProgram::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Prior',
            'status' => 'completed',
            'fee_type' => 'none',
        ]);
        TrainingRegistration::create([
            'program_id' => $prior->id,
            'teacher_id' => $teacher->id,
            'school_id' => $school->id,
            'status' => 'confirmed',
        ]);

        $this->assertTrue($service->isEligible($program, $teacher));

        $program->update([
            'eligibility_config' => [
                'prior_training' => ['required' => true, 'program_id' => $prior->id + 999],
            ],
        ]);
        $this->assertFalse($service->isEligible($program->fresh(), $teacher));

        $program->update([
            'eligibility_config' => [
                'prior_training' => ['required' => true, 'program_id' => $prior->id],
            ],
        ]);
        $this->assertTrue($service->isEligible($program->fresh(), $teacher));
    }

    public function test_region_ids_via_school_assignment(): void
    {
        [$sahodaya, $school, $program, $teacher] = $this->seedContext([
            'eligibility_config' => [
                'region_ids' => [],
            ],
        ]);

        $region = Region::create([
            'tenant_id' => $sahodaya->id,
            'name' => 'North',
            'code' => 'N',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $program->update([
            'eligibility_config' => ['region_ids' => [$region->id]],
        ]);

        $service = app(TeacherTrainingEligibilityService::class);
        $this->assertFalse($service->isEligible($program->fresh(), $teacher));

        SchoolRegionAssignment::create([
            'tenant_id' => $sahodaya->id,
            'region_id' => $region->id,
            'school_id' => $school->id,
            'academic_year' => '2025-26',
            'source' => 'sahodaya',
        ]);

        $this->assertTrue($service->isEligible($program->fresh(), $teacher));
    }

    public function test_assert_teacher_eligible_throws(): void
    {
        [, , $program, $teacher] = $this->seedContext([
            'eligibility_config' => [
                'min_experience_years' => 10,
            ],
        ], [
            'experience_years' => 1,
        ]);

        $this->expectException(ValidationException::class);
        app(TeacherTrainingEligibilityService::class)->assertTeacherEligible($program, $teacher);
    }
}
