<?php

namespace App\Services\State\Fest;

use App\Models\FestStateProgramItem;
use App\Models\State\StateClassCategory;
use App\Models\State\StateFestEvent;
use App\Models\State\StateGradeBand;
use App\Models\State\StatePointRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Grade bands, point rules and class categories for a State event.
 *
 * Seeded from the Kalotsavam manual tables the Sahodaya side already loads — the same numbers, since
 * config/fest_confed_kalotsav_scoring.php's own docblock records that the manual applies at every
 * level — and then owned by the event. Config is the seed; the event's rows are the truth, because a
 * manual revised between seasons must not silently rescore last season's results.
 */
class StateGradingService
{
    /** The manual's own table, as the Sahodaya side loads it. */
    public const DEFAULT_GRADING = 'fest_default_kalotsav_grading';

    /** The Confederation State Kalotsavam table, whose grade bands carry only a minimum. */
    public const CONFED_SCORING = 'fest_confed_kalotsav_scoring';

    // ── Grade bands ────────────────────────────────────────────────────────────────────────

    /** @return Collection<int, StateGradeBand> */
    public function bands(StateFestEvent $event, ?string $itemId = null): Collection
    {
        $bands = StateGradeBand::where('state_event_id', $event->id)
            ->where('item_id', $itemId)
            ->orderByDesc('min_score')->get();

        // An item with no bands of its own falls back to the event's, rather than to no grading at
        // all — which would score every mark in that item at zero.
        if ($bands->isEmpty() && $itemId !== null) {
            return $this->bands($event);
        }

        return $bands;
    }

    public function gradeFor(StateFestEvent $event, float $score, ?string $itemId = null): ?string
    {
        return $this->bands($event, $itemId)->first(fn (StateGradeBand $b) => $b->contains($score))?->grade;
    }

    /**
     * Replace an event's (or one item's) bands wholesale.
     *
     * Validated as a set, not row by row: overlapping bands make a score's grade depend on row order,
     * and a gap makes a score in it ungraded. Both are silent at entry and obvious only when a result
     * is wrong.
     *
     * @param  list<array{grade: string, min_score: float, max_score: float}>  $bands
     * @return Collection<int, StateGradeBand>
     */
    public function saveBands(StateFestEvent $event, array $bands, ?string $itemId = null): Collection
    {
        $this->assertBandsCoherent($bands);

        return DB::connection('state')->transaction(function () use ($event, $bands, $itemId) {
            StateGradeBand::where('state_event_id', $event->id)->where('item_id', $itemId)->delete();

            $sorted = collect($bands)->sortByDesc('min_score')->values();

            foreach ($sorted as $i => $band) {
                StateGradeBand::create([
                    'state_event_id' => $event->id,
                    'state_id' => $event->state_id,
                    'item_id' => $itemId,
                    'grade' => trim($band['grade']),
                    'min_score' => $band['min_score'],
                    'max_score' => $band['max_score'],
                    'sort_order' => $i,
                ]);
            }

            return $this->bands($event, $itemId);
        });
    }

    /** @param  list<array{grade: string, min_score: float, max_score: float}>  $bands */
    private function assertBandsCoherent(array $bands): void
    {
        if ($bands === []) {
            throw ValidationException::withMessages(['bands' => 'A grade scale needs at least one band.']);
        }

        $sorted = collect($bands)->sortBy('min_score')->values();
        $seen = [];

        foreach ($sorted as $i => $band) {
            $grade = strtoupper(trim($band['grade']));

            if ($grade === '') {
                throw ValidationException::withMessages(['bands' => 'Every band needs a grade name.']);
            }

            if (isset($seen[$grade])) {
                throw ValidationException::withMessages(['bands' => "Grade \"{$band['grade']}\" appears twice."]);
            }
            $seen[$grade] = true;

            if ((float) $band['min_score'] > (float) $band['max_score']) {
                throw ValidationException::withMessages([
                    'bands' => "Grade {$band['grade']} has its minimum above its maximum.",
                ]);
            }

            $next = $sorted[$i + 1] ?? null;

            if ($next && (float) $next['min_score'] <= (float) $band['max_score']) {
                throw ValidationException::withMessages([
                    'bands' => "Grades {$band['grade']} and {$next['grade']} overlap. A score in the overlap would take whichever band happened to be checked first.",
                ]);
            }

            if ($next && (float) $next['min_score'] > (float) $band['max_score'] + 1) {
                $gapFrom = (float) $band['max_score'] + 1;
                $gapTo = (float) $next['min_score'] - 1;

                throw ValidationException::withMessages([
                    'bands' => "Nothing covers {$gapFrom}–{$gapTo}. A mark in that range would get no grade and score no points.",
                ]);
            }
        }
    }

