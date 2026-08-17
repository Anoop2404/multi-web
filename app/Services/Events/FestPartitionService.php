<?php

namespace App\Services\Events;

use App\Models\FestCateringOrder;
use App\Models\FestEvent;
use App\Models\FestFoodBill;
use App\Models\FestFoodCoupon;
use App\Models\FestRegistration;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FestPartitionService
{
    public function conductMode(FestEvent $event): string
    {
        if (($event->conduct_mode ?? 'standard') === 'partitioned') {
            return 'partitioned';
        }

        if (! $event->parent_event_id) {
            $hasPartitions = FestEvent::where('parent_event_id', $event->id)
                ->where(function ($q) {
                    $q->whereNotNull('partition_key')
                        ->orWhereNotNull('cluster_key');
                })
                ->exists();

            if ($hasPartitions) {
                return 'partitioned';
            }
        }

        return 'standard';
    }

    public function isPartitionedHub(FestEvent $event): bool
    {
        if ($event->parent_event_id) {
            return false;
        }

        if ($this->conductMode($event) !== 'partitioned') {
            return false;
        }

        return $this->partitions($event)->isNotEmpty();
    }

    public function shouldCombineAtFinale(FestEvent $event): bool
    {
        return (bool) ($event->combine_regions_at_finale ?? true);
    }

    /** @deprecated Use isPartitionedHub() */
    public function isUmbrella(FestEvent $event): bool
    {
        return $this->isPartitionedHub($event);
    }

    public function partitionKey(FestEvent $event): ?string
    {
        return $event->partition_key ?? $event->cluster_key;
    }

    public function partitionRole(FestEvent $event): ?string
    {
        if ($event->partition_role) {
            return $event->partition_role;
        }

        if ($event->cluster_key && $event->parent_event_id) {
            return 'cluster';
        }

        return null;
    }

    /** @return Collection<int, FestEvent> */
    public function partitions(FestEvent $hub): Collection
    {
        if ($hub->parent_event_id) {
            return collect();
        }

        return FestEvent::where('parent_event_id', $hub->id)
            ->where(function ($q) {
                $q->whereNotNull('partition_key')
                    ->orWhereNotNull('cluster_key');
            })
            ->orderBy('partition_role')
            ->orderBy('cluster_label')
            ->orderBy('event_start')
            ->get();
    }

    /** @return list<string> */
    public function partitionKeys(FestEvent $hub): array
    {
        return $this->partitions($hub)
            ->map(fn (FestEvent $p) => $this->partitionKey($p))
            ->filter()
            ->values()
            ->all();
    }

    public function partitionByKey(FestEvent $hub, string $key): ?FestEvent
    {
        return $this->partitions($hub)->first(
            fn (FestEvent $p) => $this->partitionKey($p) === $key
        );
    }

    public function partitionLabel(FestEvent $hub, string $key): string
    {
        $partition = $this->partitionByKey($hub, $key);

        return $partition?->cluster_label
            ?? $partition?->title
            ?? ucfirst(str_replace(['-', '_'], ' ', $key));
    }

    /** @return list<array{school_id: string, school_name: string, total_points: int, rank: int}> */
    public function scoreboardByPartition(FestEvent $hub, string $partitionKey): array
    {
        $partition = $this->partitionByKey($hub, $partitionKey);

        if (! $partition) {
            return [];
        }

        return EventContext::for($partition)->scoreboardBySchoolForEvent();
    }

    /**
     * Combined school points across configured child partitions.
     *
     * §7.3a note (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md, 2026-08-15): this sums a
     * school's points across sibling *region-partition* events for ONE phase (or one
     * non-phased hub) — e.g. Tirur + Manjeri region children combined into a single
     * standing. It does NOT sum across *phases* of the same event — that is a separate
     * aggregation axis handled by FestPhaseScoreboardService, which reuses the shared
     * aggregateScoreboardAcrossPartitions() loop below rather than duplicating it.
     *
     * @return list<array{school_id: string, school_name: string, total_points: int, rank: int}>
     */
    public function combinedScoreboard(FestEvent $hub): array
    {
        $config = $this->aggregationConfig($hub);
        $includeRoles = $config['include_roles'] ?? ['region', 'finale', 'cluster'];

        $partitions = $this->partitions($hub)->filter(function (FestEvent $partition) use ($includeRoles) {
            $role = $this->partitionRole($partition);

            return ! $role || in_array($role, $includeRoles, true);
        });

        return $this->aggregateScoreboardAcrossPartitions(
            $partitions,
            fn (FestEvent $partition) => EventContext::for($partition)->scoreboardBySchoolForEvent()
        );
    }

    /**
     * Shared aggregation loop: sum school points across an arbitrary set of partition
     * events, given a per-partition scoreboard source callback, then rank the totals.
     *
     * Extracted from combinedScoreboard() (§7.3a, 2026-08-15) so FestPhaseScoreboardService
     * can reuse the exact same "sum rows from N partitions, then rank" mechanics for its
     * regional-phase case — scoped to a phase's region-partition children with a
     * phase-filtered scoreboard source, instead of combinedScoreboard()'s whole-event
     * source — without duplicating the accumulation/ranking logic.
     *
     * @param  Collection<int, FestEvent>  $partitions
     * @param  callable(FestEvent): list<array{school_id: string, total_points: int}>  $scoreboardSource
     * @return list<array{school_id: string, school_name: string, total_points: int, rank: int}>
     */
    public function aggregateScoreboardAcrossPartitions(Collection $partitions, callable $scoreboardSource): array
    {
        $totals = [];

        foreach ($partitions as $partition) {
            foreach ($scoreboardSource($partition) as $row) {
                $sid = $row['school_id'];
                $totals[$sid] = ($totals[$sid] ?? 0) + (int) $row['total_points'];
            }
        }

        return $this->rankSchoolTotals($totals);
    }

    /**
     * Per-region summary cards for the hub's drill-down panel (Phase 4, §2.5 of
     * docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md — "a full Sahodaya admin can
     * inspect one region's data without leaving the hub page"). Reuses the exact
     * same items/registrations/results_published/schools/athletes numbers that
     * FestEventController::show() already computes for a single event's own
     * Overview page — just run once per region partition, so the drill-down cards
     * stay consistent with what an admin would see after navigating into the
     * region's own page.
     *
     * @return list<array{
     *     id: int, title: string, label: string, status: string,
     *     results_published: bool, venue: ?string, partition_role: ?string,
     *     items_count: int, registrations_count: int,
     *     schools_count: ?int, athletes_count: ?int,
     * }>
     */
    public function regionDrillDownSummary(FestEvent $hub): array
    {
        $regionPartitions = $this->partitions($hub)->filter(
            fn (FestEvent $p) => $this->partitionRole($p) === 'region'
        );

        return $regionPartitions->map(function (FestEvent $partition) {
            $summary = [
                'id'                  => $partition->id,
                'title'               => $partition->title,
                'label'               => $partition->cluster_label ?? $partition->title,
                'status'              => $partition->status,
                'results_published'   => (bool) $partition->results_published,
                'venue'               => $partition->venue,
                'partition_role'      => $this->partitionRole($partition),
                'items_count'         => $partition->items()->count(),
                'registrations_count' => $partition->registrations()->count(),
                'schools_count'       => null,
                'athletes_count'      => null,
            ];

            if ($partition->event_type === 'sports') {
                $regs = $partition->registrations()
                    ->whereIn('status', FestRegistration::ACTIVE_STATUSES)
                    ->with('participants')
                    ->get();
                $summary['schools_count'] = $regs->pluck('school_id')->unique()->count();
                $summary['athletes_count'] = $regs->flatMap(fn ($r) => $r->participants ?? [])
                    ->filter(fn ($p) => $p->participant_role !== 'standby')
                    ->count();
            }

            return $summary;
        })->values()->all();
    }

    /** @return array<string, mixed> */
    public function aggregationConfig(FestEvent $hub): array
    {
        $stored = $hub->aggregation_config;
        if (is_array($stored) && $stored !== []) {
            return $stored;
        }

        if ($hub->event_type === 'kids_fest') {
            return config('fest_conduct_presets.kids_fest.aggregation_config', [
                'include_roles' => ['cluster'],
                'method' => 'sum_points',
            ]);
        }

        return [
            'include_roles' => ['region', 'finale'],
            'method' => 'sum_points',
            'overall_label' => 'Overall Championship',
        ];
    }

    public function spawnPartition(FestEvent $hub, array $data): FestEvent
    {
        abort_if($hub->parent_event_id, 422, 'Create partitions on the hub event, not a child partition.');

        $allowedTypes = ['kids_fest', 'kalolsavam', 'kalotsav', 'english_fest', 'science_fest', 'teacher_fest', 'sports', 'custom'];
        abort_unless(
            in_array($hub->event_type, $allowedTypes, true) || $this->conductMode($hub) === 'partitioned',
            422,
            'Partitions are only supported on partitioned hub events.'
        );

        $key = Str::slug($data['partition_key'] ?? $data['cluster_key'] ?? $data['cluster_label'] ?? $data['title'] ?? '');
        if ($key === '') {
            throw ValidationException::withMessages(['partition_key' => 'Partition key is required.']);
        }

        $exists = FestEvent::where('parent_event_id', $hub->id)
            ->where(function ($q) use ($key) {
                $q->where('partition_key', $key)->orWhere('cluster_key', $key);
            })
            ->exists();
        abort_if($exists, 422, 'A partition with this key already exists.');

        $attrs = [
            'cluster_key'   => $key,
            'cluster_label' => $data['cluster_label'] ?? $data['title'] ?? ucfirst($key),
            'partition_key' => $key,
            'partition_role'=> $data['partition_role'] ?? 'region',
            'region_id'     => $data['region_id'] ?? null,
            'venue'         => $data['venue'] ?? $hub->venue,
            'event_start'   => $data['event_start'] ?? $hub->event_start,
            'event_end'     => $data['event_end'] ?? $hub->event_end,
            'level_round'   => $data['level_round'] ?? $hub->level_round,
            'status'        => 'draft',
            'conduct_mode'  => 'standard',
            'copy_items'    => false,
        ];

        if (! empty($data['scoring_preset'])) {
            $attrs['scoring_preset'] = $data['scoring_preset'];
        } elseif ($hub->scoring_preset) {
            $attrs['scoring_preset'] = $hub->scoring_preset;
        }

        $child = app(FestCascadeService::class)->spawnChildEvent($hub, $data['title'], $attrs);

        if ($hub->items()->exists()) {
            app(FestItemSyncService::class)->copyItemsToPartition($hub, $child, $data['partition_role'] ?? 'region');
        }

        // Food menu replication only applies to region partitions — see
        // docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.8 (Gap I).
        if (($data['partition_role'] ?? 'region') === 'region' && $hub->foodMenuItems()->exists()) {
            app(FestFoodMenuSyncService::class)->copyMenuToPartition($hub, $child);
        }

        // A region/cluster added after the hub already has fees configured should start
        // with that configuration, not blank — see
        // FestSchoolEventFeeService::propagateFeeSettingsToChildren(). No-op until the hub
        // is actually conduct_mode = 'partitioned'.
        app(FestSchoolEventFeeService::class)->propagateFeeSettingsToChildren($hub->fresh());

        return $child;
    }

    /**
     * Spawn all partitions from a named preset (e.g. mcs_kalotsav).
     *
     * @return list<FestEvent>
     */
    public function spawnFromPreset(FestEvent $hub, string $presetKey): array
    {
        $preset = config("fest_conduct_presets.{$presetKey}");
        abort_if(! $preset, 422, "Unknown conduct preset: {$presetKey}");

        $hub->update([
            'conduct_mode'        => $preset['conduct_mode'] ?? 'partitioned',
            'aggregation_config'  => $preset['aggregation_config'] ?? null,
            'scoring_preset'      => $preset['scoring_preset'] ?? null,
        ]);

        if (! empty($preset['participation_preset'])) {
            $feeSettings = $hub->fee_settings ?? [];
            $feeSettings['participation_preset'] = $preset['participation_preset'];
            $hub->update(['fee_settings' => $feeSettings]);
        }

        $created = [];
        foreach ($preset['partitions'] ?? [] as $partitionDef) {
            $key = $partitionDef['partition_key'];
            if ($this->partitionByKey($hub, $key)) {
                continue;
            }

            $created[] = $this->spawnPartition($hub, [
                'title'          => $partitionDef['cluster_label'] ?? ucfirst($key),
                'partition_key'  => $key,
                'cluster_label'  => $partitionDef['cluster_label'] ?? ucfirst($key),
                'partition_role' => $partitionDef['partition_role'] ?? 'region',
                'level_round'    => $partitionDef['level_round'] ?? $hub->level_round,
                'scoring_preset' => $preset['scoring_preset'] ?? null,
            ]);
        }

        return $created;
    }

    /** Kids Fest backward compatibility. */
    public function spawnCluster(FestEvent $umbrella, array $data): FestEvent
    {
        if ($umbrella->conduct_mode !== 'partitioned' && $umbrella->event_type === 'kids_fest') {
            $umbrella->update(['conduct_mode' => 'partitioned']);
        }

        return $this->spawnPartition($umbrella, array_merge($data, [
            'partition_role' => $data['partition_role'] ?? 'cluster',
        ]));
    }

    /**
     * Hub-level food summary across all region partitions — same idea as combinedScoreboard(),
     * but for food billing/catering/coupons, none of which aggregate across partitions on
     * their own (each FestFoodBill/FestCateringOrder/FestFoodCoupon row is scoped to its own
     * partition's event_id, with no hub-wide view). Without this, an admin had to open every
     * region's own Food Menu/Billing page and add the numbers up by hand.
     *
     * See docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.9 (Gap J).
     *
     * @return array{
     *     billing: array{total: float, paid: float, balance: float},
     *     catering_head_count: int,
     *     coupons: array{issued: int, redeemed: int},
     *     by_region: list<array{region: string, total: float, paid: float, balance: float, head_count: int}>,
     * }
     */
    public function combinedFoodSummary(FestEvent $hub): array
    {
        $regionPartitions = $this->partitions($hub)->filter(
            fn (FestEvent $p) => $this->partitionRole($p) === 'region'
        );

        $billingTotal = 0.0;
        $billingPaid = 0.0;
        $cateringHeadCount = 0;
        $couponsIssued = 0;
        $couponsRedeemed = 0;
        $byRegion = [];

        foreach ($regionPartitions as $partition) {
            $bills = FestFoodBill::where('event_id', $partition->id)
                ->where('status', '!=', FestFoodBill::STATUS_CANCELLED)
                ->get(['amount_total', 'amount_paid']);

            $regionTotal = (float) $bills->sum('amount_total');
            $regionPaid = (float) $bills->sum('amount_paid');

            $regionHeadCount = (int) FestCateringOrder::where('event_id', $partition->id)
                ->where('status', 'confirmed')
                ->sum('head_count');

            $issued = FestFoodCoupon::where('event_id', $partition->id)->count();
            $redeemed = FestFoodCoupon::where('event_id', $partition->id)->where('status', 'redeemed')->count();

            $billingTotal += $regionTotal;
            $billingPaid += $regionPaid;
            $cateringHeadCount += $regionHeadCount;
            $couponsIssued += $issued;
            $couponsRedeemed += $redeemed;

            $byRegion[] = [
                'region'     => $partition->cluster_label ?? $partition->title,
                'total'      => round($regionTotal, 2),
                'paid'       => round($regionPaid, 2),
                'balance'    => round($regionTotal - $regionPaid, 2),
                'head_count' => $regionHeadCount,
            ];
        }

        return [
            'billing' => [
                'total'   => round($billingTotal, 2),
                'paid'    => round($billingPaid, 2),
                'balance' => round($billingTotal - $billingPaid, 2),
            ],
            'catering_head_count' => $cateringHeadCount,
            'coupons' => [
                'issued'   => $couponsIssued,
                'redeemed' => $couponsRedeemed,
            ],
            'by_region' => $byRegion,
        ];
    }

    /**
     * Turn a school_id => points totals map into ranked rows with school names.
     * Public: shared by combinedScoreboard()/aggregateScoreboardAcrossPartitions() here
     * and by FestPhaseScoreboardService::cumulativeOverall() (§7.3a) for its own
     * sum-across-phases totals map, so both aggregation axes rank identically.
     *
     * @param array<string, int> $totals
     */
    public function rankSchoolTotals(array $totals): array
    {
        if ($totals === []) {
            return [];
        }

        $schools = Tenant::whereIn('id', array_keys($totals))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->keyBy('id');

        $rank = 1;
        $previousTotal = null;
        $position = 0;
        $rows = [];

        foreach (collect($totals)->sortDesc() as $schoolId => $total) {
            $position++;
            if ($previousTotal !== null && (int) $total < (int) $previousTotal) {
                $rank = $position;
            }
            $previousTotal = (int) $total;

            $rows[] = [
                'school_id'    => $schoolId,
                'school_name'  => $schools[$schoolId]?->name ?? $schoolId,
                'total_points' => (int) $total,
                'rank'         => $rank,
            ];
        }

        return $rows;
    }
}
