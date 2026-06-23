<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use Illuminate\Http\Request;

class FestScheduleController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load('items');

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with(['registration.item', 'student', 'teacher', 'group'])
            ->get();

        $schedules = FestSchedule::where('event_id', $event->id)
            ->with(['item', 'participant.student', 'participant.teacher'])
            ->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->get();

        return $this->inertia('Sahodaya/Events/Schedule', [
            'event'        => $event,
            'participants' => $participants,
            'schedules'    => $schedules,
        ]);
    }

    public function store(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_id'        => 'required|exists:fest_event_items,id',
            'participant_id' => 'nullable|exists:fest_participants,id',
            'scheduled_at'   => 'nullable|date',
            'stage'          => 'nullable|string|max:100',
            'sort_order'     => 'nullable|integer|min:0',
        ]);

        $data['event_id'] = $event->id;
        $data['sort_order'] = $data['sort_order'] ?? (FestSchedule::where('event_id', $event->id)->max('sort_order') ?? 0) + 1;

        FestSchedule::updateOrCreate(
            [
                'item_id'        => $data['item_id'],
                'participant_id' => $data['participant_id'] ?? null,
            ],
            $data
        );

        return back()->with('success', 'Schedule saved.');
    }

    public function autoGenerate(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with('registration.item')
            ->get()
            ->sortBy(fn ($p) => [$p->registration->item_id, $p->chest_no ?? 9999, $p->id]);

        $order = 1;
        foreach ($participants as $participant) {
            FestSchedule::updateOrCreate(
                [
                    'item_id'        => $participant->registration->item_id,
                    'participant_id' => $participant->id,
                ],
                [
                    'event_id'   => $event->id,
                    'sort_order' => $order++,
                ]
            );
        }

        return back()->with('success', 'Performance order generated from chest numbers.');
    }
}
