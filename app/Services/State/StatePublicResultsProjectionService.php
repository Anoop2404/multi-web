<?php

namespace App\Services\State;

use App\Models\State\StateFestEvent;
use App\Models\State\StateFestRegistration;

class StatePublicResultsProjectionService
{
    /**
     * Generate privacy-filtered public results projection for State Final events.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPublicResults(StateFestEvent $event): array
    {
        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->where('status', 'approved')
            ->with('participants')
            ->get();

        $publicRows = [];

        foreach ($registrations as $registration) {
            foreach ($registration->participants as $participant) {
                $publicRows[] = [
                    'item_code'      => $registration->item_code,
                    'student_name'   => $participant->student_name,
                    'school_name'    => $registration->school_name ?: 'Participating School',
                    'chest_number'   => $participant->chest_number,
                    'position'       => $participant->meta['position'] ?? null,
                    'grade'          => $participant->meta['grade'] ?? null,
                    'published_at'   => now()->toIso8601String(),
                ];
            }
        }

        return $publicRows;
    }
}
