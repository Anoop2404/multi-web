<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\BoardResultRanking;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\TopperSubjectMark;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * MVP ranking engine for CBSE board results.
 *
 * Implemented: overall_pass_percent, overall, stream (avg topper %), subject (top mark leaders).
 */
class RankingEngine
{
    public const SCOPE_OVERALL_PASS_PERCENT = 'overall_pass_percent';

    public const SCOPE_OVERALL = 'overall';

    public const SCOPE_STREAM = 'stream';

    public const SCOPE_SUBJECT = 'subject';

    /**
     * Recompute rankings for a Sahodaya + academic year.
     *
     * @param  list<string>|null  $scopes
     * @return array{scopes: list<string>, rows: int}
     */
    public function recompute(string $sahodayaId, string $academicYear, ?array $scopes = null): array
    {
        $scopes ??= [
            self::SCOPE_OVERALL_PASS_PERCENT,
            self::SCOPE_OVERALL,
            self::SCOPE_STREAM,
            self::SCOPE_SUBJECT,
        ];

        $schoolIds = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->pluck('id')
            ->all();

        $results = BoardResult::query()
            ->whereIn('tenant_id', $schoolIds)
            ->where('academic_year', $academicYear)
            ->whereIn('status', [BoardResult::STATUS_APPROVED, BoardResult::STATUS_PUBLISHED])
            ->with(['toppers.subjectMarks', 'toppers.examStream'])
            ->get();

        $written = 0;

        DB::transaction(function () use ($sahodayaId, $academicYear, $scopes, $results, &$written) {
            BoardResultRanking::query()
                ->where('sahodaya_id', $sahodayaId)
                ->where('academic_year', $academicYear)
                ->whereIn('scope', $scopes)
                ->delete();

            foreach ($scopes as $scope) {
                $rows = match ($scope) {
                    self::SCOPE_OVERALL_PASS_PERCENT => $this->rankByPassPercent($results),
                    self::SCOPE_OVERALL => $this->rankOverall($results),
                    self::SCOPE_STREAM => $this->rankByStream($results),
                    self::SCOPE_SUBJECT => $this->rankBySubject($results),
                    default => collect(),
                };

                foreach ($rows as $row) {
                    BoardResultRanking::create([
                        'sahodaya_id' => $sahodayaId,
                        'academic_year' => $academicYear,
                        'examination_type' => $row['examination_type'] ?? null,
                        'class' => $row['class'] ?? null,
                        'scope' => $scope,
                        'entity_type' => $row['entity_type'] ?? 'school',
                        'entity_id' => $row['entity_id'],
                        'board_result_id' => $row['board_result_id'] ?? null,
                        'rank' => $row['rank'],
                        'score' => $row['score'] ?? null,
                        'tie_rule_applied' => $row['tie_rule_applied'] ?? null,
                        'meta' => $row['meta'] ?? null,
                    ]);
                    $written++;
                }
            }
        });

        return ['scopes' => $scopes, 'rows' => $written];
    }

