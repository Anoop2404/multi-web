<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use Illuminate\Support\Collection;

/**
 * Resolves the public festival catalogue and the direct data boundary for one
 * operational event.
 *
 * Public phase/region leaves are real events with their own venue, lifecycle,
 * schedule and results. The root remains useful for administration and later
 * championship rollups, but public pages must not silently replace a requested
 * leaf with that root or query its siblings.
 */
class PublicOperationalEventService
{
    public const PUBLIC_STATUSES = ['published', 'registration_open', 'ongoing', 'completed'];

    /**
     * @return Collection<int, FestEvent>
     */
    public function listedForTenant(string $tenantId): Collection
    {
        return FestEvent::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::PUBLIC_STATUSES)
            ->where('nav_hidden', false)
            ->with([
                'parentEvent:id,title,event_start,event_end',
                'sourcePhase:id,name,sort_order,is_regional',
                'region:id,name,code',
                'childEvents:id,parent_event_id,partition_role,source_phase_id,region_id',
            ])
            ->orderBy('sort_order')
            ->orderByRaw('CASE WHEN event_start IS NULL THEN 1 ELSE 0 END')
            ->orderBy('event_start')
            ->orderBy('title')
            ->get([
                'id', 'tenant_id', 'parent_event_id', 'root_event_id', 'source_phase_id',
                'region_id', 'partition_role', 'partition_key', 'cluster_key',
                'title', 'event_type', 'event_start', 'event_end', 'venue', 'status',
                'results_published', 'schedule_published', 'nav_hidden', 'conduct_mode',
                'workflow_mode', 'phase_mode_enabled', 'sort_order',
            ])
            ->reject(fn (FestEvent $event) => $this->isAdministrativeContainer($event))
            ->values();
    }

    /**
     * Group standalone public events for discovery without turning their parent
     * programme or phase into public navigation.
     *
     * @param  Collection<int, FestEvent>  $events
     * @return list<array{key: string, label: string, description: string, series: list<array{label: string, events: Collection<int, FestEvent>}>}>
     */
    public function catalogueGroups(Collection $events): array
    {
        $definitions = [
            'live' => ['label' => 'Live & Open', 'description' => 'Events currently running or accepting registrations.'],
            'upcoming' => ['label' => 'Upcoming', 'description' => 'Published events preparing to begin.'],
            'completed' => ['label' => 'Completed', 'description' => 'Events with completed programmes and published archives.'],
        ];

        return collect($definitions)
            ->map(function (array $definition, string $key) use ($events) {
                $matching = $events->filter(fn (FestEvent $event) => $this->catalogueStatus($event) === $key);

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'series' => $matching
                        ->groupBy(fn (FestEvent $event) => $event->parentEvent?->title ?: $event->title)
                        ->map(fn (Collection $seriesEvents, string $label) => [
                            'label' => $label,
                            'events' => $seriesEvents->values(),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $group) => $group['series'] !== [])
            ->values()
            ->all();
    }

    public function catalogueStatus(FestEvent $event): string
    {
        return match ($event->status) {
            'ongoing', 'registration_open' => 'live',
            'completed' => 'completed',
            default => 'upcoming',
        };
    }

    /** @return array<string, mixed> */
    public function publicContext(FestEvent $event): array
    {
        $event->loadMissing(['parentEvent:id,title', 'sourcePhase:id,name,sort_order,is_regional', 'region:id,name,code']);

        return [
            'series' => $event->parentEvent?->title,
            'phase' => $event->sourcePhase?->name,
            'phase_order' => $event->sourcePhase?->sort_order,
            'region' => $event->region?->name,
            'venue' => $event->venue,
            'status' => $event->status,
            'status_label' => ucfirst(str_replace('_', ' ', (string) $event->status)),
            'is_regional' => (bool) ($event->sourcePhase?->is_regional || $event->region_id),
        ];
    }

    public function isPubliclyAccessible(FestEvent $event): bool
    {
        $event->loadMissing('childEvents');

        return ! $event->nav_hidden
            && in_array($event->status, self::PUBLIC_STATUSES, true)
            && ! $this->isAdministrativeContainer($event);
    }

    /**
     * A direct, event-local scope compatible with the current scoreboard and
     * controller view contracts. It deliberately contains exactly one event id.
     *
     * @return array{
     *   key: string,
     *   label: string,
     *   role: string,
     *   event_id: int,
     *   event_ids: list<int>,
     *   results_published: bool,
     *   schedule_published: bool,
     *   source_phase_id: int|null,
     *   region_id: int|null
     * }
     */
    public function directScope(FestEvent $event): array
    {
        return [
            'key' => 'event:'.$event->id,
            'label' => $event->title,
            'role' => 'event',
            'event_id' => (int) $event->id,
            'event_ids' => [(int) $event->id],
            'results_published' => (bool) $event->results_published,
            'schedule_published' => (bool) $event->schedule_published,
            'source_phase_id' => $event->source_phase_id ? (int) $event->source_phase_id : null,
            'region_id' => $event->region_id ? (int) $event->region_id : null,
        ];
    }

    public function isAdministrativeContainer(FestEvent $event): bool
    {
        if ($event->parent_event_id !== null || $event->childEvents->isEmpty()) {
            return false;
        }

        if ($event->isSportsSeasonEvent()) {
            return true;
        }

        if ($event->workflow_mode === FestPhasedWorkflowService::MODE || $event->phase_mode_enabled) {
            return true;
        }

        if ($event->conduct_mode === 'partitioned') {
            return true;
        }

        return $event->childEvents->contains(fn (FestEvent $child) => $child->source_phase_id !== null
            || $child->region_id !== null
            || in_array($child->partition_role, ['region', 'cluster', 'finale', 'phase', 'digi_fest'], true)
        );
    }
}
