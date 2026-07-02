<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\McqExam;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Mcq\McqEligibilityService;
use App\Support\Mcq\McqExamEligibilityConfig;
use Tests\TestCase;

class McqEligibilityServiceTest extends TestCase
{
    public function test_all_scope_allows_any_class(): void
    {
        $exam = new McqExam([
            'exam_level' => 1,
            'eligibility_config' => McqExamEligibilityConfig::normalize(['scope' => 'all']),
        ]);

        $student = new Student(['gender' => 'male']);
        $student->setRelation('schoolClass', new SchoolClass(['class_category_id' => 99, 'name' => 'Class 10']));

        $this->assertTrue(app(McqEligibilityService::class)->isEligible($exam, $student));
    }

    public function test_class_category_filter(): void
    {
        $exam = new McqExam([
            'exam_level' => 1,
            'eligibility_config' => McqExamEligibilityConfig::normalize([
                'scope' => 'filtered',
                'class_category_ids' => [2, 3],
            ]),
        ]);

        $inCategory = new Student(['gender' => 'female']);
        $inCategory->setRelation('schoolClass', new SchoolClass(['class_category_id' => 2, 'name' => 'Class 8']));

        $outCategory = new Student(['gender' => 'female']);
        $outCategory->setRelation('schoolClass', new SchoolClass(['class_category_id' => 5, 'name' => 'Class 1']));

        $service = app(McqEligibilityService::class);
        $this->assertTrue($service->isEligible($exam, $inCategory));
        $this->assertFalse($service->isEligible($exam, $outCategory));
    }

    public function test_gender_filter_with_category(): void
    {
        $exam = new McqExam([
            'exam_level' => 1,
            'eligibility_config' => McqExamEligibilityConfig::normalize([
                'scope' => 'filtered',
                'class_category_ids' => [2],
                'gender' => 'male',
            ]),
        ]);

        $eligible = new Student(['gender' => 'male']);
        $eligible->setRelation('schoolClass', new SchoolClass(['class_category_id' => 2, 'name' => 'Class 8']));

        $wrongGender = new Student(['gender' => 'female']);
        $wrongGender->setRelation('schoolClass', new SchoolClass(['class_category_id' => 2, 'name' => 'Class 8']));

        $service = app(McqEligibilityService::class);
        $this->assertTrue($service->isEligible($exam, $eligible));
        $this->assertFalse($service->isEligible($exam, $wrongGender));
    }
}
