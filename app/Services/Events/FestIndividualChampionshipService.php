<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestMark;
use App\Models\Student;
use App\Models\Tenant;
use App\Support\FestCategoryMerge;
use App\Support\FestClassGroupScheme;
use App\Support\FestTeamSquadRules;
use Illuminate\Support\Collection;

/**
 * Individual (student) championship leaderboard — computed live, straight off
 * published marks, every time it's read. No "Recalculate" button or stored
 * snapshot to go stale.
 */
class FestIndividualChampionshipService
{
    /** The only categories this leaderboard may ever bucket a student into. */
    public const INDIVIDUAL_CATEGORY_KEYS = ['lp', 'up', 'hs', 'hss', 'open'];

    public function __construct(
        private FestGradePointService $gradePoints,
    ) {}

    /**
     * Resolves individual championship config for event.
     *
     * @return array<string, mixed>
     */
    public function getConfig(FestEvent $event): array
    {
        $root = $event->rootEvent();
        $stored = $root->aggregation_config['individual_championship_config'] ?? [];

        return [
            'male_title' => $stored['male_title'] ?? ($event->event_type === 'sports' ? 'Individual Champion (Boys)' : 'Kalaprathibha'),
            'female_title' => $stored['female_title'] ?? ($event->event_type === 'sports' ? 'Individual Champion (Girls)' : 'Kalathilakam'),
            'runner_up_title' => $stored['runner_up_title'] ?? 'Runner Up',
            'disabled' => (bool) ($stored['disabled'] ?? false),
            'max_counting_items' => (int) ($stored['max_counting_items'] ?? 0), // 0 = unlimited / all items
            'multi_person_mode' => $stored['multi_person_mode'] ?? 'tie_break_only', // 'tie_break_only', 'include_weighted', 'exclude'
            'group_weight_percent' => (int) ($stored['group_weight_percent'] ?? 100),
            'must_have_first_place' => (bool) ($stored['must_have_first_place'] ?? false),
            'minimum_points' => (int) ($stored['minimum_points'] ?? 0),
            'excluded_item_categories' => (array) ($stored['excluded_item_categories'] ?? []),
            'excluded_individual_categories' => (array) ($stored['excluded_individual_categories'] ?? []),
            'group_by_gender' => (bool) ($stored['group_by_gender'] ?? true),
        ];
    }