    /**
     * Dense ranking by pass_percent (desc). Ties share rank; next rank skips (competition ranking).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rankByPassPercent(Collection $results): Collection
    {
        $sorted = $results
            ->sort(function (BoardResult $a, BoardResult $b) {
                $cmp = $b->pass_percent <=> $a->pass_percent;
                if ($cmp !== 0) {
                    return $cmp;
                }
                // Tie-break: higher distinctions, then higher total_appeared, then tenant_id.
                $cmp = ($b->distinctions ?? 0) <=> ($a->distinctions ?? 0);
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = ($b->total_appeared ?? 0) <=> ($a->total_appeared ?? 0);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp((string) $a->tenant_id, (string) $b->tenant_id);
            })
            ->values();

        return $this->assignCompetitionRanks($sorted, fn (BoardResult $r) => (float) $r->pass_percent, 'pass_percent_then_distinctions');
    }

    /**
     * Overall school ranking: highest_mark desc, then pass_percent, then distinctions.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rankOverall(Collection $results): Collection
    {
        $sorted = $results
            ->sort(function (BoardResult $a, BoardResult $b) {
                $cmp = ((float) ($b->highest_mark ?? 0)) <=> ((float) ($a->highest_mark ?? 0));
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = $b->pass_percent <=> $a->pass_percent;
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = ($b->distinctions ?? 0) <=> ($a->distinctions ?? 0);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp((string) $a->tenant_id, (string) $b->tenant_id);
            })
            ->values();

        return $this->assignCompetitionRanks(
            $sorted,
            fn (BoardResult $r) => (float) ($r->highest_mark ?? $r->pass_percent ?? 0),
            'highest_mark_then_pass_percent'
        );
    }

    /**
     * Stream ranking: average topper percentage per school+stream (Class XII).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rankByStream(Collection $results): Collection
    {
        $buckets = collect();

        foreach ($results->where('class', 12) as $result) {
            /** @var BoardResult $result */
            $grouped = $result->toppers->groupBy(fn (Topper $t) => $t->stream_id ?: ($t->stream ?: 'unknown'));
            foreach ($grouped as $streamKey => $toppers) {
                $pcts = $toppers->pluck('percentage')->filter(fn ($p) => $p !== null)->all();
                if ($pcts === []) {
                    continue;
                }
                $avg = array_sum($pcts) / count($pcts);
                $streamLabel = $toppers->first()?->examStream?->label
                    ?? $toppers->first()?->stream
                    ?? (string) $streamKey;
                $buckets->push((object) [
                    'result' => $result,
                    'score' => $avg,
                    'stream_key' => $streamKey,
                    'stream_label' => $streamLabel,
                    'topper_count' => count($pcts),
                ]);
            }
        }

        $sorted = $buckets->sort(function ($a, $b) {
            $cmp = $b->score <=> $a->score;
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a->result->tenant_id, (string) $b->result->tenant_id);
        })->values();

        $rows = collect();
        $lastScore = null;
        $lastRank = 0;
        foreach ($sorted as $index => $bucket) {
            $score = (float) $bucket->score;
            $position = $index + 1;
            if ($lastScore === null || abs($score - $lastScore) > 0.0001) {
                $rank = $position;
                $applied = null;
            } else {
                $rank = $lastRank;
                $applied = 'avg_topper_percent';
            }
            $rows->push([
                'entity_type' => 'school',
                'entity_id' => (string) $bucket->result->tenant_id,
                'board_result_id' => $bucket->result->id,
                'examination_type' => $bucket->result->examination_type,
                'class' => $bucket->result->class,
                'rank' => $rank,
                'score' => $score,
                'tie_rule_applied' => $applied,
                'meta' => [
                    'stream' => $bucket->stream_label,
                    'stream_key' => $bucket->stream_key,
                    'topper_count' => $bucket->topper_count,
                ],
            ]);
            $lastScore = $score;
            $lastRank = $rank;
        }

        return $rows;
    }

    /**
     * Subject ranking: best mark per subject across schools (student entity).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rankBySubject(Collection $results): Collection
    {
        $topperIds = $results->flatMap->toppers->pluck('id')->filter()->all();
        if ($topperIds === []) {
            return collect();
        }

        $marks = TopperSubjectMark::query()
            ->whereIn('topper_id', $topperIds)
            ->with('topper')
            ->orderByDesc('marks')
            ->get()
            ->groupBy('subject_label');

        $rows = collect();
        foreach ($marks as $subject => $group) {
            $lastScore = null;
            $lastRank = 0;
            foreach ($group->values() as $index => $mark) {
                $score = (float) $mark->marks;
                $position = $index + 1;
                if ($lastScore === null || abs($score - $lastScore) > 0.0001) {
                    $rank = $position;
                    $applied = null;
                } else {
                    $rank = $lastRank;
                    $applied = 'subject_marks';
                }
                $topper = $mark->topper;
                $rows->push([
                    'entity_type' => 'student',
                    'entity_id' => (string) ($topper?->id ?? $mark->id),
                    'board_result_id' => $topper?->board_result_id,
                    'examination_type' => null,
                    'class' => 12,
                    'rank' => $rank,
                    'score' => $score,
                    'tie_rule_applied' => $applied,
                    'meta' => [
                        'subject' => $subject,
                        'subject_id' => $mark->subject_id,
                        'student_name' => $topper?->name,
                        'school_id' => $topper?->tenant_id,
                    ],
                ]);
                $lastScore = $score;
                $lastRank = $rank;
            }
        }

        return $rows;
    }

    /**
     * Competition ranking: equal scores share rank; next rank = position (1,2,2,4).
     *
     * @param  Collection<int, BoardResult>  $sorted
     * @param  callable(BoardResult): float  $scoreFn
     * @return Collection<int, array<string, mixed>>
     */
    private function assignCompetitionRanks(Collection $sorted, callable $scoreFn, string $tieRule): Collection
    {
        $rows = collect();
        $lastScore = null;
        $lastRank = 0;

        foreach ($sorted as $index => $result) {
            $score = $scoreFn($result);
            $position = $index + 1;

            if ($lastScore === null || abs($score - $lastScore) > 0.0001) {
                $rank = $position;
                $applied = null;
            } else {
                $rank = $lastRank;
                $applied = $tieRule;
            }

            $rows->push([
                'entity_type' => 'school',
                'entity_id' => (string) $result->tenant_id,
                'board_result_id' => $result->id,
                'examination_type' => $result->examination_type,
                'class' => $result->class,
                'rank' => $rank,
                'score' => $score,
                'tie_rule_applied' => $applied,
                'meta' => [
                    'pass_percent' => $result->pass_percent,
                    'highest_mark' => $result->highest_mark,
                    'distinctions' => $result->distinctions,
                    'total_appeared' => $result->total_appeared,
                ],
            ]);

            $lastScore = $score;
            $lastRank = $rank;
        }

        return $rows;
    }

    /**
     * Top schools by overall_pass_percent for dashboard widgets.
     *
     * @return list<array{school_id: string, school_name: string, rank: int, score: float|null, pass_percent: float|null, class: int|null, examination_type: string|null}>
     */
    public function topSchools(string $sahodayaId, string $academicYear, int $limit = 5): array
    {
        $rankings = BoardResultRanking::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where('academic_year', $academicYear)
            ->where('scope', self::SCOPE_OVERALL_PASS_PERCENT)
            ->orderBy('rank')
            ->limit($limit)
            ->get();

        if ($rankings->isEmpty()) {
            $this->recompute($sahodayaId, $academicYear, [self::SCOPE_OVERALL_PASS_PERCENT, self::SCOPE_OVERALL]);
            $rankings = BoardResultRanking::query()
                ->where('sahodaya_id', $sahodayaId)
                ->where('academic_year', $academicYear)
                ->where('scope', self::SCOPE_OVERALL_PASS_PERCENT)
                ->orderBy('rank')
                ->limit($limit)
                ->get();
        }

        $names = Tenant::whereIn('id', $rankings->pluck('entity_id'))->pluck('name', 'id');

        return $rankings->map(fn (BoardResultRanking $r) => [
            'school_id' => $r->entity_id,
            'school_name' => $names[$r->entity_id] ?? $r->entity_id,
            'rank' => $r->rank,
            'score' => $r->score,
            'pass_percent' => $r->meta['pass_percent'] ?? $r->score,
            'class' => $r->class,
            'examination_type' => $r->examination_type,
        ])->all();
    }

    /**
     * Pass-% trend by academic year (avg of published/approved results).
     *
     * @return list<array{academic_year: string, avg_pass_percent: float, schools: int}>
     */
    public function passPercentTrend(string $sahodayaId, int $years = 5): array
    {
        $schoolIds = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->pluck('id')
            ->all();

        if ($schoolIds === []) {
            return [];
        }

        return BoardResult::query()
            ->whereIn('tenant_id', $schoolIds)
            ->whereIn('status', [BoardResult::STATUS_APPROVED, BoardResult::STATUS_PUBLISHED])
            ->selectRaw('academic_year, AVG(pass_percent) as avg_pass_percent, COUNT(*) as schools')
            ->groupBy('academic_year')
            ->orderByDesc('academic_year')
            ->limit($years)
            ->get()
            ->map(fn ($row) => [
                'academic_year' => $row->academic_year,
                'avg_pass_percent' => round((float) $row->avg_pass_percent, 2),
                'schools' => (int) $row->schools,
            ])
            ->values()
            ->all();
    }
}
