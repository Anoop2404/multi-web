<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestGradeConfig;
use App\Models\FestMark;
use App\Models\FestPointRule;

class FestGradePointService
{
    private const ATHLETICS_STANDARD = [1 => 8, 2 => 7, 3 => 6, 4 => 5, 5 => 4, 6 => 3];

    /** Default CKSC-style point table when no rules configured. */
    private const DEFAULT_POINTS = [
        'A_plus' => ['1' => 10, '2' => 7, '3' => 5],
        'A'      => ['1' => 8, '2' => 5, '3' => 3],
        'B'      => ['1' => 5, '2' => 3, '3' => 2],
        'C'      => ['1' => 3, '2' => 2, '3' => 1],
    ];

    public function pointsForMark(FestEvent $event, FestMark $mark): int
    {
        $isGroup = in_array($mark->participant?->registration?->item?->participant_type, ['group', 'team'], true);

        if ($event->scoring_preset === 'mcs_kalotsav') {
            return $this->mcsPointsForMark($mark, $isGroup);
        }

        if ($event->scoring_preset === 'confed_kalotsav') {
            return $this->confedPointsForMark($mark, $isGroup);
        }

        if ($event->event_type === 'sports' && $mark->position) {
            return app(FestRankPointService::class)->pointsForRank($event, (int) $mark->position, $isGroup);
        }

        $rule = FestPointRule::where('event_id', $event->id)
            ->where('is_group', $isGroup)
            ->when($mark->grade, fn ($q) => $q->where('grade', $this->normalizeGrade($mark->grade)))
            ->when($mark->position, fn ($q) => $q->where('position', $mark->position))
            ->first();

        if ($rule) {
            if (($rule->points_table ?? 'custom') === 'athletics_standard' && $mark->position) {
                return (int) (self::ATHLETICS_STANDARD[(int) $mark->position] ?? 0);
            }

            return (int) $rule->points;
        }

        $grade = $this->normalizeGrade($mark->grade);
        $pos = (string) ($mark->position ?? '');

        return (int) (self::DEFAULT_POINTS[$grade][$pos] ?? ($mark->score ?? 0));
    }

    public function resolveGradeFromScore(FestEvent $event, ?int $itemId, float $score): ?string
    {
        if ($event->scoring_preset === 'mcs_kalotsav') {
            return $this->resolveMcsGradeFromScore($score);
        }

        if ($event->scoring_preset === 'confed_kalotsav') {
            return $this->resolveConfedGradeFromScore($score);
        }

        $configs = FestGradeConfig::where('event_id', $event->id)
            ->where(function ($q) use ($itemId) {
                $q->where('item_id', $itemId)->orWhereNull('item_id');
            })
            ->orderByRaw('item_id IS NULL ASC')
            ->get();

        foreach ($configs as $cfg) {
            $min = $cfg->min_score ?? 0;
            $max = $cfg->max_score ?? 100;
            if ($score >= $min && $score <= $max) {
                return str_replace('_plus', '+', $cfg->grade);
            }
        }

        return null;
    }

    private function mcsPointsForMark(FestMark $mark, bool $isGroup): int
    {
        $table = $isGroup
            ? config('fest_mcs_scoring.group_points', [])
            : config('fest_mcs_scoring.individual_points', []);

        $grade = $this->normalizeMcsGrade($mark->grade);
        $pos = (string) ($mark->position ?? '');

        return (int) ($table[$grade][$pos] ?? 0);
    }

    public function resolveMcsGradeFromScore(float $score): ?string
    {
        return $this->highestMatchingBand(config('fest_mcs_scoring.grades', []), $score);
    }

    /** Official Confederation State Kalolsavam Manual table — see config/fest_confed_kalotsav_scoring.php. */
    private function confedPointsForMark(FestMark $mark, bool $isGroup): int
    {
        $table = $isGroup
            ? config('fest_confed_kalotsav_scoring.group_points', [])
            : config('fest_confed_kalotsav_scoring.individual_points', []);

        $grade = $this->normalizeMcsGrade($mark->grade); // same A/B/C-only normalization the manual uses
        $pos = (string) ($mark->position ?? '');

        return (int) ($table[$grade][$pos] ?? 0);
    }

    public function resolveConfedGradeFromScore(float $score): ?string
    {
        return $this->highestMatchingBand(config('fest_confed_kalotsav_scoring.grades', []), $score);
    }

    /**
     * Returns the label of the highest-threshold band the score clears — e.g. for
     * A(min70)/B(min60)/C(min50) and a score of 75, that's A, not C.
     *
     * Both callers previously iterated without sorting or breaking, so a later (lower-
     * threshold) band always overwrote an earlier match — every score ≥ the lowest band's
     * minimum resolved to that lowest grade. A score of 85 was graded C. Fixed by sorting
     * bands high-to-low and returning on first match, so config array order can't matter.
     *
     * @param  array<string, array{min?: float|int, label?: string}>  $bands
     */
    private function highestMatchingBand(array $bands, float $score): ?string
    {
        $sorted = $bands;
        uasort($sorted, fn ($a, $b) => ($b['min'] ?? 0) <=> ($a['min'] ?? 0));

        foreach ($sorted as $key => $band) {
            if ($score >= (float) ($band['min'] ?? 0)) {
                return $band['label'] ?? $key;
            }
        }

        return null;
    }

    private function normalizeGrade(?string $grade): string
    {
        return match (strtoupper((string) $grade)) {
            'A+', 'A_PLUS' => 'A_plus',
            'A' => 'A',
            'B' => 'B',
            'C' => 'C',
            default => 'C',
        };
    }

    private function normalizeMcsGrade(?string $grade): string
    {
        return match (strtoupper((string) $grade)) {
            'A+', 'A_PLUS', 'A' => 'A',
            'B' => 'B',
            default => 'C',
        };
    }
}
