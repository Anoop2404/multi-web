<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use Illuminate\Http\Request;

class FestAppealController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $appeals = FestAppeal::where('event_id', $event->id)
            ->with(['participant.student', 'participant.registration.item'])
            ->latest()
            ->get();

        $disqualified = \App\Models\FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id))
            ->whereNotNull('disqualified_at')
            ->with(['student', 'registration.item'])
            ->get();

        return $this->inertia('Sahodaya/Events/Appeals', [
            'event'          => $event,
            'appeals'        => $appeals,
            'disqualified'   => $disqualified,
        ]);
    }

    public function resolve(Request $request, string $tenantId, FestEvent $event, FestAppeal $appeal)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($appeal->event_id !== $event->id, 403);

        $data = $request->validate([
            'status'          => 'required|in:approved,rejected',
            'resolution_note' => 'nullable|string|max:1000',
        ]);

        $appeal->update([
            'status'               => $data['status'],
            'resolution_note'      => $data['resolution_note'] ?? null,
            'resolved_by_user_id'  => $request->user()->id,
            'resolved_at'          => now(),
        ]);

        return back()->with('success', 'Appeal '.$data['status'].'.');
    }

    public function disqualify(Request $request, string $tenantId, FestEvent $event, FestParticipant $participant)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($participant->registration->event_id !== $event->id, 403);

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $participant->update([
            'disqualified_at'          => now(),
            'disqualification_reason'  => $data['reason'],
        ]);

        return back()->with('success', 'Participant disqualified.');
    }

    public function reinstate(string $tenantId, FestEvent $event, FestParticipant $participant)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($participant->registration->event_id !== $event->id, 403);

        $participant->update([
            'disqualified_at'         => null,
            'disqualification_reason' => null,
        ]);

        return back()->with('success', 'Disqualification removed.');
    }
}
