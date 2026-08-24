<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\FestEvent;
use Illuminate\Support\Collection;

class FestEventActivityService
{
    /** @return Collection<int, array<string, mixed>> */
    public function forPage(FestEvent $event, string $page, int $limit = 20): Collection
    {
        return $this->query($event, $page, $limit);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function forEvent(FestEvent $event, int $limit = 20, ?string $page = null, ?int $itemId = null, ?string $search = null): Collection
    {
        return $this->query($event, $page, $limit, $itemId, $search);
    }

    /** @return list<array<string, mixed>> */
    public function forProgram(string $tenantId, string $program, int $limit = 20): array
    {
        return AuditLog::query()
            ->with('user:id,name,email')
            ->where('properties->tenant_id', $tenantId)
            ->where('properties->program', $program)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id'          => $log->id,
                'action'      => $log->action,
                'description' => $log->description,
                'page'        => $log->properties['page'] ?? null,
                'item_id'     => $log->properties['item_id'] ?? null,
                'item_title'  => $log->properties['item_title'] ?? null,
                'chest_no'    => $log->properties['chest_no'] ?? null,
                'participant' => $log->properties['participant'] ?? null,
                'school'      => $log->properties['school'] ?? null,
                'user'        => $log->user?->only('id', 'name', 'email'),
                'created_at'  => $log->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function forCatalog(string $tenantId, string $page, int $limit = 20): array
    {
        return AuditLog::query()
            ->with('user:id,name,email')
            ->where('properties->tenant_id', $tenantId)
            ->where('properties->page', $page)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id'          => $log->id,
                'action'      => $log->action,
                'description' => $log->description,
                'user'        => $log->user?->only('id', 'name', 'email'),
                'created_at'  => $log->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function query(FestEvent $event, ?string $page, int $limit, ?int $itemId = null, ?string $search = null): Collection
    {
        $morph = (new FestEvent)->getMorphClass();
        $eventId = (string) $event->id;

        return AuditLog::query()
            ->with('user:id,name,email')
            ->where(function ($q) use ($morph, $eventId, $event) {
                $q->where(function ($q2) use ($morph, $eventId) {
                    $q2->where('subject_type', $morph)->where('subject_id', $eventId);
                })->orWhere('properties->event_id', $event->id);
            })
            ->when($page !== null && $page !== '', fn ($q) => $q->where('properties->page', $page))
            ->when($itemId !== null, function ($q) use ($itemId) {
                $q->where(function ($q2) use ($itemId) {
                    $q2->where('properties->item_id', $itemId)
                       ->orWhere('properties->item_id', (string) $itemId);
                });
            })
            ->when($search !== null && $search !== '', function ($q) use ($search) {
                $term = '%'.strtolower($search).'%';
                $q->where(function ($q2) use ($term) {
                    $q2->whereRaw('LOWER(description) LIKE ?', [$term])
                       ->orWhereHas('user', fn ($u) => $u->whereRaw('LOWER(name) LIKE ?', [$term]))
                       ->orWhereRaw('LOWER(CAST(properties AS TEXT)) LIKE ?', [$term]);
                });
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id'          => $log->id,
                'action'      => $log->action,
                'description' => $log->description,
                'page'        => $log->properties['page'] ?? null,
                'item_id'     => $log->properties['item_id'] ?? null,
                'item_title'  => $log->properties['item_title'] ?? null,
                'chest_no'    => $log->properties['chest_no'] ?? null,
                'participant' => $log->properties['participant'] ?? null,
                'school'      => $log->properties['school'] ?? null,
                'user'        => $log->user?->only('id', 'name', 'email'),
                'created_at'  => $log->created_at?->toDateTimeString(),
            ]);
    }
}
