<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestJudgeAssignment;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestMarkSaveService;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestPublicVisibilityService;
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

        $itemProgress = $assignments->groupBy('item_id')->map(function ($group) use ($user) {
            $item = $group->first()->item;
            $eventId = $group->first()->event_id;
            $participantIds = FestParticipant::whereHas('registration', fn ($q) => $q
                ->where('event_id', $eventId)
                ->where('item_id', $item?->id)
                ->where('status', 'approved'))
                ->pluck('id');

            $total = $participantIds->count();
            $marked = FestMark::where('event_id', $eventId)
                ->where('item_id', $item?->id)
                ->whereIn('participant_id', $participantIds)
                ->where(function ($q) {
                    $q->whereNotNull('grade')->orWhereNotNull('score')->orWhereNotNull('position');
                })
                ->count();

            return [
                'item_id'    => $item?->id,
                'item_title' => $item?->title,
                'event_id'   => $eventId,
                'marked'     => $marked,
                'total'      => $total,
            ];
        })->values();

        return inertia('Portal/Judge/Dashboard', [
            'sahodaya'     => $sahodaya->only('id', 'name'),
            'events'       => $events,
            'assignments'  => $assignmentsByEvent,
            'itemProgress' => $itemProgress,
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

        $visibility = app(FestPublicVisibilityService::class);
        $maskNames = $visibility->strictAnonymity($event) && ! $event->results_published;

        $participantLabels = [];
        foreach ($registrations as $reg) {
            foreach ($reg->participants as $p) {
                $participantLabels[$p->id] = $visibility->publicReference($event, $p);
            }
        }

        return inertia('Portal/Judge/MarkEntry', [
            'sahodaya'          => Tenant::find($tenantId)?->only('id', 'name'),
            'event'             => $event->load('items'),
            'registrations'     => $registrations,
            'marks'             => $marks,
            'assignedItems'     => $itemIds->values(),
            'maskNames'         => $maskNames,
            'participantLabels' => $participantLabels,
        ]);
    }

    public function storeMark(Request $request, string $tenantId, FestEvent $event, FestMarkSaveService $markSave)
    {
        abort_if($event->tenant_id !== $tenantId, 403);

        $user = $request->user();
        $itemIds = $this->assignedItemIds($user->id, $event->id);

        $data = $request->validate([
            'participant_id'    => 'required|exists:fest_participants,id',
            'item_id'           => 'required|exists:fest_event_items,id',
            'grade'             => 'nullable|in:A,A+,B,C',
            'position'          => 'nullable|integer|min:1|max:255',
            'score'             => 'nullable|numeric|min:0',
            'measurement_value' => 'nullable|string|max:50',
            'measurement_unit'  => 'nullable|string|max:20',
        ]);

        if ($itemIds->isNotEmpty() && ! $itemIds->contains($data['item_id'])
            && ! $user->hasAnyRole(['sahodaya_admin', 'mark_entry_admin']) && ! $user->isSuperAdmin()) {
            abort(403, 'You are not assigned to this item.');
        }

        EventLifecycleGate::allowMarkEntry($event);

        $result = $markSave->save($event, $data, $user->id);

        app(PlatformAuditLogger::class)->judgeMarkEntered(
            $event,
            (int) $data['participant_id'],
            (int) $data['item_id'],
        );

        return back()->with('success', $result['message']);
    }

    private function assignedItemIds(int $userId, int $eventId)
    {
        return FestJudgeAssignment::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->pluck('item_id');
    }
}
