<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\BoardResultRanking;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\TopperCountConfig;
use Illuminate\Support\Collection;

/**
 * Turns the auto-computed student rankings (RankingEngine::recomputeStudentToppers) into the
 * actual "Sahodaya toppers" list an admin sees — i.e. applies the configured top_n + tie_mode
 * cutoff (Sahodaya settings, per TopperCountConfig) on top of the objective ranking.
 */
class SahodayaTopperSelectionService
{
    public function __construct(
        private readonly RankingEngine $ranking,
        private readonly TopperCountService $counts,
        private readonly RankStyleService $rankStyles,
    ) {}

    /**
     * Auto-computed Sahodaya-wide Class X overall topper list.
     *
     * @return list<array<string, mixed>>
     */
    public function overallForClassX(string $sahodayaId, string $academicYear): array
    {
        $rows = $this->rankedRows($sahodayaId, $academicYear, RankingEngine::SCOPE_STUDENT_OVERALL, 10);
        $hydrated = $this->cutAndHydrate($rows, $sahodayaId, 10, TopperCountConfig::SCOPE_OVERALL, null);

        if (empty($hydrated)) {
            $schoolNames = Tenant::where('parent_id', $sahodayaId)->where('type', 'school')->pluck('name', 'id');
            $toppers = $this->eligibleToppersQuery($sahodayaId, $academicYear, 10)
                ->whereNotNull('percentage')
                ->orderByDesc('percentage')
                ->get();

            return $toppers->map(function (Topper $t, int $idx) use ($schoolNames) {
                return [
                    'rank'           => $t->rank ?? ($idx + 1),
                    'student_name'   => $t->name,
                    'name'           => $t->name,
                    'school_id'      => $t->tenant_id,
                    'school_name'    => $schoolNames[$t->tenant_id] ?? $t->tenant_id,
                    'admission_no'   => $t->admission_no,
                    'roll_no'        => $t->roll_no,
                    'percentage'     => (float) $t->percentage,
                    'marks_obtained' => $t->marks_obtained,
                    'total_marks'    => $t->total_marks,
                    'class'          => 10,
                ];
            })->all();
        }

        return $hydrated;
    }

    /**
     * Auto-computed Sahodaya-wide Class XII topper lists, one per stream.
     *
     * @return array<string, list<array<string, mixed>>> keyed by stream label
     */
    public function byStreamForClassXII(string $sahodayaId, string $academicYear): array
    {
        $rows = $this->rankedRows($sahodayaId, $academicYear, RankingEngine::SCOPE_STUDENT_STREAM, 12);
        $grouped = collect($rows)->groupBy(function ($row) {
            $st = $row['meta']['stream'] ?? null;
            if (blank($st) || in_array(strtolower(trim($st)), ['unknown', 'unknown stream', 'general', 'general / all streams'], true)) {
                return null;
            }
            return ucfirst($st);
        })->filter(fn ($v, $k) => !blank($k));

        $out = [];
        foreach ($grouped as $streamLabel => $streamRows) {
            $streamKey = $streamRows->first()['meta']['stream_key'] ?? null;
            $streamId = is_numeric($streamKey) ? (int) $streamKey : null;

            $out[$streamLabel] = $this->cutAndHydrate(
                $streamRows->sortBy('rank')->values()->all(),
                $sahodayaId,
                12,
                TopperCountConfig::SCOPE_STREAM,
                $streamId,
            );
        }

        if (empty($out)) {
            $schoolNames = Tenant::where('parent_id', $sahodayaId)->where('type', 'school')->pluck('name', 'id');
            $toppers = $this->eligibleToppersQuery($sahodayaId, $academicYear, 12)
                ->whereNotNull('percentage')
                ->get();

            $fallbackGrouped = $toppers->groupBy(function (Topper $t) {
                $st = $t->examStream?->label ?? $t->stream;
                if (blank($st) || in_array(strtolower(trim($st)), ['unknown', 'unknown stream', 'general', 'general / all streams'], true)) {
                    return null;
                }
                return ucfirst($st);
            })->filter(fn ($v, $k) => !blank($k));

            foreach ($fallbackGrouped as $streamLabel => $streamToppers) {
                $sorted = $streamToppers->sortByDesc(fn (Topper $t) => (float) $t->percentage)->values();
                $out[$streamLabel] = $sorted->map(function (Topper $t, int $idx) use ($schoolNames) {
                    return [
                        'rank'           => $t->rank ?? ($idx + 1),
                        'student_name'   => $t->name,
                        'name'           => $t->name,
                        'school_id'      => $t->tenant_id,
                        'school_name'    => $schoolNames[$t->tenant_id] ?? $t->tenant_id,
                        'admission_no'   => $t->admission_no,
                        'roll_no'        => $t->roll_no,
                        'percentage'     => (float) $t->percentage,
                        'marks_obtained' => $t->marks_obtained,
                        'total_marks'    => $t->total_marks,
                        'stream'         => $t->examStream?->label ?? $t->stream,
                        'class'          => 12,
                    ];
                })->all();
            }
        }

        return $out;
    }

