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

        if (! $participant->chest_no && $participant->registration?->item) {
            $participant->update([
                'chest_no' => EventContext::for($event)->nextChestNumber($participant->registration->item),
            ]);
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
        $participant->update([
            'chest_no'          => null,
            'chest_revealed_at' => null,
        ]);
    }
}
