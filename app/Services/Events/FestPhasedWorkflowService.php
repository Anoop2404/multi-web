<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestPhaseRegion;
use App\Models\FestRegistrationBatch;
use App\Models\FestMark;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FestPhasedWorkflowService
{
    public const MODE = 'phased_regional_billing';

    public function upsertBatch(FestEvent $event, string $code, array $defaults, array $overrides = []): FestRegistrationBatch
    {
        $batch = FestRegistrationBatch::firstOrNew(['event_id' => $event->id, 'code' => $code]);
        if ($batch->exists && $batch->status !== 'draft') {
            unset($defaults['status']);
        }
        $batch->fill(array_merge($defaults, $overrides, ['code' => $code]));
        $batch->save();

        return $batch;
    }

    /**
     * @param  list<int>  $regionIds
     * @param  array<int, ?string>  $venues  Venue per region_id. A region_id absent from this
     *   array leaves its existing venue untouched (callers that don't manage venue, e.g. the
     *   config-driven FestPhasedStructureConfigurator, don't need to pass this at all).
     * @param  array<int, array{conduct_start?: ?string, conduct_end?: ?string, registration_open?: ?string, registration_close?: ?string}>  $dates
     *   Per-region conduct and registration window — a regional phase can run on different
     *   actual dates per region (e.g. Tirur Region vs Manjeri Region), overriding the
     *   phase/event's own dates. A region_id absent from this array leaves its existing
     *   dates untouched.
     */
    public function syncAllowedRegions(FestEventPhase $phase, array $regionIds, array $venues = [], array $dates = []): void
    {
        $regionIds = collect($regionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        $removed = FestPhaseRegion::where('phase_id', $phase->id)
            ->where('enabled', true)
            ->whereNotIn('region_id', $regionIds->all())
            ->pluck('region_id');

        foreach ($removed as $regionId) {
            $leaf = FestEvent::where('parent_event_id', $phase->event_id)
                ->where('source_phase_id', $phase->id)
                ->where('region_id', $regionId)
                ->first();
            if ($leaf && (FestRegistration::where('event_id', $leaf->id)->exists()
                || FestSchedule::where('event_id', $leaf->id)->exists()
                || FestMark::where('event_id', $leaf->id)->exists())) {
                throw ValidationException::withMessages([
                    'region_ids' => 'A region cannot be removed after registrations, scheduling, or mark entry has started for this phase.',
                ]);
            }
        }

        FestPhaseRegion::where('phase_id', $phase->id)
            ->whereNotIn('region_id', $regionIds->all())
            ->update(['enabled' => false]);

        FestEvent::where('parent_event_id', $phase->event_id)
            ->where('source_phase_id', $phase->id)
            ->whereIn('region_id', $removed)
            ->update(['nav_hidden' => true, 'registration_locked' => true]);

        foreach ($regionIds as $regionId) {
            $attrs = ['enabled' => true];
            if (array_key_exists($regionId, $venues)) {
                $attrs['venue'] = $venues[$regionId] ?: null;
            }
            if (array_key_exists($regionId, $dates)) {
                $attrs['conduct_start_at'] = $dates[$regionId]['conduct_start'] ?? null;
                $attrs['conduct_end_at'] = $dates[$regionId]['conduct_end'] ?? null;
                $attrs['registration_open'] = $dates[$regionId]['registration_open'] ?? null;
                $attrs['registration_close'] = $dates[$regionId]['registration_close'] ?? null;
            }
            FestPhaseRegion::updateOrCreate(
                ['phase_id' => $phase->id, 'region_id' => $regionId],
                $attrs
            );
        }
    }
}
