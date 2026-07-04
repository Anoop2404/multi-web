<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use Illuminate\Support\Collection;

class FestMandatoryItemService
{
    /** @return Collection<int, FestEventItem> */
    public function missingForSchool(FestEvent $event, string $schoolId): Collection
    {
        $mandatoryIds = FestEventItem::where('event_id', $event->id)
            ->where('is_mandatory', true)
            ->where('is_enabled', true)
            ->pluck('id');

        if ($mandatoryIds->isEmpty()) {
            return collect();
        }

        $registeredIds = FestRegistration::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved'])
            ->whereIn('item_id', $mandatoryIds)
            ->pluck('item_id');

        return FestEventItem::whereIn('id', $mandatoryIds->diff($registeredIds))
            ->orderBy('display_order')
            ->get();
    }

    /** @return list<string> */
    public function validateBeforeApproval(FestEvent $event, string $schoolId): array
    {
        return $this->missingForSchool($event, $schoolId)
            ->map(fn (FestEventItem $item) => "Mandatory item not registered: {$item->title}")
            ->values()
            ->all();
    }

    /** @return list<array{school_id: string, school_name: string, missing: list<string>}> */
    public function schoolsWithMissing(FestEvent $event): array
    {
        $schoolIds = FestRegistration::where('event_id', $event->id)
            ->distinct()
            ->pluck('school_id');

        $schoolNames = \App\Models\Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');

        $rows = [];
        foreach ($schoolIds as $schoolId) {
            $missing = $this->missingForSchool($event, $schoolId);
            if ($missing->isEmpty()) {
                continue;
            }

            $rows[] = [
                'school_id'   => $schoolId,
                'school_name' => $schoolNames[$schoolId] ?? $schoolId,
                'missing'     => $missing->pluck('title')->all(),
            ];
        }

        return $rows;
    }
}
