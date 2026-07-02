<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\Tenant;
use App\Services\Events\EventContext;
use App\Services\Events\FestChestNumberService;
use App\Services\Audit\PlatformAuditLogger;

class FestChestNumberController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return $this->inertia('Sahodaya/Events/ChestNumbers', $this->withEventActivity($event, FestPageActivity::CHEST_NUMBERS, [
            'event'        => $event,
            'participants' => $this->participantRows($event),
            'greenRoom'    => $this->greenRoomRows($event),
        ]));
    }

    public function greenRoom(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return $this->inertia('Sahodaya/Events/ChestNumbers', $this->withEventActivity($event, FestPageActivity::CHEST_NUMBERS, [
            'event'        => $event,
            'participants' => $this->participantRows($event),
            'greenRoom'    => $this->greenRoomRows($event),
            'view'         => 'green-room',
        ]));
    }

    public function generate(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $ctx = EventContext::for($event);
        $count = 0;

        FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with('registration.item')
            ->whereNull('chest_no')
            ->each(function (FestParticipant $p) use ($ctx, &$count) {
                if (! $p->registration->item_id) {
                    return;
                }
                $p->update(['chest_no' => $ctx->nextChestNumber($p->registration->item)]);
                $count++;
            });

        $audit->festEvent($event, FestPageActivity::CHEST_NUMBERS, 'fest.chest_numbers.generated', "Assigned {$count} chest number(s)", [
            'count' => $count,
        ]);

        return back()->with('success', "Assigned {$count} chest number(s).");
    }

    public function clearChest(string $tenantId, FestEvent $event, FestParticipant $participant, FestChestNumberService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($participant->registration->event_id !== $event->id, 403);

        $service->clearChest($participant);

        $audit->festEvent($event, FestPageActivity::CHEST_NUMBERS, 'fest.chest_number.cleared', 'Chest number cleared', [
            'participant_id' => $participant->id,
        ]);

        return back()->with('success', 'Chest number cleared.');
    }

    public function revealChest(string $tenantId, FestEvent $event, FestParticipant $participant, FestChestNumberService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($participant->registration->event_id !== $event->id, 403);

        $service->revealAtStageEntry($participant);

        $audit->festEvent($event, FestPageActivity::CHEST_NUMBERS, 'fest.chest_number.revealed', 'Chest number revealed', [
            'participant_id' => $participant->id,
        ]);

        $participant->loadMissing('registration.school', 'student', 'teacher');
        $schoolId = $participant->registration?->school_id;
        if ($schoolId) {
            app(\App\Services\Events\FestEventNotifier::class)->notifySchoolForChestReveal(
                $event,
                $schoolId,
                $participant->student?->name ?? $participant->teacher?->name ?? 'Participant',
            );
        }

        return back()->with('success', 'Chest number revealed.');
    }

    public function print(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $rows = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->whereNotNull('chest_no')
            ->with(['registration.item', 'student', 'teacher'])
            ->orderBy('chest_no')
            ->get()
            ->map(function (FestParticipant $p) {
                return [
                    'chest_no' => $p->chest_no,
                    'name'     => $p->student?->name ?? $p->teacher?->name,
                    'item'     => $p->registration->item?->title,
                    'school'   => Tenant::find($p->registration->school_id)?->name,
                ];
            });

        return view('fest.chest-numbers-print', [
            'event' => $event,
            'rows'  => $rows,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function participantRows(FestEvent $event): array
    {
        return FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with(['registration.item', 'registration.school', 'student', 'teacher', 'group'])
            ->orderBy('chest_no')
            ->get()
            ->map(function (FestParticipant $p) {
                return [
                    'id'                   => $p->id,
                    'chest_no'             => $p->chest_no,
                    'chest_revealed_at'    => $p->chest_revealed_at,
                    'name'                 => $p->student?->name ?? $p->teacher?->name,
                    'school'               => $p->registration->school?->name ?? Tenant::find($p->registration->school_id)?->name,
                    'item'                 => $p->registration->item?->title,
                    'group'                => $p->group?->team_name,
                    'level_registration_number' => $p->level_registration_number,
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function greenRoomRows(FestEvent $event): array
    {
        return FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved')
            ->whereHas('item', fn ($i) => $i->where('stage_type', 'on_stage')))
            ->whereNull('chest_revealed_at')
            ->with(['registration.item', 'registration.school', 'student', 'teacher'])
            ->orderBy('chest_no')
            ->get()
            ->map(function (FestParticipant $p) {
                return [
                    'id'        => $p->id,
                    'chest_no'  => $p->chest_no,
                    'name'      => $p->student?->name ?? $p->teacher?->name,
                    'school'    => $p->registration->school?->name ?? Tenant::find($p->registration->school_id)?->name,
                    'item'      => $p->registration->item?->title,
                ];
            })
            ->all();
    }
}
