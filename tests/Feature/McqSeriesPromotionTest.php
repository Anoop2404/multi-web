<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Mcq\McqEligibilityService;
use App\Services\Mcq\McqSeriesPromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McqSeriesPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_level2_promotion_locks_qualifiers_by_cutoff(): void
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        SahodayaProfile::create([
            'tenant_id'                   => $sahodayaId,
            'require_student_verification'=> false,
        ]);

        Tenant::create([
            'id'        => $schoolId,
            'type'      => 'school',
            'name'      => 'Promo School',
            'parent_id' => $sahodayaId,
            'is_active' => true,
        ]);

        $level1 = McqExam::create([
            'tenant_id'         => $sahodayaId,
            'title'             => 'Science Level 1',
            'exam_type'         => 'assessment',
            'status'            => 'completed',
            'exam_level'        => 1,
            'results_published' => true,
            'promotion_locked'  => true,
        ]);

        $level2 = McqExam::create([
            'tenant_id'        => $sahodayaId,
            'title'            => 'Science Level 2',
            'exam_type'        => 'assessment',
            'status'           => 'published',
            'exam_level'       => 2,
            'parent_exam_id'   => $level1->id,
            'eligibility_mode' => 'cutoff_marks',
            'cutoff_score'     => 8,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $schoolId,
            'name'      => 'Class 9',
            'is_active' => true,
        ]);

        $qualifiedStudent = Student::create([
            'tenant_id'       => $schoolId,
            'school_class_id' => $class->id,
            'name'            => 'Top Scorer',
            'status'          => 'active',
        ]);

        $belowCutoffStudent = Student::create([
            'tenant_id'       => $schoolId,
            'school_class_id' => $class->id,
            'name'            => 'Low Scorer',
            'status'          => 'active',
        ]);

        $absentStudent = Student::create([
            'tenant_id'       => $schoolId,
            'school_class_id' => $class->id,
            'name'            => 'Absent Student',
            'status'          => 'active',
        ]);

        foreach ([
            [$qualifiedStudent, 10, 'present', 'submitted'],
            [$belowCutoffStudent, 5, 'present', 'submitted'],
            [$absentStudent, null, 'absent', 'registered'],
        ] as [$student, $score, $attendance, $status]) {
            $registration = McqRegistration::create([
                'exam_id'           => $level1->id,
                'student_id'        => $student->id,
                'school_id'         => $schoolId,
                'status'            => $status,
                'attendance_status' => $attendance,
                'approval_status'   => 'approved',
            ]);

            if ($score !== null) {
                McqMark::create([
                    'registration_id' => $registration->id,
                    'score'           => $score,
                    'percentage'      => $score * 10,
                    'rank'            => $score === 10 ? 1 : 2,
                ]);
            }
        }

        $promotion = app(McqSeriesPromotionService::class);
        $qualifiers = $promotion->qualifiers($level2);

        $this->assertCount(1, $qualifiers);
        $this->assertSame($qualifiedStudent->id, $qualifiers->first()['student_id']);

        $locked = $promotion->lockPromotionList($level2, 1);

        $this->assertTrue($locked->promotion_locked);
        $this->assertSame([$qualifiedStudent->id], $locked->promoted_student_ids);
        $this->assertSame('manual', $locked->eligibility_mode);

        $eligibility = app(McqEligibilityService::class);
        $this->assertTrue($eligibility->isEligible($locked, $qualifiedStudent));
        $this->assertFalse($eligibility->isEligible($locked, $belowCutoffStudent));
        $this->assertStringContainsString('promotion list', (string) $eligibility->ineligibilityReason($locked, $belowCutoffStudent));
    }
}
