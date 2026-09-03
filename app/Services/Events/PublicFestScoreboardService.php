<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\Tenant;
use App\Support\FestClassGroupScheme;
use App\Support\FestSportsAgeGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the public school scoreboard for every supported event topology.
 *
 * Public controllers use a root event plus a topology-neutral scope. A standard
 * event has only "overall"; a partitioned event adds one scope for each Region,
 * Cluster, Phase, or Finale child. Category is an independent filter, so links
 * such as Region A + HS work without a second scoreboard implementation.
 */
class PublicFestScoreboardService
{
    public function __construct(
        private FestPartitionService $partitions,
        private FestGradePointService $gradePoints,
    ) {}

    public function rootEvent(FestEvent $event): FestEvent
    {
        $isPartitionChild = $event->parent_event_id
            && (
                $event->partition_key
                || $event->cluster_key
                || in_array($event->partition_role, ['region', 'cluster', 'finale', 'phase'], true)
            );

        if (! $isPartitionChild) {
            return $event;
        }

        return FestEvent::where('tenant_id', $event->tenant_id)
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->findOrFail($event->parent_event_id);
    }

    /** @return list<array{key: string, label: string, role: string, event_id: int|null, results_published: bool, schedule_published: bool}> */
    public function scopes(FestEvent $event): array
    {
        $root = $this->rootEvent($event);
        $config = $this->partitions->aggregationConfig($root);
        $overallLabel = (string) ($config['overall_label'] ?? 'Overall');

        $scopes = [[
            'key' => 'overall',
            'label' => $overallLabel,
            'role' => 'overall',
            'event_id' => null,
            'results_published' => $this->overallIsPublished($root),
            'schedule_published' => $this->overallScheduleIsPublished($root),
        ]];

        if ($root->usesPhasedRegionalBilling()) {
            foreach ($root->phases()->with(['allowedRegions.region'])->get() as $phase) {
                $leaves = FestEvent::where('parent_event_id', $root->id)
                    ->where('source_phase_id', $phase->id)
                    ->with('region:id,name,code')
                    ->get();
                $scopes[] = [
                    'key' => 'phase:'.$phase->id,
                    'label' => $phase->name.' — Combined',
                    'role' => 'phase',
                    'event_id' => null,
                    'event_ids' => $leaves->where('results_published', true)->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    'source_phase_id' => $phase->id,
                    'region_id' => null,
                    'results_published' => (bool) $phase->results_published,
                    'schedule_published' => (bool) $phase->schedule_published,
                ];

                foreach ($leaves->whereNotNull('region_id') as $leaf) {
                    $scopes[] = [
                        'key' => 'phase:'.$phase->id.':region:'.$leaf->region_id,
                        'label' => $phase->name.' — '.($leaf->region?->name ?? $leaf->cluster_label),
                        'role' => 'region',
                        'event_id' => (int) $leaf->id,
                        'event_ids' => [(int) $leaf->id],
                        'source_phase_id' => $phase->id,
                        'region_id' => (int) $leaf->region_id,
                        'results_published' => (bool) $leaf->results_published,
                        'schedule_published' => (bool) $leaf->schedule_published,
                    ];
                }
            }

            return $scopes;
        }

        foreach ($this->partitions->partitions($root) as $partition) {
            $key = $this->partitions->partitionKey($partition);
            if (! $key) {
                continue;
            }

            $scopes[] = [
                'key' => 'partition:'.$key,
                'label' => $this->partitions->partitionLabel($root, $key),
                'role' => $this->partitions->partitionRole($partition) ?? 'partition',
                'event_id' => (int) $partition->id,
                'results_published' => (bool) $partition->results_published,
                'schedule_published' => (bool) $partition->schedule_published,
            ];
        }

        return $scopes;
    }

    /**
     * @return array{key: string, label: string, role: string, event_id: int|null, results_published: bool, schedule_published: bool, event_ids: list<int>}
     */
    public function resolveScope(FestEvent $event, ?string $requestedScope = null, ?string $legacyCluster = null): array
    {
        $root = $this->rootEvent($event);

        if (! $requestedScope && $legacyCluster) {
            $requestedScope = $legacyCluster === 'combined'
                ? 'overall'
                : 'partition:'.$legacyCluster;
        }

        if (! $requestedScope && $event->parent_event_id) {
            $key = $this->partitions->partitionKey($event);
            $requestedScope = $key ? 'partition:'.$key : 'overall';
        }

        $requestedScope ??= 'overall';
        $scope = collect($this->scopes($root))->firstWhere('key', $requestedScope);
        abort_unless($scope, 404);

        $eventIds = isset($scope['event_ids'])
            ? $scope['event_ids']
            : ($scope['event_id']
            ? [(int) $scope['event_id']]
            : $this->overallEventIds($root));

        return $scope + ['event_ids' => $eventIds];
    }

