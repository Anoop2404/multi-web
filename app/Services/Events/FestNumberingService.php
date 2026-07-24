<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestLevelRegistration;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Support\FestTeamSquadRules;
use Illuminate\Support\Facades\DB;

class FestNumberingService
{
    /** Event-wide chest scope for non-sports (or items without a head). */
    public const CHEST_SCOPE_EVENT = 0;

    /** @return array<string, mixed> */
    public function settings(FestEvent $event): array
    {
        $defaults = [
            'event_reg_start' => 1,
            'event_reg_prefix' => '',
            'chest_no_start' => 100,
            'chest_no_prefix' => '',
            'auto_assign_on_approve' => true,
            'auto_assign_chest_on_create' => false,
        ];

        $stored = is_array($event->numbering_settings) ? $event->numbering_settings : [];

        return array_merge($defaults, $stored);
    }

    public function nextEventRegNumber(FestEvent $event): string
    {
        $settings = $this->settings($event);
        $prefix = (string) ($settings['event_reg_prefix'] ?? '');
        $start = (int) ($settings['event_reg_start'] ?? 1);

        $fromLevelRegs = FestLevelRegistration::where('event_id', $event->id)
            ->pluck('registration_number')
            ->map(fn (?string $num) => $this->parseSequence($num, $prefix));

        $fromParticipants = FestParticipant::where('event_id', $event->id)
            ->whereNotNull('level_registration_number')
            ->pluck('level_registration_number')
            ->map(fn (?string $num) => $this->parseSequence($num, $prefix));

        $maxSeq = $fromLevelRegs->merge($fromParticipants)->max();
        $next = max($start, ($maxSeq ?? 0) + 1);

        if ($prefix === '') {
            return (string) $next;
        }

        return $prefix.sprintf('%04d', $next);
    }

    public function chestHeadScope(FestEvent $event, FestEventItem $item): int
    {
        if ($event->event_type === 'sports') {
            return (int) ($item->head_id ?? self::CHEST_SCOPE_EVENT);
        }

        return (int) $item->id;
    }

    public function nextChestNumber(FestEvent $event, FestEventItem $item): int
    {
        return DB::transaction(function () use ($event, $item) {
            FestEvent::where('id', $event->id)->lockForUpdate()->first();

            $settings = $this->settings($event);
            $start = (int) ($item->chest_no_start ?? $settings['chest_no_start'] ?? 100);
            $headScope = $this->chestHeadScope($event, $item);

            $max = FestParticipant::where('event_id', $event->id)
                ->where('chest_head_id', $headScope)
                ->whereNotNull('chest_no')
                ->max('chest_no');

            $groupMax = FestGroup::where('event_id', $event->id)
                ->whereHas('registration', fn ($q) => $q->where('item_id', $item->id))
                ->whereNotNull('chest_no')
                ->max('chest_no');

            $highest = max((int) $max, (int) $groupMax);

            return $highest > 0 ? max($start, $highest + 1) : $start;
        });
    }

    /** Team/group/pair/trio items get ONE chest number for the whole squad. */
    public function isGroupItem(FestEventItem $item): bool
    {
        return FestTeamSquadRules::isMultiPerson($item->participant_type);
    }

    /**
     * Resolve/assign the shared chest number for a team/group registration.
     * Returns the newly-assigned number, or null if the group already had one.
     */
    public function resolveGroupChestNumber(FestEvent $event, FestEventItem $item, FestGroup $group): ?int
    {
        if ($group->chest_no !== null) {
            return null;
        }

        $chest = $this->nextChestNumber($event, $item);
        $group->update(['event_id' => $event->id, 'chest_no' => $chest]);

        return $chest;
    }

    public function persistedChestNumber(FestParticipant $participant): ?int
    {
        $raw = $participant->getAttributes()['chest_no'] ?? null;

        return $raw !== null ? (int) $raw : null;
    }

    /** Chest number already assigned to this person in the event for the item's head scope. */
    public function existingChestNumber(FestEvent $event, FestEventItem $item, FestParticipant $participant): ?int
    {
        $persisted = $this->persistedChestNumber($participant);
        if ($persisted !== null) {
            return $persisted;
        }

        $headScope = $this->chestHeadScope($event, $item);

        $query = FestParticipant::where('event_id', $event->id)
            ->where('chest_head_id', $headScope)
            ->whereNotNull('chest_no');

        if ($participant->student_id) {
            $value = (clone $query)->where('student_id', $participant->student_id)->value('chest_no');

            return $value !== null ? (int) $value : null;
        }

        if ($participant->teacher_id) {
            $value = (clone $query)->where('teacher_id', $participant->teacher_id)->value('chest_no');

            return $value !== null ? (int) $value : null;
        }

        return null;
    }

    /** Resolved chest for display — includes sibling registrations under the same item head. */
    public function effectiveChestNumber(FestParticipant $participant): ?int
    {
        $participant->loadMissing('group');
        if ($participant->group_id && $participant->group?->chest_no !== null) {
            return (int) $participant->group->chest_no;
        }

        $persisted = $this->persistedChestNumber($participant);
        if ($persisted !== null) {
            return $persisted;
        }

        $participant->loadMissing('registration.event', 'registration.item');
        $event = $participant->registration?->event;
        $item = $participant->registration?->item;

        if (! $event || ! $item) {
            return null;
        }

        return $this->existingChestNumber($event, $item, $participant);
    }

