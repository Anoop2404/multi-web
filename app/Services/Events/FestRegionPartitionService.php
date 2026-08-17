<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
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

    /** @var array<string, ?Region> request-scoped cache: school → region (keyed sahodaya:school:group) */
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

    /**
     * The membership region a school belongs to for the active year, or null. Memoized
     * per request.
     *
     * §7.3 item 3 (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md, 2026-08-15): optional
     * `$partitionGroup` parameter, added on top of the pre-existing signature.
     *
     * Backward-compat guarantee: every caller that already existed before this change
     * calls schoolRegion() with two arguments, so $partitionGroup defaults to null —
     * and null resolves the exact same query this method has always run (the legacy
     * Sahodaya-wide `SchoolRegionAssignment` row, i.e. `partition_group IS NULL`,
     * scoped only by school_id + academic_year). Omitting $partitionGroup is always
     * safe and always means "today's behavior, unchanged."
     *
     * When a caller DOES pass a $partitionGroup (a regional phase's
     * `region_partition_group`, e.g. 'off_stage' or 'sargadhara'), this instead
     * resolves the group-scoped assignment row for that school/year/group — the
     * independent-per-phase-group region §7.3 item 4 depends on.
     */
    public function schoolRegion(?string $sahodayaId, string $schoolId, ?string $partitionGroup = null): ?Region
    {
        if (! $sahodayaId) {
            $sahodayaId = Tenant::find($schoolId)?->parent_id;
            if (! $sahodayaId) {
                return null;
            }
        }

        $key = $sahodayaId.':'.$schoolId.':'.($partitionGroup ?? '');
        if (array_key_exists($key, self::$schoolRegionCache)) {
            return self::$schoolRegionCache[$key];
        }

        $year = AcademicYear::forSahodaya($sahodayaId);

        $assignment = SchoolRegionAssignment::forTenant($sahodayaId)
            ->forYear($year)
            ->where('school_id', $schoolId)
            ->forPartitionGroup($partitionGroup)
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

        // Sports uses a nested season -> discipline -> region topology. Running the
        // generic one-level synchronizer here corrupts that shape by placing regions
        // directly under the season.
        if ($hub->event_type === 'sports') {
            return;
        }

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
     * Push hub-level lifecycle fields down onto every REGION partition child by default
     * (never finale/cluster — those run on their own later timeline by design, same
     * scoping as combinedFoodSummary() elsewhere in this topology) — unless the caller
     * opts into $includeFinale (see its own doc below) for the one case where finale
     * should move with the hub too.
     *
     * inheritRegistrationLifecycle() above is one-shot and conditional — it only fires
     * once, at spawn/first-registration time, and only writes a field if the child's own
     * value is still null. That leaves every hub lifecycle change made AFTER regions
     * already exist (reschedule the registration window, lock registration, publish
     * results/schedule, mark the event completed) with zero effect on any child — each
     * region had to be updated by hand. This is the live cascade: call it every time an
     * admin actually changes one of these fields on the hub, and it unconditionally
     * overwrites the matching field on every region child — the same push-on-save pattern
     * already shipped for fee configuration (see FestSchoolEventFeeService::
     * propagateFeeSettingsToChildren()).
     *
     * Deliberately only the subset of fields the caller actually changed (not a wholesale
     * copy of every lifecycle column) — a region that a region-admin has independently
     * pushed further along (e.g. their own mark entry already underway) shouldn't have
     * unrelated fields clobbered by a hub save that only touched, say, the registration
     * window.
     *
     * @param  array<string, mixed>  $fields  subset of: registration_open, registration_close,
     *   registration_locked, status, scoring_locked, results_published, schedule_published
     * @param  bool  $includeFinale  Also cascade to finale/cluster children, not just region —
     *   opt-in, since registration windows/locks genuinely run on a separate later timeline
     *   for finale by design. Results publication is the one case where finale SHOULD move
     *   with the hub: a hub-level "Publish Results" represents the whole fest being final,
     *   and a finale child left unpublished would show incomplete/stale results on its own
     *   public page. See Phase 3 audit item 2.
     */
    public function cascadeLifecycleToChildren(FestEvent $hub, array $fields, bool $includeFinale = false): void
    {
        if (($hub->conduct_mode ?? 'standard') !== 'partitioned' || empty($fields)) {
            return;
        }

        $allowed = [
            'registration_open', 'registration_close', 'registration_locked',
            'status', 'scoring_locked', 'results_published', 'schedule_published',
        ];
        $fields = array_intersect_key($fields, array_flip($allowed));

        if (empty($fields)) {
            return;
        }

        FestEvent::where('parent_event_id', $hub->id)
            ->when(
                $includeFinale,
                fn ($q) => $q->whereIn('partition_role', ['region', 'finale', 'cluster']),
                fn ($q) => $q->where('partition_role', 'region'),
            )
            ->get()
            ->each(fn (FestEvent $child) => $child->update($fields));
    }

    /**
     * Ensure a partition child event exists per membership region and (re)assign every
     * school to its region's partition. Returns a summary for the admin.
     *
     * This is the legacy, Sahodaya-wide entry point — it copies the FULL hub item
     * catalogue (no phase filtering) into every region child, exactly as before §7.3.
     * Unaffected by the region-per-phase-group addendum: a Sahodaya that never
     * configures a regional FestEventPhase (region_partition_group) keeps calling this
     * one method, with this exact behavior, forever. See syncPartitionsFromRegionsForPhase()
     * below for the new per-group entry point regional phases use instead.
     *
     * @return array{partitions_created: int, schools_assigned: int}
     */
    public function syncPartitionsFromRegions(FestEvent $hub): array
    {
        abort_if($hub->parent_event_id, 422, 'Sync regions on the hub event, not a partition.');
        abort_if($hub->event_type === 'sports', 422, 'Sports regions must be synced below each sport discipline.');

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
            ->forPartitionGroup(null)
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
     * §7.3 items 2/3 (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md, 2026-08-15): the per-group
     * variant of syncPartitionsFromRegions(), called once per regional phase rather than
     * once per hub. This is new — nothing existing calls it, so it changes no current
     * behavior on its own; it only takes effect once a Sahodaya actually configures a
     * regional FestEventPhase (region_partition_group set) and something invokes this
     * method for it, which is wiring left to the phase-controls admin UI (outside this
     * change's scope).
     *
     * Deliberately reuses the SAME shared, hub-level set of region-partition child
     * events that syncPartitionsFromRegions() already creates/maintains — a hub's
     * "Tirur" region child is one FestEvent shared by every regional phase on that hub,
     * not one child event per (region, group) pair. What differs per call is which
     * items get copied into it (only $phase's items — see FestItemSyncService::
     * copyItemsToPartition()'s new $phase parameter, §7.3 item 5) and which
     * SchoolRegionAssignment rows are read (scoped to $phase->region_partition_group
     * instead of the legacy Sahodaya-wide NULL-group rows).
     *
     * Known limitation, carried over honestly rather than silently "solved" here:
     * FestEventSchoolPartition (the table that routes a school to one partition_key on
     * a hub for registration) still has a single row per (event_id, school_id) — it has
     * no group dimension. If a school is assigned to different physical regions for two
     * different regional phases on the same hub, calling this method for both phases in
     * turn leaves that shared row pointing at whichever phase synced last. Making
     * registration routing itself independent per phase (§7.3 item 4's "registers under
     * whichever region they picked for that group") needs its own follow-up beyond the
     * §7.3 items 1/2/3/5 scope this method covers — likely a group-aware evolution of
     * FestEventSchoolPartition or a routing table alongside it.
     *
     * @return array{partitions_created: int, schools_assigned: int}
     */
    public function syncPartitionsFromRegionsForPhase(FestEvent $hub, FestEventPhase $phase): array
    {
        abort_if($hub->parent_event_id, 422, 'Sync regions on the hub event, not a partition.');
        abort_if($hub->event_type === 'sports', 422, 'Sports regions must be synced below each sport discipline.');
        abort_if((int) $phase->event_id !== (int) $hub->id, 422, 'Phase does not belong to this hub event.');

        $partitionGroup = $phase->region_partition_group;
        abort_if(! filled($partitionGroup), 422, 'Phase is not configured as a regional phase (region_partition_group is not set).');

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

            // Only $phase's own items — this is the one behavioral difference from
            // syncPartitionsFromRegions() above (§7.3 item 5).
            app(FestItemSyncService::class)->copyItemsToPartition($hub, $partition, 'region', $phase);
            app(FestFoodMenuSyncService::class)->copyMenuToPartition($hub, $partition);
            $this->inheritRegistrationLifecycle($hub, $partition);
        }

        app(FestSchoolEventFeeService::class)->propagateFeeSettingsToChildren($hub->fresh());

        $year = AcademicYear::forSahodaya($hub->tenant_id);
        $assignments = SchoolRegionAssignment::forTenant($hub->tenant_id)
            ->forYear($year)
            ->forPartitionGroup($partitionGroup)
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
