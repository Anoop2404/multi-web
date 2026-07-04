<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
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
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function requireStudentItem(string $audience, array $filters): void
    {
        if ($audience !== 'student') {
            return;
        }

        if (($filters['scope'] ?? 'item') === 'event') {
            return;
        }

        if (empty($filters['item_id'])) {
            abort(422, 'Select a fest item before generating student ID cards.');
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{item_title: string, item_id: int, cards: list<array<string, mixed>>}>
     */
    public function cardsGroupedByItem(FestEvent $event, array $filters = []): array
    {
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
        $event->loadMissing(['items.head' => fn ($q) => $q->orderBy('sort_order')]);

        $byStudent = [];
        foreach ($event->items as $item) {
            if (! $item->head_id) {
                continue;
            }

            $itemFilters = array_merge($filters, ['item_id' => $item->id, 'scope' => 'item']);
            foreach ($this->individualStudentCards($event, $itemFilters) as $card) {
                $studentKey = $card['student_id'] ?? ($card['name'] ?? uniqid());
                $headId = $item->head_id;
                $byStudent[$headId][$studentKey]['head_title'] = $item->head?->name ?? 'General';
                $byStudent[$headId][$studentKey]['head_id'] = $headId;
                $byStudent[$headId][$studentKey]['card'] = array_merge($byStudent[$headId][$studentKey]['card'] ?? $card, [
                    'student_id' => $card['student_id'] ?? null,
                    'name' => $card['name'] ?? '',
                    'school_name' => $card['school_name'] ?? '',
                    'level_registration_number' => $card['level_registration_number'] ?? null,
                    'photo_url' => $card['photo_url'] ?? null,
                    'qr_svg' => $card['qr_svg'] ?? null,
                ]);
                $byStudent[$headId][$studentKey]['items'][] = $item->title;
            }
        }

        $sections = [];
        foreach ($byStudent as $headId => $students) {
            $cards = [];
            foreach ($students as $row) {
                $card = $row['card'];
                $card['items_list'] = implode(', ', array_unique($row['items'] ?? []));
                $cards[] = $card;
            }
            if ($cards !== []) {
                $sections[] = [
                    'head_title' => $students[array_key_first($students)]['head_title'] ?? 'Head',
                    'head_id' => $headId,
                    'cards' => $cards,
                ];
            }
        }

        return $sections;
    }

    /** @return array<string, mixed> */
    public function indexMeta(FestEvent $event, ?string $schoolId = null): array
    {
        return [
            'students'   => $this->studentCount($event, $schoolId),
            'volunteers' => FestVolunteer::where('event_id', $event->id)->count(),
            'staff'      => FestEventStaff::where('event_id', $event->id)->count(),
            'schools'    => $this->schoolOptions($event),
        ];
    }

    /** @return array<int, int> item_id => participant count */
    public function itemParticipantCounts(FestEvent $event, ?string $schoolId = null): array
    {
        $rows = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('status', 'approved')
                ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
            ->where('participant_role', '!=', 'standby')
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->join('fest_registrations', 'fest_participants.registration_id', '=', 'fest_registrations.id')
            ->selectRaw('fest_registrations.item_id, COUNT(*) as aggregate')
            ->groupBy('fest_registrations.item_id')
            ->pluck('aggregate', 'item_id');

        return $rows->map(fn ($count) => (int) $count)->all();
    }

    /** @return array<int, int> item_id => approved registration count */
    public function itemRegistrationCounts(FestEvent $event, ?string $schoolId = null): array
    {
        return FestRegistration::query()
            ->where('event_id', $event->id)
            ->where('status', 'approved')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
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
        return FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved')
            ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->where('participant_role', '!=', 'standby')
            ->count();
    }

    /** @param  array<string, mixed>  $filters */
    private function studentCards(FestEvent $event, array $filters): array
    {
        if (($filters['scope'] ?? 'item') === 'event') {
            return $this->eventParticipantCards($event, $filters);
        }

        $layout = $filters['layout'] ?? 'individual';

        if ($layout === 'team') {
            return $this->teamCards($event, $filters);
        }

        return $this->individualStudentCards($event, $filters);
    }

    /** @param  array<string, mixed>  $filters */
    private function eventParticipantCards(FestEvent $event, array $filters): array
    {
        $schoolId = $filters['school_id'] ?? null;
        $participantIds = $filters['participant_ids'] ?? null;

        $query = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved')
            ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
            ->where('participant_role', '!=', 'standby')
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->with(['student.tenant', 'teacher.tenant', 'registration.item', 'registration.school']);

        if (is_array($participantIds) && $participantIds !== []) {
            $query->whereIn('id', $participantIds);
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
                    'detail'          => $event->title,
                    'items'           => $items,
                    'item_count'      => count($items),
                    'item_label'      => null,
                    'id_label'        => 'Event pass',
                    'secondary_label' => 'Items',
                    'secondary_value' => (string) count($items),
                    'footer'          => $event->title,
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

        $query = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved')
            ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId))
            ->when($itemId, fn ($q2) => $q2->where('item_id', $itemId)))
            ->where('participant_role', '!=', 'standby')
            ->with(['student.tenant', 'teacher.tenant', 'registration.item', 'registration.school']);

        if (is_array($participantIds) && $participantIds !== []) {
            $query->whereIn('id', $participantIds);
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
            ->where('event_id', $event->id)
            ->where('status', 'approved')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
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
        $item = $p->registration?->item?->title ?? '—';
        $festId = $p->level_registration_number ?? '—';
        $chest = $this->chestService->participantLabel($p);
        $regNo = $p->student?->reg_no ?? $p->teacher?->reg_no ?? '';
        $scheduleLine = $this->scheduleLine($schedule);
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
            'detail'          => $item,
            'item_label'      => $item !== '—' ? $item : null,
            'schedule'        => $scheduleLine,
            'id_label'        => 'Fest ID',
            'id_number'       => $festId,
            'secondary_label' => $chest !== '—' && $chest !== $festId ? 'Chest' : 'Reg no',
            'secondary_value' => ($chest !== '—' && $chest !== $festId) ? $chest : ($regNo ?: '—'),
            'qr_src'          => $this->qrService->dataUri($qrPayload),
            'footer'          => $scheduleLine ?: $event->title,
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
}
