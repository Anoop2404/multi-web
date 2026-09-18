<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use Illuminate\Validation\ValidationException;
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
     * Manually override a participant's (or their team's) chest number to an exact
     * value an admin chose — e.g. matching a number already printed on a badge.
     * Mirrors clearChest()'s scoping: a group item's number lives on the FestGroup and
     * covers the whole squad, an individual's is shared across every sibling item
     * registration for the same student/teacher within the head scope. Throws 422 if
     * the number collides with anyone else already holding it in that scope.
     */
    public function setChest(FestParticipant $participant, int $chestNo): void
    {
        $participant->loadMissing('registration.event', 'registration.item', 'group');
        $event = $participant->registration?->event;
        $item = $participant->registration?->item;
        abort_unless($event && $item, 404);

        $numbering = app(FestNumberingService::class);
        $headScope = $numbering->chestHeadScope($event, $item);
        $eventIds = $event->reportableEventIds();
        $itemIds = $event->reportableItemIds([$item->id]);

        if ($participant->group_id && $participant->group && $numbering->isGroupItem($item)) {
            $group = $participant->group;

            $taken = FestGroup::whereIn('event_id', $eventIds)
                ->whereHas('registration', fn ($q) => $q->whereIn('item_id', $itemIds))
                ->where('chest_no', $chestNo)
                ->where('id', '!=', $group->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages(['chest_no' => "Chest number {$chestNo} is already in use."]);
            }

            try {
                $group->update(['chest_no' => $chestNo]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // The exists() check above has a genuine TOCTOU gap — two admins (or one
                // admin double-submitting) assigning the same number to two different
                // squads within the same instant can both pass it before either commits.
                // The DB's own unique constraint is the real backstop; this just turns its
                // raw 500 into the same friendly message the pre-check already gives.
                throw ValidationException::withMessages(['chest_no' => "Chest number {$chestNo} is already in use."]);
            }

            return;
        }

        $eventId = $event->id;

        // Only rows that already carry a chest_no are checked for a collision — and per
        // assignMissingChestNumbers()'s convention, event_id is always backfilled onto a
        // row in the same write that gives it a chest_no, so the denormalized column is
        // reliable here.
        $taken = FestParticipant::query()
            ->where('event_id', $eventId)
            ->where('chest_head_id', $headScope)
            ->where('chest_no', $chestNo)
            ->where('id', '!=', $participant->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['chest_no' => "Chest number {$chestNo} is already in use."]);
        }

        // A participant who has never been auto-assigned still carries event_id = null
        // and chest_head_id = 0 (its un-backfilled defaults — see assignMissingChestNumbers()
        // above), so neither can be trusted to locate THIS row; scope through the reliable
        // registration->event_id instead, and match this exact row by id regardless of its
        // stale chest_head_id. The `chest_head_id = $headScope` arm still catches sibling
        // item registrations for the same student/teacher that already share this head
        // scope. Every matched row gets event_id + chest_head_id backfilled along with
        // chest_no, same as assignMissingChestNumbers() does.
        $query = FestParticipant::whereHas('registration', fn ($q) => $q->where('event_id', $eventId))
            ->where(fn ($q) => $q->where('chest_head_id', $headScope)->orWhere('id', $participant->id));

        if ($participant->student_id) {
            $query->where('student_id', $participant->student_id);
        } elseif ($participant->teacher_id) {
            $query->where('teacher_id', $participant->teacher_id);
        } else {
            $query->where('id', $participant->id);
        }

        try {
            $query->update(['event_id' => $eventId, 'chest_head_id' => $headScope, 'chest_no' => $chestNo]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Same TOCTOU gap as the group branch above: the exists() check at line 162
            // and this write aren't atomic, so two near-simultaneous assignments of the
            // same number can both pass the check before either commits — the second to
            // actually write trips fest_participants_event_head_chest_unique. Convert the
            // DB's own 500 into the same validation message the pre-check already
            // produces for the (much more common) non-race case, instead of an admin
            // seeing a raw Internal Server Error page for what is, from their side, just
            // "that number's taken."
            throw ValidationException::withMessages(['chest_no' => "Chest number {$chestNo} is already in use."]);
        }
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
            'chest_revealed_at' => null,
        ]);

        $groupQuery = \App\Models\FestGroup::query()
            ->where('event_id', $event->id);

        if ($item) {
            $groupQuery->whereHas('registration', fn ($q) => $q->where('item_id', $item->id));
        }

        $groupQuery->update([
            'chest_no'          => null,
            'chest_revealed_at' => null,
        ]);

        return $clearedCount;
    }
}
