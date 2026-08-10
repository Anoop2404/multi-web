<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestEventStaff;
use App\Models\FestMark;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\SchoolRegionAssignment;
use App\Models\Tenant;
use App\Support\AcademicYear;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only topology audit (docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md §8.1,
 * Phase 0). Never writes. Intended to run before any data-changing migration/repair
 * command, and periodically to catch drift.
 *
 * Detects:
 *  - standard events with partition children (G9)
 *  - partitioned parents with operational rows (registrations/marks/schedules) still on
 *    the parent itself instead of a region child (G2, §8.3)
 *  - region children missing/duplicate region_id (G2)
 *  - duplicate children for one region/role under a parent
 *  - parent reports that would mix regional preliminaries with finale/cluster rows (G4)
 *  - Sports roots already converted to a generic regional hub instead of using the
 *    sports_season/sports_discipline topology (G10)
 *  - phase definitions/assignments that differ between parent and children (G8)
 *  - school partition mappings that disagree with the active-year region assignment (G5)
 *  - staff assigned region_admin duty with no region_id (G1)
 *  - region admins assigned directly on a hub that currently exposes a Combined report
 *    (G1, G6)
 */
class FestAuditEventTopology extends Command
{
    protected $signature = 'fest:audit-event-topology
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--event= : Limit to one root fest_events id}
        {--format=table : table|json|csv}';

    protected $description = 'Read-only audit of region/phase/Sports event topology anomalies (remediation plan §8.1)';

    /** @var list<array<string, mixed>> */
    private array $findings = [];

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $format = $this->option('format');

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where(function ($inner) use ($sahodayaOpt) {
                    $inner->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
                });
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenants.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant, $eventOpt) {
                    $this->auditTenant($tenant, $eventOpt);
                });
            } catch (\Throwable $e) {
                $this->addFinding($tenant->id, null, 'audit.error', "Audit failed for tenant: {$e->getMessage()}");
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->output($format);

        return self::SUCCESS;
    }

    private function auditTenant(Tenant $tenant, null|string|int $eventOpt): void
    {
        $roots = FestEvent::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('parent_event_id')
            ->when($eventOpt, fn ($q) => $q->whereKey($eventOpt))
            ->get();

        foreach ($roots as $root) {
            $this->auditStandardWithChildren($tenant, $root);
            $this->auditOperationalRowsOnParent($tenant, $root);
            $this->auditRegionIdIntegrity($tenant, $root);
            $this->auditDuplicateChildren($tenant, $root);
            $this->auditMixedRolesInReports($tenant, $root);
            $this->auditSportsMisconfiguration($tenant, $root);
            $this->auditPhaseDrift($tenant, $root);
            $this->auditSchoolPartitionVsRegionAssignment($tenant, $root);
        }

        $this->auditRegionAdminStaffing($tenant);
    }

    private function auditStandardWithChildren(Tenant $tenant, FestEvent $root): void
    {
        $childCount = FestEvent::where('parent_event_id', $root->id)->count();

        if (($root->conduct_mode ?? 'standard') === 'standard' && $childCount > 0) {
            $this->addFinding($tenant->id, $root->id, 'standard_event_with_children',
                "Event '{$root->title}' is conduct_mode=standard but has {$childCount} child event(s). G9.");
        }
    }

    private function auditOperationalRowsOnParent(Tenant $tenant, FestEvent $root): void
    {
        if (($root->conduct_mode ?? 'standard') !== 'partitioned') {
            return;
        }

        $regRows = FestRegistration::where('event_id', $root->id)->count();
        if ($regRows > 0) {
            $this->addFinding($tenant->id, $root->id, 'operational_rows_on_partitioned_parent',
                "Partitioned parent '{$root->title}' still has {$regRows} registration(s) directly on the parent event_id. §8.3 repair required.");
        }

        if (Schema::hasTable('fest_schedules')) {
            $schedRows = FestSchedule::where('event_id', $root->id)->count();
            if ($schedRows > 0) {
                $this->addFinding($tenant->id, $root->id, 'operational_rows_on_partitioned_parent',
                    "Partitioned parent '{$root->title}' still has {$schedRows} schedule row(s) directly on the parent event_id.");
            }
        }

        if (Schema::hasTable('fest_marks')) {
            $markRows = FestMark::where('event_id', $root->id)->count();
            if ($markRows > 0) {
                $this->addFinding($tenant->id, $root->id, 'operational_rows_on_partitioned_parent',
                    "Partitioned parent '{$root->title}' still has {$markRows} mark row(s) directly on the parent event_id.");
            }
        }
    }

    private function auditRegionIdIntegrity(Tenant $tenant, FestEvent $root): void
    {
        $regionChildren = FestEvent::where('parent_event_id', $root->id)
            ->where('partition_role', 'region')
            ->get();

        foreach ($regionChildren as $child) {
            if ($child->region_id === null) {
                $this->addFinding($tenant->id, $root->id, 'region_child_missing_region_id',
                    "Region-partition child '{$child->title}' (#{$child->id}) under '{$root->title}' has no region_id. G2.");
            }
        }
    }

    private function auditDuplicateChildren(Tenant $tenant, FestEvent $root): void
    {
        $dupes = FestEvent::where('parent_event_id', $root->id)
            ->whereNotNull('region_id')
            ->selectRaw('region_id, partition_role, count(*) as c')
            ->groupBy('region_id', 'partition_role')
            ->having('c', '>', 1)
            ->get();

        foreach ($dupes as $dupe) {
            $this->addFinding($tenant->id, $root->id, 'duplicate_region_children',
                "Parent '{$root->title}' has {$dupe->c} children for region_id={$dupe->region_id} role={$dupe->partition_role}.");
        }
    }

    private function auditMixedRolesInReports(Tenant $tenant, FestEvent $root): void
    {
        $roles = FestEvent::where('parent_event_id', $root->id)
            ->whereNotNull('partition_role')
            ->distinct()
            ->pluck('partition_role');

        $hasRegion = $roles->contains('region');
        $hasFinaleOrCluster = $roles->contains('finale') || $roles->contains('cluster');

        if ($hasRegion && $hasFinaleOrCluster) {
            $this->addFinding($tenant->id, $root->id, 'mixed_partition_roles',
                "Parent '{$root->title}' has both region and finale/cluster children — reportableEventIds() combines them unless the caller filters by role. G4.");
        }
    }

    private function auditSportsMisconfiguration(Tenant $tenant, FestEvent $root): void
    {
        if ($root->event_type !== 'sports') {
            return;
        }

        $childRoles = FestEvent::where('parent_event_id', $root->id)
            ->pluck('partition_role')
            ->filter()
            ->unique();

        // A Sports root should only ever have sports_season (self-tag) or
        // sports_discipline children — never a bare 'region'/'finale'/'cluster' child
        // directly under the season, which would mean it was converted to a generic
        // regional hub instead of using the season -> sport -> region nested topology
        // (G10, plan §7).
        if ($childRoles->intersect(['region', 'finale', 'cluster'])->isNotEmpty()) {
            $this->addFinding($tenant->id, $root->id, 'sports_root_generic_regional_hub',
                "Sports season '{$root->title}' has generic region/finale/cluster children directly under it instead of sports_discipline children. G10 — needs the nested Sports topology in Phase 7, not generic region partitioning.");
        }
    }

    private function auditPhaseDrift(Tenant $tenant, FestEvent $root): void
    {
        if (! Schema::hasTable('fest_event_phases')) {
            return;
        }

        $parentPhaseCodes = FestEventPhase::where('event_id', $root->id)->pluck('code')->filter()->unique()->sort()->values();

        $children = FestEvent::where('parent_event_id', $root->id)->where('partition_role', 'region')->get();
        foreach ($children as $child) {
            $childPhaseCodes = FestEventPhase::where('event_id', $child->id)->pluck('code')->filter()->unique()->sort()->values();

            if ($parentPhaseCodes->isNotEmpty() && $childPhaseCodes->all() !== $parentPhaseCodes->all()) {
                $childList = $childPhaseCodes->implode(', ');
                $parentList = $parentPhaseCodes->implode(', ');
                $this->addFinding($tenant->id, $root->id, 'phase_drift',
                    "Region child '{$child->title}' (#{$child->id}) phase codes [{$childList}] differ from parent [{$parentList}]. G8.");
            }

        }
    }

    private function auditSchoolPartitionVsRegionAssignment(Tenant $tenant, FestEvent $root): void
    {
        if (! Schema::hasTable('fest_event_school_partitions')) {
            return;
        }

        $year = AcademicYear::forSahodaya($tenant->id);

        $partitions = \App\Models\FestEventSchoolPartition::where('event_id', $root->id)->get();
        if ($partitions->isEmpty()) {
            return;
        }

        $regionChildrenByKey = FestEvent::where('parent_event_id', $root->id)
            ->where('partition_role', 'region')
            ->get()
            ->keyBy('partition_key');

        $activeAssignments = SchoolRegionAssignment::forTenant($tenant->id)
            ->forYear($year)
            ->get()
            ->keyBy('school_id');

        $mismatches = 0;
        foreach ($partitions as $partition) {
            $expectedChild = $regionChildrenByKey->get($partition->partition_key);
            $assignment = $activeAssignments->get($partition->school_id);

            if ($expectedChild && $assignment && (int) $assignment->region_id !== (int) $expectedChild->region_id) {
                $mismatches++;
            }
        }

        if ($mismatches > 0) {
            $this->addFinding($tenant->id, $root->id, 'school_partition_vs_active_region_mismatch',
                "{$mismatches} school partition row(s) under '{$root->title}' disagree with the active-year ({$year}) SchoolRegionAssignment. G5.");
        }
    }

    private function auditRegionAdminStaffing(Tenant $tenant): void
    {
        $staffRows = FestEventStaff::where('duty', 'region_admin')->get();

        foreach ($staffRows as $staff) {
            if ($staff->region_id === null) {
                $this->addFinding($tenant->id, $staff->event_id, 'region_admin_missing_region',
                    "FestEventStaff #{$staff->id} (user #{$staff->user_id}) has duty=region_admin on event #{$staff->event_id} with no region_id. G1 — was blocked by the Phase 1 middleware fix, but the row itself should still be corrected or removed.");

                continue;
            }

            $event = FestEvent::find($staff->event_id);
            if ($event && $event->parent_event_id === null && ($event->conduct_mode ?? 'standard') === 'partitioned') {
                $this->addFinding($tenant->id, $staff->event_id, 'region_admin_on_combined_hub',
                    "FestEventStaff #{$staff->id} is a region_admin assigned directly on partitioned hub #{$event->id} ('{$event->title}'). Confirm every report reachable from that hub route is scope-narrowed (Phase 2/3), not just Registration Register.");
            }
        }
    }

    private function addFinding(string $tenantId, ?int $eventId, string $type, string $message): void
    {
        $this->findings[] = [
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'type' => $type,
            'message' => $message,
        ];
    }

    private function output(string $format): void
    {
        if ($this->findings === []) {
            $this->info('No topology anomalies found.');

            return;
        }

        switch ($format) {
            case 'json':
                $this->line(json_encode($this->findings, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->line('tenant_id,event_id,type,message');
                foreach ($this->findings as $f) {
                    $this->line(sprintf('%s,%s,%s,"%s"', $f['tenant_id'], $f['event_id'] ?? '', $f['type'], str_replace('"', '""', $f['message'])));
                }
                break;
            default:
                $this->table(['Tenant', 'Event', 'Type', 'Message'], array_map(
                    fn ($f) => [$f['tenant_id'], $f['event_id'] ?? '—', $f['type'], $f['message']],
                    $this->findings,
                ));
        }

        $this->warn(count($this->findings).' anomaly(ies) found. Run with --format=json for scripting.');
    }
}
