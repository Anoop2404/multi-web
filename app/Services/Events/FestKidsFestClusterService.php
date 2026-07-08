<?php

namespace App\Services\Events;

use App\Models\FestEvent;

class FestKidsFestClusterService
{
    public function __construct(
        private FestPartitionService $partitions,
    ) {}

    public function isUmbrella(FestEvent $event): bool
    {
        if ($event->event_type !== 'kids_fest' || $event->parent_event_id) {
            return false;
        }

        return $this->partitions->isPartitionedHub($event);
    }

    /** @return \Illuminate\Support\Collection<int, FestEvent> */
    public function clusters(FestEvent $umbrella): \Illuminate\Support\Collection
    {
        return $this->partitions->partitions($umbrella);
    }

    public function spawnCluster(FestEvent $umbrella, array $data): FestEvent
    {
        return $this->partitions->spawnCluster($umbrella, $data);
    }

    /** @return list<array{school_id: string, school_name: string, total_points: int, rank: int}> */
    public function combinedScoreboard(FestEvent $umbrella): array
    {
        return $this->partitions->combinedScoreboard($umbrella);
    }
}