    /**
     * Resolves the public-facing overlay config for the Individual Championship
     * tab on the public results page. Stored in aggregation_config['public_overlays'].
     *
     * @return array{enabled: bool, categories: list<string>}
     */
    public function getPublicOverlayConfig(FestEvent $event): array
    {
        $root = $event->rootEvent();
        $stored = $root->aggregation_config['public_overlays'] ?? [];

        return [
            'enabled' => ! $this->getConfig($event)['disabled'] && (bool) ($stored['enabled'] ?? true),
            'categories' => isset($stored['categories']) && is_array($stored['categories'])
                ? array_values(array_unique($stored['categories']))
                : [],
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboardForEvent(FestEvent $event, bool $directPhotoUrls = false): Collection
    {
        $config = $this->getConfig($event);
        if ($config['disabled']) {
            return collect();
        }

        return $this->rankAndFormat($this->pointsForEvent($event), $directPhotoUrls, $config);
    }

    /**
     * Ranked within category AND gender together.
     *
     * @param  Collection<int, object>  $allRows
     * @return Collection<int, array<string, mixed>>
     */
    public function rankAndFormat(Collection $allRows, bool $directPhotoUrls = false, ?array $config = null): Collection
    {
        $config = $config ?? [];
        if ($config['disabled'] ?? false) {
            return collect();
        }
        $excluded = (array) ($config['excluded_individual_categories'] ?? []);
        $allRows = $allRows->reject(fn ($row) => in_array($row->category, $excluded, true));
        $minPoints = (int) ($config['minimum_points'] ?? 0);
        $mustFirst = (bool) ($config['must_have_first_place'] ?? false);

        if ($minPoints > 0) {
            $allRows = $allRows->filter(fn ($r) => ($r->points ?? 0) >= $minPoints);
        }
        if ($mustFirst) {
            $allRows = $allRows->filter(fn ($r) => ($r->firsts ?? 0) > 0);
        }

        $allRows = $allRows->sort(function ($a, $b) {
            return [$b->points, $b->firsts ?? 0, $b->group_points, $a->student_id]
                <=> [$a->points, $a->firsts ?? 0, $a->group_points, $b->student_id];
        })->values();

        $rankKey = ($config['group_by_gender'] ?? true) !== false
            ? fn ($row) => $row->category.'|'.$row->gender
            : fn ($row) => $row->category;
        $rankedByCategory = $allRows->groupBy($rankKey)
            ->flatMap(function ($groupRows) {
                $rank = 0;
                $previousKey = null;

                return $groupRows->values()->map(function ($row) use (&$rank, &$previousKey) {
                    $key = $row->points.'|'.($row->firsts ?? 0).'|'.$row->group_points;
                    if ($key !== $previousKey) {
                        $rank++;
                        $previousKey = $key;
                    }

                    return [$row, $rank];
                });
        });

        $overallRankByStudent = $allRows->values()->mapWithKeys(fn ($row, int $index) => [$row->student_id => $index + 1]);
        $schoolNames = Tenant::query()
            ->whereIn('id', $allRows->pluck('student.tenant_id')->filter()->unique())
            ->get(['id', 'name', 'type'])
            ->mapWithKeys(fn (Tenant $school) => [$school->id => $school->name]);

        return $rankedByCategory->map(function (array $pair) use ($overallRankByStudent, $schoolNames, $directPhotoUrls) {
            [$row, $rank] = $pair;

            return [
                'rank'         => $rank,
                'overall_rank' => $overallRankByStudent[$row->student_id] ?? null,
                'points'       => $row->points,
                'firsts'       => $row->firsts ?? 0,
                'group_points' => $row->group_points,
                'category'     => $row->category,
                'gender'       => $row->gender,
                'student'      => [
                    'id'     => $row->student_id,
                    'name'   => $row->student?->name,
                    'reg_no' => $row->student?->reg_no,
                    'photo'  => $directPhotoUrls ? $row->student?->publicPhotoUrl() : $row->student?->photoDataUri(),
                    'photo_fallback' => $directPhotoUrls ? $row->student?->publicPhotoFallbackUrl() : null,
                ],
                'school' => $schoolNames[$row->student?->tenant_id] ?? null,
            ];
        })->sortBy([
            ['category', 'asc'],
            ['gender', 'asc'],
            ['rank', 'asc'],
        ])->values();
    }

    /**
     * Crowned champions summary per category and overall.
     *
     * @return array<string, mixed>
     */
    public function championsSummary(FestEvent $event, bool $directPhotoUrls = false, ?Collection $leaderboard = null): array
    {
        $config = $this->getConfig($event);
        $leaderboard ??= $this->leaderboardForEvent($event, $directPhotoUrls);
        $root = $event->rootEvent();
        $canonicalLabels = FestClassGroupScheme::canonicalLabels(null, $root);
        $groupByGender = ($config['group_by_gender'] ?? true) !== false;

        $categories = $leaderboard->pluck('category')->unique()->values();

        $categoryChampions = [];
        foreach ($categories as $catKey) {
            $catRows = $leaderboard->filter(fn ($r) => $r['category'] === $catKey);

            if ($groupByGender) {
                $boys = $catRows->filter(fn ($r) => $r['gender'] === 'male')->values();
                $girls = $catRows->filter(fn ($r) => $r['gender'] === 'female')->values();

                $categoryChampions[] = [
                    'category' => $catKey,
                    'category_label' => $canonicalLabels[$catKey] ?? strtoupper($catKey),
                    'male_champion' => $boys->firstWhere('rank', 1) ? $boys->firstWhere('rank', 1) + ['title' => $config['male_title']] : null,
                    'male_runner_up' => $boys->firstWhere('rank', 2) ? $boys->firstWhere('rank', 2) + ['title' => $config['runner_up_title']] : null,
                    'female_champion' => $girls->firstWhere('rank', 1) ? $girls->firstWhere('rank', 1) + ['title' => $config['female_title']] : null,
                    'female_runner_up' => $girls->firstWhere('rank', 2) ? $girls->firstWhere('rank', 2) + ['title' => $config['runner_up_title']] : null,
                ];
            } else {
                $top = $catRows->sortBy('rank')->values();
                $categoryChampions[] = [
                    'category' => $catKey,
                    'category_label' => $canonicalLabels[$catKey] ?? strtoupper($catKey),
                    'champion' => $top->first() ? $top->first() + ['title' => $config['male_title']] : null,
                    'runner_up' => $top->firstWhere('rank', 2) ? $top->firstWhere('rank', 2) + ['title' => $config['runner_up_title']] : null,
                ];
            }
        }

        if ($groupByGender) {
            // Overall fest champions (top overall boy and girl across any category)
            $allBoys = $leaderboard->filter(fn ($r) => $r['gender'] === 'male')->sortBy('overall_rank')->values();
            $allGirls = $leaderboard->filter(fn ($r) => $r['gender'] === 'female')->sortBy('overall_rank')->values();

            return [
                'config' => $config,
                'category_champions' => $categoryChampions,
                'overall_male_champion' => $allBoys->first() ? $allBoys->first() + ['title' => 'Overall ' . $config['male_title']] : null,
                'overall_female_champion' => $allGirls->first() ? $allGirls->first() + ['title' => 'Overall ' . $config['female_title']] : null,
            ];
        }

        return [
            'config' => $config,
            'category_champions' => $categoryChampions,
            'overall_champion' => $leaderboard->sortBy('overall_rank')->first() ? $leaderboard->sortBy('overall_rank')->first() + ['title' => 'Overall ' . $config['male_title']] : null,
        ];
    }

    /**
     * Full item-by-item breakdown for a student.
     *
     * @return array<string, mixed>
     */
    public function studentItemBreakdown(FestEvent $event, int $studentId): array
    {
        $student = Student::find($studentId);
        if (! $student) {
            return [];
        }

        $config = $this->getConfig($event);
        $maxCounting = $config['max_counting_items'];
        $multiMode = $config['multi_person_mode'];
        $groupWeight = $config['group_weight_percent'];

        $marks = FestMark::where('event_id', $event->id)
            ->whereHas('participant', fn ($q) => $q->where('student_id', $studentId))
            ->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')->where('results_hidden', false))
            ->with(['item', 'participant'])
            ->get();

        $items = [];
        $soloItems = [];

        foreach ($marks as $mark) {
            $item = $mark->item;
            if (! $item) {
                continue;
            }

            $rawPoints = $this->gradePoints->pointsForMark($event, $mark);
            $isMulti = FestTeamSquadRules::isMultiPerson($item->participant_type);

            $entry = [
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'title' => $item->title,
                'class_group' => $item->class_group,
                'category' => $item->category,
                'participant_type' => $item->participant_type,
                'is_multi_person' => $isMulti,
                'score' => $mark->score,
                'grade' => $mark->grade,
                'position' => $mark->position,
                'raw_points' => $rawPoints,
                'counted_points' => 0,
                'is_counted' => false,
                'is_tie_break' => false,
            ];

            if ($isMulti) {
                if ($multiMode === 'include_weighted') {
                    $entry['counted_points'] = round(($rawPoints * $groupWeight) / 100, 2);
                    $entry['is_counted'] = true;
                } elseif ($multiMode === 'tie_break_only') {
                    $entry['counted_points'] = 0;
                    $entry['is_tie_break'] = true;
                }
            } else {
                $soloItems[] = &$entry;
            }

            $items[] = &$entry;
            unset($entry);
        }

        // Apply item capping on solo items
        usort($soloItems, fn ($a, $b) => $b['raw_points'] <=> $a['raw_points']);
        foreach ($soloItems as $idx => &$itemRef) {
            if ($maxCounting <= 0 || $idx < $maxCounting) {
                $itemRef['counted_points'] = $itemRef['raw_points'];
                $itemRef['is_counted'] = true;
            } else {
                $itemRef['counted_points'] = 0;
                $itemRef['is_counted'] = false;
            }
        }
        unset($itemRef);

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'reg_no' => $student->reg_no,
                'gender' => $student->gender,
                'school' => Tenant::where('id', $student->tenant_id)->value('name'),
            ],
            'items' => $items,
            'total_points' => collect($items)->sum('counted_points'),
            'group_tiebreak_points' => collect($items)->where('is_tie_break', true)->sum('raw_points'),
            'firsts_count' => collect($items)->where('position', 1)->count(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function crossPhaseStanding(FestEvent $hub, bool $directPhotoUrls = false): Collection
    {
        if ($this->getConfig($hub)['disabled']) {
            return collect();
        }
        return $this->rankAndFormat($this->sumAcrossLeaves($this->allPhaseLeaves($hub)), $directPhotoUrls, $this->getConfig($hub));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function crossPhaseStandingForVisibleLeaves(FestEvent $hub, Collection $visibleLeafIds, bool $directPhotoUrls = false): Collection
    {
        if ($this->getConfig($hub)['disabled']) {
            return collect();
        }
        $visible = $this->allPhaseLeaves($hub)->filter(fn (FestEvent $leaf) => $visibleLeafIds->contains($leaf->id));

        return $this->rankAndFormat($this->sumAcrossLeaves($visible), $directPhotoUrls, $this->getConfig($hub));
    }

    /** @return Collection<int, FestEvent> */
    private function allPhaseLeaves(FestEvent $hub): Collection
    {
        $phaseIds = FestEventPhase::where('event_id', $hub->id)->pluck('id');
        if ($phaseIds->isEmpty()) {
            return collect();
        }

        return FestEvent::where('parent_event_id', $hub->id)
            ->whereIn('source_phase_id', $phaseIds)
            ->get();
    }

    /**
     * @param  Collection<int, FestEvent>  $leaves
     * @return Collection<int, object>
     */
    private function sumAcrossLeaves(Collection $leaves): Collection
    {
        if ($leaves->isEmpty()) {
            return collect();
        }

        return $leaves
            ->flatMap(fn (FestEvent $leaf) => $this->pointsForEvent($leaf))
            ->groupBy('student_id')
            ->map(function (Collection $studentRows) {
                $first = $studentRows->first();

                return (object) [
                    'student_id'   => $first->student_id,
                    'student'      => $first->student,
                    'category'     => $first->category,
                    'gender'       => $first->gender,
                    'points'       => $studentRows->sum('points'),
                    'firsts'       => $studentRows->sum('firsts'),
                    'group_points' => $studentRows->sum('group_points'),
                ];
            })
            ->values();
    }

    /**
     * Live aggregate of one event's own published marks into per-student championship points.
     *
     * @return Collection<int, object>
     */
    public function pointsForEvent(FestEvent $event, array $options = []): Collection
    {
        $config = array_merge($this->getConfig($event), $options);
        $categoryMap = FestCategoryMerge::map($event->rootEvent());
        $maxCounting = $config['max_counting_items'];
        $multiMode = $config['multi_person_mode'];
        $groupWeight = $config['group_weight_percent'];
        $excludedCategories = (array) ($config['excluded_item_categories'] ?? []);

        $studentItems = [];

        FestMark::where('event_id', $event->id)
            ->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')->where('results_hidden', false))
            ->with(['item', 'participant.student', 'participant.registration.item'])
            ->each(function (FestMark $mark) use ($event, $categoryMap, $excludedCategories, &$studentItems) {
                $student = $mark->participant?->student;
                if (! $student) {
                    return;
                }

                $item = $mark->participant->registration?->item;
                if (! $item) {
                    return;
                }

                if (! empty($excludedCategories)) {
                    $itemCat = strtolower((string) $item->category);
                    if (in_array($itemCat, $excludedCategories, true)) {
                        return;
                    }
                }

                $rawClassGroup = $item->class_group ?: 'open';
                $canonicalCategory = FestClassGroupScheme::canonicalKey($rawClassGroup);
                $canonicalCategory = in_array($canonicalCategory, self::INDIVIDUAL_CATEGORY_KEYS, true) ? $canonicalCategory : 'open';

                $merged = $categoryMap[$rawClassGroup] ?? $categoryMap[$canonicalCategory] ?? $canonicalCategory;
                $category = in_array($merged, self::INDIVIDUAL_CATEGORY_KEYS, true) ? $merged : $canonicalCategory;

                $gender = match ($student->gender) {
                    'male'   => 'male',
                    'female' => 'female',
                    default  => null,
                };
                if ($gender === null) {
                    return;
                }

                $points = $this->gradePoints->pointsForMark($event, $mark);
                $isMulti = FestTeamSquadRules::isMultiPerson($item->participant_type);

                $studentItems[$student->id]['student'] = $student;
                // Open group items must not move an individual competitor out of
                // their own class category merely because that mark was read last.
                $existing = $studentItems[$student->id] ?? [];
                if (! isset($existing['category']) || (! $isMulti &&
                    (($existing['category_from_group'] ?? false) || $existing['category'] === 'open'))) {
                    $studentItems[$student->id]['category'] = $category;
                    $studentItems[$student->id]['category_from_group'] = $isMulti;
                }
                $studentItems[$student->id]['gender'] = $gender;
                $studentItems[$student->id]['marks'][] = [
                    'mark' => $mark,
                    'points' => $points,
                    'is_multi' => $isMulti,
                    'position' => $mark->position,
                ];
            });

        $aggregated = [];

        foreach ($studentItems as $studentId => $data) {
            $student = $data['student'];
            $category = $data['category'];
            $gender = $data['gender'];
            $marksList = $data['marks'];

            $firsts = 0;
            $soloPointsList = [];
            $groupPoints = 0;
            $countedWeightedGroupPoints = 0;

            foreach ($marksList as $entry) {
                if ($entry['position'] === 1) {
                    $firsts++;
                }

                if ($entry['is_multi']) {
                    if ($multiMode === 'include_weighted') {
                        $countedWeightedGroupPoints += round(($entry['points'] * $groupWeight) / 100, 2);
                    } elseif ($multiMode === 'tie_break_only') {
                        $groupPoints += $entry['points'];
                    }
                } else {
                    $soloPointsList[] = $entry['points'];
                }
            }

            // Cap solo items if max_counting_items > 0
            rsort($soloPointsList);
            if ($maxCounting > 0 && count($soloPointsList) > $maxCounting) {
                $soloPointsList = array_slice($soloPointsList, 0, $maxCounting);
            }
            $totalPoints = array_sum($soloPointsList) + $countedWeightedGroupPoints;

            $aggregated[] = (object) [
                'student_id'   => $student->id,
                'student'      => $student,
                'points'       => $totalPoints,
                'firsts'       => $firsts,
                'group_points' => $groupPoints,
                'category'     => $category,
                'gender'       => $gender,
            ];
        }

        return collect($aggregated);
    }
}
