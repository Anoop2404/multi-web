<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use Illuminate\Support\Facades\DB;
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
        if ($event?->chest_reveal_mode === 'stage_entry' && ! $this->isRevealed($participant)) {
            return '—';
        }

        $chest = app(FestNumberingService::class)->effectiveChestNumber($participant);

        return (string) ($chest ?? '—');
    }

    private function isRevealed(FestParticipant $participant): bool
    {
        if ($participant->group_id) {
            if (! $participant->relationLoaded('group')) {
                $participant->loadMissing('group');
            }
            if ($participant->group) {
                return (bool) $participant->group->chest_revealed_at;
            }
        }

        return (bool) $participant->chest_revealed_at;
    }

    public function revealAtStageEntry(FestParticipant $participant): void
    {
        $event = $participant->registration?->event;
        abort_unless($event, 404);

        if (($event->chest_reveal_mode ?? 'immediate') !== 'stage_entry') {
            throw new HttpException(422, 'This event does not use stage-entry chest reveal.');
        }

        $participant->loadMissing('group', 'registration.item');
        $item = $participant->registration?->item;
        $numbering = app(FestNumberingService::class);

        if ($item && $participant->group_id && $participant->group && $numbering->isGroupItem($item)) {
            $group = $participant->group;
            if ($group->chest_revealed_at) {
                return;
            }
            if ($group->chest_no === null) {
                $numbering->resolveGroupChestNumber($event, $item, $group);
            }
            $group->update(['chest_revealed_at' => now()]);

            return;
        }

        if ($participant->chest_revealed_at) {
            return;
        }

        if (! $numbering->persistedChestNumber($participant) && $item) {
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

    /**
     * Organizer-entered chest number, replacing auto-generation for this participant
     * (or their whole squad, for team/group items). Locked and scope-checked the same
     * way as nextChestNumber() so a manual entry can't collide with a number another
     * organizer is assigning at the same moment.
     */
    public function setChest(FestParticipant $participant, int $chestNo): void
    {
        $participant->loadMissing('registration.event', 'registration.item', 'group');
        $event = $participant->registration?->event;
        $item = $participant->registration?->item;
        abort_unless($event && $item, 404);

        $numbering = app(FestNumberingService::class);

        DB::transaction(function () use ($participant, $event, $item, $chestNo, $numbering) {
            FestEvent::where('id', $event->id)->lockForUpdate()->first();

            if ($numbering->isGroupItem($item) && $participant->group_id && $participant->group) {
                $group = $participant->group;

                if ($group->chest_is_manual) {
                    throw new HttpException(422, 'This team already has a manually-entered chest number. Clear it first before entering a new one.');
                }

                $conflict = FestGroup::where('event_id', $event->id)
                    ->where('chest_no', $chestNo)
                    ->where('id', '!=', $group->id)
                    ->exists();

                if ($conflict) {
                    throw new HttpException(422, "Chest number {$chestNo} is already assigned to another team.");
                }

                $group->update(['event_id' => $event->id, 'chest_no' => $chestNo, 'chest_is_manual' => true]);

                return;
            }

            if ($participant->chest_is_manual) {
                throw new HttpException(422, 'This participant already has a manually-entered chest number. Clear it first before entering a new one.');
            }

            $headScope = $numbering->chestHeadScope($event, $item);

            $conflict = FestParticipant::where('event_id', $event->id)
                ->where('chest_head_id', $headScope)
                ->where('chest_no', $chestNo)
                ->where('id', '!=', $participant->id)
                ->exists();

            if ($conflict) {
                throw new HttpException(422, "Chest number {$chestNo} is already assigned to another participant.");
            }

            $participant->update([
                'event_id'        => $event->id,
                'chest_head_id'   => $headScope,
                'chest_no'        => $chestNo,
                'chest_is_manual' => true,
            ]);
        });
    }

    public function clearChest(FestParticipant $participant): void
    {
        $participant->loadMissing('registration.event', 'registration.item', 'group');
        $event = $participant->registration?->event;
        $item = $participant->registration?->item;

        if ($item && $participant->group_id && $participant->group
            && $event && app(FestNumberingService::class)->isGroupItem($item)) {
            // Team/group items: clearing one member's chest clears the
            // whole squad's shared number.
            $participant->group->update([
                'chest_no'          => null,
                'chest_is_manual'   => false,
                'chest_revealed_at' => null,
            ]);

            return;
        }

        $eventId = $participant->event_id ?? $participant->registration?->event_id;
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
            'chest_is_manual'   => false,
            'chest_revealed_at' => null,
        ]);
    }

    public function clearAllForEvent(FestEvent $event, ?FestEventItem $item = null): int
    {
        $participantQuery = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id));

        if ($item) {
            $participantQuery->whereHas('registration', fn ($q) => $q->where('item_id', $item->id));
        }

        $clearedCount = $participantQuery->whereNotNull('chest_no')->count();

        $participantQuery->update([
            'chest_no'          => null,
            'chest_is_manual'   => false,
            'chest_revealed_at' => null,
        ]);

        $groupQuery = \App\Models\FestGroup::query()
            ->where('event_id', $event->id);

        if ($item) {
            $groupQuery->whereHas('registration', fn ($q) => $q->where('item_id', $item->id));
        }

        $groupQuery->update([
            'chest_no'          => null,
            'chest_is_manual'   => false,
            'chest_revealed_at' => null,
        ]);

        return $clearedCount;
    }
}
