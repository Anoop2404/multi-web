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
    public function regionsApply(?string $sahodayaId): bool
    {
        if (! $sahodayaId) {
            return false;
        }

        return self::$regionsApplyCache[$sahodayaId] ??=
            Region::forTenant($sahodayaId)->active()->exists();
    }

    /** The membership region a school belongs to for the active year, or null. Memoized per request. */
    public function schoolRegion(?string $sahodayaId, string $schoolId): ?Region
    {
        if (! $sahodayaId) {
            $sahodayaId = Tenant::find($schoolId)?->parent_id;
            if (! $sahodayaId) {
                return null;
            }
        }

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
        $sahodayaId = $hub->tenant_id ?: Tenant::find($schoolId)?->parent_id;
        if (! $sahodayaId) {
            return null;
        }

        $region = $this->schoolRegion($sahodayaId, $schoolId);

        return $region ? $this->partitionKeyForRegion($region) : null;
    }

    public function partitionKeyForRegion(Region $region): string
    {
        return Str::slug($region->code ?: $region->name);
    }

    /** Block regional registration until the school has a membership region. */
    public function assertRegionSelected(FestEvent $event, Tenant $school): void
    {
        $hub = $event->parent_event_id
            ? FestEvent::find($event->parent_event_id) ?? $event
            : $event;

        if ($this->partitions->conductMode($hub) !== 'partitioned') {
            return;
        }

        $sahodayaId = $hub->tenant_id;
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
     * Convenience entry-point to auto-sync regional partitions for an event whenever
     * the event is created or published. Safe to call at any time — a no-op when
     * regions don't apply or the event is not partitioned. Swallows exceptions so
     * it never blocks the calling action.
     */
    public function autoSyncIfApplicable(FestEvent $event): void
    {
        $hub = $event->parent_event_id
            ? FestEvent::find($event->parent_event_id) ?? $event
            : $event;

        if (! $this->regionsApply($hub->tenant_id)) {
            return;
        }

        try {
            if ($this->partitions->conductMode($hub) !== 'partitioned') {
                // Auto-enable partitioned mode for region-applicable events
                $hub->update(['conduct_mode' => 'partitioned']);
                $hub->refresh();
            }

            $this->syncPartitionsFromRegions($hub);
        } catch (\Throwable) {
            // Never block the creating/publishing action
        }
    }

    /**
     * Auto-created regional children share the hub's school-registration lifecycle.
     * Preserve any child that an administrator has already moved beyond draft.
     */
    public function inheritRegistrationLifecycle(FestEvent $hub, FestEvent $partition): FestEvent
    {
        if ($partition->status !== 'draft'
            || ! in_array($hub->status, ['published', 'registration_open'], true)) {
            return $partition;
        }

        $partition->update([
            'status' => $hub->status,
            'registration_open' => $partition->registration_open ?? $hub->registration_open,
            'registration_close' => $partition->registration_close ?? $hub->registration_close,
        ]);

        return $partition;
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

            $partition = $this->partitions->partitionByKey($hub, $key);
            $expectedTitle = "{$hub->title} — {$region->name}";

            if (! $partition) {
                $partition = $this->partitions->spawnPartition($hub, [
                    'title'          => $expectedTitle,
                    'partition_key'  => $key,
                    'cluster_label'  => $region->name,
                    'partition_role' => 'region',
                    'region_id'      => $region->id,
                ]);
                $created++;
            } else {
                if (! str_contains($partition->title, $hub->title)) {
                    $partition->update(['title' => $expectedTitle]);
                }
                if ($partition->region_id !== $region->id) {
                    $partition->update(['region_id' => $region->id]);
                }
            }

            // Re-sync existing children too. This repairs partitions created before
            // catalogue import and older English Fest children that only received
            // off-stage individual items.
            app(FestItemSyncService::class)->copyItemsToPartition($hub, $partition, 'region');
            app(FestFoodMenuSyncService::class)->copyMenuToPartition($hub, $partition);
            $this->inheritRegistrationLifecycle($hub, $partition);
        }

        // Newly created regions (and any repaired above) need the hub's already-configured
        // fee schedule, item-level overrides, and head overrides too — otherwise a region
        // synced after fees were set on the hub starts out with none of them on its own
        // rows (see FestSchoolEventFeeService::propagateFeeSettingsToChildren()).
        app(FestSchoolEventFeeService::class)->propagateFeeSettingsToChildren($hub->fresh());

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

    /**
     * Route a single school to its region's partition on every already-partitioned
     * hub for this Sahodaya, right when the school's membership region changes.
     * This is what "Sync Partitions from Sahodaya Regions" does for one school —
     * running it here means the admin no longer has to re-click that button every
     * time a school newly picks (or changes) its region.
     *
     * Only assigns into partitions that already exist; it never creates a partition
     * (that stays an explicit admin action via the Sync button / event settings).
     *
     * @return int number of hub events the school was (re)assigned on
     */
    public function syncSchoolAcrossHubs(string $sahodayaId, string $schoolId): int
    {
        self::flushCache();

        $region = $this->schoolRegion($sahodayaId, $schoolId);
        if (! $region) {
            return 0;
        }

        $key = $this->partitionKeyForRegion($region);

        $hubs = FestEvent::where('tenant_id', $sahodayaId)
            ->whereNull('parent_event_id')
            ->where('conduct_mode', 'partitioned')
            ->get();

        $updated = 0;
        foreach ($hubs as $hub) {
            $partition = $this->partitions->partitionByKey($hub, $key);
            if (! $partition) {
                continue;
            }

            FestEventSchoolPartition::updateOrCreate(
                ['event_id' => $hub->id, 'school_id' => $schoolId],
                ['partition_key' => $key, 'assigned_at' => now()],
            );
            $updated++;
        }

        return $updated;
    }
}
