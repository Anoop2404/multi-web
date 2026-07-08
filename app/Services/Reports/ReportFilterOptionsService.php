<?php

namespace App\Services\Reports;

use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Models\McqExam;
use App\Models\Tenant;
use App\Support\TenancyDatabase;

class ReportFilterOptionsService
{
    /**
     * @param  list<string>  $filterKeys
     * @param  array<string, mixed>  $currentFilters
     * @return array<string, list<array{id: int|string, label: string}>>
     */
    public function forFilters(string $sahodayaId, array $filterKeys, array $currentFilters = []): array
    {
        $options = [];

        foreach ($filterKeys as $key) {
            $options[$key] = match ($key) {
                'school_id' => $this->schoolOptions($sahodayaId),
                'event_id'  => $this->eventOptions($sahodayaId),
                'head_id'   => $this->headOptions($currentFilters['event_id'] ?? null),
                'exam_id'   => $this->examOptions($sahodayaId),
                default     => [],
            };
        }

        return $options;
    }

    /** @return list<array{id: string, label: string}> */
    private function schoolOptions(string $sahodayaId): array
    {
        $ids = TenancyDatabase::schoolIdsFor($sahodayaId);

        if ($ids === []) {
            return [];
        }

        return Tenant::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Tenant $t) => ['id' => $t->id, 'label' => $t->name])
            ->values()
            ->all();
    }

    /** @return list<array{id: int, label: string}> */
    private function eventOptions(string $sahodayaId): array
    {
        return FestEvent::query()
            ->where('tenant_id', $sahodayaId)
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_type', 'event_start'])
            ->map(function (FestEvent $e) {
                $date = $e->event_start?->format('j M Y') ?? '—';

                return [
                    'id'    => $e->id,
                    'label' => "{$e->title} ({$e->event_type}) · {$date}",
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array{id: int, label: string}> */
    private function headOptions(mixed $eventId): array
    {
        if (! $eventId) {
            return [];
        }

        return FestItemHead::query()
            ->where('event_id', (int) $eventId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (FestItemHead $h) => ['id' => $h->id, 'label' => $h->name])
            ->values()
            ->all();
    }

    /** @return list<array{id: int, label: string}> */
    private function examOptions(string $sahodayaId): array
    {
        return McqExam::query()
            ->where('tenant_id', $sahodayaId)
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'exam_level', 'scheduled_at'])
            ->map(function (McqExam $e) {
                $date = $e->scheduled_at?->format('j M Y') ?? '—';
                $level = $e->exam_level ? " · {$e->exam_level}" : '';

                return [
                    'id'    => $e->id,
                    'label' => "{$e->title}{$level} · {$date}",
                ];
            })
            ->values()
            ->all();
    }
}