    /** @return list<int> */
    private function overallEventIds(FestEvent $root): array
    {
        if ($root->usesPhasedRegionalBilling()) {
            $publishedPhaseIds = $root->phases()->where('results_published', true)->pluck('id');

            return FestEvent::where('parent_event_id', $root->id)
                ->whereIn('source_phase_id', $publishedPhaseIds)
                ->where('results_published', true)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (! $this->partitions->isPartitionedHub($root)) {
            return [(int) $root->id];
        }

        if (! $this->partitions->shouldCombineAtFinale($root)) {
            return [(int) $root->id];
        }

        $includeRoles = $this->partitions->aggregationConfig($root)['include_roles']
            ?? ['region', 'finale', 'cluster'];

        return $this->partitions->partitions($root)
            ->filter(fn (FestEvent $partition) => in_array(
                $this->partitions->partitionRole($partition) ?? 'partition',
                $includeRoles,
                true,
            ))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function overallIsPublished(FestEvent $root): bool
    {
        if ($root->usesPhasedRegionalBilling()) {
            return $root->phases()->where('results_published', true)->exists();
        }

        if (! $root->results_published) {
            return false;
        }

        if (! $this->partitions->isPartitionedHub($root) || ! $this->partitions->shouldCombineAtFinale($root)) {
            return true;
        }

        $includedIds = $this->overallEventIds($root);
        if ($includedIds === []) {
            return false;
        }

        return ! FestEvent::whereIn('id', $includedIds)
            ->where('results_published', false)
            ->exists();
    }

    private function overallScheduleIsPublished(FestEvent $root): bool
    {
        if ($root->usesPhasedRegionalBilling()) {
            return $root->phases()->where('schedule_published', true)->exists();
        }

        if (! $root->schedule_published) {
            return false;
        }

        if (! $this->partitions->isPartitionedHub($root) || ! $this->partitions->shouldCombineAtFinale($root)) {
            return true;
        }

        $includedIds = $this->overallEventIds($root);
        if ($includedIds === []) {
            return false;
        }

        return ! FestEvent::whereIn('id', $includedIds)
            ->where('schedule_published', false)
            ->exists();
    }

    /** @return list<string> */
    public function categories(FestEvent $event, array $scope): array
    {
        $root = $this->rootEvent($event);
        $column = $root->event_type === 'sports' ? 'age_group' : 'class_group';

        return FestEventItem::whereIn('event_id', $scope['event_ids'])
            ->where('is_enabled', true)
            ->whereNotNull($column)
            ->where($column, '!=', 'open')
            ->distinct()
            ->pluck($column)
            ->filter()
            ->sort()
            ->values()
            ->all();
    }

    public function categoryLabel(FestEvent $event, string $category): string
    {
        $root = $this->rootEvent($event);

        if ($root->event_type === 'sports') {
            return FestSportsAgeGroup::labels($root->tenant_id)[$category] ?? self::humanizeCategoryKey($category);
        }

        return FestClassGroupScheme::resolveItemLabel(FestClassGroupScheme::labels(null, $root), $category);
    }

    /**
     * Last-resort label when an item's class_group/category key isn't recognized by
     * the event's configured scheme (e.g. items tagged "category_1" on an event whose
     * scheme setting resolves to a preset keyed "lp"/"up"/"hs"/"hss" — a real scheme/item
     * mismatch that belongs to whoever configures that event, not something guessable
     * here). "Category 1" instead of a raw "CATEGORY_1" slug at least stays presentable
     * on the public pages while that mismatch exists.
     */
    private static function humanizeCategoryKey(string $category): string
    {
        return Str::of($category)->replace('_', ' ')->title()->toString();
    }

    /** @return list<array{school_id: string, school_name: string, total_points: int, rank: int}> */
    public function scoreboard(FestEvent $event, array $scope, ?string $category = null): array
    {
        $root = $this->rootEvent($event);

        if (! $category) {
            if ($root->usesPhasedRegionalBilling()) {
                if (($scope['role'] ?? null) === 'phase' && ! empty($scope['source_phase_id'])) {
                    $phase = $root->phases()->findOrFail($scope['source_phase_id']);

                    return app(FestPhaseScoreboardService::class)->phaseScoreboard($phase);
                }
                if (($scope['role'] ?? null) === 'overall') {
                    return app(FestPhaseScoreboardService::class)->cumulativeOverall($root);
                }
            }

            if ($scope['event_id']) {
                $partition = FestEvent::where('tenant_id', $root->tenant_id)
                    ->findOrFail($scope['event_id']);

                return EventContext::for($partition)->scoreboardBySchoolForEvent();
            }

            return EventContext::for($root)->scoreboardBySchool();
        }

        $allowedCategories = $this->categories($root, $scope);
        abort_unless(in_array($category, $allowedCategories, true), 404);

        $eventMap = FestEvent::where('tenant_id', $root->tenant_id)
            ->whereIn('id', $scope['event_ids'])
            ->get()
            ->keyBy('id');

        // Pair/group items save one FestMark per teammate (same registration_id,
        // same position/score) — count each registration once, not once per teammate.
        $marks = FestMark::whereIn('event_id', $scope['event_ids'])
            ->with(['participant.registration.item', 'item'])
            ->whereHas('item', function ($query) use ($root, $category) {
                $column = $root->event_type === 'sports' ? 'age_group' : 'class_group';
                $query->where($column, $category);
                // Same rule provisionalScoreboard() below already enforces, and that
                // $marks in FestPortalController::results()/itemResults() enforce for
                // the item-wise/individual-item pages — an item only counts once ITS
                // OWN results have been published, and never again once explicitly
                // hidden, regardless of the whole event's own results_published flag.
                // This branch was missing both checks entirely, so a category's public
                // standings/toppers showed every item's marks the moment the event got
                // published, including items nobody had published or that were later
                // unpublished — live production symptom: "Item-wise" tab correctly
                // showing 0 published items while Category-wise/Toppers showed real
                // school point totals for that same event.
                $query->whereNotNull('results_published_at')->where('results_hidden', false);
            })
            ->get()
            ->unique(fn (FestMark $m) => $m->deduplicationKey());

        $totals = [];
        foreach ($marks as $mark) {
            $participant = $mark->participant;
            $schoolId = $participant?->registration?->school_id;
            $scopeEvent = $eventMap->get($mark->event_id);

            if (! $schoolId || ! $scopeEvent || $participant->disqualified_at) {
                continue;
            }

            $totals[$schoolId] = ($totals[$schoolId] ?? 0)
                + $this->gradePoints->pointsForMark($scopeEvent, $mark);
        }

        return $this->rankTotals($totals);
    }

    /**
     * Live standings computed only from items that have individually published their
     * results (item.results_published_at set) — for use before the whole-event
     * official publish action has run. scoreboard()'s overall branch reads a
     * FestResult snapshot that's only written AT that publish action, so there's
     * nothing to read yet; this recomputes from FestMark every call instead,
     * deliberately not cached, since which items are published keeps changing
     * during a live event. Category and overall share one implementation here
     * (scoreboard() needs two, since the official overall path is snapshot-based
     * but its category path already computes live).
     *
     * @return list<array{school_id: string, school_name: string, total_points: int, rank: int}>
     */
    public function provisionalScoreboard(FestEvent $event, array $scope, ?string $category = null): array
    {
        $root = $this->rootEvent($event);
        $categoryColumn = $root->event_type === 'sports' ? 'age_group' : 'class_group';

        $marks = FestMark::whereIn('event_id', $scope['event_ids'])
            ->whereHas('item', function ($query) use ($category, $categoryColumn) {
                $query->whereNotNull('results_published_at');
                if ($category) {
                    $query->where($categoryColumn, $category);
                }
            })
            ->with(['participant.registration.item', 'item'])
            ->get()
            ->unique(fn (FestMark $m) => $m->deduplicationKey());

        $totals = [];
        foreach ($marks as $mark) {
            $participant = $mark->participant;
            $schoolId = $participant?->registration?->school_id;

            if (! $schoolId || $participant->disqualified_at) {
                continue;
            }

            $totals[$schoolId] = ($totals[$schoolId] ?? 0) + $this->gradePoints->pointsForMark($event, $mark);
        }

        return $this->rankTotals($totals);
    }

    /**
     * @param  array<string, int|float>  $totals
     * @return list<array{school_id: string, school_name: string, total_points: int, rank: int}>
     */
    private function rankTotals(array $totals): array
    {
        if ($totals === []) {
            return [];
        }

        $schools = Tenant::whereIn('id', array_keys($totals))
            ->get(['id', 'name'])
            ->keyBy('id');

        $sorted = collect($totals)
            ->map(fn ($points, $schoolId) => [
                'school_id' => (string) $schoolId,
                'school_name' => $schools->get($schoolId)?->name ?? (string) $schoolId,
                'total_points' => (int) $points,
            ])
            ->sort(function (array $a, array $b) {
                $points = $b['total_points'] <=> $a['total_points'];

                return $points !== 0
                    ? $points
                    : [$a['school_name'], $a['school_id']] <=> [$b['school_name'], $b['school_id']];
            })
            ->values();

        return $this->applyCompetitionRanks($sorted)->all();
    }

    /** @return Collection<int, array{school_id: string, school_name: string, total_points: int, rank: int}> */
    private function applyCompetitionRanks(Collection $rows): Collection
    {
        $previousPoints = null;
        $rank = 0;

        return $rows->map(function (array $row, int $index) use (&$previousPoints, &$rank) {
            if ($previousPoints === null || $row['total_points'] < $previousPoints) {
                $rank = $index + 1;
            }

            $previousPoints = $row['total_points'];
            $row['rank'] = $rank;

            return $row;
        });
    }
}
