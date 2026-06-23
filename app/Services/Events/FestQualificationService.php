<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestQualification;
use App\Models\FestRegistration;

class FestQualificationService
{
    /** @return array{promoted: int, skipped: int} */
    public function promoteWinners(FestEvent $fromEvent, FestEvent $toEvent): array
    {
        abort_if($fromEvent->tenant_id !== $toEvent->tenant_id, 422, 'Events must belong to the same Sahodaya.');

        $promoted = 0;
        $skipped = 0;

        $items = FestEventItem::where('event_id', $fromEvent->id)->get();

        foreach ($items as $item) {
            $limit = $item->qualify_count ?? 3;

            $marks = FestMark::where('event_id', $fromEvent->id)
                ->where('item_id', $item->id)
                ->whereNotNull('position')
                ->where('position', '<=', $limit)
                ->with('participant')
                ->orderBy('position')
                ->get();

            $targetItem = $this->matchingItem($toEvent, $item);

            foreach ($marks as $mark) {
                $participant = $mark->participant;
                if (! $participant) {
                    $skipped++;

                    continue;
                }

                $participant->loadMissing('registration');

                $qual = FestQualification::firstOrCreate(
                    [
                        'event_id'       => $fromEvent->id,
                        'item_id'        => $item->id,
                        'participant_id' => $participant->id,
                    ],
                    [
                        'next_level_event_id' => $toEvent->id,
                        'promoted_at'         => now(),
                    ]
                );

                if (! $qual->wasRecentlyCreated) {
                    $skipped++;

                    continue;
                }

                if ($targetItem && $participant->student_id) {
                    $this->ensureRegistration($toEvent, $targetItem, $participant);
                }

                $promoted++;
            }
        }

        return compact('promoted', 'skipped');
    }

    private function matchingItem(FestEvent $toEvent, FestEventItem $fromItem): ?FestEventItem
    {
        return FestEventItem::where('event_id', $toEvent->id)
            ->where('title', $fromItem->title)
            ->first()
            ?? FestEventItem::where('event_id', $toEvent->id)
                ->where('category', $fromItem->category)
                ->where('participant_type', $fromItem->participant_type)
                ->first();
    }

    private function ensureRegistration(FestEvent $event, FestEventItem $item, FestParticipant $source): void
    {
        $registration = FestRegistration::where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->where('school_id', $source->registration->school_id)
            ->whereHas('participants', fn ($q) => $q->where('student_id', $source->student_id))
            ->first();

        if ($registration) {
            return;
        }

        $registration = FestRegistration::create([
            'event_id'     => $event->id,
            'item_id'      => $item->id,
            'school_id'    => $source->registration->school_id,
            'mode'         => 'winner_only',
            'status'       => 'approved',
            'submitted_at' => now(),
        ]);

        FestParticipant::create([
            'registration_id'  => $registration->id,
            'student_id'       => $source->student_id,
            'participant_type' => 'student',
        ]);
    }
}
