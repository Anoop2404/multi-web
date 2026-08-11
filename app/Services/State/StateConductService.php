<?php

namespace App\Services\State;

use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use Illuminate\Support\Facades\DB;

class StateConductService
{
    /**
     * Assign chest numbers for accepted State event registrations.
     *
     * @return int Count of chest numbers assigned.
     */
    public function assignChestNumbers(StateFestEvent $event): int
    {
        return DB::connection('state')->transaction(function () use ($event) {
            StateFestEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();

            $registrations = StateFestRegistration::where('state_event_id', $event->id)
                ->where('status', 'approved')
                ->with('participants')
                ->orderBy('id')
                ->get();

            $count = 0;
            $highest = StateFestParticipant::where('state_event_id', $event->id)
                ->whereNotNull('chest_number')
                ->get(['chest_number'])
                ->map(fn ($participant) => ctype_digit((string) $participant->chest_number) ? (int) $participant->chest_number : 0)
                ->max() ?? 100;
            $chestCounter = max(101, $highest + 1);

            foreach ($registrations as $registration) {
                foreach ($registration->participants as $participant) {
                    if (! $participant->chest_number) {
                        $participant->update([
                            'state_event_id' => $event->id,
                            'chest_number' => (string) $chestCounter++,
                        ]);
                        $count++;
                    } elseif (! $participant->state_event_id) {
                        $participant->update(['state_event_id' => $event->id]);
                    }
                }
            }

            return $count;
        });
    }
}
