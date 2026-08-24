<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGradeConfig;
use App\Models\FestMark;
use App\Models\FestPointRule;
use Illuminate\Support\Collection;

class FestGradePointService
{
    /** Default CKSC-style point table when no rules configured. */
    private const DEFAULT_POINTS = [
        'A_plus' => ['1' => 10, '2' => 7, '3' => 5],
        'A'      => ['1' => 8, '2' => 5, '3' => 3],
        'B'      => ['1' => 5, '2' => 3, '3' => 2],
        'C'      => ['1' => 3, '2' => 2, '3' => 1],
    ];

    public function pointsForMark(FestEvent $event, FestMark $mark): int
    {
        $item = $mark->item ?? $mark->participant?->registration?->item;
        $itemId = $mark->item_id ?? $item?->id;
        $participantType = strtolower((string) ($item?->participant_type ?? 'individual'));
        $isGroup = $participantType !== 'individual';

        if ($mark->score !== null && $itemId) {
            $effectiveGrade = $this->resolveGradeFromScore($event, (int) $itemId, (float) $mark->score);
            if ($effectiveGrade !== $mark->grade) {
                $mark->grade = $effectiveGrade;
            }
        }

        // A scoring preset is only the DEFAULT table, not a hard override — if the admin
        // has actually configured Grade Points Master rules for this event (e.g. via
        // "Load Kalolsavam Manual Standard" and then edited them, or built their own from
        // scratch), those custom rules take over. Previously this short-circuited
        // unconditionally, so the preset tab looked fully editable but any custom rules
        // saved there were silently never read — the fixed table always won regardless.
        $hasCustomPointRules = FestPointRule::where('event_id', $event->id)->exists();

        if (! $hasCustomPointRules && $event->scoring_preset === 'mcs_kalotsav') {
            return $this->mcsPointsForMark($mark, $isGroup);
        }

        if (! $hasCustomPointRules && $event->scoring_preset === 'confed_kalotsav') {
            return $this->confedPointsForMark($mark, $isGroup);
        }

        if ($event->event_type === 'sports' && $mark->position) {
            return app(FestRankPointService::class)->pointsForRank($event, (int) $mark->position, $participantType);
        }

        // Nothing to go on at all — no rank and no grade to award anything from.
        if (! $mark->position && ! $mark->grade) {
            return 0;
        }

        $gradeMatch = function ($q) use ($event, $mark) {
            $mark->grade
                ? $q->where('grade', $this->normalizeGrade($event, $mark->grade))
                : $q->whereNull('grade');
        };

        // Exact match on this grade at this exact position — e.g. "Grade A, 1st place".
        // latest('id') is a defensive tiebreaker, not the real fix: storePointRule() now
        // upserts on (event_id, grade, position, is_group) so this combination can't be
        // saved twice going forward, but existing duplicate rows from before that fix (or
        // any other future write path) would otherwise make ->first() pick an effectively
        // arbitrary row — this at least keeps that pick consistent across requests instead
        // of drifting between whichever row the database happens to return first.
        if ($mark->position) {
            $rule = FestPointRule::where('event_id', $event->id)
                ->where('is_group', $isGroup)
                ->where('position', $mark->position)
                ->where($gradeMatch)
                ->latest('id')
                ->first();

            if ($rule) {
                if (($rule->points_table ?? 'custom') === 'athletics_standard') {
                    return (int) (FestRankPointService::ATHLETICS_STANDARD[(int) $mark->position] ?? 0);
                }

                return (int) $rule->points;
            }
        }

        // If no rank-specific rule matched above and the mark has no grade awarded,
        // do not award any fallback grade points (a null grade should not assume grade 'C').
        if (! $mark->grade) {
            return 0;
        }

        $anyPositionRule = FestPointRule::where('event_id', $event->id)
            ->where('is_group', $isGroup)
            ->whereNull('position')
            ->where($gradeMatch)
            ->latest('id')
            ->first();

        if ($anyPositionRule) {
            return (int) $anyPositionRule->points;
        }

        $grade = $this->normalizeGrade($event, $mark->grade);

        if ($mark->position && isset(self::DEFAULT_POINTS[$grade][(string) $mark->position])) {
            $pts = (int) self::DEFAULT_POINTS[$grade][(string) $mark->position];

            return $isGroup ? $pts * 2 : $pts;
        }

        $defaultGradeOnly = [
            'A_plus' => $isGroup ? 10 : 5,
            'A'      => $isGroup ? 6 : 3,
            'B'      => $isGroup ? 4 : 2,
            'C'      => $isGroup ? 2 : 1,
        ];

        return (int) ($defaultGradeOnly[$grade] ?? 0);
    }