    /**
     * Resolve chest for assignment. Same student/teacher keeps one chest per item head (sports)
     * or per event (other fest types).
     *
     * @return array{chest: int, persist: bool, chest_head_id: int}
     */
    public function resolveChestAssignment(FestEvent $event, FestEventItem $item, FestParticipant $participant): array
    {
        $headScope = $this->chestHeadScope($event, $item);
        $existing = $this->existingChestNumber($event, $item, $participant);

        if ($existing !== null) {
            return ['chest' => $existing, 'persist' => false, 'chest_head_id' => $headScope];
        }

        return [
            'chest'          => $this->nextChestNumber($event, $item),
            'persist'        => true,
            'chest_head_id'  => $headScope,
        ];
    }

    public function nextItemRegistrationNumber(FestEvent $event, FestEventItem $item): string
    {
        $start = (int) ($item->item_reg_id_start ?? 1);

        $maxSeq = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('item_id', $item->id))
            ->whereNotNull('item_registration_number')
            ->pluck('item_registration_number')
            ->map(fn (?string $num) => $this->parseSequence($num, ''))
            ->max();

        $next = max($start, ($maxSeq ?? 0) + 1);

        return (string) $next;
    }

    public function shouldAutoAssignChestOnCreate(FestEvent $event, FestEventItem $item): bool
    {
        $settings = $this->settings($event);

        if ($settings['auto_assign_chest_on_create'] ?? false) {
            return true;
        }

        return $event->event_type === 'sports' && (bool) $item->head_id;
    }

    public function assignParticipantNumbers(FestParticipant $participant): void
    {
        $participant->loadMissing('registration.event', 'registration.item', 'student', 'group');
        $registration = $participant->registration;
        $event = $registration?->event;
        $item = $registration?->item;

        if (! $event || ! $item) {
            return;
        }

        $headScope = $this->chestHeadScope($event, $item);
        $updates = [
            'event_id'      => $event->id,
            'chest_head_id' => $headScope,
        ];

        if (! $participant->level_registration_number) {
            if ($participant->student) {
                $updates['level_registration_number'] = app(FestLevelRegistrationService::class)
                    ->issueForStudent($event, $participant->student);
            } elseif ($participant->teacher) {
                $updates['level_registration_number'] = app(FestLevelRegistrationService::class)
                    ->issueForTeacher($event, $participant->teacher);
            }
        }

        if (! $participant->item_registration_number) {
            $updates['item_registration_number'] = $this->nextItemRegistrationNumber($event, $item);
        }

        if ($this->isGroupItem($item) && $participant->group_id && $participant->group) {
            // Team/group items: the number lives on the squad (FestGroup),
            // never on the individual participant row.
            $this->resolveGroupChestNumber($event, $item, $participant->group);
        } elseif (! $this->persistedChestNumber($participant) && $this->shouldAutoAssignChestOnCreate($event, $item)) {
            ['chest' => $chest, 'persist' => $persist, 'chest_head_id' => $chestHeadId] = $this->resolveChestAssignment($event, $item, $participant);
            if ($persist) {
                $updates['chest_no'] = $chest;
                $updates['chest_head_id'] = $chestHeadId;
            }
        }

        if (count($updates) > 1) {
            $participant->update($updates);
        }
    }

    /** Assign chest numbers to participants missing them. */
    public function assignMissingChestNumbers(FestEvent $event, ?FestEventItem $item = null): int
    {
        $count = 0;

        // Team/group items: one chest number per squad (FestGroup), not per member.
        FestGroup::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->when($item, fn ($q2) => $q2->where('item_id', $item->id))
            ->whereHas('item', fn ($qi) => $qi->whereIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES)))
            ->with('registration.item')
            ->whereNull('chest_no')
            ->each(function (FestGroup $group) use ($event, &$count) {
                $groupItem = $group->registration?->item;
                if (! $groupItem || ! $this->isGroupItem($groupItem)) {
                    return;
                }

                if ($this->resolveGroupChestNumber($event, $groupItem, $group) !== null) {
                    $count++;
                }
            });

        FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->when($item, fn ($q2) => $q2->where('item_id', $item->id))
            ->whereDoesntHave('item', fn ($qi) => $qi->whereIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES)))
            ->with('registration.item')
            ->whereNull('chest_no')
            ->whereNull('group_id')
            ->each(function (FestParticipant $p) use ($event, &$count) {
                if (! $p->registration?->item) {
                    return;
                }

                $item = $p->registration->item;
                ['chest' => $chest, 'persist' => $persist, 'chest_head_id' => $chestHeadId] = $this->resolveChestAssignment(
                    $event,
                    $item,
                    $p
                );

                if (! $persist) {
                    return;
                }

                $p->update([
                    'event_id'      => $event->id,
                    'chest_head_id' => $chestHeadId,
                    'chest_no'      => $chest,
                ]);
                $count++;
            });

        return $count;
    }

    /** Assign item reg numbers to participants missing them. */
    public function assignMissingItemRegNumbers(FestEvent $event, ?FestEventItem $item = null): int
    {
        $count = 0;

        FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->when($item, fn ($q2) => $q2->where('item_id', $item->id)))
            ->with('registration.item')
            ->whereNull('item_registration_number')
            ->each(function (FestParticipant $p) use ($event, &$count) {
                if (! $p->registration?->item) {
                    return;
                }
                $this->assignParticipantNumbers($p);
                $count++;
            });

        return $count;
    }

    private function parseSequence(?string $value, string $prefix): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if ($prefix !== '' && str_starts_with($value, $prefix)) {
            $tail = substr($value, strlen($prefix));

            return ctype_digit($tail) ? (int) $tail : 0;
        }

        if ($prefix === '' && ctype_digit($value)) {
            return (int) $value;
        }

        if (preg_match('/(\d+)$/', $value, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
