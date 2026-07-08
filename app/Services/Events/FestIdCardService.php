<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestVolunteer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantStorage;

class FestIdCardService
{
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

        $scope = $filters['scope'] ?? 'item';

        if (in_array($scope, ['event', 'head_all'], true)) {
            return;
        }

        if ($scope === 'head' && empty($filters['head_id'])) {
            abort(422, 'Select an item head before generating student ID cards.');
        }

        if ($scope === 'item' && empty($filters['item_id'])) {
            abort(422, 'Select a fest item before generating student ID cards.');
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{item_title: string, item_id: int, cards: list<array<string, mixed>>}>
     */
    public function cardsGroupedByItem(FestEvent $event, array $filters = []): array
    {
        if ($event->event_type === 'sports') {
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

            if ($layout === 'team' && in_array($item->participant_type, ['group', 'team'], true)) {
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
                $q->where('event_id', $event->id);
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

        return $rows->map(fn ($count) => (int) $count)->all();
    }

    /** @return array<int, int> item_id => approved registration count */
    public function itemRegistrationCounts(FestEvent $event, ?string $schoolId = null, bool $schoolDownloads = false): array
    {
        $filters = array_filter([
            'school_id'        => $schoolId,
            'school_downloads' => $schoolDownloads || $schoolId !== null,
        ]);

        $query = FestRegistration::query()->where('event_id', $event->id);
        $this->constrainRegistrationStatus($query, $filters);
        if (! empty($filters['school_id'])) {
            $query->where('school_id', $filters['school_id']);
        }

        return $query
            ->selectRaw('item_id, COUNT(*) as aggregate')
            ->groupBy('item_id')
            ->pluck('aggregate', 'item_id')
            ->map(fn ($count) => (int) $count)
            ->all();
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
            $q->where('event_id', $event->id);
            $this->constrainRegistrationStatus($q, $filters);
            if (! empty($filters['school_id'])) {
                $q->where('school_id', $filters['school_id']);
            }
        })
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->where('participant_role', '!=', 'standby')
            ->count();
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
            $q->where('event_id', $event->id);
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

                return array_merge($card, [
                    'card_type'       => 'head_participant',
                    'role_label'      => $card['role_label'],
                    'head_label'      => $headName,
                    'head_id'         => (int) ($head?->id ?? $lead->registration?->item?->head_id ?? 0),
                    'detail'          => null,
                    'item_label'      => $headName,
                    'items'           => $items,
                    'item_count'      => count($items),
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
            $q->where('event_id', $event->id);
            $this->constrainRegistrationStatus($q, $filters);
            if (! empty($filters['school_id'])) {
                $q->where('school_id', $filters['school_id']);
            }
        })
            ->where('participant_role', '!=', 'standby')
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->with(['student.tenant', 'teacher.tenant', 'registration.item.head', 'registration.school']);

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
                    ->values()
                    ->all();

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
                    'items'           => $items,
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
            $q->where('event_id', $event->id);
            $this->constrainRegistrationStatus($q, $filters);
            if (! empty($filters['school_id'])) {
                $q->where('school_id', $filters['school_id']);
            }
            if ($itemId) {
                $q->where('item_id', $itemId);
            }
        })
            ->where('participant_role', '!=', 'standby')
            ->with(['student.tenant', 'teacher.tenant', 'registration.item.head', 'registration.school']);

        if (is_array($participantIds) && $participantIds !== []) {
            $query->whereIn('id', $participantIds);
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', (int) $filters['student_id']);
        }

        $participants = $query->orderBy('id')->get();
        $schedules = $this->schedulesForParticipants($event, $participants->pluck('id'));

