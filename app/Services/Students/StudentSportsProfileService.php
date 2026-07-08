<?php

namespace App\Services\Students;

use App\Models\FestEvent;
use App\Models\FestLevelRegistration;
use App\Models\FestParticipant;
use App\Models\FestRecordBreak;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestEventRegistrationService;
use App\Services\Events\FestItemWindowResolver;

class StudentSportsProfileService
{
    public function __construct(
        private FestItemWindowResolver $windowResolver,
        private FestEventRegistrationService $eventRegistrationService,
    ) {}

    /** @return array{sports_events: list<array<string, mixed>>, other_fest: list<array<string, mixed>>} */
    public function forStudent(Student $student, string $schoolId, string $context = 'school'): array
    {
        $school = Tenant::find($schoolId);

        $participants = FestParticipant::query()
            ->where('student_id', $student->id)
            ->whereHas('registration', fn ($q) => $q->where('school_id', $schoolId))
            ->with([
                'mark',
                'registration.event:id,title,event_type,status,require_event_registration',
                'registration.item:id,title,head_id,sport_discipline,ranking_direction,category',
                'registration.item.head:id,name,competition_start,competition_end',
            ])
            ->get();

        $levelRegs = FestLevelRegistration::query()
            ->where('student_id', $student->id)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->with('event:id,title,event_type,status,require_event_registration')
            ->get()
            ->keyBy('event_id');

        $markIds = $participants->map(fn (FestParticipant $p) => $p->mark?->id)->filter()->unique()->values();
        $recordBreaksByMark = $markIds->isEmpty()
            ? collect()
            : FestRecordBreak::query()->whereIn('mark_id', $markIds)->get()->keyBy('mark_id');

        $sportsByEvent = collect();
        $otherFest = collect();

        foreach ($participants as $participant) {
            $registration = $participant->registration;
            $event = $registration?->event;
            $item = $registration?->item;

            if (! $event || ! $item) {
                continue;
            }

            if ($event->event_type !== 'sports') {
                $otherFest->push(array_merge(
                    $this->otherFestRow($participant),
                    ['id_cards' => $school ? $this->idCardLinks($event, $student, $school, $context) : null],
                ));

                continue;
            }

            $eventId = $event->id;
            if (! $sportsByEvent->has($eventId)) {
                $levelReg = $levelRegs->get($eventId);
                $sportsByEvent->put($eventId, [
                    'event_id'    => $eventId,
                    'event_title' => $event->title,
                    'event_status'=> $event->status,
                    'require_event_registration' => (bool) $event->require_event_registration,
                    'event_registration' => $levelReg ? [
                        'registered'            => true,
                        'registration_number' => $levelReg->registration_number,
                        'registered_at'       => $levelReg->registered_at?->toIso8601String(),
                        'status'              => $levelReg->status,
                    ] : [
                        'registered' => false,
                    ],
                    'heads' => [],
                ]);
            }

            $headId = $item->head_id ?? 0;
            $headName = $item->head?->name ?? 'General';
            $eventEntry = $sportsByEvent->get($eventId);

            if (! isset($eventEntry['heads'][$headId])) {
                $eventEntry['heads'][$headId] = [
                    'head_id'            => $headId ?: null,
                    'head_name'          => $headName,
                    'competition_start'  => $item->head?->competition_start?->format('Y-m-d'),
                    'competition_end'    => $item->head?->competition_end?->format('Y-m-d'),
                    'items'              => [],
                ];
            }

            $mark = $participant->mark;
            $recordBreak = $mark ? $recordBreaksByMark->get($mark->id) : null;

            $eventEntry['heads'][$headId]['items'][] = [
                'item_id'           => $item->id,
                'item_title'        => $item->title,
                'sport_discipline'  => $item->sport_discipline,
                'registration_status' => $registration->status,
                'chest_no'          => $participant->chest_no,
                'fest_id'           => $participant->level_registration_number,
                'competition_start' => $this->windowResolver->effectiveCompetitionStart($item)?->format('Y-m-d'),
                'competition_end'   => $this->windowResolver->effectiveCompetitionEnd($item)?->format('Y-m-d'),
                'mark'              => $mark ? [
                    'measurement_value' => $mark->measurement_value,
                    'measurement_unit'  => $mark->measurement_unit
                        ?? match ($item->ranking_direction) {
                            'higher_better' => 'm',
                            'lower_better'  => 's',
                            default         => null,
                        },
                    'position'          => $mark->position,
                    'grade'             => $mark->grade,
                    'score'             => $mark->score,
                    'record_break'      => $recordBreak !== null,
                    'record_break_label'=> $recordBreak
                        ? "Broke record: {$recordBreak->previous_value} → {$recordBreak->new_value} {$recordBreak->record_unit}"
                        : null,
                ] : null,
            ];

            $sportsByEvent->put($eventId, $eventEntry);
        }

        foreach ($levelRegs as $eventId => $levelReg) {
            $event = $levelReg->event;
            if (! $event || $event->event_type !== 'sports' || $sportsByEvent->has($eventId)) {
                continue;
            }

            $sportsByEvent->put($eventId, [
                'event_id'    => $eventId,
                'event_title' => $event->title,
                'event_status'=> $event->status,
                'require_event_registration' => (bool) $event->require_event_registration,
                'event_registration' => [
                    'registered'            => true,
                    'registration_number' => $levelReg->registration_number,
                    'registered_at'       => $levelReg->registered_at?->toIso8601String(),
                    'status'              => $levelReg->status,
                ],
                'heads' => [],
            ]);
        }

        $openEvents = $school
            ? $this->openSportsEvents($school)
            : collect();

        foreach ($openEvents as $openEvent) {
            if ($sportsByEvent->has($openEvent->id)) {
                continue;
            }

            $levelReg = $levelRegs->get($openEvent->id);
            $sportsByEvent->put($openEvent->id, [
                'event_id'    => $openEvent->id,
                'event_title' => $openEvent->title,
                'event_status'=> $openEvent->status,
                'require_event_registration' => (bool) $openEvent->require_event_registration,
                'event_registration' => $levelReg ? [
                    'registered'            => true,
                    'registration_number' => $levelReg->registration_number,
                    'registered_at'       => $levelReg->registered_at?->toIso8601String(),
                    'status'              => $levelReg->status,
                ] : [
                    'registered' => false,
                ],
                'heads' => [],
            ]);
        }

        $sportsEvents = $sportsByEvent
            ->map(function (array $event) use ($openEvents, $schoolId, $student, $school, $context) {
                $event['heads'] = collect($event['heads'])
                    ->sortBy('head_name')
                    ->map(function (array $head) {
                        $head['items'] = collect($head['items'])->sortBy('item_title')->values()->all();

                        return $head;
                    })
                    ->values()
                    ->all();

                $festEvent = $openEvents->firstWhere('id', $event['event_id'])
                    ?? FestEvent::find($event['event_id']);
                if ($festEvent && $school) {
                    $event['actions'] = $this->registrationActions($festEvent, $student, $school, $context);
                    $event['id_cards'] = $this->idCardLinks($festEvent, $student, $school, $context);
                }

                return $event;
            })
            ->sortByDesc(fn (array $e) => $e['event_registration']['registered_at'] ?? '')
            ->values()
            ->all();

        return [
            'sports_events'          => $sportsEvents,
            'other_fest'             => $otherFest->values()->all(),
            'has_open_sports_events' => $openEvents->isNotEmpty(),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, FestEvent> */
    private function openSportsEvents(Tenant $school): \Illuminate\Support\Collection
    {
        return FestEvent::query()
            ->where('tenant_id', $school->parent_id)
            ->ofType('sports')
            ->visibleToSchool($school->id)
            ->whereIn('status', ['published', 'registration_open', 'ongoing'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'status', 'require_event_registration', 'registration_locked', 'registration_open', 'registration_close', 'event_reg_start', 'event_reg_end']);
    }

    /** @return array<string, mixed>|null */
    private function idCardLinks(FestEvent $event, Student $student, Tenant $school, string $context = 'school'): ?array
    {
        $hasRegistration = FestParticipant::query()
            ->where('student_id', $student->id)
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('school_id', $school->id)
                ->whereNotIn('status', ['rejected', 'withdrawn']))
            ->exists();

        if (! $hasRegistration) {
            return null;
        }

        if ($context === 'school') {
            $downloadGate = app(\App\Services\School\SchoolDocumentDownloadGateService::class)
                ->payload($school, $event);

            if ($downloadGate['blocked']) {
                return [
                    'available'        => false,
                    'payment_pending'  => true,
                    'reason'           => $downloadGate['reason'],
                    'links'            => $downloadGate['links'],
                ];
            }
        }

        $base = $context === 'portal'
            ? "/portal/student/{$school->id}/fest/{$event->id}/id-card"
            : "/school-admin/{$school->id}/students/{$student->id}/fest/{$event->id}/id-card";

        $defaultScope = $event->event_type === 'sports' ? 'head' : 'event';

        $heads = [];
        if ($event->event_type === 'sports') {
            $headRows = FestParticipant::query()
                ->where('student_id', $student->id)
                ->whereHas('registration', fn ($q) => $q
                    ->where('event_id', $event->id)
                    ->where('school_id', $school->id)
                    ->whereNotIn('status', ['rejected', 'withdrawn']))
                ->with('registration.item.head:id,name')
                ->get()
                ->map(fn (FestParticipant $p) => $p->registration?->item?->head)
                ->filter()
                ->unique('id')
                ->values();

            foreach ($headRows as $head) {
                if (! $head) {
                    continue;
                }
                $heads[] = [
                    'head_id'       => $head->id,
                    'head_name'     => $head->name,
                    'view_url'      => "{$base}?scope=head&head_id={$head->id}&inline=1",
                    'download_url'  => "{$base}?scope=head&head_id={$head->id}",
                ];
            }
        }

        return [
            'available'              => true,
            'payment_pending'        => false,
            'default_scope'          => $defaultScope,
            'view_url'               => "{$base}?scope={$defaultScope}&inline=1",
            'download_url'           => "{$base}?scope={$defaultScope}",
            'event_pass_view_url'    => "{$base}?scope=event&inline=1",
            'event_pass_download_url'=> "{$base}?scope=event",
            'heads'                  => $heads,
        ];
    }

    /** @return array<string, mixed> */
    private function registrationActions(FestEvent $event, Student $student, Tenant $school, string $context = 'school'): array
    {
        $registered = (bool) ($this->eventRegistrationService->studentIsRegistered($event, $student->id));
        $eventRegOpen = $this->eventRegistrationService->isEventRegistrationOpen($event);
        $festClosed = (bool) $school->fest_registration_closed;
        $schoolBlocked = $festClosed || (bool) $event->registration_locked;

        $requiresEventReg = $this->eventRegistrationService->requireEventRegistration($event);
        $feeGate = app(\App\Services\Events\FestRegistrationFeeGate::class);
        $feeCleared = $feeGate->isSchoolFeeCleared($event, $school->id);
        $downloadGate = $context === 'school'
            ? app(\App\Services\School\SchoolDocumentDownloadGateService::class)->payload($school, $event)
            : null;

        $canRegisterItems = ! $schoolBlocked
            && $event->isRegistrationOpen()
            && (! $requiresEventReg || $registered);

        if ($context === 'portal') {
            $selfReg = (bool) $event->allow_student_self_register;
            $portalBase = "/portal/student/{$school->id}";

            if (! $selfReg) {
                return [
                    'context'                 => 'portal',
                    'read_only'               => true,
                    'read_only_reason'        => 'Your school registers athletes for this event. Contact your sports coordinator if you need to join.',
                    'fest_registrations_url'  => "{$portalBase}/fest-registrations",
                    'event_registration_open' => false,
                    'item_registration_open'  => false,
                    'can_register_event'      => false,
                    'can_register_items'      => false,
                    'fee_blocks_items'        => false,
                    'event_fee_cleared'       => $feeCleared,
                    'download_gate'           => $downloadGate,
                    'school_registration_closed' => $festClosed,
                ];
            }

            return [
                'context'                 => 'portal',
                'read_only'               => false,
                'event_registration_open' => $eventRegOpen && ! $schoolBlocked,
                'item_registration_open'  => $canRegisterItems,
                'can_register_event'      => ! $registered && $eventRegOpen && ! $schoolBlocked,
                'can_register_items'      => $canRegisterItems && $selfReg,
                'register_event_url'      => "{$portalBase}/fest/{$event->id}/register",
                'eligible_items_url'      => "{$portalBase}/fest/{$event->id}/eligible-items",
                'fest_registrations_url'  => "{$portalBase}/fest-registrations",
                'fee_blocks_items'        => false,
                'event_fee_cleared'       => $feeCleared,
                'download_gate'           => $downloadGate,
                'school_registration_closed' => $festClosed,
                'school_block_message'    => $festClosed
                    ? 'Fest registration is closed for your school.'
                    : null,
            ];
        }

        $base = "/school-admin/{$school->id}";

        return [
            'context'                 => 'school',
            'read_only'               => false,
            'event_registration_open' => $eventRegOpen && ! $schoolBlocked,
            'item_registration_open'  => $canRegisterItems,
            'can_register_event'      => ! $registered && $eventRegOpen && ! $schoolBlocked,
            'can_register_items'      => $canRegisterItems,
            'register_event_url'      => "{$base}/students/{$student->id}/sports/events/{$event->id}/register",
            'register_items_url'      => "{$base}/students/{$student->id}/sports/events/{$event->id}/items",
            'eligible_items_url'      => "{$base}/students/{$student->id}/sports/events/{$event->id}/eligible-items",
            'event_registration_url'  => "{$base}/sports/events/{$event->id}/registration",
            'item_registration_url'   => "{$base}/sports/item-registration?event={$event->id}",
            'fee_blocks_items'        => false,
            'event_fee_cleared'       => $feeCleared,
            'download_gate'           => $downloadGate,
            'school_registration_closed' => $festClosed,
            'school_block_message'    => $festClosed
                ? 'Fest registration is closed for your school.'
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function otherFestRow(FestParticipant $participant): array
    {
        $registration = $participant->registration;

        return [
            'event_id'    => $registration->event_id,
            'event_title' => $registration->event?->title,
            'event_type'  => $registration->event?->event_type,
            'item_title'  => $registration->item?->title,
            'status'      => $registration->status,
            'chest_no'    => $participant->chest_no,
            'fest_id'     => $participant->level_registration_number,
            'mark'        => $participant->mark?->only(['grade', 'position', 'score']),
        ];
    }
}
