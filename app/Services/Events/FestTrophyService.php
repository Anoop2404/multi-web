<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestTrophy;
use App\Models\FestTrophyTemplate;
use App\Models\Tenant;
use App\Support\FestCategoryMerge;
use App\Support\FestClassGroupScheme;
use App\Support\FestOverallCategoryExclusion;
use App\Support\FestTeamSquadRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FestTrophyService
{
    public function __construct(
        private FestGradePointService $gradePoints,
        private FestIndividualChampionshipService $individualChampionship,
    ) {}

    /** @return Collection<int, FestTrophy> */
    public function trophiesForEvent(FestEvent $event): Collection
    {
        return FestTrophy::where('event_id', $event->id)
            ->with(['item'])
            ->orderBy('trophy_no')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Resolves live winners for each trophy in the list.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function resolveWinners(FestEvent $event, ?Collection $trophies = null, bool $cumulative = false): Collection
    {
        $trophies = $trophies ?? $this->trophiesForEvent($event);
        if ($trophies->isEmpty()) {
            return collect();
        }

        $root = $event->rootEvent();
        $targetEvent = $cumulative ? $root : $event;

        // Preload cached school standings
        $overallSchoolStandings = $this->overallSchoolPoints($targetEvent, $cumulative);
        $categorySchoolStandings = $this->categorySchoolPoints($targetEvent, $cumulative);
        $individualLeaderboard = $cumulative
            ? $this->individualChampionship->crossPhaseStanding($root)
            : $this->individualChampionship->leaderboardForEvent($event);

        return $trophies->map(function (FestTrophy $trophy) use (
            $targetEvent,
            $cumulative,
            $overallSchoolStandings,
            $categorySchoolStandings,
            $individualLeaderboard
        ) {
            $winner = match ($trophy->trophy_type) {
                FestTrophy::TYPE_OVERALL => $this->resolveOverallWinner($trophy, $overallSchoolStandings),
                FestTrophy::TYPE_CATEGORY => $this->resolveCategoryWinner($trophy, $categorySchoolStandings),
                FestTrophy::TYPE_ITEM => $this->resolveItemWinner($targetEvent, $trophy, $cumulative),
                FestTrophy::TYPE_ITEM_GROUP => $this->resolveItemGroupWinner($targetEvent, $trophy, $cumulative),
                FestTrophy::TYPE_INDIVIDUAL_CHAMPIONSHIP => $this->resolveIndividualWinner($trophy, $individualLeaderboard),
                default => null,
            };

            return [
                'trophy' => [
                    'id' => $trophy->id,
                    'trophy_no' => $trophy->trophy_no,
                    'title' => $trophy->title,
                    'trophy_type' => $trophy->trophy_type,
                    'position' => $trophy->position,
                    'position_ordinal' => $trophy->positionOrdinal(),
                    'award_type' => $trophy->award_type,
                    'category_key' => $trophy->category_key,
                    'item_id' => $trophy->item_id,
                    'item_name' => $trophy->item?->title,
                    'item_name_pattern' => $trophy->item_name_pattern,
                    'item_ids' => $trophy->item_ids ?? [],
                    'item_group_name' => $trophy->item_group_name,
                    'gender' => $trophy->gender,
                    'notes' => $trophy->notes,
                    'is_rolling' => $trophy->is_rolling,
                    'donor_name' => $trophy->donor_name,
                    'sort_order' => $trophy->sort_order,
                    'is_active' => $trophy->is_active,
                ],
                'winner' => $winner,
                'has_winner' => $winner !== null && ! empty($winner['name']),
            ];
        });
    }

    /**
     * Resolve overall points winner (1st, 2nd, 3rd school).
     */
    private function resolveOverallWinner(FestTrophy $trophy, Collection $overallStandings): ?array
    {
        $index = $trophy->position - 1;
        $row = $overallStandings->values()->get($index);
        if (! $row) {
            return null;
        }

        return [
            'type' => 'school',
            'name' => $row['school_name'],
            'school_id' => $row['school_id'],
            'points' => $row['points'],
            'detail' => "{$row['points']} pts overall",
            'is_tied' => $row['is_tied'] ?? false,
        ];
    }

    /**
     * Resolve category-wise school winner (1st, 2nd, 3rd in Category I, II, III, IV).
     */
    private function resolveCategoryWinner(FestTrophy $trophy, array $categoryStandings): ?array
    {
        $catKey = strtolower((string) ($trophy->category_key ?? ''));
        // Try direct key or canonical alias
        $catRows = $categoryStandings[$catKey]
            ?? $categoryStandings[FestClassGroupScheme::canonicalKey($catKey)]
            ?? collect();

        $index = $trophy->position - 1;
        $row = $catRows->values()->get($index);
        if (! $row) {
            return null;
        }

        return [
            'type' => 'school',
            'name' => $row['school_name'],
            'school_id' => $row['school_id'],
            'points' => $row['points'],
            'detail' => "{$row['points']} pts in " . ($trophy->category_key ?: 'Category'),
            'is_tied' => $row['is_tied'] ?? false,
        ];
    }

    /**
     * Resolve single item winner (1st, 2nd, 3rd in One Act Play, Oppana, etc.).
     */
    private function resolveItemWinner(FestEvent $event, FestTrophy $trophy, bool $cumulative = false): ?array
    {
        $item = $this->findItemForTrophy($event, $trophy);
        if (! $item) {
            return null;
        }

        $mark = FestMark::where('item_id', $item->id)
            ->where('position', $trophy->position)
            ->with(['participant.student', 'participant.registration'])
            ->first();

        if (! $mark || ! $mark->participant) {
            return null;
        }

        $participant = $mark->participant;
        $student = $participant->student;
        $schoolId = $participant->registration?->school_id;
        $schoolName = $schoolId ? Tenant::where('id', $schoolId)->value('name') : null;

        $studentName = $student?->name ?? 'Participant';
        $chestNo = $participant->chest_no ?? $participant->chest_number ?? $student?->reg_no;

        $isMultiPerson = FestTeamSquadRules::isMultiPerson($item->participant_type);
        $displayName = $isMultiPerson && $schoolName
            ? $schoolName . ($studentName ? " ({$studentName})" : '')
            : ($studentName . ($schoolName ? " — {$schoolName}" : ''));

        return [
            'type' => $isMultiPerson ? 'school' : 'individual',
            'name' => $displayName,
            'student_name' => $studentName,
            'school_name' => $schoolName,
            'chest_no' => $chestNo,
            'score' => $mark->score,
            'grade' => $mark->grade,
            'item_title' => $item->title,
            'detail' => ($mark->grade ? "Grade {$mark->grade} · " : '') . ($mark->score ? "{$mark->score} marks" : ''),
        ];
    }

    /**
     * Resolve item group / cluster winner (e.g. Music items, Art items sum of points).
     */
    private function resolveItemGroupWinner(FestEvent $event, FestTrophy $trophy, bool $cumulative = false): ?array
    {
        $items = $this->findItemsForGroup($event, $trophy);
        if ($items->isEmpty()) {
            return null;
        }

        $itemIds = $items->pluck('id');
        $marks = FestMark::whereIn('item_id', $itemIds)
            ->whereNotNull('position')
            ->with(['participant.registration', 'item'])
            ->get()
            ->filter(fn (FestMark $m) => $m->participant && $m->participant->registration);

        if ($marks->isEmpty()) {
            return null;
        }

        $schoolPoints = [];
        foreach ($marks as $mark) {
            $schoolId = $mark->participant->registration->school_id;
            if (! $schoolId) {
                continue;
            }
            $pts = $this->gradePoints->pointsForMark($event, $mark);
            $schoolPoints[$schoolId] = ($schoolPoints[$schoolId] ?? 0) + $pts;
        }

        if (empty($schoolPoints)) {
            return null;
        }

        arsort($schoolPoints);
        $schools = Tenant::whereIn('id', array_keys($schoolPoints))->pluck('name', 'id');

        $index = $trophy->position - 1;
        $sortedKeys = array_keys($schoolPoints);
        if (! isset($sortedKeys[$index])) {
            return null;
        }

        $winnerSchoolId = $sortedKeys[$index];
        $points = $schoolPoints[$winnerSchoolId];

        return [
            'type' => 'school',
            'name' => $schools[$winnerSchoolId] ?? 'School',
            'school_id' => $winnerSchoolId,
            'points' => $points,
            'detail' => "{$points} pts across {$items->count()} items",
        ];
    }

    /**
     * Resolve individual championship winner (Kalaprathibha, Kalathilakam, Category Champion).
     */
    private function resolveIndividualWinner(FestTrophy $trophy, Collection $leaderboard): ?array
    {
        $filtered = $leaderboard;

        if ($trophy->category_key) {
            $cat = strtolower($trophy->category_key);
            $filtered = $filtered->filter(fn ($r) => strtolower((string) ($r['category'] ?? '')) === $cat
                || FestClassGroupScheme::canonicalKey($r['category'] ?? '') === FestClassGroupScheme::canonicalKey($cat));
        }

        if ($trophy->gender) {
            $gender = strtolower($trophy->gender);
            $filtered = $filtered->filter(fn ($r) => strtolower((string) ($r['gender'] ?? '')) === $gender);
        }

        $winnerRow = $filtered->firstWhere('rank', $trophy->position);
        if (! $winnerRow) {
            return null;
        }

        return [
            'type' => 'individual',
            'name' => ($winnerRow['student']['name'] ?? 'Student') . ($winnerRow['school'] ? " ({$winnerRow['school']})" : ''),
            'student_name' => $winnerRow['student']['name'] ?? null,
            'reg_no' => $winnerRow['student']['reg_no'] ?? null,
            'school_name' => $winnerRow['school'] ?? null,
            'points' => $winnerRow['points'] ?? 0,
            'detail' => "{$winnerRow['points']} championship pts",
        ];
    }

    /**
     * Find item matching trophy (by ID or pattern/category).
     */
    public function findItemForTrophy(FestEvent $event, FestTrophy $trophy): ?FestEventItem
    {
        if ($trophy->item_id) {
            return FestEventItem::where('event_id', $event->id)->find($trophy->item_id);
        }

        $query = FestEventItem::where('event_id', $event->id);

        if ($trophy->item_name_pattern) {
            $pattern = str_replace('%', '.*', preg_quote($trophy->item_name_pattern, '/'));
            $items = $query->get();
            return $items->first(function (FestEventItem $item) use ($trophy) {
                $needle = strtolower($trophy->item_name_pattern);
                $haystack = strtolower($item->title);
                if (str_contains($haystack, $needle)) {
                    if ($trophy->category_key) {
                        return str_contains(strtolower((string) $item->class_group), strtolower($trophy->category_key))
                            || str_contains(strtolower((string) $item->category), strtolower($trophy->category_key));
                    }
                    return true;
                }
                return false;
            });
        }

        return null;
    }

    /**
     * Find items for an item group / cluster.
     *
     * @return Collection<int, FestEventItem>
     */
    public function findItemsForGroup(FestEvent $event, FestTrophy $trophy): Collection
    {
        if (! empty($trophy->item_ids)) {
            return FestEventItem::where('event_id', $event->id)->whereIn('id', $trophy->item_ids)->get();
        }

        $groupName = strtolower((string) ($trophy->item_group_name ?? ''));
        $query = FestEventItem::where('event_id', $event->id);

        if (str_contains($groupName, 'music')) {
            $musicKeywords = ['classical music', 'light music', 'mappilappattu', 'violin', 'guitar', 'flute', 'mrudangam', 'tabala', 'vocal'];
            return $query->get()->filter(function (FestEventItem $item) use ($musicKeywords) {
                if (strtolower((string) $item->category) === 'music') {
                    return true;
                }
                $title = strtolower($item->title);
                foreach ($musicKeywords as $kw) {
                    if (str_contains($title, $kw)) {
                        return true;
                    }
                }
                return false;
            })->values();
        }

        if (str_contains($groupName, 'art') || str_contains($groupName, 'drawing')) {
            $artKeywords = ['pencil drawing', 'painting', 'crayon', 'water colour', 'water color', 'oil colour', 'oil color', 'cartoon', 'clay'];
            return $query->get()->filter(function (FestEventItem $item) use ($artKeywords) {
                if (strtolower((string) $item->category) === 'art') {
                    return true;
                }
                $title = strtolower($item->title);
                foreach ($artKeywords as $kw) {
                    if (str_contains($title, $kw)) {
                        return true;
                    }
                }
                return false;
            })->values();
        }

        return collect();
    }

    /**
     * Overall school points helper.
     *
     * @return Collection<int, array{school_id: string, school_name: string, points: float, is_tied: bool}>
     */
    private function overallSchoolPoints(FestEvent $event, bool $cumulative = false): Collection
    {
        $excludedCategories = FestOverallCategoryExclusion::excluded($event->rootEvent());

        $marks = FestMark::where('event_id', $event->id)
            ->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')->where('results_hidden', false))
            ->with(['participant.registration', 'item'])
            ->get();

        $scores = [];
        foreach ($marks as $mark) {
            $item = $mark->item;
            if ($item && in_array($item->class_group, $excludedCategories, true)) {
                continue;
            }
            $schoolId = $mark->participant?->registration?->school_id;
            if (! $schoolId) {
                continue;
            }
            $pts = $this->gradePoints->pointsForMark($event, $mark);
            $scores[$schoolId] = ($scores[$schoolId] ?? 0) + $pts;
        }

        arsort($scores);
        $schools = Tenant::whereIn('id', array_keys($scores))->pluck('name', 'id');

        $rows = [];
        foreach ($scores as $schoolId => $pts) {
            $rows[] = [
                'school_id' => $schoolId,
                'school_name' => $schools[$schoolId] ?? 'School',
                'points' => (float) $pts,
                'is_tied' => false,
            ];
        }

        return collect($rows);
    }

    /**
     * Category-wise school points helper.
     *
     * @return array<string, Collection<int, array{school_id: string, school_name: string, points: float, is_tied: bool}>>
     */
    private function categorySchoolPoints(FestEvent $event, bool $cumulative = false): array
    {
        $categoryMap = FestCategoryMerge::map($event->rootEvent());

        $marks = FestMark::where('event_id', $event->id)
            ->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at')->where('results_hidden', false))
            ->with(['participant.registration', 'item'])
            ->get();

        $byCategory = [];
        $schoolIds = [];

        foreach ($marks as $mark) {
            $schoolId = $mark->participant?->registration?->school_id;
            if (! $schoolId || ! $mark->item) {
                continue;
            }
            $rawCat = strtolower($mark->item->class_group ?: 'open');
            $mergedCat = $categoryMap[$rawCat] ?? $rawCat;

            $pts = $this->gradePoints->pointsForMark($event, $mark);
            $byCategory[$mergedCat][$schoolId] = ($byCategory[$mergedCat][$schoolId] ?? 0) + $pts;
            $schoolIds[] = $schoolId;
        }

        $schools = Tenant::whereIn('id', array_unique($schoolIds))->pluck('name', 'id');

        $result = [];
        foreach ($byCategory as $cat => $schoolScores) {
            arsort($schoolScores);
            $rows = [];
            foreach ($schoolScores as $schoolId => $pts) {
                $rows[] = [
                    'school_id' => $schoolId,
                    'school_name' => $schools[$schoolId] ?? 'School',
                    'points' => (float) $pts,
                    'is_tied' => false,
                ];
            }
            $result[$cat] = collect($rows);
        }

        return $result;
    }

    /**
     * Seeds the standard Kochi Metro Sahodaya 60-trophy template from the user's document.
     */
    public function seedKochiMetroPreset(FestEvent $event): int
    {
        return DB::transaction(function () use ($event) {
            // Delete existing trophies to avoid duplicates if re-seeding
            FestTrophy::where('event_id', $event->id)->delete();

            $template = FestTrophyTemplate::firstOrCreate(
                ['event_id' => $event->id, 'code' => 'kochi-metro-kalotsav-60'],
                [
                    'name' => 'Metro Kalotsav 2025 Standard List (60 Trophies)',
                    'description' => 'Official 60-Trophy Distribution Template from Kochi Metro Sahodaya Youth Festival.',
                    'is_default' => true,
                ]
            );

            $definitions = $this->kochiMetroPresetDefinitions();
            $items = FestEventItem::where('event_id', $event->id)->get();

            $created = 0;
            foreach ($definitions as $def) {
                $matchedItemId = null;
                if (! empty($def['item_name_pattern'])) {
                    $needle = strtolower($def['item_name_pattern']);
                    $cat = ! empty($def['category_key']) ? strtolower($def['category_key']) : null;
                    $matched = $items->first(function (FestEventItem $it) use ($needle, $cat) {
                        $haystack = strtolower($it->title);
                        if (str_contains($haystack, $needle)) {
                            if ($cat) {
                                return str_contains(strtolower((string) $it->class_group), $cat);
                            }
                            return true;
                        }
                        return false;
                    });
                    $matchedItemId = $matched?->id;
                }

                // Resolve item_ids for item_group trophies: persist the matched items so the
                // modal shows them as already-selected and resolvers don't depend on a fragile
                // name-based re-derivation every time. Falls back to a transient resolve if the
                // trophy is missing a group name (shouldn't happen with this preset).
                $resolvedItemIds = null;
                if (($def['trophy_type'] ?? null) === 'item_group' && ! empty($def['item_group_name'])) {
                    $groupName = strtolower($def['item_group_name']);
                    $resolvedItemIds = $items->filter(function (FestEventItem $it) use ($groupName) {
                        if (str_contains($groupName, 'music')) {
                            if (strtolower((string) $it->category) === 'music') {
                                return true;
                            }
                            $title = strtolower($it->title);
                            foreach (['classical music', 'light music', 'mappilappattu', 'violin', 'guitar', 'flute', 'mrudangam', 'tabala', 'vocal'] as $kw) {
                                if (str_contains($title, $kw)) {
                                    return true;
                                }
                            }
                            return false;
                        }
                        if (str_contains($groupName, 'art') || str_contains($groupName, 'drawing')) {
                            if (strtolower((string) $it->category) === 'art') {
                                return true;
                            }
                            $title = strtolower($it->title);
                            foreach (['pencil drawing', 'painting', 'crayon', 'water colour', 'water color', 'oil colour', 'oil color', 'cartoon', 'clay'] as $kw) {
                                if (str_contains($title, $kw)) {
                                    return true;
                                }
                            }
                            return false;
                        }
                        return false;
                    })->pluck('id')->all();
                }

                FestTrophy::create([
                    'event_id' => $event->id,
                    'template_id' => $template->id,
                    'trophy_no' => $def['trophy_no'],
                    'title' => $def['title'],
                    'trophy_type' => $def['trophy_type'],
                    'position' => $def['position'],
                    'award_type' => $def['award_type'] ?? FestTrophy::AWARD_SCHOOL,
                    'category_key' => $def['category_key'] ?? null,
                    'item_id' => $matchedItemId,
                    'item_name_pattern' => $def['item_name_pattern'] ?? null,
                    'item_ids' => $resolvedItemIds,
                    'item_group_name' => $def['item_group_name'] ?? null,
                    'notes' => $def['notes'] ?? null,
                    'is_rolling' => $def['is_rolling'] ?? false,
                    'sort_order' => $def['trophy_no'],
                    'is_active' => true,
                ]);
                $created++;
            }

            return $created;
        });
    }

    /**
     * Copies / inherits trophy template from parent event.
     */
    public function copyFromParent(FestEvent $childEvent): int
    {
        abort_unless($childEvent->parent_event_id, 422, 'This event does not have a parent event.');
        $parent = FestEvent::findOrFail($childEvent->parent_event_id);

        $parentTrophies = FestTrophy::where('event_id', $parent->id)->orderBy('trophy_no')->get();
        abort_if($parentTrophies->isEmpty(), 422, "Parent event '{$parent->title}' has no trophies configured yet.");

        return DB::transaction(function () use ($childEvent, $parent, $parentTrophies) {
            FestTrophy::where('event_id', $childEvent->id)->delete();

            $parentTemplate = FestTrophyTemplate::where('event_id', $parent->id)->first();
            $childTemplate = null;
            if ($parentTemplate) {
                $childTemplate = FestTrophyTemplate::updateOrCreate(
                    ['event_id' => $childEvent->id, 'code' => $parentTemplate->code],
                    [
                        'name' => $parentTemplate->name,
                        'description' => "Inherited from parent event: {$parent->title}",
                        'is_default' => true,
                    ]
                );
            }

            $childItems = FestEventItem::where('event_id', $childEvent->id)->get();
            $count = 0;

            foreach ($parentTrophies as $trophy) {
                // Try to find matching item in child event by code or title
                $matchedItemId = null;
                if ($trophy->item_id) {
                    $parentItem = FestEventItem::find($trophy->item_id);
                    if ($parentItem) {
                        $match = $childItems->first(fn ($i) => ($i->item_code && $i->item_code === $parentItem->item_code)
                            || strcasecmp($i->title, $parentItem->title) === 0);
                        $matchedItemId = $match?->id;
                    }
                } elseif ($trophy->item_name_pattern) {
                    $needle = strtolower($trophy->item_name_pattern);
                    $match = $childItems->first(fn ($i) => str_contains(strtolower($i->title), $needle));
                    $matchedItemId = $match?->id;
                }

                $clone = $trophy->replicate();
                $clone->event_id = $childEvent->id;
                $clone->template_id = $childTemplate?->id;
                $clone->item_id = $matchedItemId;
                $clone->save();
                $count++;
            }

            return $count;
        });
    }

    /**
     * Pushes trophy template from parent event down to all child events.
     */
    public function pushToChildEvents(FestEvent $parentEvent): int
    {
        $children = FestEvent::where('parent_event_id', $parentEvent->id)->get();
        abort_if($children->isEmpty(), 422, 'This event has no child events to push to.');

        $count = 0;
        foreach ($children as $child) {
            $count += $this->copyFromParent($child);
        }

        return $count;
    }

    /**
     * Save or update a single trophy.
     */
    public function saveTrophy(FestEvent $event, array $data, ?int $id = null): FestTrophy
    {
        $attributes = [
            'trophy_no' => (int) $data['trophy_no'],
            'title' => (string) $data['title'],
            'trophy_type' => (string) $data['trophy_type'],
            'position' => (int) ($data['position'] ?? 1),
            'award_type' => (string) ($data['award_type'] ?? FestTrophy::AWARD_SCHOOL),
            'category_key' => filled($data['category_key'] ?? null) ? (string) $data['category_key'] : null,
            'item_id' => ! empty($data['item_id']) ? (int) $data['item_id'] : null,
            'item_name_pattern' => filled($data['item_name_pattern'] ?? null) ? (string) $data['item_name_pattern'] : null,
            'item_ids' => ! empty($data['item_ids']) ? (array) $data['item_ids'] : null,
            'item_group_name' => filled($data['item_group_name'] ?? null) ? (string) $data['item_group_name'] : null,
            'gender' => filled($data['gender'] ?? null) ? (string) $data['gender'] : null,
            'notes' => filled($data['notes'] ?? null) ? (string) $data['notes'] : null,
            'is_rolling' => (bool) ($data['is_rolling'] ?? false),
            'donor_name' => filled($data['donor_name'] ?? null) ? (string) $data['donor_name'] : null,
            'sort_order' => (int) ($data['sort_order'] ?? $data['trophy_no']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($id) {
            $trophy = FestTrophy::where('event_id', $event->id)->findOrFail($id);
            $trophy->update($attributes);
            return $trophy;
        }

        return FestTrophy::create(['event_id' => $event->id] + $attributes);
    }

    public function deleteTrophy(FestEvent $event, int $id): void
    {
        $trophy = FestTrophy::where('event_id', $event->id)->findOrFail($id);
        $trophy->delete();
    }

    /**
     * Exact 60 trophies definition from Kochi Metro Sahodaya Kalotsav sheet.
     *
     * @return list<array<string, mixed>>
     */
    private function kochiMetroPresetDefinitions(): array
    {
        return [
            ['trophy_no' => 1, 'title' => 'Ever-rolling trophies shall be awarded to the schools securing First position in overall points.', 'trophy_type' => 'overall', 'position' => 1, 'is_rolling' => true],
            ['trophy_no' => 2, 'title' => 'Ever-rolling trophies shall be awarded to the school securing Second position in overall points.', 'trophy_type' => 'overall', 'position' => 2, 'is_rolling' => true],
            ['trophy_no' => 3, 'title' => 'Ever-rolling trophies shall be awarded to the school securing Third position in overall points.', 'trophy_type' => 'overall', 'position' => 3, 'is_rolling' => true],
            ['trophy_no' => 4, 'title' => 'First position in Category-I', 'trophy_type' => 'category', 'category_key' => 'category_1', 'position' => 1],
            ['trophy_no' => 5, 'title' => 'Second position in Category-I', 'trophy_type' => 'category', 'category_key' => 'category_1', 'position' => 2],
            ['trophy_no' => 6, 'title' => 'Third position in Category-I', 'trophy_type' => 'category', 'category_key' => 'category_1', 'position' => 3],
            ['trophy_no' => 7, 'title' => 'First position in Category-II', 'trophy_type' => 'category', 'category_key' => 'category_2', 'position' => 1],
            ['trophy_no' => 8, 'title' => 'Second position in Category-II', 'trophy_type' => 'category', 'category_key' => 'category_2', 'position' => 2],
            ['trophy_no' => 9, 'title' => 'Third position in Category-II', 'trophy_type' => 'category', 'category_key' => 'category_2', 'position' => 3],
            ['trophy_no' => 10, 'title' => 'First position in Category-III', 'trophy_type' => 'category', 'category_key' => 'category_3', 'position' => 1],
            ['trophy_no' => 11, 'title' => 'Second position in Category-III', 'trophy_type' => 'category', 'category_key' => 'category_3', 'position' => 2],
            ['trophy_no' => 12, 'title' => 'Third position in Category-III', 'trophy_type' => 'category', 'category_key' => 'category_3', 'position' => 3],
            ['trophy_no' => 13, 'title' => 'First position in Category-IV', 'trophy_type' => 'category', 'category_key' => 'category_4', 'position' => 1],
            ['trophy_no' => 14, 'title' => 'Second position in Category-IV', 'trophy_type' => 'category', 'category_key' => 'category_4', 'position' => 2],
            ['trophy_no' => 15, 'title' => 'Third position in Category-IV', 'trophy_type' => 'category', 'category_key' => 'category_4', 'position' => 3],
            ['trophy_no' => 16, 'title' => 'First position in Group Dance Girls (Category II)', 'trophy_type' => 'item', 'item_name_pattern' => 'Group Dance', 'category_key' => 'category_2', 'position' => 1],
            ['trophy_no' => 17, 'title' => 'Second position in Group Dance Girls (Category II)', 'trophy_type' => 'item', 'item_name_pattern' => 'Group Dance', 'category_key' => 'category_2', 'position' => 2],
            ['trophy_no' => 18, 'title' => 'Third position in Group Dance Girls (Category II)', 'trophy_type' => 'item', 'item_name_pattern' => 'Group Dance', 'category_key' => 'category_2', 'position' => 3],
            ['trophy_no' => 19, 'title' => 'First position in Group Dance Girls (Category III)', 'trophy_type' => 'item', 'item_name_pattern' => 'Group Dance', 'category_key' => 'category_3', 'position' => 1],
            ['trophy_no' => 20, 'title' => 'Second position in Group Dance Girls (Category III)', 'trophy_type' => 'item', 'item_name_pattern' => 'Group Dance', 'category_key' => 'category_3', 'position' => 2],
            ['trophy_no' => 21, 'title' => 'Third position in Group Dance Girls (Category III)', 'trophy_type' => 'item', 'item_name_pattern' => 'Group Dance', 'category_key' => 'category_3', 'position' => 3],
            ['trophy_no' => 22, 'title' => 'First position in Music items', 'trophy_type' => 'item_group', 'item_group_name' => 'Music items', 'notes' => 'Music - (Classical Music (Karnatic), Light Music, Mappilappattu, Violin, Guitar, Flute, Mrudangam, Tabala)', 'position' => 1],
            ['trophy_no' => 23, 'title' => 'Second position in Music items', 'trophy_type' => 'item_group', 'item_group_name' => 'Music items', 'notes' => 'Music - (Classical Music (Karnatic), Light Music, Mappilappattu, Violin, Guitar, Flute, Mrudangam, Tabala)', 'position' => 2],
            ['trophy_no' => 24, 'title' => 'Third position in Music items', 'trophy_type' => 'item_group', 'item_group_name' => 'Music items', 'notes' => 'Music - (Classical Music (Karnatic), Light Music, Mappilappattu, Violin, Guitar, Flute, Mrudangam, Tabala)', 'position' => 3],
            ['trophy_no' => 25, 'title' => 'First position in One Act Play', 'trophy_type' => 'item', 'item_name_pattern' => 'One Act Play', 'position' => 1],
            ['trophy_no' => 26, 'title' => 'Second position in One Act Play', 'trophy_type' => 'item', 'item_name_pattern' => 'One Act Play', 'position' => 2],
            ['trophy_no' => 27, 'title' => 'Third Position in One Act Play', 'trophy_type' => 'item', 'item_name_pattern' => 'One Act Play', 'position' => 3],
            ['trophy_no' => 28, 'title' => 'First position in Mime', 'trophy_type' => 'item', 'item_name_pattern' => 'Mime', 'position' => 1],
            ['trophy_no' => 29, 'title' => 'Second position in Mime', 'trophy_type' => 'item', 'item_name_pattern' => 'Mime', 'position' => 2],
            ['trophy_no' => 30, 'title' => 'Third position in Mime', 'trophy_type' => 'item', 'item_name_pattern' => 'Mime', 'position' => 3],
            ['trophy_no' => 31, 'title' => 'First position in Group Song', 'trophy_type' => 'item', 'item_name_pattern' => 'Group Song', 'position' => 1],
            ['trophy_no' => 32, 'title' => 'Second position in Group Song', 'trophy_type' => 'item', 'item_name_pattern' => 'Group Song', 'position' => 2],
            ['trophy_no' => 33, 'title' => 'Third position in Group Song', 'trophy_type' => 'item', 'item_name_pattern' => 'Group Song', 'position' => 3],
            ['trophy_no' => 34, 'title' => 'First position in Patriotic Song', 'trophy_type' => 'item', 'item_name_pattern' => 'Patriotic Song', 'position' => 1],
            ['trophy_no' => 35, 'title' => 'Second position in Patriotic Song', 'trophy_type' => 'item', 'item_name_pattern' => 'Patriotic Song', 'position' => 2],
            ['trophy_no' => 36, 'title' => 'Third position in Patriotic Song', 'trophy_type' => 'item', 'item_name_pattern' => 'Patriotic Song', 'position' => 3],
            ['trophy_no' => 37, 'title' => 'First position in Western Music Concert', 'trophy_type' => 'item', 'item_name_pattern' => 'Western Music Concert', 'position' => 1],
            ['trophy_no' => 38, 'title' => 'Second position in Western Music Concert', 'trophy_type' => 'item', 'item_name_pattern' => 'Western Music Concert', 'position' => 2],
            ['trophy_no' => 39, 'title' => 'Third Position in Western Music Concert', 'trophy_type' => 'item', 'item_name_pattern' => 'Western Music Concert', 'position' => 3],
            ['trophy_no' => 40, 'title' => 'First position in Band Display', 'trophy_type' => 'item', 'item_name_pattern' => 'Band Display', 'position' => 1],
            ['trophy_no' => 41, 'title' => 'Second position in Band Display', 'trophy_type' => 'item', 'item_name_pattern' => 'Band Display', 'position' => 2],
            ['trophy_no' => 42, 'title' => 'Third Position in Band Display', 'trophy_type' => 'item', 'item_name_pattern' => 'Band Display', 'position' => 3],
            ['trophy_no' => 43, 'title' => 'First position in Oppana', 'trophy_type' => 'item', 'item_name_pattern' => 'Oppana', 'position' => 1],
            ['trophy_no' => 44, 'title' => 'Second position in Oppana', 'trophy_type' => 'item', 'item_name_pattern' => 'Oppana', 'position' => 2],
            ['trophy_no' => 45, 'title' => 'Third position in Oppana', 'trophy_type' => 'item', 'item_name_pattern' => 'Oppana', 'position' => 3],
            ['trophy_no' => 46, 'title' => 'First position in Margamkali', 'trophy_type' => 'item', 'item_name_pattern' => 'Margamkali', 'position' => 1],
            ['trophy_no' => 47, 'title' => 'Second position in Margamkali', 'trophy_type' => 'item', 'item_name_pattern' => 'Margamkali', 'position' => 2],
            ['trophy_no' => 48, 'title' => 'Third position in Margamkali', 'trophy_type' => 'item', 'item_name_pattern' => 'Margamkali', 'position' => 3],
            ['trophy_no' => 49, 'title' => 'First position in Kolkali', 'trophy_type' => 'item', 'item_name_pattern' => 'Kolkali', 'position' => 1],
            ['trophy_no' => 50, 'title' => 'Second position in Kolkali', 'trophy_type' => 'item', 'item_name_pattern' => 'Kolkali', 'position' => 2],
            ['trophy_no' => 51, 'title' => 'Third position in Kolkali', 'trophy_type' => 'item', 'item_name_pattern' => 'Kolkali', 'position' => 3],
            ['trophy_no' => 52, 'title' => 'First position in Duffmutt', 'trophy_type' => 'item', 'item_name_pattern' => 'Duffmutt', 'position' => 1],
            ['trophy_no' => 53, 'title' => 'Second position in Duffmutt', 'trophy_type' => 'item', 'item_name_pattern' => 'Duffmutt', 'position' => 2],
            ['trophy_no' => 54, 'title' => 'Third position in Duffmutt', 'trophy_type' => 'item', 'item_name_pattern' => 'Duffmutt', 'position' => 3],
            ['trophy_no' => 55, 'title' => 'First position in Thiruvathirakali', 'trophy_type' => 'item', 'item_name_pattern' => 'Thiruvathirakali', 'position' => 1],
            ['trophy_no' => 56, 'title' => 'Second Position in Thiruvathirakali', 'trophy_type' => 'item', 'item_name_pattern' => 'Thiruvathirakali', 'position' => 2],
            ['trophy_no' => 57, 'title' => 'Third position in Thiruvathirakali', 'trophy_type' => 'item', 'item_name_pattern' => 'Thiruvathirakali', 'position' => 3],
            ['trophy_no' => 58, 'title' => 'First position in Art items', 'trophy_type' => 'item_group', 'item_group_name' => 'Art items', 'notes' => 'Art items - (Pencil Drawing, Painting Crayon colour, Painting Water colour, Painting, Oil colour, Cartoon)', 'position' => 1],
            ['trophy_no' => 59, 'title' => 'Second position in Art items', 'trophy_type' => 'item_group', 'item_group_name' => 'Art items', 'notes' => 'Art items - (Pencil Drawing, Painting Crayon colour, Painting Water colour, Painting, Oil colour, Cartoon)', 'position' => 2],
            ['trophy_no' => 60, 'title' => 'Third Position in Art items', 'trophy_type' => 'item_group', 'item_group_name' => 'Art items', 'notes' => 'Art items - (Pencil Drawing, Painting Crayon colour, Painting Water colour, Painting, Oil colour, Cartoon)', 'position' => 3],
        ];
    }
}
