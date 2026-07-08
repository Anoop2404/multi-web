<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventSchoolPartition;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class FestSchoolPartitionService
{
    public function __construct(
        private FestPartitionService $partitions,
    ) {}

    public function resolvePartitionKey(FestEvent $hub, string $schoolId): ?string
    {
        if ($this->partitions->conductMode($hub) !== 'partitioned') {
            return null;
        }

        $explicit = FestEventSchoolPartition::where('event_id', $hub->id)
            ->where('school_id', $schoolId)
            ->value('partition_key');

        if ($explicit) {
            return $explicit;
        }

        // Fall back to the school's membership region so Kalotsav routing works without
        // assigning partitions twice — as long as a matching partition child exists.
        if ($hub->event_type === 'kalolsavam') {
            $key = app(FestRegionPartitionService::class)->partitionKeyForSchool($hub, $schoolId);
            if ($key !== null && $this->partitions->partitionByKey($hub, $key)) {
                return $key;
            }
        }

        return null;
    }

    public function assign(FestEvent $hub, string $schoolId, string $partitionKey, ?int $assignedBy = null): FestEventSchoolPartition
    {
        abort_if($hub->parent_event_id, 422, 'Assign schools on the hub event.');
        abort_if($this->partitions->conductMode($hub) !== 'partitioned', 422, 'Event is not partitioned.');

        $partition = $this->partitions->partitionByKey($hub, $partitionKey);
        abort_if(! $partition, 422, "Unknown partition: {$partitionKey}");

        $school = Tenant::findOrFail($schoolId);
        abort_if($school->parent_id !== $hub->tenant_id, 403);

        return FestEventSchoolPartition::updateOrCreate(
            ['event_id' => $hub->id, 'school_id' => $schoolId],
            [
                'partition_key' => $partitionKey,
                'assigned_by'   => $assignedBy,
                'assigned_at'   => now(),
            ]
        );
    }

    /** @param array<string, string> $assignments school_id => partition_key */
    public function bulkAssign(FestEvent $hub, array $assignments, ?int $assignedBy = null): int
    {
        $count = 0;
        foreach ($assignments as $schoolId => $partitionKey) {
            $this->assign($hub, (string) $schoolId, (string) $partitionKey, $assignedBy);
            $count++;
        }

        return $count;
    }

    public function requireAssignment(FestEvent $hub, string $schoolId): string
    {
        $key = $this->resolvePartitionKey($hub, $schoolId);
        if (! $key) {
            throw ValidationException::withMessages([
                'partition' => 'Your school must be assigned to a region before registering for this event.',
            ]);
        }

        return $key;
    }

    /** @return array<string, string> */
    public function assignmentsForHub(FestEvent $hub): array
    {
        return FestEventSchoolPartition::where('event_id', $hub->id)
            ->pluck('partition_key', 'school_id')
            ->all();
    }
}
