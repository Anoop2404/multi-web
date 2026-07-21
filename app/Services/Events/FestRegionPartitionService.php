<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventSchoolPartition;
use App\Models\Region;
use App\Models\SchoolRegionAssignment;
use App\Models\Tenant;
use App\Support\AcademicYear;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bridges membership-level Kalotsav regions (regions + school_region_assignments)
 * to per-event fest partitions (fest_event_school_partitions + child events), so
 * admins assign a school to a region once and Kalotsav registration routes correctly.
 */
class FestRegionPartitionService
{
    /** @var array<string, bool> request-scoped cache: regions configured per Sahodaya */
    private static array $regionsApplyCache = [];

    /** @var array<string, ?Region> request-scoped cache: school → region (keyed sahodaya:school) */
    private static array $schoolRegionCache = [];

    public function __construct(
        private FestPartitionService $partitions,
    ) {}

    /** Regions are configured (active) for this Sahodaya. Memoized per request. */
    public function regionsApply(string $sahodayaId): bool
    {
        return self::$regionsApplyCache[$sahodayaId] ??=
            Region::forTenant($sahodayaId)->active()->exists();
    }

    /** The membership region a school belongs to for the active year, or null. Memoized per request. */
    public function schoolRegion(string $sahodayaId, string $schoolId): ?Region
    {
        $key = $sahodayaId.':'.$schoolId;
        if (array_key_exists($key, self::$schoolRegionCache)) {
            return self::$schoolRegionCache[$key];
        }

        $year = AcademicYear::forSahodaya($sahodayaId);

        $assignment = SchoolRegionAssignment::forTenant($sahodayaId)
            ->forYear($year)
            ->where('school_id', $schoolId)
            ->with('region')
            ->first();

        return self::$schoolRegionCache[$key] = $assignment?->region;
    }

    /** Clear memo caches (call after mutating region assignments in a long-running process). */
    public static function flushCache(): void
    {
        self::$regionsApplyCache = [];
        self::$schoolRegionCache = [];
    }

    /** Partition key derived from a school's membership region (slug of region code). */
    public function partitionKeyForSchool(FestEvent $hub, string $schoolId): ?string
    {
        $region = $this->schoolRegion($hub->tenant_id, $schoolId);

        return $region ? $this->partitionKeyForRegion($region) : null;
    }

    public function partitionKeyForRegion(Region $region): string
    {
        return Str::slug($region->code ?: $region->name);
    }

    /**
     * Block Kalotsav registration until a school has picked/been assigned a region,
     * but only when the Sahodaya actually runs Kalotsav by region.
     */
    public function assertRegionSelected(FestEvent $event, Tenant $school): void
    {
        if (($event->conduct_mode ?? 'standard') !== 'partitioned' && $event->event_type !== 'kalolsavam') {
            return;
        }

        $sahodayaId = $event->tenant_id;
        if (! $this->regionsApply($sahodayaId)) {
            return;
        }

        if ($this->schoolRegion($sahodayaId, $school->id) === null) {
            throw ValidationException::withMessages([
                'region' => 'Select your region before registering. Set it in annual registration, or ask your Sahodaya to assign it.',
            ]);
        }
    }

    /**
     * Ensure a partition child event exists per membership region and (re)assign every
     * school to its region's partition. Returns a summary for the admin.
     *
     * @return array{partitions_created: int, schools_assigned: int}
     */
    public function syncPartitionsFromRegions(FestEvent $hub): array
    {
        abort_if($hub->parent_event_id, 422, 'Sync regions on the hub event, not a partition.');

        $regions = Region::forTenant($hub->tenant_id)->active()->orderBy('sort_order')->orderBy('name')->get();
        abort_if($regions->isEmpty(), 422, 'No active regions configured for this Sahodaya.');

        if (($hub->conduct_mode ?? 'standard') !== 'partitioned') {
            $hub->update(['conduct_mode' => 'partitioned']);
        }

        $created = 0;
        $keyByRegionId = [];
        foreach ($regions as $region) {
            $key = $this->partitionKeyForRegion($region);
            $keyByRegionId[$region->id] = $key;

            if (! $this->partitions->partitionByKey($hub, $key)) {
                $this->partitions->spawnPartition($hub, [
                    'title'          => $region->name,
                    'partition_key'  => $key,
                    'cluster_label'  => $region->name,
                    'partition_role' => 'region',
                ]);
                $created++;
            }
        }

        $year = AcademicYear::forSahodaya($hub->tenant_id);
        $assignments = SchoolRegionAssignment::forTenant($hub->tenant_id)
            ->forYear($year)
            ->get(['school_id', 'region_id']);

        $assigned = 0;
        foreach ($assignments as $assignment) {
            $key = $keyByRegionId[$assignment->region_id] ?? null;
            if ($key === null) {
                continue;
            }

            FestEventSchoolPartition::updateOrCreate(
                ['event_id' => $hub->id, 'school_id' => $assignment->school_id],
                ['partition_key' => $key, 'assigned_at' => now()],
            );
            $assigned++;
        }

        return ['partitions_created' => $created, 'schools_assigned' => $assigned];
    }
}
