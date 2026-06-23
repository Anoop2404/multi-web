<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestJudgeAssignment;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Events\FestScoreboardUpdated;
use App\Services\Events\EventContext;
use Illuminate\Http\Request;

class JudgeDashboardController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $sahodaya = Tenant::where('id', $tenantId)->where('type', 'sahodaya')->firstOrFail();
        $user = $request->user();

        $assignments = FestJudgeAssignment::where('user_id', $user->id)
            ->whereHas('event', fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['event', 'item'])
            ->get();

        $events = FestEvent::whereIn('id', $assignments->pluck('event_id')->unique())
            ->whereIn('status', ['published', 'registration_open', 'ongoing'])
            ->get();

        $assignmentsByEvent = $assignments->groupBy('event_id')->map(fn ($group) => $group->values());

        return inertia('Portal/Judge/Dashboard', [
            'sahodaya'    => $sahodaya->only('id', 'name'),
            'events'      => $events,
            'assignments' => $assignmentsByEvent,
        ]);
    }

    public function marks(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);

        $user = $request->user();
        $itemIds = $this->assignedItemIds($user->id, $event->id);

        if ($itemIds->isEmpty() && ! $user->hasAnyRole(['sahodaya_admin', 'mark_entry_admin']) && ! $user->isSuperAdmin()) {
            abort(403, 'No items assigned to you for this event.');
        }

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('status', 'approved')
            ->when($itemIds->isNotEmpty(), fn ($q) => $q->whereIn('item_id', $itemIds))
            ->with(['item', 'participants.student', 'participants.teacher'])
            ->get();

        $marks = FestMark::where('event_id', $event->id)
            ->when($itemIds->isNotEmpty(), fn ($q) => $q->whereIn('item_id', $itemIds))
            ->get()
            ->keyBy('participant_id');

        return inertia('Portal/Judge/MarkEntry', [
            'sahodaya'      => Tenant::find($tenantId)?->only('id', 'name'),
            'event'         => $event->load('items'),
            'registrations' => $registrations,
            'marks'         => $marks,
            'assignedItems' => $itemIds->values(),
        ]);
    }

    public function storeMark(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);

        $user = $request->user();
        $itemIds = $this->assignedItemIds($user->id, $event->id);

        $data = $request->validate([
            'participant_id' => 'required|exists:fest_participants,id',
            'item_id'        => 'required|exists:fest_event_items,id',
            'grade'          => 'nullable|in:A,B,C',
            'position'       => 'nullable|integer|min:1|max:255',
            'score'          => 'nullable|numeric|min:0',
        ]);

        if ($itemIds->isNotEmpty() && ! $itemIds->contains($data['item_id'])
            && ! $user->hasAnyRole(['sahodaya_admin', 'mark_entry_admin']) && ! $user->isSuperAdmin()) {
            abort(403, 'You are not assigned to this item.');
        }

        $participant = FestParticipant::findOrFail($data['participant_id']);
        abort_if($participant->registration->event_id !== $event->id, 403);

        FestMark::updateOrCreate(
            ['item_id' => $data['item_id'], 'participant_id' => $data['participant_id']],
            array_merge($data, [
                'event_id'  => $event->id,
                'locked_by' => $user->id,
                'locked_at' => now(),
            ])
        );

        EventContext::for($event)->recalculateSchoolPoints();
        FestScoreboardUpdated::dispatch($event->fresh());

        return back()->with('success', 'Mark saved.');
    }

    private function assignedItemIds(int $userId, int $eventId)
    {
        return FestJudgeAssignment::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->pluck('item_id');
    }
}
