<?php

namespace App\Services\BoardResults;

use App\Models\AcademicAward;
use App\Models\Achievement;
use App\Models\BoardResult;
use App\Models\BoardResultRanking;
use App\Models\ExamStream;
use App\Models\Tenant;
use App\Models\Topper;
use App\Services\Audit\DataChangeLogger;
use App\Support\AchievementCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Awards Engine — used by publish pipeline + Achievement linkage (#152).
 */
class AwardsEngine
{
    /**
     * @return array{awards: int, achievements: int}
     */
    public function recompute(string $sahodayaId, string $academicYear): array
    {
        if (! Schema::hasTable('academic_awards')) {
            return ['awards' => 0, 'achievements' => 0];
        }

        $schoolIds = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->pluck('id')
            ->all();

        $created = 0;

        DB::transaction(function () use ($sahodayaId, $academicYear, $schoolIds, &$created) {
            // Clear system achievements linked to awards we are about to replace.
            if (Schema::hasColumn('achievements', 'source_award_id')) {
                $oldIds = AcademicAward::query()
                    ->where('sahodaya_id', $sahodayaId)
                    ->where('academic_year', $academicYear)
                    ->pluck('id');
                if ($oldIds->isNotEmpty()) {
                    Achievement::query()
                        ->whereIn('source_award_id', $oldIds)
                        ->where('is_system_generated', true)
                        ->delete();
                }
            }

            AcademicAward::query()
                ->where('sahodaya_id', $sahodayaId)
                ->where('academic_year', $academicYear)
                ->delete();

            app(DataChangeLogger::class)->event(
                'awards_recompute_started',
                "Awards engine recomputing for {$academicYear}",
                null,
                'board_result',
                null,
                ['sahodaya_id' => $sahodayaId, 'academic_year' => $academicYear],
            );
            $created += $this->awardFromRanking(
                $sahodayaId,
                $academicYear,
                AcademicAward::TYPE_BEST_ACADEMIC_SCHOOL,
                RankingEngine::SCOPE_OVERALL_PASS_PERCENT,
                null,
                'Best Academic School'
            );

            $created += $this->awardFromRanking(
                $sahodayaId,
                $academicYear,
                AcademicAward::TYPE_BEST_CLASS_X,
                RankingEngine::SCOPE_OVERALL_PASS_PERCENT,
                10,
                'Best Class X School'
            );

            $created += $this->awardFromRanking(
                $sahodayaId,
                $academicYear,
                AcademicAward::TYPE_BEST_CLASS_XII,
                RankingEngine::SCOPE_OVERALL_PASS_PERCENT,
                12,
                'Best Class XII School'
            );

            $created += $this->awardBestStreamSchool($sahodayaId, $academicYear, $schoolIds, AcademicAward::TYPE_BEST_SCIENCE, ['bio_science', 'computer_science'], 'Best Science School');
            $created += $this->awardBestStreamSchool($sahodayaId, $academicYear, $schoolIds, AcademicAward::TYPE_BEST_COMMERCE, ['commerce'], 'Best Commerce School');
            $created += $this->awardBestStreamSchool($sahodayaId, $academicYear, $schoolIds, AcademicAward::TYPE_BEST_HUMANITIES, ['humanities'], 'Best Humanities School');

            $created += $this->awardMostSubjectToppers($sahodayaId, $academicYear, $schoolIds);
            $created += $this->awardExcellence($sahodayaId, $academicYear, $schoolIds);
        });

        $achievements = $this->syncAchievements($sahodayaId, $academicYear);

        return ['awards' => $created, 'achievements' => $achievements];
    }

    private function awardFromRanking(
        string $sahodayaId,
        string $academicYear,
        string $awardType,
        string $scope,
        ?int $class,
        string $title,
    ): int {
        $query = BoardResultRanking::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where('academic_year', $academicYear)
            ->where('scope', $scope)
            ->where('rank', 1)
            ->orderByDesc('score');

        if ($class !== null) {
            $query->where('class', $class);
        }

        $top = $query->first();
        if (! $top) {
            return 0;
        }

        AcademicAward::create([
            'sahodaya_id' => $sahodayaId,
            'tenant_id' => $top->entity_id,
            'academic_year' => $academicYear,
            'award_type' => $awardType,
            'board_result_id' => $top->board_result_id,
            'score' => $top->score,
            'title' => $title,
            'meta' => array_merge($top->meta ?? [], [
                'class' => $class ?? $top->class,
                'examination_type' => $top->examination_type,
            ]),
            'computed_at' => now(),
        ]);

        return 1;
    }

    /** @param  list<string>  $schoolIds */
    private function awardMostSubjectToppers(string $sahodayaId, string $academicYear, array $schoolIds): int
    {
        if ($schoolIds === []) {
            return 0;
        }

        $counts = Topper::query()
            ->whereHas('boardResult', function ($q) use ($schoolIds, $academicYear) {
                $q->whereIn('tenant_id', $schoolIds)
                    ->where('academic_year', $academicYear)
                    ->whereIn('status', [BoardResult::STATUS_APPROVED, BoardResult::STATUS_PUBLISHED]);
            })
            ->selectRaw('tenant_id, COUNT(*) as c')
            ->groupBy('tenant_id')
            ->orderByDesc('c')
            ->first();

        if (! $counts || (int) $counts->c < 1) {
            return 0;
        }

        AcademicAward::create([
            'sahodaya_id' => $sahodayaId,
            'tenant_id' => $counts->tenant_id,
            'academic_year' => $academicYear,
            'award_type' => AcademicAward::TYPE_MOST_SUBJECT_TOPPERS,
            'score' => (float) $counts->c,
            'title' => 'Most Subject Toppers',
            'meta' => ['topper_count' => (int) $counts->c],
            'computed_at' => now(),
        ]);

        return 1;
    }

    /** @param  list<string>  $schoolIds */
    private function awardExcellence(string $sahodayaId, string $academicYear, array $schoolIds): int
    {
        $best = BoardResult::query()
            ->whereIn('tenant_id', $schoolIds)
            ->where('academic_year', $academicYear)
            ->whereIn('status', [BoardResult::STATUS_APPROVED, BoardResult::STATUS_PUBLISHED])
            ->orderByDesc('pass_percent')
            ->orderByDesc('distinctions')
            ->first();

        if (! $best) {
            return 0;
        }

        $exists = AcademicAward::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where('academic_year', $academicYear)
            ->where('award_type', AcademicAward::TYPE_BEST_ACADEMIC_SCHOOL)
            ->where('tenant_id', $best->tenant_id)
            ->exists();

        if ($exists) {
            return 0;
        }

        AcademicAward::create([
            'sahodaya_id' => $sahodayaId,
            'tenant_id' => $best->tenant_id,
            'academic_year' => $academicYear,
            'award_type' => AcademicAward::TYPE_EXCELLENCE,
            'board_result_id' => $best->id,
            'score' => $best->pass_percent,
            'title' => 'Academic Excellence',
            'meta' => [
                'pass_percent' => $best->pass_percent,
                'distinctions' => $best->distinctions,
                'class' => $best->class,
                'examination_type' => $best->examination_type,
            ],
            'computed_at' => now(),
        ]);

        return 1;
    }

    /** Auto-populate system achievements from awards (#152). */
    public function syncAchievements(string $sahodayaId, string $academicYear): int
    {
        if (! Schema::hasTable('academic_awards') || ! Schema::hasColumn('achievements', 'source_award_id')) {
            return 0;
        }

        $awards = AcademicAward::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where('academic_year', $academicYear)
            ->get();

        $synced = 0;
        foreach ($awards as $award) {
            if (! $award->tenant_id) {
                continue;
            }

            $existing = Achievement::query()
                ->where('source_award_id', $award->id)
                ->first();

            $payload = [
                'tenant_id' => $award->tenant_id,
                'title' => $award->title ?: str_replace('_', ' ', ucfirst($award->award_type)),
                'description' => 'System award for academic year '.$academicYear,
                'category' => AchievementCatalog::normalizeCategory('academic'),
                'level' => AchievementCatalog::normalizeLevel('district'),
                'academic_year' => $academicYear,
                'source_award_id' => $award->id,
                'is_system_generated' => true,
                'achieved_at' => now()->toDateString(),
            ];

            if ($existing) {
                $before = $existing->only(['title', 'category', 'level', 'academic_year']);
                $existing->update($payload);
                app(DataChangeLogger::class)->updated(
                    $existing,
                    'System achievement updated from award',
                    DataChangeLogger::diff($before, $existing->only(array_keys($before))),
                    $award->tenant_id,
                    'achievement',
                    ['source_award_id' => $award->id],
                );
            } else {
                $achievement = Achievement::create($payload);
                app(DataChangeLogger::class)->created(
                    $achievement,
                    'System achievement created from award',
                    $award->tenant_id,
                    'achievement',
                    ['source_award_id' => $award->id, 'title' => $achievement->title],
                );
            }
            $synced++;
        }

        return $synced;
    }

    /**
     * @return list<array{award_type: string, title: string, school_id: ?string, school_name: string, score: float|null}>
     */
    public function recentForDashboard(string $sahodayaId, string $academicYear, int $limit = 7): array
    {
        if (! Schema::hasTable('academic_awards')) {
            return [];
        }

        $awards = AcademicAward::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where('academic_year', $academicYear)
            ->orderBy('award_type')
            ->limit($limit)
            ->get();

        $names = Tenant::whereIn('id', $awards->pluck('tenant_id')->filter())->pluck('name', 'id');

        return $awards->map(fn (AcademicAward $a) => [
            'award_type' => $a->award_type,
            'title' => $a->title,
            'school_id' => $a->tenant_id,
            'school_name' => $names[$a->tenant_id] ?? $a->tenant_id,
            'score' => $a->score,
        ])->all();
    }

    /**
     * @param  list<string>  $schoolIds
     * @param  list<string>  $streamCodes
     */
    private function awardBestStreamSchool(
        string $sahodayaId,
        string $academicYear,
        array $schoolIds,
        string $awardType,
        array $streamCodes,
        string $title,
    ): int {
        $streamIds = ExamStream::query()
            ->forSahodaya($sahodayaId)
            ->whereIn('code', $streamCodes)
            ->pluck('id')
            ->all();
        if ($streamIds === [] || $schoolIds === []) {
            return 0;
        }

        $bySchool = [];
        $results = BoardResult::query()
            ->whereIn('tenant_id', $schoolIds)
            ->where('academic_year', $academicYear)
            ->where('class', 12)
            ->whereIn('status', [BoardResult::STATUS_APPROVED, BoardResult::STATUS_PUBLISHED])
            ->with(['toppers' => fn ($q) => $q->whereIn('stream_id', $streamIds)])
            ->get();

        foreach ($results as $result) {
            $pcts = $result->toppers->pluck('percentage')->filter()->all();
            if ($pcts === []) {
                continue;
            }
            $bySchool[$result->tenant_id] = [
                'avg' => array_sum($pcts) / count($pcts),
                'board_result_id' => $result->id,
            ];
        }

        if ($bySchool === []) {
            return 0;
        }

        uasort($bySchool, fn ($a, $b) => $b['avg'] <=> $a['avg']);
        $winnerId = array_key_first($bySchool);

        AcademicAward::create([
            'sahodaya_id' => $sahodayaId,
            'tenant_id' => $winnerId,
            'academic_year' => $academicYear,
            'award_type' => $awardType,
            'board_result_id' => $bySchool[$winnerId]['board_result_id'],
            'score' => round($bySchool[$winnerId]['avg'], 4),
            'title' => $title,
            'meta' => ['stream_codes' => $streamCodes],
            'computed_at' => now(),
        ]);

        return 1;
    }
}
