<?php

namespace App\Services\BoardResults;

use App\Models\AcademicAward;
use App\Models\BoardResult;
use App\Models\BoardResultRanking;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

/**
 * Academic Excellence Report + Historical Comparison (#148).
 * Uses awards when present; otherwise rankings + pass-% trend.
 */
class AcademicExcellenceReportService
{
    public function __construct(
        private readonly RankingEngine $ranking,
    ) {}

    /**
     * @return array{
     *   academic_year: string,
     *   awards: list<array<string, mixed>>,
     *   top_schools: list<array<string, mixed>>,
     *   historical: list<array{academic_year: string, avg_pass_percent: float, schools: int}>,
     *   year_comparison: list<array<string, mixed>>,
     *   source: string
     * }
     */
    public function report(string $sahodayaId, string $academicYear): array
    {
        $awards = $this->awards($sahodayaId, $academicYear);
        $topSchools = $this->ranking->topSchools($sahodayaId, $academicYear, 10);
        $historical = $this->ranking->passPercentTrend($sahodayaId, 6);
        $yearComparison = $this->yearComparison($sahodayaId, $historical);

        return [
            'academic_year' => $academicYear,
            'awards' => $awards,
            'top_schools' => $topSchools,
            'historical' => $historical,
            'year_comparison' => $yearComparison,
            'source' => $awards !== [] ? 'awards+rankings' : 'rankings',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function awards(string $sahodayaId, string $academicYear): array
    {
        if (! Schema::hasTable('academic_awards')) {
            return [];
        }

        $rows = AcademicAward::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where('academic_year', $academicYear)
            ->orderBy('award_type')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = Tenant::whereIn('id', $rows->pluck('tenant_id'))->pluck('name', 'id');

        return $rows->map(fn (AcademicAward $a) => [
            'id' => $a->id,
            'award_type' => $a->award_type,
            'title' => $a->title ?? str_replace('_', ' ', ucfirst($a->award_type)),
            'school_id' => $a->tenant_id,
            'school_name' => $names[$a->tenant_id] ?? $a->tenant_id,
            'score' => $a->score,
            'class' => $a->meta['class'] ?? null,
            'examination_type' => $a->meta['examination_type'] ?? null,
            'meta' => $a->meta,
        ])->all();
    }

    /**
     * @param  list<array{academic_year: string, avg_pass_percent: float, schools: int}>  $historical
     * @return list<array<string, mixed>>
     */
    private function yearComparison(string $sahodayaId, array $historical): array
    {
        $schoolIds = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->pluck('id')
            ->all();

        $comparison = [];
        foreach ($historical as $row) {
            $published = BoardResult::query()
                ->whereIn('tenant_id', $schoolIds)
                ->where('academic_year', $row['academic_year'])
                ->where('status', BoardResult::STATUS_PUBLISHED)
                ->count();

            $rankRows = BoardResultRanking::query()
                ->where('sahodaya_id', $sahodayaId)
                ->where('academic_year', $row['academic_year'])
                ->where('scope', RankingEngine::SCOPE_OVERALL_PASS_PERCENT)
                ->count();

            $comparison[] = [
                'academic_year' => $row['academic_year'],
                'avg_pass_percent' => $row['avg_pass_percent'],
                'schools_reported' => $row['schools'],
                'published_count' => $published,
                'ranked_schools' => $rankRows,
            ];
        }

        return $comparison;
    }
}
