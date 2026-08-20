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
            ->when($mark->grade, fn ($q) => $q->where('grade', $this->normalizeGrade($event, $mark->grade)))
            ->when($mark->position, fn ($q) => $q->where('position', $mark->position))
            ->first();

        if ($rule) {
            if (($rule->points_table ?? 'custom') === 'athletics_standard' && $mark->position) {
                return (int) (self::ATHLETICS_STANDARD[(int) $mark->position] ?? 0);
            }

            return (int) $rule->points;
        }

        $grade = $this->normalizeGrade($event, $mark->grade);
        $pos = (string) ($mark->position ?? '');

        // A position with no configured table entry (e.g. 4th place, when only the top 3
        // score) earns zero championship points — never the raw judged score. Falling back
        // to $mark->score here used to silently add raw marks into the awarded-points total,
        // which the platform's own scoring rules forbid (see mcsPointsForMark()/
        // confedPointsForMark() above, which both already return 0 in the equivalent case).
        return (int) (self::DEFAULT_POINTS[$grade][$pos] ?? 0);
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
        if ($event->scoring_preset === 'mcs_kalotsav') {
            return $this->resolveMcsGradeFromScore($score);
        }

        if ($event->scoring_preset === 'confed_kalotsav') {
            return $this->resolveConfedGradeFromScore($score);
        }

        $totalMarks = $itemId ? FestEventItem::find($itemId)?->total_marks : null;
        $usePercentage = $totalMarks !== null && (float) $totalMarks > 0;
        $value = $usePercentage ? ($score / (float) $totalMarks) * 100 : $score;

        $configs = FestGradeConfig::where('event_id', $event->id)
            ->where(function ($q) use ($itemId) {
                $q->where('item_id', $itemId)->orWhereNull('item_id');
            })
            ->when($usePercentage, fn ($q) => $q->whereNotNull('min_percent'))
            ->when(! $usePercentage, fn ($q) => $q->whereNull('min_percent'))
            ->get();

        // Item-specific bands take priority over event-wide (item_id null) ones — checked as
        // two separate sorted passes, not one mixed query, so the priority ordering can't
        // interfere with the highest-band-wins sort within each tier.
        return $this->highestMatchingGradeConfig($configs->where('item_id', $itemId), $value, $usePercentage)
            ?? $this->highestMatchingGradeConfig($configs->whereNull('item_id'), $value, $usePercentage);
    }

    /**
     * Same "highest matching band wins regardless of storage order" fix as
     * highestMatchingBand() below, generalized for FestGradeConfig rows — which store an
     * explicit closed [min, max] range rather than a bare threshold. Previously this path
     * matched on first-row-in-query-order with no sort, so a score could resolve to a lower
     * grade than it actually earned whenever bands weren't already stored high-to-low.
     *
     * @param  Collection<int, FestGradeConfig>  $configs
     */
    private function highestMatchingGradeConfig(Collection $configs, float $value, bool $usePercentage): ?string
    {
        $sorted = $configs->sortByDesc(fn (FestGradeConfig $c) => (float) ($usePercentage ? $c->min_percent : $c->min_score));

        foreach ($sorted as $cfg) {
            $min = (float) ($usePercentage ? $cfg->min_percent : $cfg->min_score);
            $max = (float) ($usePercentage ? ($cfg->max_percent ?? 100) : ($cfg->max_score ?? 100));
            if ($value >= $min && $value <= $max) {
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
