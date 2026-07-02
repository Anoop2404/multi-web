<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestStateProgramPropagation;
use App\Support\TenancyDatabase;
use Illuminate\Support\Collection;

class StateDashboardService
{
    public function propagationStatus(): Collection
    {
        return FestStateProgramPropagation::query()
            ->with(['program', 'sahodaya'])
            ->get()
            ->groupBy('state_program_id')
            ->map(function ($rows, $programId) {
                $program = $rows->first()?->program;

                $clusters = $rows->map(function (FestStateProgramPropagation $propagation) {
                    $eventMeta = null;

                    if ($propagation->sahodaya && $propagation->tenant_event_id) {
                        $eventMeta = TenancyDatabase::runWhenDatabaseReady($propagation->sahodaya, function () use ($propagation) {
                            $event = FestEvent::find($propagation->tenant_event_id);
                            if (! $event) {
                                return null;
                            }

                            return [
                                'status'             => $event->status,
                                'results_published'  => (bool) $event->results_published,
                                'registration_count' => $event->registrations()->count(),
                                'title'              => $event->title,
                            ];
                        });
                    }

                    return [
                        'sahodaya_id'       => $propagation->sahodaya_id,
                        'sahodaya_name'     => $propagation->sahodaya?->name,
                        'level_round'       => $propagation->level_round,
                        'tenant_event_id'   => $propagation->tenant_event_id,
                        'event'             => $eventMeta,
                        'manage_url'        => ($propagation->sahodaya_id && $propagation->tenant_event_id)
                            ? "/sahodaya-admin/{$propagation->sahodaya_id}/events/{$propagation->tenant_event_id}"
                            : null,
                    ];
                });

                return [
                    'program_id'       => $programId,
                    'program_title'    => $program?->title,
                    'program_status'   => $program?->status,
                    'clusters'         => $clusters->values(),
                    'propagated_count' => $clusters->whereNotNull('tenant_event_id')->count(),
                    'total_slots'      => $clusters->count(),
                ];
            })
            ->values();
    }

    /** @return array{cluster_events: int, results_published: int, in_progress: int, rows: \Illuminate\Support\Collection} */
    public function clusterResultsRollup(): array
    {
        $propagations = FestStateProgramPropagation::query()
            ->with('sahodaya')
            ->where('level_round', 'sahodaya')
            ->whereNotNull('tenant_event_id')
            ->get();

        $published = 0;
        $ongoing = 0;
        $rows = [];

        foreach ($propagations as $propagation) {
            if (! $propagation->sahodaya) {
                continue;
            }

            $meta = TenancyDatabase::runWhenDatabaseReady($propagation->sahodaya, function () use ($propagation) {
                $event = FestEvent::find($propagation->tenant_event_id);

                return $event ? [
                    'results_published' => (bool) $event->results_published,
                    'status'            => $event->status,
                    'title'             => $event->title,
                ] : null;
            });

            if (! $meta) {
                continue;
            }

            if ($meta['results_published']) {
                $published++;
            } elseif (in_array($meta['status'], ['ongoing', 'registration_open'], true)) {
                $ongoing++;
            }

            $rows[] = [
                'sahodaya_name'     => $propagation->sahodaya->name,
                'sahodaya_id'       => $propagation->sahodaya_id,
                'event_id'          => $propagation->tenant_event_id,
                'event_title'       => $meta['title'],
                'status'            => $meta['status'],
                'results_published' => $meta['results_published'],
                'manage_url'        => "/sahodaya-admin/{$propagation->sahodaya_id}/events/{$propagation->tenant_event_id}",
            ];
        }

        return [
            'cluster_events'    => count($rows),
            'results_published' => $published,
            'in_progress'       => $ongoing,
            'rows'              => collect($rows)->take(15)->values(),
        ];
    }

    /** @return array{total_registrations: int, approved_registrations: int, clusters: \Illuminate\Support\Collection} */
    public function clusterParticipationRollup(): array
    {
        $propagations = FestStateProgramPropagation::query()
            ->with('sahodaya')
            ->where('level_round', 'sahodaya')
            ->whereNotNull('tenant_event_id')
            ->get();

        $total = 0;
        $approved = 0;
        $clusters = [];

        foreach ($propagations as $propagation) {
            if (! $propagation->sahodaya) {
                continue;
            }

            $counts = TenancyDatabase::runWhenDatabaseReady($propagation->sahodaya, function () use ($propagation) {
                $event = FestEvent::find($propagation->tenant_event_id);
                if (! $event) {
                    return null;
                }

                $query = FestRegistration::where('event_id', $event->id);

                return [
                    'event_title' => $event->title,
                    'total'       => (clone $query)->count(),
                    'approved'    => (clone $query)->where('status', 'approved')->count(),
                ];
            });

            if (! $counts) {
                continue;
            }

            $total += $counts['total'];
            $approved += $counts['approved'];

            $clusters[] = [
                'sahodaya_name' => $propagation->sahodaya->name,
                'event_title'   => $counts['event_title'],
                'total'         => $counts['total'],
                'approved'      => $counts['approved'],
                'manage_url'    => "/sahodaya-admin/{$propagation->sahodaya_id}/events/{$propagation->tenant_event_id}/registrations",
            ];
        }

        return [
            'total_registrations'   => $total,
            'approved_registrations'=> $approved,
            'clusters'              => collect($clusters)->sortByDesc('approved')->take(12)->values(),
        ];
    }
}