    /** @return array{scopes: list<string>, rows: int} */
    public function recompute(string $sahodayaId, string $academicYear): array
    {
        return $this->ranking->recomputeStudentToppers($sahodayaId, $academicYear);
    }

    /**
     * Every Class X student (from any submitted result) scoring at/above the threshold overall —
     * not rank-limited, no Top-N/tie-mode cutoff.
     *
     * @return list<array<string, mixed>>
     */
    public function achieversForClassX(string $sahodayaId, string $academicYear, float $threshold = 90.0): array
    {
        $toppers = $this->eligibleToppersQuery($sahodayaId, $academicYear, 10)
            ->where('percentage', '>=', $threshold)
            ->orderByDesc('percentage')
            ->get();

        $rankStyle = $this->counts->resolveRankStyle($sahodayaId, 10, TopperCountConfig::SCOPE_OVERALL, null, null);

        return $this->hydrateAchievers($toppers, $rankStyle, $this->counts->isNoRankMode($sahodayaId));
    }

    /**
     * Every Class XII student scoring at/above the threshold overall, grouped by stream
     * (each stream's list is further groupable by school on the frontend, same row shape
     * as byStreamForClassXII so both lists can share display markup).
     *
     * @return array<string, list<array<string, mixed>>> keyed by stream label
     */
    public function achieversByStreamForClassXII(string $sahodayaId, string $academicYear, float $threshold = 90.0): array
    {
        $toppers = $this->eligibleToppersQuery($sahodayaId, $academicYear, 12)
            ->where('percentage', '>=', $threshold)
            ->orderByDesc('percentage')
            ->get();

        $grouped = $toppers->groupBy(function (Topper $t) {
            $st = $t->examStream?->label ?? $t->stream;
            if (blank($st) || in_array(strtolower(trim($st)), ['unknown', 'unknown stream', 'general', 'general / all streams'], true)) {
                return null;
            }
            return ucfirst($st);
        })->filter(fn ($v, $k) => !blank($k));

        $noRank = $this->counts->isNoRankMode($sahodayaId);
        $out = [];
        foreach ($grouped as $streamLabel => $group) {
            $streamId = $group->first()?->stream_id;
            $rankStyle = $this->counts->resolveRankStyle($sahodayaId, 12, TopperCountConfig::SCOPE_STREAM, is_numeric($streamId) ? (int) $streamId : null, null);
            $out[$streamLabel] = $this->hydrateAchievers($group, $rankStyle, $noRank);
        }

        return $out;
    }

    private function eligibleToppersQuery(string $sahodayaId, string $academicYear, int $class)
    {
        $schoolIds = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->pluck('id');

        return Topper::query()
            ->with('examStream')
            ->overallEntries()
            ->whereHas('boardResult', function ($q) use ($schoolIds, $academicYear, $class) {
                $q->whereIn('tenant_id', $schoolIds)
                    ->where('academic_year', $academicYear)
                    ->where('class', $class)
                    ->where('status', '!=', BoardResult::STATUS_REJECTED);
            });
    }

