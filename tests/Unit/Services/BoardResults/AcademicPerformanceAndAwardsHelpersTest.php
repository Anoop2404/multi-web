<?php

namespace Tests\Unit\Services\BoardResults;

use App\Models\AcademicAward;
use App\Models\ApiConfig;
use App\Services\BoardResults\AcademicPerformanceIndexEngine;
use App\Services\BoardResults\AwardsEngine;
use App\Services\BoardResults\TopperCountService;
use PHPUnit\Framework\TestCase;

class AcademicPerformanceAndAwardsHelpersTest extends TestCase
{
    public function test_api_config_normalizes_weights_to_100(): void
    {
        $config = new ApiConfig([
            'weight_pass_percent' => 40,
            'weight_distinctions' => 20,
            'weight_highest_mark' => 20,
            'weight_toppers' => 20,
        ]);

        $weights = $config->normalizedWeights();
        $this->assertEqualsWithDelta(100.0, array_sum($weights), 0.01);
        $this->assertEqualsWithDelta(40.0, $weights['pass_percent'], 0.01);
    }

    public function test_award_types_cover_seven_frd_categories(): void
    {
        $this->assertCount(7, AcademicAward::types());
        $this->assertContains(AcademicAward::TYPE_BEST_ACADEMIC_SCHOOL, AcademicAward::types());
        $this->assertContains(AcademicAward::TYPE_BEST_SCIENCE, AcademicAward::types());
        $this->assertContains(AcademicAward::TYPE_EXCELLENCE, AcademicAward::types());
        $this->assertArrayHasKey(AcademicAward::TYPE_BEST_ACADEMIC_SCHOOL, AcademicAward::typeLabels());
    }

    public function test_topper_count_default_cap(): void
    {
        $this->assertSame(5, TopperCountService::DEFAULT_TOP_N);
    }

    public function test_api_engine_and_awards_engine_are_constructible(): void
    {
        $this->assertInstanceOf(AcademicPerformanceIndexEngine::class, new AcademicPerformanceIndexEngine);
        $this->assertInstanceOf(AwardsEngine::class, new AwardsEngine);
    }
}