        return $participants->map(fn (FestParticipant $p) => $this->participantCard($event, $p, $schedules->get($p->id)))
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $filters */
    private function teamCards(FestEvent $event, array $filters): array
    {
        $schoolId = $filters['school_id'] ?? null;
        $itemId = isset($filters['item_id']) ? (int) $filters['item_id'] : null;

        $query = FestRegistration::query()
            ->where('event_id', $event->id);
        $this->constrainRegistrationStatus($query, $filters);
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }
        $query
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
            ->when(! empty($filters['student_id']), fn ($q) => $q->whereHas(
                'participants',
                fn ($p) => $p->where('student_id', (int) $filters['student_id'])->where('participant_role', '!=', 'standby'),
            ))
            ->whereHas('item', fn ($q) => $q->whereIn('participant_type', ['group', 'team']))
            ->with([
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

        return $registrations->map(function (FestRegistration $registration) use ($event, $schedules) {
            $performers = $registration->participants
                ->sortBy(fn (FestParticipant $p) => $p->participant_role === 'performer' ? 0 : 1);

            $lead = $performers->first();
            $festId = $lead?->level_registration_number ?? sprintf('REG-%04d', $registration->id);
            $teamName = $registration->groups->first()?->team_name;
            $itemTitle = $registration->item?->title ?? '—';
            $school = $registration->school?->name ?? '—';
            $scheduleLine = $lead ? $this->scheduleLine($schedules->get($lead->id)) : null;

            $members = $performers->map(function (FestParticipant $p) {
                $name = $p->student?->name ?? $p->teacher?->name ?? 'Member';
                $chest = $this->chestService->participantLabel($p);

                return [
                    'name'      => $name,
                    'fest_id'   => $p->level_registration_number ?? '—',
                    'chest'     => $chest !== '—' ? $chest : null,
                    'initials'  => $this->initials($name),
                    'photo_url' => $this->portraitUrl($p),
                    'photo_src' => $this->portraitDataUri($p),
                    'role'      => str($p->participant_role)->replace('_', ' ')->title()->toString(),
                ];
            })->values()->all();

            $qrPayload = $this->qrPayload($event, 'registration', (string) $registration->id, $festId);

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
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function participantCard(FestEvent $event, FestParticipant $p, ?FestSchedule $schedule): array
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

        return [
            'card_type'       => 'individual',
            'audience'        => 'student',
            'role_label'      => $isTeacher ? 'TEACHER' : 'STUDENT',
            'role_class'      => $isTeacher ? 'staff' : 'student',
            'name'            => $name,
            'initials'        => $this->initials($name),
            'photo_url'       => $this->portraitUrl($p),
            'photo_src'       => $this->portraitDataUri($p),
            'subtitle'        => $school,
            'detail'          => $item !== '—' ? $item : null,
            'head_label'      => $headName,
            'item_label'      => $item !== '—' ? $item : ($headName ?: null),
            'schedule'        => $scheduleLine,
            'id_label'        => 'Fest ID',
            'id_number'       => $festId,
            'secondary_label' => null,
            'secondary_value' => null,
            'qr_src'          => $this->qrService->dataUri($qrPayload),
            'footer'          => null,
            'entity_id'       => (string) $p->id,
        ];
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

    private function portraitDataUri(FestParticipant $p): ?string
    {
        if ($p->student) {
            return TenantStorage::photoDataUri(
                $p->student->relationLoaded('tenant') ? $p->student->tenant : Tenant::find($p->student->tenant_id),
                $p->student->photo,
            );
        }

        if ($p->teacher) {
            return $p->teacher->photoDataUri();
        }

        return null;
    }

    /** @param  \Illuminate\Support\Collection<int, int|string>  $participantIds */
    private function schedulesForParticipants(FestEvent $event, $participantIds)
    {
        if ($participantIds->isEmpty()) {
            return collect();
        }

        return FestSchedule::where('event_id', $event->id)
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
            ];
        })->values()->all();
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
        if ($filters['school_downloads'] ?? false) {
            $query->whereNotIn('status', ['rejected', 'withdrawn']);
        } else {
            $query->where('status', 'approved');
        }
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
}
