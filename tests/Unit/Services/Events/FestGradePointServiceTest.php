<?php

namespace Tests\Unit\Services\Events;

use App\Services\Events\FestGradePointService;
use Tests\TestCase;

/**
 * Regression coverage for a real bug found while cross-checking
 * docs/STATE_KALOTSAV_MASTER_IMPLEMENTATION_PLAN.md against this codebase (2026-08-09):
 * resolveMcsGradeFromScore()/resolveConfedGradeFromScore() looped over grade bands
 * (A min-70, B min-60, C min-50) without breaking on match, so the LAST band a score
 * cleared always won — since a high score clears every lower band too, an 85 was being
 * graded C, not A. Fixed by sorting bands high-to-low and returning on first match.
 *
 * Matches the master plan's required test matrix: "Grade thresholds at
 * below/minimum/exact/above boundaries" (§17.1 / §29.14 "Connection isolation" row's
 * sibling "Results" row).
 */
class FestGradePointServiceTest extends TestCase
{
    private function service(): FestGradePointService
    {
        return app(FestGradePointService::class);
    }

    public function test_confed_grade_boundaries(): void
    {
        $service = $this->service();

        // Below the lowest band (C, min 50) — no grade.
        $this->assertNull($service->resolveConfedGradeFromScore(49.99));

        // Exactly at each threshold.
        $this->assertSame('C', $service->resolveConfedGradeFromScore(50));
        $this->assertSame('B', $service->resolveConfedGradeFromScore(60));
        $this->assertSame('A', $service->resolveConfedGradeFromScore(70));

        // Just below each threshold falls back to the next band down.
        $this->assertSame('C', $service->resolveConfedGradeFromScore(59.99));
        $this->assertSame('B', $service->resolveConfedGradeFromScore(69.99));

        // Above every threshold — the regression case. Before the fix this returned 'C'.
        $this->assertSame('A', $service->resolveConfedGradeFromScore(85));
        $this->assertSame('A', $service->resolveConfedGradeFromScore(100));
    }

    public function test_mcs_grade_boundaries(): void
    {
        $service = $this->service();

        $this->assertNull($service->resolveMcsGradeFromScore(49.99));
        $this->assertSame('C', $service->resolveMcsGradeFromScore(50));
        $this->assertSame('B', $service->resolveMcsGradeFromScore(60));
        $this->assertSame('A', $service->resolveMcsGradeFromScore(70));
        $this->assertSame('C', $service->resolveMcsGradeFromScore(59.99));
        $this->assertSame('B', $service->resolveMcsGradeFromScore(69.99));

        // Regression case — before the fix, any score above 50 resolved to 'C'.
        $this->assertSame('A', $service->resolveMcsGradeFromScore(85));
        $this->assertSame('A', $service->resolveMcsGradeFromScore(100));
    }
}