    // ── Point rules ────────────────────────────────────────────────────────────────────────

    /** @return Collection<int, StatePointRule> */
    public function rules(StateFestEvent $event): Collection
    {
        return StatePointRule::where('state_event_id', $event->id)
            ->orderBy('is_group')->orderByRaw('position is null')->orderBy('position')->orderBy('grade')
            ->get();
    }

    public function pointsFor(StateFestEvent $event, ?string $grade, ?int $position, bool $isGroup): int
    {
        // The most specific matching rule wins. Ordering by specificity rather than trusting row
        // order means "A, 1st" beats "any, 1st" however the rows were entered.
        return (int) ($this->rules($event)
            ->filter(fn (StatePointRule $r) => $r->matches($grade, $position, $isGroup))
            ->sortByDesc(fn (StatePointRule $r) => $r->specificity())
            ->first()?->points ?? 0);
    }

    /**
     * @param  list<array{grade: ?string, position: ?int, is_group: bool, points: int}>  $rules
     * @return Collection<int, StatePointRule>
     */
    public function saveRules(StateFestEvent $event, array $rules): Collection
    {
        return DB::connection('state')->transaction(function () use ($event, $rules) {
            StatePointRule::where('state_event_id', $event->id)->delete();

            $seen = [];

            foreach ($rules as $rule) {
                $grade = filled($rule['grade'] ?? null) ? trim($rule['grade']) : null;
                $position = ($rule['position'] ?? null) !== null && $rule['position'] !== ''
                    ? (int) $rule['position'] : null;
                $isGroup = (bool) ($rule['is_group'] ?? false);

                // The table has a unique index on this triple; caught here so the operator gets a
                // sentence rather than a constraint violation.
                $key = strtoupper((string) $grade).'|'.$position.'|'.($isGroup ? 'g' : 'i');
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages([
                        'rules' => 'Two rules cover the same grade, position and item type. Remove one.',
                    ]);
                }
                $seen[$key] = true;

                StatePointRule::create([
                    'state_event_id' => $event->id,
                    'state_id' => $event->state_id,
                    'grade' => $grade,
                    'position' => $position,
                    'is_group' => $isGroup,
                    'points' => (int) $rule['points'],
                ]);
            }

            return $this->rules($event);
        });
    }

    // ── Seeding from the manual ────────────────────────────────────────────────────────────

    /**
     * Load the manual's standard tables onto this event.
     *
     * Two tables are offered because the platform already carries both: the default Kalotsav grading
     * the Sahodaya side loads one-click, and the Confederation State Kalotsavam table. They agree on
     * grade boundaries and on place points; they differ in that the default adds a "No Grade" band so
     * a mark below 50 still resolves to something rather than to nothing.
     *
     * @return array{bands: int, rules: int}
     */
    public function applyManualStandard(StateFestEvent $event, string $table = self::DEFAULT_GRADING): array
    {
        $bands = $this->manualBands($table);
        $rules = $this->manualRules($table);

        if ($bands === [] || $rules === []) {
            throw ValidationException::withMessages([
                'table' => 'That standard table is not configured on this installation.',
            ]);
        }

        $this->saveBands($event, $bands);
        $this->saveRules($event, $rules);

        return ['bands' => count($bands), 'rules' => count($rules)];
    }

    /** @return list<array{grade: string, min_score: float, max_score: float}> */
    public function manualBands(string $table): array
    {
        if ($table === self::DEFAULT_GRADING) {
            return collect(config(self::DEFAULT_GRADING.'.grade_bands', []))
                ->map(fn (array $b) => [
                    'grade' => $b['grade'],
                    'min_score' => (float) $b['min'],
                    'max_score' => (float) $b['max'],
                ])->all();
        }

        // The confed table states only a minimum per grade, so each band runs up to just below the
        // next one — derived here rather than hardcoded, so editing the config stays enough.
        $grades = collect(config(self::CONFED_SCORING.'.grades', []))
            ->map(fn (array $g, string $key) => ['grade' => $g['label'] ?? $key, 'min' => (float) $g['min']])
            ->sortByDesc('min')->values();

        $ceiling = 100.0;
        $bands = [];

        foreach ($grades as $grade) {
            $bands[] = ['grade' => $grade['grade'], 'min_score' => $grade['min'], 'max_score' => $ceiling];
            $ceiling = $grade['min'] - 1;
        }

        // Everything below the lowest stated grade. Without it a mark under the floor is ungraded and
        // scores nothing, which is a silent zero rather than a stated one.
        if ($ceiling >= 0) {
            $bands[] = ['grade' => 'No Grade', 'min_score' => 0.0, 'max_score' => $ceiling];
        }

        return $bands;
    }

    /** @return list<array{grade: ?string, position: ?int, is_group: bool, points: int}> */
    public function manualRules(string $table): array
    {
        if ($table === self::DEFAULT_GRADING) {
            return collect(config(self::DEFAULT_GRADING.'.point_rules', []))
                ->map(fn (array $r) => [
                    'grade' => $r['grade'],
                    'position' => $r['position'],
                    'is_group' => (bool) $r['is_group'],
                    'points' => (int) $r['points'],
                ])->all();
        }

        $rules = [];

        foreach ([false => 'individual_points', true => 'group_points'] as $isGroup => $key) {
            foreach (config(self::CONFED_SCORING.'.'.$key, []) as $grade => $byPosition) {
                foreach ($byPosition as $position => $points) {
                    $rules[] = [
                        'grade' => $grade,
                        'position' => (int) $position,
                        'is_group' => (bool) $isGroup,
                        'points' => (int) $points,
                    ];
                }
            }
        }

        // Grade points with no placing — the manual's "participation at grade" tier.
        foreach (config(self::CONFED_SCORING.'.grade_points', []) as $type => $byGrade) {
            foreach ($byGrade as $grade => $points) {
                $rules[] = [
                    'grade' => $grade,
                    'position' => null,
                    'is_group' => $type === 'group',
                    'points' => (int) $points,
                ];
            }
        }

        return $rules;
    }

    /** What a standard table would set, without setting it — for showing the operator before they commit. */
    public function previewManual(string $table): array
    {
        return ['bands' => $this->manualBands($table), 'rules' => $this->manualRules($table)];
    }

    // ── Class categories ───────────────────────────────────────────────────────────────────

    /** @return Collection<int, StateClassCategory> */
    public function categories(StateFestEvent $event): Collection
    {
        return StateClassCategory::where('state_event_id', $event->id)
            ->orderBy('sort_order')->orderBy('code')->get();
    }

    /**
     * Seed the categories the State Kalotsav items are already coded against.
     *
     * Class bounds come from the scheme's own labels ("Category 2 — Classes 5, 6 & 7"), read rather
     * than restated, so the categories cannot drift from the labels an operator sees elsewhere.
     *
     * @return int categories written
     */
    public function seedCategories(StateFestEvent $event, string $scheme = 'kalotsav_category'): int
    {
        $groups = config("fest_class_group_schemes.schemes.{$scheme}.groups", []);

        if ($groups === []) {
            throw ValidationException::withMessages(['scheme' => 'That category scheme is not configured.']);
        }

        $order = 0;

        foreach ($groups as $code => $label) {
            [$min, $max] = $this->classRangeFromLabel($label);

            StateClassCategory::updateOrCreate(
                ['state_event_id' => $event->id, 'code' => $code],
                [
                    'state_id' => $event->state_id,
                    'label' => $label,
                    'min_class' => $min,
                    'max_class' => $max,
                    'is_open' => $min === null && $max === null,
                    'sort_order' => $order++,
                ],
            );
        }

        return count($groups);
    }

    /**
     * Pull "Classes 5, 6 & 7" out of a label.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function classRangeFromLabel(string $label): array
    {
        if (preg_match_all('/\b(\d{1,2})\b/', $label, $matches)) {
            // The leading "Category 2" is a category number, not a class, so it is dropped before the
            // range is read — otherwise Category 2's range would start at 2.
            $numbers = collect($matches[1])->map(fn ($n) => (int) $n)
                ->filter(fn (int $n) => $n >= 1 && $n <= 12)->values();

            if (preg_match('/^\s*category\s+(\d{1,2})/i', $label, $prefix)) {
                $numbers = $numbers->slice(1)->values();
            }

            if ($numbers->isNotEmpty()) {
                return [$numbers->min(), $numbers->max()];
            }
        }

        return [null, null];
    }

    /** @param  array<string, mixed>  $data */
    public function saveCategory(StateFestEvent $event, array $data, ?string $id = null): StateClassCategory
    {
        $min = ($data['min_class'] ?? null) !== null && $data['min_class'] !== '' ? (int) $data['min_class'] : null;
        $max = ($data['max_class'] ?? null) !== null && $data['max_class'] !== '' ? (int) $data['max_class'] : null;

        if ($min !== null && $max !== null && $min > $max) {
            throw ValidationException::withMessages(['min_class' => 'The lowest class is above the highest.']);
        }

        $attributes = [
            'state_id' => $event->state_id,
            'label' => $data['label'],
            'min_class' => $min,
            'max_class' => $max,
            'is_open' => (bool) ($data['is_open'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        if ($id) {
            $category = StateClassCategory::where('state_event_id', $event->id)->findOrFail($id);
            $category->forceFill($attributes)->save();

            return $category;
        }

        return StateClassCategory::updateOrCreate(
            ['state_event_id' => $event->id, 'code' => $data['code']],
            $attributes,
        );
    }

    public function deleteCategory(StateFestEvent $event, string $id): void
    {
        $category = StateClassCategory::where('state_event_id', $event->id)->findOrFail($id);

        // Refused rather than cascaded: items coded to a deleted category would stop being eligibility
        // checked at all, which is worse than leaving the category in place.
        $inUse = FestStateProgramItem::where('state_program_id', $event->state_program_id)
            ->where('class_group', $category->code)->count();

        if ($inUse) {
            throw ValidationException::withMessages([
                'category' => "{$inUse} item(s) are in {$category->label}. Move them to another category first.",
            ]);
        }

        $category->delete();
    }

    /**
     * Items whose category does not exist. These are the items whose entries nothing can check.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function itemsWithUnknownCategory(StateFestEvent $event): Collection
    {
        $codes = $this->categories($event)->pluck('code');

        return FestStateProgramItem::where('state_program_id', $event->state_program_id)
            ->whereNotNull('class_group')
            ->whereNotIn('class_group', $codes->all())
            ->get(['id', 'item_code', 'title', 'class_group'])
            ->map(fn (FestStateProgramItem $i) => [
                'item_id' => $i->id, 'item_code' => $i->item_code,
                'title' => $i->title, 'class_group' => $i->class_group,
            ]);
    }
}
