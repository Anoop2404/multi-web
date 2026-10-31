<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestLevelRegistration;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolChestRange;
use App\Support\FestTeamSquadRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FestNumberingService
{
    /** Shared individual sports chest scope (team squads retain their own chest). */
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
            // Off by default so chests wait for bulk "Assign missing" (shuffled),
            // instead of serial issue-on-approve ordered by registration id.
            'auto_assign_chest_on_approve' => false,
        ];

        $stored = is_array($event->numbering_settings) ? $event->numbering_settings : [];

        return array_merge($defaults, $stored);
    }

    public function nextEventRegNumber(FestEvent $event): string
    {
        // Locked and transactional for the same reason as nextChestNumber() below: this is
        // called both from live registration (already inside a FestEvent-locked transaction,
        // where re-acquiring the lock here is a safe no-op) and from admin bulk-assign paths
        // (FestLevelRegistrationService::backfillEvent()) that previously had no lock of their
        // own, allowing them to race with a concurrent registration and hand out duplicate
        // event registration numbers.
        return DB::transaction(function () use ($event) {
            FestEvent::where('id', $event->id)->lockForUpdate()->first();

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
        });
    }

    public function chestHeadScope(FestEvent $event, FestEventItem $item): int
    {
        if ($event->event_type === 'sports') {
            return $this->isGroupItem($item) ? (int) ($item->head_id ?? self::CHEST_SCOPE_EVENT) : self::CHEST_SCOPE_EVENT;
        }

        return (int) $item->id;
    }

    /**
     * Pick the next free chest number for a participant.
     *
     * When $schoolId is given the service first looks for a per-school range
     * configured in fest_school_chest_ranges and restricts the search to
     * [chest_no_start, chest_no_end] for that school. If the school's block
     * is fully exhausted (or no range is configured) it falls back to the
     * event-wide pool starting from the item/event default start.
     *
     * @param  string|null  $schoolId  The school whose range should be preferred.
     */
    public function nextChestNumber(FestEvent $event, FestEventItem $item, ?string $schoolId = null): int
    {
        return DB::transaction(function () use ($event, $item, $schoolId) {
            FestEvent::where('id', $event->id)->lockForUpdate()->first();

            $settings  = $this->settings($event);
            $headScope = $this->chestHeadScope($event, $item);
            $eventIds  = $event->reportableEventIds();
            $itemIds   = $event->reportableItemIds([$item->id]);

            // Every item sharing a head shares one identity space (a student keeps the same
            // chest number across sibling items — see resolveChestAssignment()), so a new
            // number can never collide with ANY number already used anywhere in that head,
            // regardless of which item handed it out. Previously this only tracked the
            // single highest number in scope and always continued past it
            // (max($start, $highest + 1)) — so once any sibling item's numbering climbed
            // above this item's own configured chest_no_start, that start was silently
            // superseded and every item under a shared head was forced onto one combined
            // sequence. Scanning for the lowest free slot at or above this item's own start
            // instead lets each item's configured range apply independently (e.g. item A
            // starting at 100, item B at 500) while still guaranteeing uniqueness within the
            // shared head.
            $usedByParticipants = FestParticipant::whereHas('registration', fn ($q) => $q->whereIn('event_id', $eventIds))
                ->when($event->event_type !== 'sports', fn ($q) => $q->where('chest_head_id', $headScope))
                ->whereNotNull('chest_no')
                ->pluck('chest_no');

            $usedByGroups = FestGroup::whereIn('event_id', $eventIds)
                ->when($event->event_type !== 'sports', fn ($q) => $q->whereHas('registration', fn ($r) => $r->whereIn('item_id', $itemIds)))
                ->whereNotNull('chest_no')
                ->pluck('chest_no');

            $used = $usedByParticipants->merge($usedByGroups)
                ->map(fn ($n) => (int) $n)
                ->flip();

            // --- Per-school chest range (Sports events only) ---
            // If the school has a configured range, try to find a free slot within it first.
            if ($event->event_type === 'sports' && $schoolId) {
                $range = FestSchoolChestRange::where('event_id', $event->id)
                    ->where('school_id', $schoolId)
                    ->first();

                if ($range) {
                    $candidate = $range->chest_no_start;
                    $ceiling   = $range->chest_no_end; // null means open-ended

                    while ($used->has($candidate)) {
                        $candidate++;
                        // If we have a hard ceiling and we've exceeded it, fall through to
                        // the event-wide pool below and warn the admin.
                        if ($ceiling !== null && $candidate > $ceiling) {
                            Log::warning('FestNumberingService: school chest range exhausted', [
                                'event_id'       => $event->id,
                                'school_id'      => $schoolId,
                                'chest_no_start' => $range->chest_no_start,
                                'chest_no_end'   => $range->chest_no_end,
                            ]);
                            $candidate = null;
                            break;
                        }
                    }

                    if ($candidate !== null) {
                        return $candidate;
                    }
                }
            }

            // --- Event-wide fallback (or no school range configured) ---
            $start = (int) ($event->event_type === 'sports'
                ? ($settings['chest_no_start'] ?? 100)
                : ($item->chest_no_start ?? $settings['chest_no_start'] ?? 100));
            $candidate = $start;
            while ($used->has($candidate)) {
                $candidate++;
            }

            return $candidate;
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
    public function resolveGroupChestNumber(FestEvent $event, FestEventItem $item, FestGroup $group, ?string $schoolId = null): ?int
    {
        if ($group->chest_no !== null) {
            return null;
        }

        $schoolId = $schoolId ?? $group->registration?->school_id;
        $chest = $this->nextChestNumber($event, $item, $schoolId);
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
        if ($persisted !== null && ! ($event->event_type === 'sports' && ! $this->isGroupItem($item))) {
            return $persisted;
        }

        $headScope = $this->chestHeadScope($event, $item);
        $eventIds = $event->reportableEventIds();

        $query = FestParticipant::whereHas('registration', fn ($q) => $q->whereIn('event_id', $eventIds))
            ->when($event->event_type === 'sports' && ! $this->isGroupItem($item),
                fn ($q) => $q->whereNull('group_id')->whereNull('disqualified_at')
                    ->where(fn ($r) => $r->whereNull('participant_role')->orWhere('participant_role', '!=', 'standby'))
                    ->whereHas('registration', fn ($r) => $r->active())
                    ->whereDoesntHave('registration.item', fn ($i) => $i->whereIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES)),
                fn ($q) => $q->where('chest_head_id', $headScope))
            ->whereNotNull('chest_no')->orderBy('id');

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
        $persisted = $this->persistedChestNumber($participant);

        if ($participant->group_id) {
            if (! $participant->relationLoaded('group')) {
                $participant->loadMissing('group');
            }
            if ($participant->group?->chest_no !== null) {
                return (int) $participant->group->chest_no;
            }
        }

        if (! $participant->relationLoaded('registration') || ! $participant->registration?->relationLoaded('event') || ! $participant->registration?->relationLoaded('item')) {
            $participant->loadMissing('registration.event', 'registration.item');
        }
        $event = $participant->registration?->event;
        $item = $participant->registration?->item;

        if (! $event || ! $item) {
            return $persisted;
        }

        // Report queries may eager-load only display columns. A loaded relation is
        // not necessarily complete enough to determine the shared sports scope.
        if (! array_key_exists('event_type', $event->getAttributes())) {
            $event = FestEvent::find($event->id);
            $participant->registration->setRelation('event', $event);
        }
        if ($event?->event_type === 'sports' && ! array_key_exists('head_id', $item->getAttributes())) {
            $item = FestEventItem::find($item->id);
            $participant->registration->setRelation('item', $item);
        }

        return $event && $item ? $this->existingChestNumber($event, $item, $participant) : $persisted;
    }

    /**
     * Resolve chest for assignment. Sports individuals keep one chest across all item heads in the event.
     * Other programs retain their item-based chest scope.
     *
     * @param  string|null  $schoolId  When provided, the per-school chest range is preferred.
     * @return array{chest: int, persist: bool, chest_head_id: int}
     */
    public function resolveChestAssignment(FestEvent $event, FestEventItem $item, FestParticipant $participant, ?string $schoolId = null): array
    {
        $headScope = $this->chestHeadScope($event, $item);
        $existing  = $this->existingChestNumber($event, $item, $participant);

        if ($existing !== null) {
            return ['chest' => $existing, 'persist' => false, 'chest_head_id' => $headScope];
        }

        return [
            'chest'         => $this->nextChestNumber($event, $item, $schoolId),
            'persist'       => true,
            'chest_head_id' => $headScope,
        ];
    }

    public function nextItemRegistrationNumber(FestEvent $event, FestEventItem $item): string
    {
        // See the comment on nextEventRegNumber() above — same fix, same reason: this used to
        // read MAX(item_registration_number) with no lock, which was only race-safe when called
        // from live registration (locked by the caller). assignMissingItemRegNumbers() (the
        // admin "assign missing IDs" button) called it with no lock at all.
        return DB::transaction(function () use ($event, $item) {
            FestEvent::where('id', $event->id)->lockForUpdate()->first();

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
        });
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
        $item  = $registration?->item;

        if (! $event || ! $item) {
            return;
        }

        $schoolId  = $registration->school_id ?? null;
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

        $canAssignChest = $event->event_type !== 'sports' || ($participant->participant_role !== 'standby' && ! $participant->disqualified_at);

        if ($canAssignChest && $this->isGroupItem($item) && $participant->group_id && $participant->group) {
            // Team/group items: the number lives on the squad (FestGroup),
            // never on the individual participant row.
            $this->resolveGroupChestNumber($event, $item, $participant->group, $schoolId);
        } elseif ($canAssignChest && ! $this->persistedChestNumber($participant) && $this->shouldAutoAssignChestOnCreate($event, $item)) {
            ['chest' => $chest, 'persist' => $persist, 'chest_head_id' => $chestHeadId] = $this->resolveChestAssignment($event, $item, $participant, $schoolId);
            if ($persist) {
                $updates['chest_no']       = $chest;
                $updates['chest_head_id']  = $chestHeadId;
            }
        }

        if (count($updates) > 1) {
            $participant->update($updates);
        }
    }

    /** Assign chest numbers to participants missing them. */
    public function assignMissingChestNumbers(FestEvent $event, ?FestEventItem $item = null, ?string $schoolId = null): int
    {
        if ($event->event_type === 'sports') {
            return DB::transaction(function () use ($event, $item, $schoolId) {
                FestEvent::whereKey($event->id)->lockForUpdate()->first();
                return $this->assignMissingChestBatch($event, $item, $schoolId);
            });
        }
        return $this->assignMissingChestBatch($event, $item, $schoolId);
    }

    private function assignMissingChestBatch(FestEvent $event, ?FestEventItem $item, ?string $schoolId): int
    {
        $count = 0;
        if ($event->event_type === 'sports') {
            $this->reconcileSportsStudentChests($event, $schoolId);
        }


        // Team/group items: one chest number per squad (FestGroup), not per member.
        //
        // Sports uses stable school/participant order. Other fests remain
        // shuffled, not each()/chunk()'d straight off the query: with no explicit order,
        // Eloquent's chunking enforces ORDER BY id ASC, which is really "whoever's row was
        // inserted first" -- i.e. earliest-submitted registration always wins the lowest
        // chest number, every single time this runs. Since this bulk action only fires once
        // every pending registration for the item already exists, the whole batch is known
        // up front, so shuffling it first is safe (unlike auto-assign-on-approve, which
        // hands out numbers one at a time as registrations trickle in and can't know the
        // final headcount yet) -- nothing here gets renumbered later, it's a one-time batch.
        FestGroup::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->when($event->event_type === 'sports', fn ($q) => $q->active())
            ->when($item, fn ($q2) => $q2->where('item_id', $item->id))
            ->whereHas('item', fn ($qi) => $qi->whereIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES)))
            ->with('registration.item')
            ->whereNull('chest_no')
            ->when($event->event_type === 'sports', fn ($q) => $q->whereHas('participants', fn ($p) => $p
                ->whereNull('disqualified_at')
                ->where(fn ($role) => $role->whereNull('participant_role')->orWhere('participant_role', '!=', 'standby'))))
            ->get()
            ->pipe(fn ($rows) => $event->event_type === 'sports' ? $rows->sortBy([['registration.school_id', 'asc'], ['id', 'asc']]) : $rows->shuffle())
            ->each(function (FestGroup $group) use ($event, &$count) {
                $groupItem = $group->registration?->item;
                if (! $groupItem || ! $this->isGroupItem($groupItem)) {
                    return;
                }

                if ($this->resolveGroupChestNumber($event, $groupItem, $group) !== null) {
                    $count++;
                }
            });

        // For other fests, same shuffle reasoning as the FestGroup block above: no explicit order here
        // either, so this was handing out the lowest chest number to whichever
        // participant row happened to have the lowest id -- i.e. earliest-created
        // registration always won, every time this ran.
        FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->when($event->event_type === 'sports', fn ($q) => $q->active())
            ->when($item, fn ($q2) => $q2->where('item_id', $item->id))
            ->whereDoesntHave('item', fn ($qi) => $qi->whereIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES)))
            ->with('registration.item')
            ->whereNull('chest_no')
            ->whereNull('group_id')
            ->when($event->event_type === 'sports', fn ($q) => $q
                ->whereNull('disqualified_at')
                ->where(fn ($role) => $role->whereNull('participant_role')->orWhere('participant_role', '!=', 'standby')))
            ->get()
            ->pipe(fn ($rows) => $event->event_type === 'sports' ? $rows->sortBy([['registration.school_id', 'asc'], ['student_id', 'asc'], ['id', 'asc']]) : $rows->shuffle())
            ->each(function (FestParticipant $p) use ($event, &$count) {
                if (! $p->registration?->item) {
                    return;
                }

                $item      = $p->registration->item;
                $schoolId  = $p->registration->school_id ?? null;
                $headScope = $this->chestHeadScope($event, $item);
                $existing  = $this->existingChestNumber($event, $item, $p);

                if ($existing !== null) {
                    $p->update([
                        'event_id'      => $event->id,
                        'chest_head_id' => $headScope,
                        // The canonical row owns the number; sibling registrations
                        // resolve it through FestParticipant's chest_no accessor.
                        // Persisting it twice violates event/head/chest uniqueness.
                    ]);
                    $count++;

                    return;
                }

                ['chest' => $chest, 'persist' => $persist, 'chest_head_id' => $chestHeadId] = $this->resolveChestAssignment(
                    $event,
                    $item,
                    $p,
                    $schoolId,
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

    /** Collapse legacy per-head numbers to one stored owner per individual athlete. */
    private function reconcileSportsStudentChests(FestEvent $event, ?string $schoolId): void
    {
        DB::transaction(function () use ($event, $schoolId) {
            FestEvent::whereKey($event->id)->lockForUpdate()->first();
            $participants = FestParticipant::whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)->active()
                ->when($schoolId, fn ($r) => $r->where('school_id', $schoolId))
                ->whereDoesntHave('item', fn ($i) => $i->whereIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES)))
                ->whereNull('group_id')->whereNull('disqualified_at')
                ->where(fn ($q) => $q->whereNull('participant_role')->orWhere('participant_role', '!=', 'standby'))
                ->with('registration.item')->orderBy('id')->lockForUpdate()->get();
            foreach ($participants->groupBy(fn ($p) => $p->student_id ? 's'.$p->student_id : ($p->teacher_id ? 't'.$p->teacher_id : 'p'.$p->id)) as $rows) {
                $owner = $rows->first(fn ($p) => $this->persistedChestNumber($p) !== null);
                if (! $owner) {
                    continue;
                }
                $chest = $this->persistedChestNumber($owner);
                $conflict = FestParticipant::whereHas('registration', fn ($q) => $q->where('event_id', $event->id))
                    ->where('chest_no', $chest)->whereNotIn('id', $rows->pluck('id'))->exists();
                if ($conflict) {
                    $chest = $this->nextChestNumber($event, $owner->registration->item, $owner->registration->school_id);
                }
                FestParticipant::whereIn('id', $rows->pluck('id'))->where('id', '!=', $owner->id)
                    ->update(['chest_no' => null, 'event_id' => $event->id, 'chest_head_id' => self::CHEST_SCOPE_EVENT]);
                $owner->update(['event_id' => $event->id, 'chest_head_id' => self::CHEST_SCOPE_EVENT, 'chest_no' => $chest]);
            }
        });
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
