<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\Tenant;
use App\Support\FestClassGroupScheme;
use App\Support\FestSportsAgeGroup;
use Illuminate\Support\Collection;

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

        $eventIds = $scope['event_id']
            ? [(int) $scope['event_id']]
            : $this->overallEventIds($root);

        return $scope + ['event_ids' => $eventIds];
    }

    /** @return list<int> */
    private function overallEventIds(FestEvent $root): array
    {
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
            return FestSportsAgeGroup::labels($root->tenant_id)[$category] ?? strtoupper($category);
        }

        return FestClassGroupScheme::labels(null, $root)[$category]
            ?? config("fest_item_taxonomy.class_group.{$category}", strtoupper($category));
    }

    /** @return list<array{school_id: string, school_name: string, total_points: int, rank: int}> */
    public function scoreboard(FestEvent $event, array $scope, ?string $category = null): array
    {
        $root = $this->rootEvent($event);

        if (! $category) {
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

        $marks = FestMark::whereIn('event_id', $scope['event_ids'])
            ->with(['participant.registration.item', 'item'])
            ->whereHas('item', function ($query) use ($root, $category) {
                $column = $root->event_type === 'sports' ? 'age_group' : 'class_group';
                $query->where($column, $category);
            })
            ->get();

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
