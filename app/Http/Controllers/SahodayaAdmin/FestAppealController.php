<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class FestAppealController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $appeals = FestAppeal::where('event_id', $event->id)
            ->with(['participant.student', 'participant.registration.item', 'participant.registration.school'])
            ->latest()
            ->get();

        $disqualified = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id))
            ->whereNotNull('disqualified_at')
            ->with(['student', 'teacher', 'registration.item', 'registration.school'])
            ->get();

        $disqualifyCandidates = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->whereNull('disqualified_at')
            ->with(['student', 'teacher', 'registration.item', 'registration.school'])
            ->get()
            ->map(fn (FestParticipant $p) => [
                'id'    => $p->id,
                'label' => trim(($p->student?->reg_no ? $p->student->reg_no.' · ' : '')
                    .($p->student?->name ?? $p->teacher?->name ?? 'Participant')
                    .' — '.($p->registration?->school?->name ?? '')
                    .' · '.($p->registration?->item?->title ?? '')),
            ])
            ->values()
            ->all();

        return $this->inertia('Sahodaya/Events/Appeals', $this->withEventActivity($event, FestPageActivity::APPEALS, [
            'event'                => $event,
            'appeals'              => $appeals,
            'disqualified'         => $disqualified,
            'disqualifyCandidates' => $disqualifyCandidates,
        ]));
    }

    public function resolve(Request $request, string $tenantId, FestEvent $event, FestAppeal $appeal, PlatformAuditLogger $audit)
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

        $audit->festAppealResolved($appeal, $data['status']);

        return back()->with('success', 'Appeal '.$data['status'].'.');
    }

    public function markFeePaid(string $tenantId, FestEvent $event, FestAppeal $appeal, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($appeal->event_id !== $event->id, 403);

        $appeal->update(['fee_paid_at' => now()]);

        $audit->festAppealResolved($appeal, 'fee_paid');

        return back()->with('success', 'Appeal fee marked as paid.');
    }

    public function disqualify(Request $request, string $tenantId, FestEvent $event, FestParticipant $participant, PlatformAuditLogger $audit)
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

        $audit->festEvent($event, FestPageActivity::APPEALS, 'fest.participant.disqualified', 'Participant disqualified', [
            'participant_id' => $participant->id,
        ]);

        return back()->with('success', 'Participant disqualified.');
    }

    public function reinstate(string $tenantId, FestEvent $event, FestParticipant $participant, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($participant->registration->event_id !== $event->id, 403);

        $participant->update([
            'disqualified_at'         => null,
            'disqualification_reason' => null,
        ]);

        $audit->festEvent($event, FestPageActivity::APPEALS, 'fest.participant.reinstated', 'Disqualification removed', [
            'participant_id' => $participant->id,
        ]);

        return back()->with('success', 'Disqualification removed.');
    }
}
