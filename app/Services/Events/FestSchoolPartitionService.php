<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventSchoolPartition;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FestSchoolPartitionService
{
    public function __construct(
        private FestPartitionService $partitions,
    ) {}

    /**
     * Filter a set of possibly-mixed hub + partition-child FestEvent rows down to what a
     * school should actually see: never the hub itself once it has configured partitions
     * (nothing to register there — see FestRegistrationController::
     * redirectHubToSchoolPartition()), and only the school's own assigned region among any
     * partition children, never a sibling region's. Without this, every school sees the
     * empty hub AND every region as separate, independently-billable "open event" entries
     * (school dashboard, registration listing, nav) instead of just their own.
     *
     * Falls back to returning $events unfiltered if filtering would leave nothing (e.g. no
     * region assigned to this school yet), so existing "no events" / region-required
     * messaging still has something to work with rather than a silently empty list. A no-op
     * for events with no partitions at all — the overwhelming majority of fest events.
     *
     * @param  Collection<int, FestEvent>  $events
     * @return Collection<int, FestEvent>
     */
    public function filterVisibleToSchool(Collection $events, string $schoolId): Collection
    {
        $resolvedChildIdByHub = [];

        return $events->reject(function (FestEvent $event) use ($schoolId, &$resolvedChildIdByHub) {
            // Never show the main hub event itself to schools when it is partitioned
            if ($this->partitions->isPartitionedHub($event)) {
                return true;
            }

            // Reject any orphaned child events whose source phase was deleted
            if ($event->source_phase_id && ! \App\Models\FestEventPhase::where('id', $event->source_phase_id)->exists()) {
                return true;
            }

            // For partition or phased regional child events, only keep the child event assigned to this school's region
            if ($event->parent_event_id) {
                $hub = $event->parentEvent ?: FestEvent::find($event->parent_event_id);

                // Handle Phased Regional Billing workflow
                if ($hub && $hub->usesPhasedRegionalBilling()) {
                    $sourcePhase = $event->sourcePhase;
                    if ($sourcePhase) {
                        if ($sourcePhase->isRegional()) {
                            $selection = app(FestSchoolPhaseRegionService::class)->resolve($hub, $sourcePhase, $schoolId);
                            if ($selection && $selection->region_id) {
                                return (int) $event->region_id !== (int) $selection->region_id;
                            }

                            // If no region selected for this regional phase yet, keep 1 representative child event
                            static $seenUnselectedPhases = [];
                            $phaseGroupKey = $hub->id . ':phased_region:' . $sourcePhase->id;
                            if (in_array($phaseGroupKey, $seenUnselectedPhases, true)) {
                                return true;
                            }
                            $seenUnselectedPhases[] = $phaseGroupKey;

                            return false;
                        }

                        // For a non-regional phase under a phased event, every school sees this phase's leaf event
                        return false;
                    }
                }

                if ($this->partitions->partitionKey($event) !== null) {
                    $hubId = $event->parent_event_id;

                    if (! array_key_exists($hubId, $resolvedChildIdByHub)) {
                        $key = $hub ? $this->resolvePartitionKey($hub, $schoolId) : null;
                        $child = ($hub && $key) ? $this->partitions->partitionByKey($hub, $key) : null;
                        $resolvedChildIdByHub[$hubId] = $child?->id ?? false;
                    }

                    // If school belongs to a resolved region child, reject all sibling region children
                    if ($resolvedChildIdByHub[$hubId] !== false) {
                        return $resolvedChildIdByHub[$hubId] !== $event->id;
                    }

                    // If no specific region key resolved for this school yet, keep only 1 representative child event
                    // per (parent_event_id, source_phase_id) to avoid flooding the table with duplicate region clones
                    static $seenUnassignedGroups = [];
                    $groupKey = $hubId . ':' . ($event->source_phase_id ?? 'default');
                    if (in_array($groupKey, $seenUnassignedGroups, true)) {
                        return true;
                    }
                    $seenUnassignedGroups[] = $groupKey;

                    return false;
                }
            }

            return false;
        })->values();
    }

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

        // Check the school's membership region for any partitioned hub event
        $key = app(FestRegionPartitionService::class)->partitionKeyForSchool($hub, $schoolId);
        if ($key !== null && $this->partitions->partitionByKey($hub, $key)) {
            return $key;
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
