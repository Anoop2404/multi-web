<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;

class FestRegistrationRouterService
{
    public function __construct(
        private FestPartitionService $partitions,
        private FestSchoolPartitionService $schoolPartitions,
    ) {}

    /**
     * Resolve which event should store a registration for the given hub/item/school.
     */
    public function resolveTargetEvent(FestEvent $event, FestEventItem $item, string $schoolId): FestEvent
    {
        $hub = $this->resolveHub($event);

        if ($this->partitions->conductMode($hub) !== 'partitioned') {
            return $event;
        }

        if ($hub->id !== $event->id && $event->parent_event_id === $hub->id) {
            return $event;
        }

        $partitionKey = $this->schoolPartitions->requireAssignment($hub, $schoolId);
        $role = $this->targetPartitionRole($item);

        if ($role === 'finale') {
            $finale = $this->partitions->partitions($hub)
                ->first(fn (FestEvent $p) => $this->partitions->partitionRole($p) === 'finale');

            return $finale ?? $this->partitions->partitionByKey($hub, $partitionKey) ?? $hub;
        }

        $region = $this->partitions->partitionByKey($hub, $partitionKey);
        abort_if(! $region, 422, 'Assigned region partition is not configured.');

        return $region;
    }

    public function resolveHub(FestEvent $event): FestEvent
    {
        if ($event->parent_event_id) {
            return FestEvent::find($event->parent_event_id) ?? $event;
        }

        return $event;
    }

    public function isPartitionedHub(FestEvent $event): bool
    {
        return $this->partitions->isPartitionedHub($this->resolveHub($event));
    }

    public function schoolPartitionLabel(FestEvent $event, string $schoolId): ?string
    {
        $hub = $this->resolveHub($event);
        $key = $this->schoolPartitions->resolvePartitionKey($hub, $schoolId);

        return $key ? $this->partitions->partitionLabel($hub, $key) : null;
    }

    private function targetPartitionRole(FestEventItem $item): string
    {
        $criteria = $item->criteria_json ?? [];
        if (! empty($criteria['partition_roles'])) {
            $roles = (array) $criteria['partition_roles'];
            if (in_array('finale', $roles, true) || in_array('district', $roles, true)) {
                return 'finale';
            }
        }

        if (($item->stage_type ?? '') === 'on_stage') {
            return 'finale';
        }

        if (in_array($item->participant_type, ['group', 'team'], true)) {
            return 'finale';
        }

        return 'region';
    }
}
