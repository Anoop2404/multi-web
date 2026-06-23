<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Events\FestScoreboardUpdated;
use App\Services\Events\EventContext;
use Illuminate\Http\Request;

class FestMarkEntryController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load('items');

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('status', 'approved')
            ->with(['item', 'participants.student', 'participants.teacher'])
            ->get();

        $marks = FestMark::where('event_id', $event->id)->get()->keyBy('participant_id');

        return $this->inertia('Sahodaya/Events/MarkEntry', [
            'event'         => $event,
            'registrations' => $registrations,
            'marks'         => $marks,
        ]);
    }

    public function store(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'participant_id' => 'required|exists:fest_participants,id',
            'item_id'        => 'required|exists:fest_event_items,id',
            'grade'          => 'nullable|in:A,B,C',
            'position'       => 'nullable|integer|min:1|max:255',
            'score'          => 'nullable|numeric|min:0',
        ]);

        $participant = FestParticipant::findOrFail($data['participant_id']);
        abort_if($participant->registration->event_id !== $event->id, 403);

        FestMark::updateOrCreate(
            ['item_id' => $data['item_id'], 'participant_id' => $data['participant_id']],
            array_merge($data, [
                'event_id'  => $event->id,
                'locked_by' => $request->user()->id,
                'locked_at' => now(),
            ])
        );

        EventContext::for($event)->recalculateSchoolPoints();
        FestScoreboardUpdated::dispatch($event->fresh());

        return back()->with('success', 'Mark saved.');
    }
}
