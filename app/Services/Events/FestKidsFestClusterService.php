<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FestKidsFestClusterService
{
    public function isUmbrella(FestEvent $event): bool
    {
        if ($event->event_type !== 'kids_fest' || $event->parent_event_id) {
            return false;
        }

        return FestEvent::where('parent_event_id', $event->id)
            ->whereNotNull('cluster_key')
            ->exists();
    }

    /** @return \Illuminate\Support\Collection<int, FestEvent> */
    public function clusters(FestEvent $umbrella): \Illuminate\Support\Collection
    {
        return FestEvent::where('parent_event_id', $umbrella->id)
            ->whereNotNull('cluster_key')
            ->orderBy('cluster_label')
            ->orderBy('event_start')
            ->get();
    }

    public function spawnCluster(FestEvent $umbrella, array $data): FestEvent
    {
        abort_unless($umbrella->event_type === 'kids_fest', 422, 'Clusters are only for Kids Fest events.');
        abort_if($umbrella->parent_event_id, 422, 'Create clusters on the umbrella event, not a child cluster.');

        $key = Str::slug($data['cluster_key'] ?? $data['cluster_label'] ?? $data['title']);
        if ($key === '') {
            throw ValidationException::withMessages(['cluster_key' => 'Cluster key is required.']);
        }

        $exists = FestEvent::where('parent_event_id', $umbrella->id)
            ->where('cluster_key', $key)
            ->exists();
        abort_if($exists, 422, 'A cluster with this key already exists.');

        $child = app(FestCascadeService::class)->spawnChildEvent($umbrella, $data['title'], [
            'cluster_key'   => $key,
            'cluster_label' => $data['cluster_label'] ?? $data['title'],
            'venue'         => $data['venue'] ?? $umbrella->venue,
            'event_start'   => $data['event_start'] ?? $umbrella->event_start,
            'event_end'     => $data['event_end'] ?? $umbrella->event_end,
            'status'        => 'draft',
        ]);

        return $child;
    }

    /**
     * Combined school points across all cluster child events.
     *
     * @return list<array{school_id: string, school_name: string, total_points: int, rank: int}>
     */
    public function combinedScoreboard(FestEvent $umbrella): array
    {
        $totals = [];

        foreach ($this->clusters($umbrella) as $cluster) {
            foreach (EventContext::for($cluster)->scoreboardBySchool() as $row) {
                $sid = $row['school_id'];
                $totals[$sid] = ($totals[$sid] ?? 0) + (int) $row['total_points'];
            }
        }

        if ($totals === []) {
            return [];
        }

        $schools = \App\Models\Tenant::whereIn('id', array_keys($totals))
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
