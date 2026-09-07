<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\Tenant;
use App\Services\Events\FestEventNotifier;
use Illuminate\Http\Request;

class PortalFestAppealController extends Controller
{
    public function storeStudent(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== Tenant::findOrFail($tenantId)->parent_id, 403);

        $student = $request->attributes->get('portalStudent');

        // State-level wildcard appeals are deliberately not self-service — a student
        // can request a Sahodaya-level slot directly, but a State-level slot request
        // must go through the school admin (SchoolAdmin\FestEventPortalController::
        // storeAppeal), which already supports both types.
        $data = $request->validate([
            'appeal_type'    => 'nullable|in:dispute,sahodaya_wildcard',
            'participant_id' => 'nullable|exists:fest_participants,id',
            'item_id'        => 'nullable|exists:fest_event_items,id',
            'reason'         => 'required|string|max:2000',
        ]);

        $appealType = $data['appeal_type'] ?? FestAppeal::TYPE_DISPUTE;

        if ($appealType !== FestAppeal::TYPE_DISPUTE) {
            $this->storeWildcard($request, $event, $student, $appealType, $data);

            return back()->with('success', 'Appeal submitted.');
        }

        abort_if(empty($data['participant_id']), 422, 'participant_id is required for this appeal.');
        $participant = FestParticipant::findOrFail($data['participant_id']);
        abort_if($participant->student_id !== $student->id, 403);
        abort_if($participant->registration?->event_id !== $event->id, 403);
        \App\Services\Events\EventLifecycleGate::allowAppealForParticipant($event, $participant);

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

    /**
     * A wildcard appeal has no existing FestParticipant to reference — the student was
     * skipped at the tier below, so there's nothing to dispute, only a slot to request.
     * Grantingit is a separate, Sahodaya-admin-reviewed step (FestAppealWildcardService),
     * not part of submission.
     *
     * @param  array<string, mixed>  $data
     */
    private function storeWildcard(Request $request, FestEvent $event, $student, string $appealType, array $data): void
    {
        abort_if(empty($data['item_id']), 422, 'item_id is required for a wildcard appeal.');
        $item = FestEventItem::findOrFail($data['item_id']);
        abort_if($item->event_id !== $event->id, 422, 'Item does not belong to this event.');
        abort_if(! $event->appeals_open, 422, 'Appeals are not open for this event.');

        $duplicate = FestAppeal::where('event_id', $event->id)
            ->where('student_id', $student->id)
            ->where('item_id', $item->id)
            ->whereIn('appeal_type', FestAppeal::WILDCARD_TYPES)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
        abort_if($duplicate, 422, 'An active wildcard appeal already exists for this student and item.');

        FestAppeal::create([
            'event_id'             => $event->id,
            'appeal_type'          => $appealType,
            'student_id'           => $student->id,
            'item_id'              => $item->id,
            'reason'               => $data['reason'],
            'fee_amount'           => $event->appeal_fee_amount,
            'status'               => 'pending',
            'submitted_by_user_id' => $request->user()->id,
        ]);

        app(FestEventNotifier::class)->appealReceived($event, $student->name ?? 'Participant');
    }

    public function storeTeacher(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== Tenant::findOrFail($tenantId)->parent_id, 403);

        $teacher = $request->attributes->get('portalTeacher');

        $data = $request->validate([
            'participant_id' => 'required|exists:fest_participants,id',
            'reason'         => 'required|string|max:2000',
        ]);

        $participant = FestParticipant::findOrFail($data['participant_id']);
        abort_if($participant->teacher_id !== $teacher->id, 403);
        abort_if($participant->registration?->event_id !== $event->id, 403);
        \App\Services\Events\EventLifecycleGate::allowAppealForParticipant($event, $participant);

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
