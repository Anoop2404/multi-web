<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FestChestNumberService
{
    public function participantLabel(FestParticipant $participant): string
    {
        $item = $participant->registration?->item;
        $isOffStage = ($item?->stage_type ?? '') === 'off_stage';

        if ($isOffStage) {
            return $participant->level_registration_number
                ?? $participant->student?->reg_no
                ?? $participant->student?->admission_number
                ?? (string) $participant->id;
        }

        $event = $participant->registration?->event;
        if ($event?->chest_reveal_mode === 'stage_entry' && ! $participant->chest_revealed_at) {
            return '—';
        }

        return (string) ($participant->chest_no ?? '—');
    }

    public function revealAtStageEntry(FestParticipant $participant): void
    {
        $event = $participant->registration?->event;
        abort_unless($event, 404);

        if (($event->chest_reveal_mode ?? 'immediate') !== 'stage_entry') {
            throw new HttpException(422, 'This event does not use stage-entry chest reveal.');
        }

        if ($participant->chest_revealed_at) {
            return;
        }

        $numbering = app(FestNumberingService::class);
        if (! $numbering->persistedChestNumber($participant) && $participant->registration?->item) {
            $item = $participant->registration->item;
            ['chest' => $chest, 'persist' => $persist, 'chest_head_id' => $chestHeadId] = $numbering->resolveChestAssignment(
                $event,
                $item,
                $participant
            );
            if ($persist) {
                $participant->update([
                    'chest_no'      => $chest,
                    'chest_head_id' => $chestHeadId,
                ]);
            }
        }

        $participant->update(['chest_revealed_at' => now()]);
    }

    public function revealFromSchedule(FestSchedule $schedule): int
    {
        $revealed = 0;
        $event = FestEvent::find($schedule->event_id);
        if (! $event || $event->chest_reveal_mode !== 'stage_entry') {
            return 0;
        }

        FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('item_id', $schedule->item_id))
            ->whereNull('chest_revealed_at')
            ->each(function (FestParticipant $p) use (&$revealed) {
                $this->revealAtStageEntry($p);
                $revealed++;
            });

        return $revealed;
    }

    public function clearChest(FestParticipant $participant): void
    {
        $participant->loadMissing('registration.event', 'registration.item');
        $eventId = $participant->event_id ?? $participant->registration?->event_id;
        $event = $participant->registration?->event;
        $item = $participant->registration?->item;
        $headScope = ($event && $item)
            ? app(FestNumberingService::class)->chestHeadScope($event, $item)
            : (int) ($participant->chest_head_id ?? FestNumberingService::CHEST_SCOPE_EVENT);

        $query = FestParticipant::query()
            ->where('event_id', $eventId)
            ->where('chest_head_id', $headScope);

        if ($participant->student_id) {
            $query->where('student_id', $participant->student_id);
        } elseif ($participant->teacher_id) {
            $query->where('teacher_id', $participant->teacher_id);
        } else {
            $query->where('id', $participant->id);
        }

        $query->update([
            'chest_no'          => null,
            'chest_revealed_at' => null,
        ]);
    }
}
