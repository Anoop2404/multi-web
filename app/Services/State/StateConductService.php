<?php

namespace App\Services\State;

use App\Models\State\StateFestEvent;
use App\Models\State\StateFestRegistration;

class StateConductService
{
    /**
     * Assign chest numbers for accepted State event registrations.
     *
     * @return int Count of chest numbers assigned.
     */
    public function assignChestNumbers(StateFestEvent $event): int
    {
        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->where('status', 'approved')
            ->with('participants')
            ->get();

        $count = 0;
        $chestCounter = 101;

        foreach ($registrations as $registration) {
            foreach ($registration->participants as $participant) {
                if (! $participant->chest_number) {
                    $participant->update(['chest_number' => (string) $chestCounter++]);
                    $count++;
                }
            }
        }

        return $count;
    }
}
