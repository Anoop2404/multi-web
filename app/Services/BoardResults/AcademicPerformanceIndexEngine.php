<?php

namespace App\Services\BoardResults;

use App\Models\AcademicPerformanceScore;
use App\Models\ApiConfig;
use App\Models\BoardResult;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class AcademicPerformanceIndexEngine
{
    /**
     * Recompute API scores for all approved/published results in a Sahodaya year.
     *
     * @return array{rows: int}
     */
    public function recompute(string $sahodayaId, string $academicYear): array
    {
        $config = ApiConfig::forSahodaya($sahodayaId);
        $weights = $config->normalizedWeights();

        $schoolIds = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->pluck('id')
            ->all();

        $results = BoardResult::query()
            ->whereIn('tenant_id', $schoolIds)
            ->where('academic_year', $academicYear)
            ->whereIn('status', [BoardResult::STATUS_APPROVED, BoardResult::STATUS_PUBLISHED])
            ->withCount('toppers')
            ->get();

        $maxDistRate = max(0.0001, (float) $results->max(function (BoardResult $r) {
            $appeared = max(1, (int) $r->total_appeared);

            return ((int) $r->distinctions) / $appeared * 100;
        }));
        $maxToppers = max(1, (int) $results->max('toppers_count'));
        $maxHighest = max(0.0001, (float) $results->max('highest_mark'));

        $written = 0;

        DB::transaction(function () use ($sahodayaId, $academicYear, $results, $weights, $maxDistRate, $maxToppers, $maxHighest, &$written) {
            AcademicPerformanceScore::query()
                ->where('sahodaya_id', $sahodayaId)
                ->where('academic_year', $academicYear)
                ->delete();

            foreach ($results as $result) {
                $appeared = max(1, (int) $result->total_appeared);
                $distRate = ((int) $result->distinctions) / $appeared * 100;
                $passComponent = min(100.0, (float) $result->pass_percent);
                $distComponent = min(100.0, $distRate / $maxDistRate * 100);
                $highComponent = min(100.0, ((float) ($result->highest_mark ?? 0)) / $maxHighest * 100);
                $topperComponent = min(100.0, ((int) $result->toppers_count) / $maxToppers * 100);

                $score =
                    ($passComponent * $weights['pass_percent'] / 100)
                    + ($distComponent * $weights['distinctions'] / 100)
                    + ($highComponent * $weights['highest_mark'] / 100)
                    + ($topperComponent * $weights['toppers'] / 100);

                AcademicPerformanceScore::create([
                    'sahodaya_id' => $sahodayaId,
                    'tenant_id' => $result->tenant_id,
                    'academic_year' => $academicYear,
                    'academic_year_id' => $result->academic_year_id,
                    'examination_type' => $result->examination_type,
                    'class' => $result->class,
                    'board_result_id' => $result->id,
                    'score' => round($score, 4),
                    'components' => [
                        'pass_percent' => round($passComponent, 4),
                        'distinctions' => round($distComponent, 4),
                        'highest_mark' => round($highComponent, 4),
                        'toppers' => round($topperComponent, 4),
                        'weights' => $weights,
                    ],
                ]);
                $written++;
            }
        });

        return ['rows' => $written];
    }

    public function scoreForSchool(string $sahodayaId, string $schoolId, string $academicYear): ?float
    {
        $row = AcademicPerformanceScore::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where('tenant_id', $schoolId)
            ->where('academic_year', $academicYear)
            ->orderByDesc('score')
            ->first();

        return $row?->score;
    }
}