    /**
     * @param  Collection<int, Topper>  $toppers
     * @return list<array<string, mixed>>
     */
    private function hydrateAchievers(Collection $toppers, string $rankStyle = RankStyleService::STYLE_COMPETITION, bool $noRank = false): array
    {
        $sorted = $toppers->sortByDesc('percentage')->values();
        $schoolNames = Tenant::whereIn('id', $sorted->pluck('tenant_id')->unique())->pluck('name', 'id');

        if ($noRank) {
            return $sorted->map(fn (Topper $t) => [
                'rank' => null,
                'percentage' => (float) $t->percentage,
                'student_name' => $t->name,
                'school_id' => $t->tenant_id,
                'school_name' => $schoolNames[$t->tenant_id] ?? $t->tenant_id,
                'admission_no' => $t->admission_no,
                'roll_no' => $t->roll_no,
                'marks_obtained' => $t->marks_obtained,
                'total_marks' => $t->total_marks,
                'photo' => $t->photo,
                'topper_id' => $t->id,
            ])->values()->all();
        }

        $rankStyle = $this->rankStyles->normalize($rankStyle);
        $rank = 0;
        $denseRank = 0;
        $prevPercentage = null;

        return $sorted->map(function (Topper $t, int $i) use ($schoolNames, $rankStyle, &$rank, &$denseRank, &$prevPercentage) {
            $percentage = (float) $t->percentage;
            if ($rankStyle === RankStyleService::STYLE_SEQUENTIAL) {
                $rank = $i + 1;
            } elseif ($rankStyle === RankStyleService::STYLE_DENSE) {
                if ($prevPercentage === null || abs($percentage - $prevPercentage) > 0.0001) {
                    $denseRank++;
                }
                $rank = max(1, $denseRank);
            } else {
                if ($prevPercentage === null || abs($percentage - $prevPercentage) > 0.0001) {
                    $rank = $i + 1;
                }
            }
            $prevPercentage = $percentage;

            return [
                'rank' => $rank,
                'percentage' => $percentage,
                'student_name' => $t->name,
                'school_id' => $t->tenant_id,
                'school_name' => $schoolNames[$t->tenant_id] ?? $t->tenant_id,
                'admission_no' => $t->admission_no,
                'roll_no' => $t->roll_no,
                'marks_obtained' => $t->marks_obtained,
                'total_marks' => $t->total_marks,
                'photo' => $t->photo,
                'topper_id' => $t->id,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{rank: int, entity_id: string, score: float|null, meta: array<string, mixed>}>
     */
    private function rankedRows(string $sahodayaId, string $academicYear, string $scope, int $class): array
    {
        $query = fn () => BoardResultRanking::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where('academic_year', $academicYear)
            ->where('scope', $scope)
            ->where('entity_type', 'student')
            ->where('class', $class)
            ->orderBy('rank')
            ->orderByDesc('score')
            ->orderBy('entity_id');

        $rows = $query()->get();

        if ($rows->isEmpty()) {
            $this->ranking->recomputeStudentToppers($sahodayaId, $academicYear);
            $rows = $query()->get();
        }

        return $rows->map(fn (BoardResultRanking $r) => [
            'rank' => $r->rank,
            'entity_id' => $r->entity_id,
            'score' => $r->score,
            'meta' => $r->meta ?? [],
        ])->all();
    }

    /**
     * Apply the configured top_n + tie_mode cutoff, then hydrate Topper + school name.
     *
     * @param  list<array{rank: int, entity_id: string, score: float|null, meta: array<string, mixed>}>  $rows  sorted by rank ascending
     * @return list<array<string, mixed>>
     */
    private function cutAndHydrate(array $rows, string $sahodayaId, int $class, string $configScope, ?int $streamId): array
    {
        if ($rows === []) {
            return [];
        }

        // Ranking rows are cached. Defensively discard any stale row that now points
        // to a subject-only topper (the migration also clears these cached scopes).
        $eligibleIds = Topper::query()
            ->overallEntries()
            ->whereIn('id', array_column($rows, 'entity_id'))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->flip();
        $rows = array_values(array_filter(
            $rows,
            fn (array $row) => $eligibleIds->has((string) $row['entity_id']),
        ));
        if ($rows === []) {
            return [];
        }

        $topN = $this->counts->resolveCap($sahodayaId, $class, $configScope, $streamId);

        if ($this->counts->isNoRankMode($sahodayaId)) {
            // No-rank mode: drop tie/rank-style handling entirely, just take the
            // top_n rows ordered by percentage descending, with no rank number.
            $rows = collect($rows)->sortByDesc(fn (array $row) => $row['score'] ?? -INF)->values()->all();
            $selected = array_map(function (array $row) {
                $row['rank'] = null;

                return $row;
            }, array_slice($rows, 0, $topN));
        } else {
            $tieMode = $this->counts->resolveTieMode($sahodayaId, $class, $configScope, $streamId);
            $rankStyle = $this->counts->resolveRankStyle($sahodayaId, $class, $configScope, $streamId);
            $rows = $this->rankStyles->assign($rows, $rankStyle, fn (array $row) => $row['score'] ?? null);

            $selected = [];
            foreach ($rows as $row) {
                if ($tieMode === TopperCountConfig::TIE_HARD_CAP) {
                    if (count($selected) >= $topN) {
                        break;
                    }
                } elseif ($row['rank'] > $topN) {
                    // Rank-cutoff mode: include every row whose rank is within Top-N.
                    break;
                }

                $selected[] = $row;
            }
        }

        $topperIds = array_column($selected, 'entity_id');
        $toppers = Topper::query()->whereIn('id', $topperIds)->get()->keyBy(fn (Topper $t) => (string) $t->id);
        $schoolNames = Tenant::whereIn('id', $toppers->pluck('tenant_id')->unique())->pluck('name', 'id');

        return collect($selected)->map(function (array $row) use ($toppers, $schoolNames) {
            $topper = $toppers->get((string) $row['entity_id']);

            return [
                'rank' => $row['rank'],
                'percentage' => $row['score'],
                'student_name' => $topper?->name ?? $row['meta']['student_name'] ?? null,
                'school_id' => $topper?->tenant_id ?? $row['meta']['school_id'] ?? null,
                'school_name' => $topper ? ($schoolNames[$topper->tenant_id] ?? $topper->tenant_id) : null,
                'admission_no' => $topper?->admission_no,
                'roll_no' => $topper?->roll_no,
                'marks_obtained' => $topper?->marks_obtained,
                'total_marks' => $topper?->total_marks,
                'photo' => $topper?->photo,
                'topper_id' => $topper?->id,
            ];
        })->values()->all();
    }
}
