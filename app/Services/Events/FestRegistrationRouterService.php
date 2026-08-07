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
            // Previously accepted ANY child of the hub verbatim here, with no check that
            // it's THIS school's own assigned partition — a request naming a sibling
            // region's child event id would register straight into that region unchecked
            // (see Phase 1 audit — "Reject direct hub and sibling-region ... requests").
            // Finale is exempt: it's the shared common round every assigned school routes
            // into regardless of which region they belong to, same as the fresh-resolve
            // path below.
            if ($this->partitions->partitionRole($event) !== 'finale') {
                $partitionKey = $this->schoolPartitions->requireAssignment($hub, $schoolId);
                $eventKey = $event->partition_key ?? $event->cluster_key;
                abort_unless($eventKey === $partitionKey, 403, 'This event belongs to a different region.');
            }

            return $event;
        }

        $partitionKey = $this->schoolPartitions->requireAssignment($hub, $schoolId);
        $role = $this->targetPartitionRole($hub, $item);

        if ($role === 'finale') {
            $finale = $this->partitions->partitions($hub)
                ->first(fn (FestEvent $p) => $this->partitions->partitionRole($p) === 'finale');

            return $finale ?? $this->partitions->partitionByKey($hub, $partitionKey) ?? $hub;
        }

        $region = $this->partitions->partitionByKey($hub, $partitionKey);
        abort_if(! $region, 422, 'Assigned region partition is not configured.');

        return $region;
    }

    /**
     * Assert that $schoolId may operate against $event directly — registration, food
     * ordering, roster/attendance viewing, payment, etc. For a partitioned hub's family
     * this means $event must be the school's own assigned region partition (or the
     * shared finale); never the hub itself, never a sibling region's child. No-op for
     * anything that isn't a partitioned hub (the vast majority of events), so this is
     * safe to add to any school-facing controller without changing behavior for
     * standard, non-regional events.
     *
     * This is the "one canonical regional event-context resolver" called for in the
     * Phase 1 plan — before this, registration had its own (partial) check inside
     * resolveTargetEvent() above, while food ordering (FestFoodOrderController) and
     * others had none at all.
     */
    public function assertSchoolCanAccess(FestEvent $event, string $schoolId): void
    {
        $hub = $this->resolveHub($event);

        if ($this->partitions->conductMode($hub) !== 'partitioned') {
            return;
        }

        abort_if($event->id === $hub->id, 422, 'Use your assigned region event, not the hub.');

        if ($this->partitions->partitionRole($event) === 'finale') {
            return;
        }

        $assignedKey = $this->schoolPartitions->requireAssignment($hub, $schoolId);
        $eventKey = $event->partition_key ?? $event->cluster_key;

        abort_unless($eventKey === $assignedKey, 403, 'This event belongs to a different region.');
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

    private function targetPartitionRole(FestEvent $hub, FestEventItem $item): string
    {
        $criteria = $item->criteria_json ?? [];
        if (! empty($criteria['partition_roles'])) {
            $roles = (array) $criteria['partition_roles'];

            return in_array('finale', $roles, true) || in_array('district', $roles, true)
                ? 'finale'
                : 'region';
        }

        // English Fest's regional topology conducts every item inside the school's
        // assigned region. The on-stage/group split below belongs to the Kalotsav
        // region + common-finale topology and made most English Fest items resolve
        // to a non-existent finale item.
        if (in_array($hub->event_type, ['sports', 'english_fest', 'science_fest', 'kids_fest', 'teacher_fest'], true)) {
            return 'region';
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
