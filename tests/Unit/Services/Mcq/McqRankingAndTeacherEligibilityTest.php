<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\McqExam;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\Mcq\McqEligibilityService;
use App\Services\Mcq\McqSeatingService;
use App\Support\Mcq\McqExamEligibilityConfig;
use Tests\TestCase;

class McqRankingAndTeacherEligibilityTest extends TestCase
{
    public function test_teacher_audience_rejects_students(): void
    {
        $exam = new McqExam([
            'exam_level' => 1,
            'eligibility_config' => McqExamEligibilityConfig::normalize(['audience' => 'teachers']),
        ]);

        $student = new Student(['gender' => 'male']);
        $this->assertFalse(app(McqEligibilityService::class)->isEligible($exam, $student));
        $this->assertSame(
            'This exam is not open to students.',
            app(McqEligibilityService::class)->ineligibilityReason($exam, $student),
        );
    }

    public function test_teacher_eligibility_min_experience(): void
    {
        $exam = new McqExam([
            'exam_level' => 1,
            'eligibility_config' => McqExamEligibilityConfig::normalize([
                'audience' => 'teachers',
                'min_experience_years' => 5,
            ]),
        ]);

        $eligible = new Teacher(['status' => 'active', 'experience_years' => 6, 'verified_at' => now()]);
        $ineligible = new Teacher(['status' => 'active', 'experience_years' => 2, 'verified_at' => now()]);

        $service = app(McqEligibilityService::class);
        $this->assertTrue($service->isTeacherEligible($exam, $eligible));
        $this->assertFalse($service->isTeacherEligible($exam, $ineligible));
    }

    public function test_default_bands_mark_d_and_f_not_rank_eligible(): void
    {
        $bands = app(\App\Services\Mcq\McqGradeService::class)->bandsForExam(new McqExam([]));
        $byLabel = collect($bands)->keyBy('label');

        $this->assertTrue($byLabel['A']['rank_eligible']);
        $this->assertFalse($byLabel['D']['rank_eligible']);
        $this->assertFalse($byLabel['F']['rank_eligible']);
    }

    public function test_seating_normalizes_halls(): void
    {
        $exam = new McqExam([
            'settings_json' => [
                'halls' => [
                    ['name' => 'Hall A', 'capacity' => 20],
                    ['name' => '', 'capacity' => 10],
                    ['name' => 'Hall B', 'capacity' => 0],
                ],
            ],
        ]);

        $halls = app(McqSeatingService::class)->normalizedHalls($exam);
        $this->assertCount(1, $halls);
        $this->assertSame('Hall A', $halls[0]['name']);
        $this->assertSame(20, $halls[0]['capacity']);
    }

    public function test_eligibility_config_allows_helpers(): void
    {
        $this->assertTrue(McqExamEligibilityConfig::allowsStudents(['audience' => 'students']));
        $this->assertFalse(McqExamEligibilityConfig::allowsTeachers(['audience' => 'students']));
        $this->assertTrue(McqExamEligibilityConfig::allowsTeachers(['audience' => 'both']));
        $this->assertTrue(McqExamEligibilityConfig::allowsStudents(['audience' => 'both']));
    }
}
