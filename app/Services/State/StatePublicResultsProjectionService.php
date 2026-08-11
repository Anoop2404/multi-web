<?php

namespace App\Services\State;

use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;

class StatePublicResultsProjectionService
{
    /**
     * Generate privacy-filtered public results projection for State Final events.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPublicResults(StateFestEvent $event): array
    {
        if (! $event->results_published) {
            return [];
        }

        return StateFestMark::where('state_event_id', $event->id)
            ->where('status', 'published')
            ->whereHas('registration', fn ($query) => $query->where('status', 'approved'))
            ->with(['registration.participants', 'participant'])
            ->orderBy('registration_id')
            ->orderBy('position')
            ->get()
            ->map(function (StateFestMark $mark) use ($event) {
                $participants = $mark->registration?->participants ?? collect();

                return [
                    'item_code' => $mark->registration?->item_code,
                    'student_name' => $participants->pluck('student_name')->filter()->implode(', ')
                        ?: $mark->participant?->student_name
                        ?: 'Participant',
                    'school_name' => $mark->registration?->school_name ?: 'Participating School',
                    'chest_number' => $participants->pluck('chest_number')->filter()->implode(', ')
                        ?: $mark->participant?->chest_number,
                    'position' => $mark->position,
                    'grade' => $mark->grade,
                    'score' => $mark->score,
                    'published_at' => $event->updated_at?->toIso8601String(),
                ];
            })
            ->all();
    }
}
