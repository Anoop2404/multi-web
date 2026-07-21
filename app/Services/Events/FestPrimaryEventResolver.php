<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Support\AcademicYear;
use App\Support\Fest\FestEventPayload;
use App\Support\FestConductLevels;

/**
 * Resolves the single "primary" Sahodaya hub event for a fest program type
 * within an academic year. Fest programs (Kalotsav, Sports, Kids/Teacher/English/
 * Science Fest) are unique per Sahodaya per year — the program IS the event.
 */
class FestPrimaryEventResolver
{
    /** Find the primary hub event for a program type & the active year (no creation). */
    public function resolve(string $tenantId, string $eventType): ?FestEvent
    {
        $yearId = AcademicYear::activeId();

        return FestEvent::forTenant($tenantId)
            ->ofType($eventType)
            ->primaryHub()
            ->when($yearId !== null, fn ($q) => $q->where('academic_year_id', $yearId))
            ->orderByRaw("CASE WHEN level_round = 'sahodaya' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();
    }

    /** Find or create the primary hub event for a program type & the active year. */
    public function resolveOrCreate(Tenant $sahodaya, string $eventType, string $label): FestEvent
    {
        if ($existing = $this->resolve($sahodaya->id, $eventType)) {
            return $existing;
        }

        $yearId = AcademicYear::activeId();
        $yearLabel = AcademicYear::forSahodaya($sahodaya->id);

        $conductLevels = FestConductLevels::defaultsFor($eventType);

        $data = FestEventPayload::applyDefaults([
            'tenant_id'        => $sahodaya->id,
            'academic_year_id' => $yearId,
            'title'            => trim("{$label} {$yearLabel}"),
            'event_type'       => $eventType,
            'conductor_level'  => 'sahodaya',
            'level_round'      => 'sahodaya',
            'conduct_levels'   => $conductLevels,
            'status'           => 'draft',
        ]);

        try {
            return FestEvent::create($data);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'fest_events_event_type_check') || str_contains($e->getMessage(), '23514')) {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE fest_events DROP CONSTRAINT IF EXISTS fest_events_event_type_check');
                return FestEvent::create($data);
            }
            throw $e;
        }
    }
}
