<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\Tenant;
use App\Services\Events\FestEventNotifier;
use Illuminate\Http\Request;

class PortalFestAppealController extends Controller
{
    public function storeStudent(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== Tenant::findOrFail($tenantId)->parent_id, 403);
        abort_unless($event->appeals_open, 422, 'Appeals are not open for this event.');

        $student = $request->attributes->get('portalStudent');

        $data = $request->validate([
            'participant_id' => 'required|exists:fest_participants,id',
            'reason'         => 'required|string|max:2000',
        ]);

        $participant = FestParticipant::findOrFail($data['participant_id']);
        abort_if($participant->student_id !== $student->id, 403);
        abort_if($participant->registration?->event_id !== $event->id, 403);

        FestAppeal::create([
            'event_id'             => $event->id,
            'participant_id'       => $participant->id,
            'reason'               => $data['reason'],
            'fee_amount'           => $event->appeal_fee_amount,
            'status'               => 'pending',
            'submitted_by_user_id' => $request->user()->id,
        ]);

        app(FestEventNotifier::class)->appealReceived(
            $event,
            $participant->student?->name ?? 'Participant',
        );

        return back()->with('success', 'Appeal submitted.');
    }

    public function storeTeacher(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== Tenant::findOrFail($tenantId)->parent_id, 403);
        abort_unless($event->appeals_open, 422, 'Appeals are not open for this event.');

        $teacher = $request->attributes->get('portalTeacher');

        $data = $request->validate([
            'participant_id' => 'required|exists:fest_participants,id',
            'reason'         => 'required|string|max:2000',
        ]);

        $participant = FestParticipant::findOrFail($data['participant_id']);
        abort_if($participant->teacher_id !== $teacher->id, 403);
        abort_if($participant->registration?->event_id !== $event->id, 403);

        FestAppeal::create([
            'event_id'             => $event->id,
            'participant_id'       => $participant->id,
            'reason'               => $data['reason'],
            'fee_amount'           => $event->appeal_fee_amount,
            'status'               => 'pending',
            'submitted_by_user_id' => $request->user()->id,
        ]);

        app(FestEventNotifier::class)->appealReceived(
            $event,
            $participant->teacher?->name ?? 'Participant',
        );

        return back()->with('success', 'Appeal submitted.');
    }
}