    /**
     * Splits a mark's points into (rank points, grade points) per the official
     * Kalolsavam Manual's grade+place formula (config/fest_confed_kalotsav_scoring.php's
     * grade_points/place_points) — but ONLY when those two components actually sum to
     * this mark's real, authoritative total from pointsForMark(). A custom rule (an
     * "Any Position" wildcard, a hand-edited value) has no defined rank/grade split, so
     * forcing one would show numbers the admin never configured; both come back null in
     * that case and the caller should fall back to showing just the total.
     *
     * @return array{rank_points: ?int, grade_points: ?int, total: int}
     */
    public function pointsBreakdown(FestEvent $event, FestMark $mark): array
    {
        $total = $this->pointsForMark($event, $mark);

        $item = $mark->item ?? $mark->participant?->registration?->item;
        $isGroup = strtolower((string) ($item?->participant_type ?? 'individual')) !== 'individual';
        $scale = $isGroup ? 'group' : 'individual';

        $grade = $this->normalizeMcsGrade($mark->grade);
        $gradePoints = config("fest_confed_kalotsav_scoring.grade_points.{$scale}.{$grade}");
        $placePoints = $mark->position
            ? config("fest_confed_kalotsav_scoring.place_points.{$scale}.{$mark->position}")
            : null;

        if ($gradePoints !== null && $placePoints !== null && ($gradePoints + $placePoints) === $total) {
            return ['rank_points' => $placePoints, 'grade_points' => $gradePoints, 'total' => $total];
        }

        return ['rank_points' => null, 'grade_points' => null, 'total' => $total];
    }

    /**
     * $itemId's own `total_marks` (when set) switches this to percentage-based matching —
     * score/total_marks*100 against FestGradeConfig rows that have min_percent/max_percent
     * set — so one set of bands (e.g. "70%+ = A") applies consistently across items with
     * different maximums, instead of needing a raw-score band per item's own scale. Items
     * with no total_marks keep the original raw-score behaviour unchanged.
     */
    public function resolveGradeFromScore(FestEvent $event, ?int $itemId, float $score): ?string
    {
        // total_marks is the item's overall ceiling across every judge (e.g. 200), not each
        // judge's own scale — each judge's input is already capped at total_marks / judgeCount
        // (see FestMarkEntryController::store() and MarkEntry.vue's perJudgeMax), so their sum
        // tops out at total_marks directly with no further multiplication needed here.
        $itemModel = $itemId ? FestEventItem::find($itemId) : null;
        $maxPossibleMarks = (float) ($itemModel?->total_marks ?? 100.0);

        $configs = FestGradeConfig::where('event_id', $event->id)
            ->where(function ($q) use ($itemId) {
                $q->where('item_id', $itemId)->orWhereNull('item_id');
            })
            ->get();

        $hasCustomConfigs = $configs->isNotEmpty();

        // A scoring preset is only the DEFAULT table, not a hard override — previously
        // this short-circuited unconditionally, so events like a State Kalotsavam had a
        // fully editable-looking Grade Master tab whose rows were silently never
        // consulted. Only fall back to the preset's fixed table when nothing custom has
        // been configured for this event; once an admin adds their own bands, those win.
        if (! $hasCustomConfigs) {
            if ($event->scoring_preset === 'mcs_kalotsav') {
                return $this->resolveMcsGradeFromScore($score);
            }

            if ($event->scoring_preset === 'confed_kalotsav') {
                return $this->resolveConfedGradeFromScore($score);
            }
        }

        $resolved = $this->highestMatchingGradeConfig($configs->where('item_id', $itemId), $score, $maxPossibleMarks)
            ?? $this->highestMatchingGradeConfig($configs->whereNull('item_id'), $score, $maxPossibleMarks);

        if ($resolved !== null || $hasCustomConfigs) {
            return $resolved;
        }

        // Standard Kalotsavam percentage grade scale fallback
        $percent = $maxPossibleMarks > 0 ? ($score / $maxPossibleMarks) * 100 : ($score <= 100 ? $score : null);
        if ($percent !== null) {
            $validGrades = $this->validGradesForEvent($event);
            if (in_array('A+', $validGrades, true)) {
                if ($percent >= 70.0) return 'A+';
                if ($percent >= 60.0) return 'A';
                if ($percent >= 50.0) return 'B';
                if ($percent >= 40.0) return 'C';
            } else {
                if ($percent >= 70.0) return 'A';
                if ($percent >= 60.0) return 'B';
                if ($percent >= 50.0) return 'C';
            }
        }

        return null;
    }

