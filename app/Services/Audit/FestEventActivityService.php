<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Services\Events\PublicFestScoreboardService;
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
                'ip_address'  => $log->ip_address,
                'user'        => $log->user?->only('id', 'name', 'email'),
                'created_at'  => $log->created_at?->toIso8601String(),
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
                'ip_address'  => $log->ip_address,
                'user'        => $log->user?->only('id', 'name', 'email'),
                'created_at'  => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function query(FestEvent $event, ?string $page, int $limit, ?int $itemId = null, ?string $search = null): Collection
    {
        $morph = (new FestEvent)->getMorphClass();
        $eventId = (string) $event->id;

        $logs = AuditLog::query()
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
                       ->orWhereRaw('LOWER(CAST(properties AS TEXT)) LIKE ?', [$term])
                       ->orWhereRaw('LOWER(COALESCE(ip_address, \'\')) LIKE ?', [$term]);
                });
            })
            ->latest()
            ->limit($limit)
            ->get();

        // Batch resolve missing participant details from description regex or properties
        $missingParticipantIds = [];
        $itemIds = [];

        foreach ($logs as $log) {
            $props = $log->properties ?? [];
            if (! empty($props['item_id'])) {
                $itemIds[] = (int) $props['item_id'];
            }
            $pid = $props['participant_id'] ?? null;
            if (! $pid && preg_match('/participant\s+#(\d+)/i', $log->description, $matches)) {
                $pid = (int) $matches[1];
            }
            if ($pid && (empty($props['participant']) || empty($props['chest_no']))) {
                $missingParticipantIds[] = (int) $pid;
            }
        }

        $participantsMap = collect();
        if (! empty($missingParticipantIds)) {
            $participantsMap = FestParticipant::whereIn('id', array_unique($missingParticipantIds))
                ->with(['student', 'teacher', 'group', 'registration.school', 'registration.item'])
                ->get()
                ->keyBy('id');
        }

        $itemsMap = collect();
        if (! empty($itemIds)) {
            $itemsMap = FestEventItem::whereIn('id', array_unique($itemIds))
                ->get()
                ->keyBy('id');
        }

        $scoreboards = app(PublicFestScoreboardService::class);

        return $logs->map(function (AuditLog $log) use ($participantsMap, $itemsMap, $event, $scoreboards) {
            $props = $log->properties ?? [];
            $pid = $props['participant_id'] ?? null;
            if (! $pid && preg_match('/participant\s+#(\d+)/i', $log->description, $matches)) {
                $pid = (int) $matches[1];
            }

            $participant = $pid ? $participantsMap->get((int) $pid) : null;
            $personName = $props['participant'] ?? $participant?->student?->name ?? $participant?->teacher?->name ?? $participant?->group?->name;
            $chestNo = $props['chest_no'] ?? $participant?->group?->chest_no ?? $participant?->chest_no;
            $schoolName = $props['school'] ?? $participant?->registration?->school?->name;
            $regNo = $participant?->student?->reg_no ?? $participant?->teacher?->reg_no;

            $itemId = $props['item_id'] ?? $participant?->registration?->item_id;
            $itemModel = $itemId ? $itemsMap->get((int) $itemId) : null;
            $itemTitle = $props['item_title'] ?? $itemModel?->title ?? $participant?->registration?->item?->title;
            $categoryKey = $itemModel?->class_group ?? $itemModel?->age_group;
            $categoryLabel = $categoryKey ? $scoreboards->categoryLabel($event, $categoryKey) : null;

            $description = $log->description;
            if (str_starts_with($description, 'Mark saved for participant #') && $participant) {
                $chestLabel = $chestNo ? "Chest #{$chestNo}" : "Participant #{$pid}";
                $itemLabel = $itemTitle ? " in {$itemTitle}" : '';
                $schoolLabel = $schoolName ? " ({$schoolName})" : '';
                $description = "Mark saved for {$chestLabel} - {$personName}{$schoolLabel}{$itemLabel}";
            }

            return [
                'id'            => $log->id,
                'action'        => $log->action,
                'description'   => $description,
                'page'          => $props['page'] ?? null,
                'item_id'       => $itemId,
                'item_title'    => $itemTitle,
                'item_code'     => $itemModel?->item_code,
                'item_category' => $categoryLabel,
                'chest_no'      => $chestNo,
                'participant'   => $personName,
                'school'        => $schoolName,
                'reg_no'        => $regNo,
                'ip_address'    => $log->ip_address,
                'user'          => $log->user?->only('id', 'name', 'email'),
                'properties'    => $props,
                'created_at'    => $log->created_at?->toIso8601String(),
            ];
        });
    }
}
