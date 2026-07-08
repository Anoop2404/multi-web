<?php

namespace App\Services\Events;

use App\Models\FestEvent;
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

        if ($event->event_type === 'kids_fest' && ! $event->parent_event_id) {
            return FestEvent::where('parent_event_id', $event->id)
                ->whereNotNull('cluster_key')
                ->exists() ? 'partitioned' : 'standard';
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
     * @return list<array{school_id: string, school_name: string, total_points: int, rank: int}>
     */
    public function combinedScoreboard(FestEvent $hub): array
    {
        $config = $this->aggregationConfig($hub);
        $includeRoles = $config['include_roles'] ?? ['region', 'finale', 'cluster'];

        $totals = [];

        foreach ($this->partitions($hub) as $partition) {
            $role = $this->partitionRole($partition);
            if ($role && ! in_array($role, $includeRoles, true)) {
                continue;
            }

            foreach (EventContext::for($partition)->scoreboardBySchoolForEvent() as $row) {
                $sid = $row['school_id'];
                $totals[$sid] = ($totals[$sid] ?? 0) + (int) $row['total_points'];
            }
        }

        return $this->rankSchoolTotals($totals);
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

        $allowedTypes = ['kids_fest', 'kalolsavam', 'kalotsav', 'custom'];
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

    /** @param array<string, int> $totals */
    private function rankSchoolTotals(array $totals): array
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