    /**
     * Match highest configured grade band against percentage calculated from maxPossibleMarks.
     *
     * @param  Collection<int, FestGradeConfig>  $configs
     */
    private function highestMatchingGradeConfig(Collection $configs, float $score, float $maxPossibleMarks): ?string
    {
        if ($configs->isEmpty()) {
            return null;
        }

        $percent = $maxPossibleMarks > 0 ? ($score / $maxPossibleMarks) * 100 : $score;

        $sorted = $configs->sortByDesc(fn (FestGradeConfig $c) => (float) ($c->min_percent ?? $c->min_score ?? 0))->values();

        $count = $sorted->count();
        for ($i = 0; $i < $count; $i++) {
            $cfg = $sorted[$i];
            $min = (float) ($cfg->min_percent ?? $cfg->min_score ?? 0);

            // A band's own configured max always wins when the admin actually set one —
            // previously every band except the top used the NEXT-higher band's min as an
            // implicit ceiling instead, silently overriding even an explicitly lower max.
            // That meant a deliberate gap between bands (e.g. B capped at 75%, A starting
            // at 80%, intentionally excluding 76-79%) had no effect — a 77% score still
            // resolved to B. Only fall back to the implicit "extend up to the next band"
            // ceiling (or 100 for the top band) when max was left blank, matching how
            // most existing configs are actually set up.
            $ownMax = $cfg->max_percent ?? $cfg->max_score;
            if ($ownMax !== null) {
                $max = (float) $ownMax;
                $matched = $percent >= $min && $percent <= $max;
            } else {
                $max = $i === 0
                    ? 100.0
                    : (float) ($sorted[$i - 1]->min_percent ?? $sorted[$i - 1]->min_score ?? 100);
                $matched = $i === 0
                    ? ($percent >= $min && $percent <= $max)
                    : ($percent >= $min && $percent < $max);
            }

            if ($matched) {
                return str_replace('_plus', '+', $cfg->grade);
            }
        }

        return null;
    }

    private function mcsPointsForMark(FestMark $mark, bool $isGroup): int
    {
        if (! $mark->position) {
            return 0;
        }

        $table = $isGroup
            ? config('fest_mcs_scoring.group_points', [])
            : config('fest_mcs_scoring.individual_points', []);

        $grade = $this->normalizeMcsGrade($mark->grade);

        return (int) ($table[$grade][(string) $mark->position] ?? 0);
    }

    public function resolveMcsGradeFromScore(float $score): ?string
    {
        return $this->highestMatchingBand(config('fest_mcs_scoring.grades', []), $score);
    }

    /** Official Confederation State Kalolsavam Manual table — see config/fest_confed_kalotsav_scoring.php. */
    private function confedPointsForMark(FestMark $mark, bool $isGroup): int
    {
        if (! $mark->position) {
            return 0;
        }

        $table = $isGroup
            ? config('fest_confed_kalotsav_scoring.group_points', [])
            : config('fest_confed_kalotsav_scoring.individual_points', []);

        $grade = $this->normalizeMcsGrade($mark->grade); // same A/B/C-only normalization the manual uses

        return (int) ($table[$grade][(string) $mark->position] ?? 0);
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

    /**
     * The set of valid grade labels for this event, best-first — driven by whatever
     * FestGradeConfig rows the event has configured (event-wide and/or per-item bands,
     * set up via the Grades settings tab), falling back to the original fixed A+/A/B/C
     * set for every event that hasn't customized its grade vocabulary. Ordered by each
     * grade's highest configured threshold (score or percent) descending, so "best grade
     * first" holds even for a fully custom label set. This is also what
     * gradeOptionsForEvent() and gradeValidationRule() build from — the single source of
     * truth for "what grades can this event's marks/point-rules use."
     *
     * @return list<string>
     */
    public function validGradesForEvent(FestEvent $event): array
    {
        $configured = FestGradeConfig::where('event_id', $event->id)
            ->get(['grade', 'min_score', 'min_percent'])
            ->groupBy(fn (FestGradeConfig $c) => str_replace('_plus', '+', $c->grade))
            ->map(fn (Collection $rows) => $rows->max(fn (FestGradeConfig $r) => (float) ($r->min_percent ?? $r->min_score ?? 0)))
            ->sortDesc();

        if ($configured->isEmpty()) {
            return ['A+', 'A', 'B', 'C'];
        }

        return $configured->keys()->all();
    }

    /** @return array<string, string> grade value => display label (identical for custom grades) */
    public function gradeOptionsForEvent(FestEvent $event): array
    {
        return collect($this->validGradesForEvent($event))->mapWithKeys(fn (string $g) => [$g => $g])->all();
    }

    public function gradeValidationRule(FestEvent $event): \Illuminate\Validation\Rules\In
    {
        return \Illuminate\Validation\Rule::in($this->validGradesForEvent($event));
    }

    /**
     * Event-aware: the four legacy grades keep their exact original A_plus-suffix DB
     * encoding (unchanged behavior for every event that never customizes its grades — the
     * overwhelming majority). Anything else is a custom label an admin defined via the
     * Grades settings tab — FestGradeConfig/FestPointRule store it verbatim, since it was
     * never a fixed DB enum value needing that encoding. An empty/unrecognized grade falls
     * back to this event's own worst configured grade rather than a hardcoded 'C', since
     * 'C' may not even be one of this event's grades once fully customized.
     */
    public function normalizeGrade(FestEvent $event, ?string $grade): string
    {
        $upper = strtoupper((string) $grade);
        $legacy = match ($upper) {
            'A+', 'A_PLUS' => 'A_plus',
            'A' => 'A',
            'B' => 'B',
            'C' => 'C',
            default => null,
        };

        if ($legacy !== null) {
            return $legacy;
        }

        if ($grade !== null && $grade !== '') {
            return $grade;
        }

        $valid = $this->validGradesForEvent($event);

        return end($valid) ?: 'C';
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
