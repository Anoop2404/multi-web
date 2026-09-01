<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventStaff;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestVolunteer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\FestSportsAgeGroup;
use App\Support\FestTeamSquadRules;
use App\Support\TenantStorage;

class FestIdCardService
{
    /**
     * Chest numbers are Sahodaya-admin-only information — schools don't see them on
     * generated ID cards regardless of the event's chest_reveal_mode (that setting
     * controls WHEN a chest number becomes visible at all — immediately vs. at stage
     * entry — not WHO it's visible to; a school must not see it even once "revealed").
     * FestSchoolReportController sets this true before calling any card-building
     * method; Sahodaya-admin's FestIdCardController leaves the default, since it does
     * need this field (and already respects chest_reveal_mode via
     * FestChestNumberService::participantLabel()).
     */
    public bool $hideChestNo = false;

    public function __construct(
        private FestIdCardQrService $qrService,
        private FestChestNumberService $chestService,
        private FestItemWindowResolver $itemWindows,
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function requireStudentItem(string $audience, array $filters): void
    {
        if ($audience !== 'student') {
            return;
        }

        $scope = $filters['scope'] ?? 'event';

        if (in_array($scope, ['event', 'head_all'], true)) {
            return;
        }

        if ($scope === 'head' && empty($filters['head_id'])) {
            return; // Allow fallback to event scope
        }

        if ($scope === 'item' && empty($filters['item_id'])) {
            return; // Allow fallback to event scope
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{item_title: string, item_id: int, cards: list<array<string, mixed>>}>
     */
    public function cardsGroupedByItem(FestEvent $event, array $filters = []): array
    {
        // Only a genuine season hub with head-tagged items (fest_event_items.head_id
        // set) should be grouped by head — a standalone sport event (e.g. a single
        // Chess event with no item heads at all) has no head_id on its items, so
        // grouping by head would silently return zero participants. See the
        // matching hasItemHeads fix in FestHeadItemNavigationService/report hubs.
        if ($event->event_type === 'sports' && FestEventItem::where('event_id', $event->id)->whereNotNull('head_id')->exists()) {
            return collect($this->cardsGroupedByHead($event, $filters))
                ->map(fn (array $section) => [
                    'item_title' => $section['head_title'],
                    'item_id'    => $section['head_id'],
                    'cards'      => $section['cards'],
                ])
                ->values()
                ->all();
        }

        $event->loadMissing(['items' => fn ($q) => $q->where('is_enabled', true)->orderBy('title')]);

        $layout = $filters['layout'] ?? 'individual';
        $sections = [];

        foreach ($event->items as $item) {
            $itemFilters = array_merge($filters, [
                'item_id' => $item->id,
                'scope'   => 'item',
            ]);

            if ($layout === 'team' && FestTeamSquadRules::isMultiPerson($item->participant_type)) {
                $cards = $this->teamCards($event, $itemFilters);
            } else {
                $cards = $this->individualStudentCards($event, $itemFilters);
            }

            if ($cards !== []) {
                $sections[] = [
                    'item_title' => $item->title,
                    'item_id'    => $item->id,
                    'cards'      => $cards,
                ];
            }
        }

        return $sections;
    }

    /**
     * One ID card section per main item head; items listed on each card footer.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{head_title: string, head_id: int, cards: list<array<string, mixed>>}>
     */
    public function cardsGroupedByHead(FestEvent $event, array $filters = []): array
    {
        $cards = $this->buildHeadParticipantCards($event, $filters);

        return collect($cards)
            ->groupBy('head_id')
            ->map(function ($headCards, $headId) {
                $first = $headCards->first();

                return [
                    'head_title' => $first['head_label'] ?? 'Item head',
                    'head_id'    => (int) $headId,
                    'cards'      => $headCards->values()->all(),
                ];
            })
            ->sortBy('head_title')
            ->values()
            ->all();
    }

    /** @return list<array{id: int, name: string, count: int}> */
    public function headOptions(FestEvent $event, ?string $schoolId = null): array
    {
        $counts = $this->headParticipantCounts($event, $schoolId);

        if ($counts === []) {
            return [];
        }

        return FestItemHead::query()
            ->whereIn('id', array_keys($counts))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (FestItemHead $head) => [
                'id'    => $head->id,
                'name'  => $head->name,
                'count' => $counts[$head->id] ?? 0,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, int> head_id => card count */
    public function headParticipantCounts(FestEvent $event, ?string $schoolId = null): array
    {
        return collect($this->buildHeadParticipantCards($event, array_filter([
            'school_id'        => $schoolId,
            'school_downloads' => $schoolId !== null,
        ])))
            ->groupBy('head_id')
            ->map(fn ($group) => $group->count())
            ->all();
    }

    /** @return array<string, mixed> */
    public function indexMeta(FestEvent $event, ?string $schoolId = null): array
    {
        return [
            'students'   => $this->studentCount($event, $schoolId),
            'heads'      => array_sum($this->headParticipantCounts($event, $schoolId)),
            'volunteers' => FestVolunteer::where('event_id', $event->id)->count(),
            'staff'      => FestEventStaff::where('event_id', $event->id)->count(),
            'schools'    => $this->schoolOptions($event),
        ];
    }

    /** @return array<int, int> item_id => participant count */
    public function itemParticipantCounts(FestEvent $event, ?string $schoolId = null, bool $schoolDownloads = false): array
    {
        $filters = array_filter([
            'school_id'        => $schoolId,
            'school_downloads' => $schoolDownloads || $schoolId !== null,
        ]);

        $rows = FestParticipant::query()
            ->whereHas('registration', function ($q) use ($event, $filters) {
                $q->whereIn('event_id', $event->reportableEventIds());
                $this->constrainRegistrationStatus($q, $filters);
                if (! empty($filters['school_id'])) {
                    $q->where('school_id', $filters['school_id']);
                }
            })
            ->where('participant_role', '!=', 'standby')
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->join('fest_registrations', 'fest_participants.registration_id', '=', 'fest_registrations.id')
            ->selectRaw('fest_registrations.item_id, COUNT(*) as aggregate')
            ->groupBy('fest_registrations.item_id')
            ->pluck('aggregate', 'item_id');

        return $this->normalizeItemCounts($rows);
    }

    /** @return array<int, int> item_id => approved registration count */
    public function itemRegistrationCounts(FestEvent $event, ?string $schoolId = null, bool $schoolDownloads = false): array
    {
        $filters = array_filter([
            'school_id'        => $schoolId,
            'school_downloads' => $schoolDownloads || $schoolId !== null,
        ]);

        $query = FestRegistration::query()->whereIn('event_id', $event->reportableEventIds());
        $this->constrainRegistrationStatus($query, $filters);
        if (! empty($filters['school_id'])) {
            $query->where('school_id', $filters['school_id']);
        }

        $rows = $query
            ->selectRaw('item_id, COUNT(*) as aggregate')
            ->groupBy('item_id')
            ->pluck('aggregate', 'item_id');

        return $this->normalizeItemCounts($rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function cards(FestEvent $event, string $audience, array $filters = []): array
    {
        return match ($audience) {
            'volunteer' => $this->volunteerCards($event, $filters),
            'staff'     => $this->staffCards($event, $filters),
            default     => $this->studentCards($event, $filters),
        };
    }

    /** @return list<array<string, string>> */
    public function schoolOptions(FestEvent $event): array
    {
        return Tenant::where('parent_id', $event->tenant_id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Tenant $t) => ['id' => $t->id, 'name' => $t->name])
            ->values()
            ->all();
    }

    private function studentCount(FestEvent $event, ?string $schoolId): int
    {
        $filters = array_filter([
            'school_id'        => $schoolId,
            'school_downloads' => $schoolId !== null,
        ]);

        return FestParticipant::whereHas('registration', function ($q) use ($event, $filters) {
            $q->whereIn('event_id', $event->reportableEventIds());
            $this->constrainRegistrationStatus($q, $filters);
            if (! empty($filters['school_id'])) {
                $q->where('school_id', $filters['school_id']);
            }
        })
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->where('participant_role', '!=', 'standby')
            ->count();
    }

    /** @return array<int, int> */
    private function normalizeItemCounts(\Illuminate\Support\Collection $counts): array
    {
        $items = FestEventItem::whereIn('id', $counts->keys())
            ->get(['id', 'inherited_from_item_id'])
            ->keyBy('id');

        return $counts->reduce(function (array $result, $count, $itemId) use ($items) {
            $item = $items->get((int) $itemId);
            $key = (int) ($item?->inherited_from_item_id ?: $itemId);
            $result[$key] = ($result[$key] ?? 0) + (int) $count;

            return $result;
        }, []);
    }

    /** @param  array<string, mixed>  $filters */
    private function studentCards(FestEvent $event, array $filters): array
    {
        $scope = $filters['scope'] ?? 'item';

        if ($scope === 'event') {
            return $this->eventParticipantCards($event, $filters);
        }

        if ($scope === 'head') {
            return $this->buildHeadParticipantCards($event, $filters);
        }

        $layout = $filters['layout'] ?? 'individual';

        if ($layout === 'team') {
            return $this->teamCards($event, $filters);
        }

        return $this->individualStudentCards($event, $filters);
    }

    /**
     * One card per student/teacher per item head, listing all items under that head.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function buildHeadParticipantCards(FestEvent $event, array $filters): array
    {
        $schoolId = $filters['school_id'] ?? null;
        $headId = isset($filters['head_id']) ? (int) $filters['head_id'] : null;
        $participantIds = $filters['participant_ids'] ?? null;

        $query = FestParticipant::whereHas('registration', function ($q) use ($event, $filters) {
            $q->whereIn('event_id', $event->reportableEventIds());
            $this->constrainRegistrationStatus($q, $filters);
            if (! empty($filters['school_id'])) {
                $q->where('school_id', $filters['school_id']);
            }
            $q->whereHas('item', fn ($q2) => $q2->whereNotNull('head_id'));
        })
            ->where('participant_role', '!=', 'standby')
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->with([
                'student.tenant',
                'teacher.tenant',
                'registration.item.head',
                'registration.school',
                'registration.event.sourcePhase',
                'registration.event.region',
            ]);

        if (is_array($participantIds) && $participantIds !== []) {
            $query->whereIn('id', $participantIds);
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', (int) $filters['student_id']);
        }

        $participants = $query->orderBy('id')->get();

        if ($headId) {
            $participants = $participants->filter(
                fn (FestParticipant $p) => (int) ($p->registration?->item?->head_id ?? 0) === $headId,
            );
        }

        if ($participants->isEmpty()) {
            return [];
        }

        $schedules = $this->schedulesForParticipants($event, $participants->pluck('id'));

        return $participants
            ->groupBy(function (FestParticipant $p) {
                $head = (int) ($p->registration?->item?->head_id ?? 0);
                $entity = $p->student_id ? 's:'.$p->student_id : 't:'.$p->teacher_id;

                return $head.':'.$entity;
            })
            ->map(function ($group) use ($event, $schedules) {
                /** @var \Illuminate\Support\Collection<int, FestParticipant> $group */
                $lead = $group->sortBy('id')->first();
                $head = $lead->registration?->item?->head;
                $headName = $head?->name ?? 'Item head';
                $items = $group
                    ->map(fn (FestParticipant $p) => $p->registration?->item?->title)
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $schedule = $group
                    ->map(fn (FestParticipant $p) => $schedules->get($p->id))
                    ->filter()
                    ->sortBy(fn (?FestSchedule $s) => $s?->scheduled_at?->timestamp ?? PHP_INT_MAX)
                    ->first();

                $card = $this->participantCard($event, $lead, $schedule);
                $entityKey = $lead->student_id ? 's'.$lead->student_id : 't'.$lead->teacher_id;

                $cleanItems = array_map(fn ($i) => str_replace('_', ' ', $i), $items);
                $itemsDisplay = implode(' · ', $cleanItems);

                return array_merge($card, [
                    'card_type'       => 'head_participant',
                    'role_label'      => $card['role_label'],
                    'head_label'      => $headName,
                    'head_id'         => (int) ($head?->id ?? $lead->registration?->item?->head_id ?? 0),
                    'detail'          => null,
                    'item_label'      => $headName,
                    'items'           => $cleanItems,
                    'items_display'   => $itemsDisplay,
                    'item_count'      => count($cleanItems),
                    'id_label'        => 'Fest ID',
                    'secondary_label' => null,
                    'secondary_value' => null,
                    'schedule'        => $this->scheduleLine($schedule),
                    'footer'          => null,
                    'entity_id'       => 'hp-'.($head?->id ?? 0).'-'.$entityKey,
                ]);
            })
            ->values()
            ->all();
    }

    /** @param  list<string>  $items */
    private function itemsFooter(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $visible = array_slice($items, 0, 3);
        $footer = implode(' · ', $visible);

        if (count($items) > 3) {
            $footer .= ' · +'.(count($items) - 3).' more';
        }

        return $footer;
    }

    /** @param  array<string, mixed>  $filters */
    private function eventParticipantCards(FestEvent $event, array $filters): array
    {
        $schoolId = $filters['school_id'] ?? null;
        $participantIds = $filters['participant_ids'] ?? null;

        $query = FestParticipant::whereHas('registration', function ($q) use ($event, $filters) {
            $q->whereIn('event_id', $event->reportableEventIds());
            $this->constrainRegistrationStatus($q, $filters);
            if (! empty($filters['school_id'])) {
                $q->where('school_id', $filters['school_id']);
            }
            if (! empty($filters['item_id'])) {
                $q->whereIn('item_id', $event->reportableItemIds([(int) $filters['item_id']]));
            }
        })
            ->where('participant_role', '!=', 'standby')
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->with(['student.tenant', 'teacher.tenant', 'registration.item.head', 'registration.school', 'registration.event.sourcePhase', 'registration.event.region']);

        if (is_array($participantIds) && $participantIds !== []) {
            $query->whereIn('id', $participantIds);
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', (int) $filters['student_id']);
        }

        $participants = $query->orderBy('id')->get();
        $schedules = $this->schedulesForParticipants($event, $participants->pluck('id'));

        return $participants
            ->groupBy(fn (FestParticipant $p) => $p->student_id ? 's:'.$p->student_id : 't:'.$p->teacher_id)
            ->map(function ($group) use ($event, $schedules) {
                /** @var \Illuminate\Support\Collection<int, FestParticipant> $group */
                $lead = $group->sortBy('id')->first();
                $items = $group
                    ->map(fn (FestParticipant $p) => $p->registration?->item?->title)
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
                $cleanItems = array_map(fn ($i) => str_replace('_', ' ', $i), $items);
                $itemsDisplay = implode(' · ', $cleanItems);

                $schedule = $group
                    ->map(fn (FestParticipant $p) => $schedules->get($p->id))
                    ->filter()
                    ->sortBy(fn (?FestSchedule $s) => $s?->scheduled_at?->timestamp ?? PHP_INT_MAX)
                    ->first();

                $card = $this->participantCard($event, $lead, $schedule);
                $entityKey = $lead->student_id ? 's'.$lead->student_id : 't'.$lead->teacher_id;

                return array_merge($card, [
                    'card_type'       => 'event_participant',
                    'role_label'      => 'PARTICIPANT',
                    'role_class'      => 'student',
                    'detail'          => null,
                    'items'           => $cleanItems,
                    'items_display'   => $itemsDisplay,
                    'item_count'      => count($items),
                    'item_label'      => null,
                    'id_label'        => 'Fest ID',
                    'secondary_label' => ($card['secondary_value'] ?? null) ? ($card['secondary_label'] ?? 'Chest') : null,
                    'secondary_value' => ($card['secondary_value'] ?? '—') !== '—' ? $card['secondary_value'] : null,
                    'schedule'        => $this->scheduleLine($schedule),
                    'footer'          => null,
                    'entity_id'       => 'ep-'.$entityKey,
                ]);
            })
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $filters */
    private function individualStudentCards(FestEvent $event, array $filters): array
    {
        $schoolId = $filters['school_id'] ?? null;
        $itemId = isset($filters['item_id']) ? (int) $filters['item_id'] : null;
        $participantIds = $filters['participant_ids'] ?? null;

        $query = FestParticipant::whereHas('registration', function ($q) use ($event, $filters, $itemId) {
            $q->whereIn('event_id', $event->reportableEventIds());
            $this->constrainRegistrationStatus($q, $filters);
            if (! empty($filters['school_id'])) {
                $q->where('school_id', $filters['school_id']);
            }
            if ($itemId) {
                $q->whereIn('item_id', $event->reportableItemIds([$itemId]));
            }
        })
            ->where('participant_role', '!=', 'standby')
            ->with(['student.tenant', 'teacher.tenant', 'registration.item.head', 'registration.school', 'registration.event.sourcePhase', 'registration.event.region']);

        if (is_array($participantIds) && $participantIds !== []) {
            $query->whereIn('id', $participantIds);
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', (int) $filters['student_id']);
        }

        $includeDataUris = (bool) ($filters['include_data_uris'] ?? false);
        $participants = $query->orderBy('id')->get();
        $schedules = $this->schedulesForParticipants($event, $participants->pluck('id'));

        return $participants->map(fn (FestParticipant $p) => $this->participantCard($event, $p, $schedules->get($p->id), $includeDataUris))
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $filters */
    private function teamCards(FestEvent $event, array $filters): array
    {
        $schoolId = $filters['school_id'] ?? null;
        $itemId = isset($filters['item_id']) ? (int) $filters['item_id'] : null;
        $includeDataUris = (bool) ($filters['include_data_uris'] ?? false);

        $query = FestRegistration::query()
            ->whereIn('event_id', $event->reportableEventIds());
        $this->constrainRegistrationStatus($query, $filters);
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }
        $query
            ->when($itemId, fn ($q) => $q->whereIn('item_id', $event->reportableItemIds([$itemId])))
            ->when(! empty($filters['student_id']), fn ($q) => $q->whereHas(
                'participants',
                fn ($p) => $p->where('student_id', (int) $filters['student_id'])->where('participant_role', '!=', 'standby'),
            ))
            ->whereHas('item', fn ($q) => $q->whereIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES))
            ->with([
                'event.sourcePhase',
                'event.region',
                'item:id,title,participant_type',
                'school:id,name',
                'groups',
                'participants' => fn ($q) => $q
                    ->where('participant_role', '!=', 'standby')
                    ->with(['student.tenant', 'teacher.tenant']),
            ])
            ->orderBy('id');

        $registrations = $query->get();
        $participantIds = $registrations->flatMap(fn ($r) => $r->participants->pluck('id'));
        $schedules = $this->schedulesForParticipants($event, $participantIds);

        return $registrations->map(function (FestRegistration $registration) use ($event, $schedules, $includeDataUris) {
            $performers = $registration->participants
                ->sortBy(fn (FestParticipant $p) => $p->participant_role === 'performer' ? 0 : 1);

            $lead = $performers->first();
            $festId = $lead?->level_registration_number ?? sprintf('REG-%04d', $registration->id);
            $group = $registration->groups->first();
            $teamName = $group?->team_name;
            $itemTitle = $registration->item?->title ?? '—';
            $school = $registration->school?->name ?? '—';
            $scheduleLine = $lead ? $this->scheduleLine($schedules->get($lead->id)) : null;

            // Team/group items carry ONE chest number for the whole squad
            // (on FestGroup, not per member) — show it on the team card
            // itself, not repeated per member.
            $chestNumber = ($group && $group->chest_no !== null) ? (string) $group->chest_no : null;
            if ($chestNumber && ($this->hideChestNo || ($event->chest_reveal_mode === 'stage_entry' && ! $group->chest_revealed_at))) {
                $chestNumber = null;
            }

            $members = $performers->map(function (FestParticipant $p) use ($includeDataUris) {
                $name = $p->student?->name ?? $p->teacher?->name ?? 'Member';
                $photoUrl = $this->portraitUrl($p);
                $photoSrc = $this->portraitDataUri($p) ?: ($photoUrl ?: $this->defaultAvatarDataUri('male'));

                return [
                    'name'      => $name,
                    'fest_id'   => $p->level_registration_number ?? '—',
                    'initials'  => $this->initials($name),
                    'photo_url' => $photoUrl,
                    'photo_src' => $photoSrc,
                    'role'      => str($p->participant_role)->replace('_', ' ')->title()->toString(),
                ];
            })->values()->all();

            $rawDate = $event->event_start ?? $event->starts_at ?? $event->start_date;
            $eventDate = $rawDate ? date('d M Y', strtotime((string) $rawDate)) : null;
            if (! $eventDate && $event->event_end) {
                $eventDate = date('d M Y', strtotime((string) $event->event_end));
            }
            $venue = $this->resolveVenue($event, null, $registration);
            $qrPayload = $this->qrPayload($event, 'registration', (string) $registration->id, $festId);
            $leafEvent = $registration->event ?? $event;
            $phaseName = $leafEvent->sourcePhase?->name ?: $leafEvent->region?->name;

            return [
                'card_type'       => 'team',
                'audience'        => 'student',
                'role_label'      => str($registration->item?->participant_type ?? 'team')->upper()->toString(),
                'role_class'      => 'student',
                'name'            => $teamName ?: $itemTitle,
                'initials'        => $this->initials($teamName ?: $itemTitle),
                'photo_url'       => null,
                'photo_src'       => null,
                'subtitle'        => $school,
                'detail'          => $itemTitle,
                'item_label'      => $itemTitle !== '—' ? $itemTitle : null,
                'chest_number'    => $chestNumber,
                'schedule'        => $scheduleLine,
                'members'         => $members,
                'member_count'    => count($members),
                'id_label'        => 'Team ID',
                'id_number'       => $festId,
                'secondary_label' => 'Members',
                'secondary_value' => (string) count($members),
                'qr_src'          => $this->qrService->dataUri($qrPayload),
                'footer'          => $scheduleLine ?: $event->title,
                'entity_id'       => 'reg-'.$registration->id,
                'event_name'      => $event->title,
                'phase_name'      => $phaseName,
                'academic_year'   => $this->resolveEventYearLabel($event),
                'event_date'      => $eventDate,
                'venue'           => $venue,
                'sahodaya_name'   => $event->tenant?->name ?? (\App\Models\Tenant::where('id', $event->tenant_id)->value('name') ?? 'Sahodaya'),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function participantCard(FestEvent $event, FestParticipant $p, ?FestSchedule $schedule, bool $includeDataUris = false): array
    {
        $isTeacher = (bool) $p->teacher_id;
        $name = $p->student?->name ?? $p->teacher?->name ?? 'Participant';
        $school = $p->registration?->school?->name ?? '—';
        $itemModel = $p->registration?->item;
        $item = $itemModel?->title ?? '—';
        $headName = $itemModel?->head?->name;
        $festId = $p->level_registration_number ?? '—';
        $scheduleLine = $this->scheduleLine($schedule)
            ?? ($itemModel ? $this->itemWindows->competitionLine($itemModel) : null);
        $qrPayload = $this->qrPayload($event, 'participant', (string) $p->id, $festId);

        // Sports meets: chest number is the field an athlete is actually
        // identified by on the field, and age category is what determines
        // which items they're eligible for — both worth surfacing on the
        // printed card face, not just buried in reports.
        $isSports = $event->event_type === 'sports';
        $chestLabel = $this->hideChestNo ? null : $this->chestService->participantLabel($p);
        $chestNumber = ($chestLabel && $chestLabel !== '—') ? $chestLabel : null;
        $ageGroupLabel = null;

        if ($isSports && $itemModel) {
            $ageKey = FestSportsAgeGroup::resolveForItem($itemModel->age_group, $itemModel->class_group, 'sports');
            if ($ageKey && $ageKey !== 'open') {
                $ageGroupLabel = FestSportsAgeGroup::labels($event->tenant_id)[$ageKey] ?? strtoupper($ageKey);
            }
        }

        $itemLabel = $item !== '—' ? $item : null;
        if ($ageGroupLabel) {
            $itemLabel = $itemLabel ? "{$itemLabel} · {$ageGroupLabel}" : $ageGroupLabel;
        }

        $studentClass = $p->student?->schoolClass?->name ?? $p->student?->class ?? null;
        $classCategory = null;
        if ($itemModel?->class_group) {
            $schemeLabels = \App\Support\FestClassGroupScheme::labels(null, $event->rootEvent());
            $classCategory = \App\Support\FestClassGroupScheme::resolveItemLabel($schemeLabels, $itemModel->class_group);
        }

        $pureCategory = $ageGroupLabel ?: ($classCategory ?: ($studentClass ? "Class {$studentClass}" : null));
        $itemTitleClean = ($item !== '—' && $item) ? str_replace('_', ' ', $item) : null;
        $categoryDisplay = $pureCategory ? str_replace('_', ' ', $pureCategory) : ($itemTitleClean ?: '—');

        $rawGender = strtolower((string) ($p->student?->gender ?? $p->teacher?->gender ?? ''));
        $gender = match (true) {
            str_starts_with($rawGender, 'f') || $rawGender === 'girl' || $rawGender === 'female' => 'female',
            str_starts_with($rawGender, 'm') || $rawGender === 'boy' || $rawGender === 'male'    => 'male',
            default                                                                               => 'neutral',
        };

        $photoUrl = $this->portraitUrl($p);
        $photoSrc = $this->resolveParticipantPhotoSrc($p, $gender, $includeDataUris);

        $rawDate = $event->event_start ?? $event->starts_at ?? $event->start_date;
        $eventDate = $rawDate ? date('d M Y', strtotime((string) $rawDate)) : null;
        if (! $eventDate && $event->event_end) {
            $eventDate = date('d M Y', strtotime((string) $event->event_end));
        }
        $venue = $this->resolveVenue($event, $p);
        $sahodayaName = $event->tenant?->name
            ?? \App\Models\Tenant::where('id', $event->tenant_id)->value('name')
            ?? 'Sahodaya';
        $rawDob = $p->student?->dob;
        $dob = $rawDob ? date('d M Y', strtotime((string) $rawDob)) : null;

        // The card's $event is often the region-agnostic hub the admin is viewing
        // (e.g. printing all cards for the season), not the specific phase/region
        // leaf event the student actually registered under — use the registration's
        // own event for the phase name so mixed-phase print runs label each card
        // correctly instead of repeating the hub's generic title on every card.
        $leafEvent = $p->registration?->event ?? $event;
        $phaseName = $leafEvent->sourcePhase?->name ?: $leafEvent->region?->name;
        // Fest events don't span academic years (a phase never carries a different
        // year than its own hub), so the hub's own record is authoritative — no need
        // to chase the leaf event's own academicYear() and risk an N+1 per card.
        $academicYear = $this->resolveEventYearLabel($event);

        return [
            'card_type'       => 'individual',
            'audience'        => 'student',
            'role_label'      => $isTeacher ? 'TEACHER' : 'STUDENT',
            'role_title'      => $isTeacher ? 'Teacher' : 'Participant',
            'role_class'      => $isTeacher ? 'staff' : 'student',
            'name'            => $name,
            'initials'        => $this->initials($name),
            'gender'          => $gender,
            'photo_url'       => $photoUrl,
            'photo_src'       => $photoSrc,
            'subtitle'        => $school,
            'school_name'     => $school,
            'student_class'   => $studentClass,
            'class_category'  => $classCategory,
            'event_name'      => $event->title,
            'phase_name'      => $phaseName,
            'academic_year'   => $academicYear,
            'event_date'      => $eventDate,
            'venue'           => $venue,
            'dob'             => $dob,
            'is_sports'       => $isSports,
            'sahodaya_name'   => $sahodayaName,
            'category'        => $categoryDisplay,
            'items_display'   => $itemTitleClean,
            'detail'          => $itemTitleClean,
            'head_label'      => $headName,
            'item_label'      => $itemTitleClean ?: ($headName ?: null),
            'age_group_label' => $ageGroupLabel,
            'chest_number'    => $chestNumber,
            'schedule'        => $scheduleLine,
            'id_label'        => 'Reg ID',
            'id_number'       => $festId,
            'secondary_label' => null,
            'secondary_value' => null,
            'qr_src'          => $this->qrService->dataUri($qrPayload),
            'footer'          => null,
            'entity_id'       => (string) $p->id,
        ];
    }

    private function resolveVenue(FestEvent $event, ?FestParticipant $p = null, ?FestRegistration $registration = null): string
    {
        $regEvent = $p?->registration?->event ?? $registration?->event;
        $schoolId = $p?->registration?->school_id ?? $registration?->school_id;

        $targetRegionId = $event->region_id
            ?? $regEvent?->region_id;

        if (! $targetRegionId && $schoolId) {
            $targetRegionId = \App\Models\SchoolRegionAssignment::forTenant($event->tenant_id)
                ->where('school_id', $schoolId)
                ->value('region_id')
                ?? \App\Models\FestSchoolPhaseRegionSelection::where('school_id', $schoolId)
                    ->value('region_id');
        }

        $rootEvent = $event->rootEvent();
        $eventIds = array_values(array_unique(array_filter([
            $event->id,
            $event->parent_event_id,
            $rootEvent->id,
            $p?->registration?->event_id,
            $registration?->event_id,
            $regEvent?->parent_event_id,
        ])));

        // 1. Check FestVenue table for matching region_id if targetRegionId exists
        if ($targetRegionId) {
            $regionalVenue = \App\Models\FestVenue::whereIn('event_id', $eventIds)
                ->where('region_id', $targetRegionId)
                ->where('is_active', true)
                ->first()
                ?? \App\Models\FestVenue::where('tenant_id', $event->tenant_id)
                    ->where('region_id', $targetRegionId)
                    ->where('is_active', true)
                    ->first();

            if ($regionalVenue && !empty($regionalVenue->name)) {
                return $regionalVenue->name;
            }

            // Check FestPhaseRegion table if phase region venue is set
            $phaseRegionVenue = \App\Models\FestPhaseRegion::where('region_id', $targetRegionId)
                ->whereNotNull('venue')
                ->where('venue', '!=', '')
                ->value('venue');

            if ($phaseRegionVenue) {
                return $phaseRegionVenue;
            }
        }

        // 2. Check FestVenue table for any active venue WITH NO region restriction or matching targetRegionId if set
        $eventVenue = \App\Models\FestVenue::whereIn('event_id', $eventIds)
            ->where('is_active', true)
            ->where(function ($q) use ($targetRegionId) {
                $q->whereNull('region_id');
                if ($targetRegionId) {
                    $q->orWhere('region_id', $targetRegionId);
                }
            })
            ->first();

        if ($eventVenue && !empty($eventVenue->name)) {
            return $eventVenue->name;
        }

        // 3. Fallback to event model columns (venue, venue_name, location_name, conductingSchool)
        $eventsToCheck = array_filter([
            $event,
            $event->parent_event_id ? FestEvent::find($event->parent_event_id) : null,
            $regEvent,
        ]);

        foreach ($eventsToCheck as $ev) {
            if (!empty($ev->venue)) {
                return $ev->venue;
            }
            if (!empty($ev->venue_name)) {
                return $ev->venue_name;
            }
            if (!empty($ev->location_name)) {
                return $ev->location_name;
            }
        }

        return '—';
    }

    private function qrPayload(FestEvent $event, string $kind, string $entityId, string $festId): string
    {
        return implode('|', [
            'FEST',
            $event->id,
            $kind,
            $entityId,
            $festId,
        ]);
    }

    private function portraitUrl(FestParticipant $p): ?string
    {
        return $p->student?->photoUrl() ?? $p->teacher?->photoUrl();
    }

    /**
     * Cached, downscaled base64 data URI for a participant's photo, for bulk ID card
     * PDFs (pdfAllItems()/pdfAllHeads() can embed every participant across an entire
     * event in one PDF). Uses photoBase64DataUri() (downscales + never hands DomPDF a
     * bare filesystem path) instead of the plain photoDataUri(), which read
     * full-resolution bytes (up to ~2MB per photo) uncached on every single PDF
     * generation. Caching per student (keyed on updated_at) means only the first
     * report/PDF that touches a given student's photo pays the decode/downscale cost.
     * See docs/N1_AND_REPORT_MEMORY_AUDIT_2026_08_03.md §2.
     */
    private function portraitDataUri(FestParticipant $p): ?string
    {
        if ($p->student) {
            $photo = $p->student->photo;
            if (! $photo) {
                return null;
            }

            // photoBase64DataUri() deliberately refuses to fetch remote http(s) URLs
            // (unlike the plain photoDataUri() this replaces) — preserve the old
            // pass-through behavior for the rare student whose `photo` column already
            // holds a full external URL rather than a relative storage path.
            if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
                return $photo;
            }

            $cacheKey = 'student-photo-thumb:'.$p->student->id.':'.($p->student->updated_at?->timestamp ?? 0);

            return \Illuminate\Support\Facades\Cache::remember(
                $cacheKey,
                now()->addDays(30),
                function () use ($p) {
                    $tenant = $p->student->relationLoaded('tenant') ? $p->student->tenant : Tenant::find($p->student->tenant_id);

                    return TenantStorage::photoBase64DataUri($tenant, $p->student->photo);
                },
            );
        }

        if ($p->teacher) {
            return $p->teacher->photoDataUri();
        }

        return null;
    }

    /**
     * Resolve a usable <img> src for a participant's photo, falling back to a
     * gender-appropriate placeholder avatar when none is on file. Public so other
     * fest-facing renderers (e.g. FestCertificateService) can reuse the same
     * public-disk/data-URI resolution instead of duplicating it.
     */
    public function resolveParticipantPhotoSrc(FestParticipant $p, string $gender, bool $includeDataUris): string
    {
        if ($includeDataUris) {
            return $this->portraitDataUri($p) ?: $this->defaultAvatarDataUri($gender);
        }

        $relativePath = $p->student?->photo ?? $p->teacher?->photo;
        if ($relativePath) {
            if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://') || str_starts_with($relativePath, 'data:image/')) {
                return $relativePath;
            }

            $tenant = $p->student?->relationLoaded('tenant') ? $p->student->tenant : ($p->student ? Tenant::find($p->student->tenant_id) : null);
            $absolute = $tenant ? TenantStorage::publicFilePath($tenant, $relativePath) : null;
            if ($absolute && is_file($absolute)) {
                $publicRoot = base_path('storage/app/public');
                if (str_starts_with($absolute, $publicRoot)) {
                    $subPath = ltrim(substr($absolute, strlen($publicRoot)), '/');
                    return asset('storage/' . $subPath);
                }
            }

            return $this->portraitDataUri($p) ?: $this->defaultAvatarDataUri($gender);
        }

        return $this->portraitUrl($p) ?: $this->defaultAvatarDataUri($gender);
    }

    /** @param  \Illuminate\Support\Collection<int, int|string>  $participantIds */
    private function schedulesForParticipants(FestEvent $event, $participantIds)
    {
        if ($participantIds->isEmpty()) {
            return collect();
        }

        return FestSchedule::whereIn('event_id', $event->reportableEventIds())
            ->whereIn('participant_id', $participantIds)
            ->with('festStage:id,name')
            ->get()
            ->keyBy('participant_id');
    }

    private function scheduleLine(?FestSchedule $schedule): ?string
    {
        if (! $schedule) {
            return null;
        }

        $parts = [];
        if ($schedule->scheduled_at) {
            $parts[] = $schedule->scheduled_at->format('d M · g:i A');
        }
        $stage = $schedule->festStage?->name ?? $schedule->stage;
        if ($stage) {
            $parts[] = $stage;
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    /** @param  array<string, mixed>  $filters */
    private function volunteerCards(FestEvent $event, array $filters): array
    {
        $ids = $filters['volunteer_ids'] ?? null;

        $query = FestVolunteer::where('event_id', $event->id)->orderBy('name');
        if (is_array($ids) && $ids !== []) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function (FestVolunteer $v) use ($event) {
            $volId = sprintf('VOL-%04d', $v->id);
            $qrPayload = $this->qrPayload($event, 'volunteer', (string) $v->id, $volId);

            return [
                'card_type'       => 'individual',
                'audience'        => 'volunteer',
                'role_label'      => 'VOLUNTEER',
                'role_class'      => 'volunteer',
                'name'            => $v->name,
                'initials'        => $this->initials($v->name),
                'photo_url'       => null,
                'photo_src'       => null,
                'subtitle'        => $v->duty ?: 'Event volunteer',
                'detail'          => $v->phone ?: '—',
                'schedule'        => null,
                'id_label'        => 'Volunteer ID',
                'id_number'       => $volId,
                'secondary_label' => 'Event',
                'secondary_value' => str($event->title)->limit(28)->toString(),
                'qr_src'          => $this->qrService->dataUri($qrPayload),
                'footer'          => $event->title,
                'entity_id'       => (string) $v->id,
                'academic_year'   => $this->resolveEventYearLabel($event),
            ];
        })->values()->all();
    }

    /** @param  array<string, mixed>  $filters */
    private function staffCards(FestEvent $event, array $filters): array
    {
        $ids = $filters['staff_ids'] ?? null;

        $query = FestEventStaff::where('event_id', $event->id)
            ->with(['stage:id,name', 'venue:id,name'])
            ->orderBy('id');

        if (is_array($ids) && $ids !== []) {
            $query->whereIn('id', $ids);
        }

        $assignments = $query->get();
        $users = User::whereIn('id', $assignments->pluck('user_id'))->get(['id', 'name', 'email'])->keyBy('id');

        return $assignments->map(function (FestEventStaff $a) use ($event, $users) {
            $user = $users->get($a->user_id);
            $name = $user?->name ?? 'Staff';
            $location = $a->stage?->name ?? $a->venue?->name ?? '—';
            $staffId = sprintf('STF-%04d', $a->id);
            $qrPayload = $this->qrPayload($event, 'staff', (string) $a->id, $staffId);

            return [
                'card_type'       => 'individual',
                'audience'        => 'staff',
                'role_label'      => 'STAFF',
                'role_class'      => 'staff',
                'name'            => $name,
                'initials'        => $this->initials($name),
                'photo_url'       => null,
                'photo_src'       => null,
                'subtitle'        => $a->duty ?: 'Event operations',
                'detail'          => $location,
                'schedule'        => null,
                'id_label'        => 'Staff ID',
                'id_number'       => $staffId,
                'secondary_label' => 'Access',
                'secondary_value' => str($user?->email ?? '—')->limit(24)->toString(),
                'qr_src'          => $this->qrService->dataUri($qrPayload),
                'footer'          => $event->title,
                'entity_id'       => (string) $a->id,
                'academic_year'   => $this->resolveEventYearLabel($event),
            ];
        })->values()->all();
    }

    /**
     * "Kalotsav {year}" heading on the Participant Pass card face. Prefers the
     * event's own academic-year record ("2026-27"); falls back to the plain
     * calendar year off the event's start date when no academic year is set.
     */
    private function resolveEventYearLabel(FestEvent $event): ?string
    {
        if ($label = $event->academicYear?->label) {
            return $label;
        }

        $rawDate = $event->event_start ?? $event->starts_at ?? $event->start_date ?? $event->event_end;

        return $rawDate ? date('Y', strtotime((string) $rawDate)) : null;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = collect($parts)->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');

        return $letters !== '' ? $letters : '?';
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     * @param  array<string, mixed>  $filters
     */
    private function constrainRegistrationStatus($query, array $filters): void
    {
        $query->whereNotIn('status', ['rejected', 'withdrawn']);
    }

    /**
     * Demo ID card for sports catalog item heads (not tied to a live event).
     *
     * @param  list<string>  $itemTitles
     * @return array<string, mixed>
     */
    public function sampleHeadCard(Tenant $sahodaya, FestItemHead $head, array $itemTitles = []): array
    {
        if ($itemTitles === []) {
            $itemTitles = ['Sample Item A', 'Sample Item B'];
        }

        $name = 'Sample Student';
        $festId = 'SP-2026-001';
        $qrPayload = implode('|', ['FEST', 'sample', 'participant', 'demo', $festId]);

        return [
            'card_type'       => 'head_participant',
            'audience'        => 'student',
            'role_label'      => 'STUDENT',
            'role_class'      => 'student',
            'name'            => $name,
            'initials'        => $this->initials($name),
            'photo_url'       => null,
            'photo_src'       => null,
            'subtitle'        => 'Sample Model School',
            'detail'          => null,
            'head_label'      => $head->name,
            'head_id'         => (int) $head->id,
            'item_label'      => $head->name,
            'items'           => $itemTitles,
            'item_count'      => count($itemTitles),
            'id_label'        => 'Fest ID',
            'id_number'       => $festId,
            'secondary_label' => null,
            'secondary_value' => null,
            'qr_src'          => $this->qrService->dataUri($qrPayload),
            'schedule'        => 'Competition dates: TBA',
            'footer'          => null,
            'entity_id'       => 'sample-head-'.$head->id,
        ];
    }

    public function defaultAvatarDataUri(string $gender): string
    {
        $svg = match ($gender) {
            'female' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#fcf4f6"/><circle cx="50" cy="37" r="19" fill="#0f3d7a"/><path fill="#0f3d7a" d="M50 61c-21 0-37 12-37 26v13h74V87c0-14-16-26-37-26z"/><path fill="#0f3d7a" d="M30 35c-2 6-3 12 0 18m40-18c2 6 3 12 0 18"/></svg>',
            'male'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#f0f7ff"/><circle cx="50" cy="38" r="20" fill="#0f3d7a"/><path fill="#0f3d7a" d="M50 63c-22 0-38 12-38 27v10h76V90c0-15-16-27-38-27z"/></svg>',
            default  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#f1f5f9"/><circle cx="50" cy="38" r="20" fill="#475569"/><path fill="#475569" d="M50 63c-22 0-38 12-38 27v10h76V90c0-15-16-27-38-27z"/></svg>',
        };

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
