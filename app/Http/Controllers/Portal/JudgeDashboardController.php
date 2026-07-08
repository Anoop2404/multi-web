<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestJudgeAssignment;
use App\Models\FestJudgeScore;
use App\Models\FestParticipant;
use App\Models\Tenant;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestJudgeScoreService;
use App\Services\Events\PortalEventHeadNavService;
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
            $marked = FestJudgeScore::where('event_id', $eventId)
                ->where('item_id', $item?->id)
                ->where('judge_user_id', $user->id)
                ->whereIn('participant_id', $participantIds)
                ->where(function ($q) {
                    $q->whereNotNull('grade')->orWhereNotNull('score');
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
        abort_if($event->event_type === 'sports', 404, 'Sports events use item head coordinators — sign in at the mark coordinator portal.');

        $user = $request->user();
        $scope = app(\App\Services\Events\FestMarkEntryScopeService::class);
        $itemIds = $scope->judgeItemIds($user, $event);

        if ($itemIds === [] && ! $user->hasAnyRole(['sahodaya_admin', 'mark_entry_admin']) && ! $user->isSuperAdmin()) {
            abort(403, 'No items assigned to you for this event.');
        }

        $registrations = $scope->scopedRegistrations($event, $user, $itemIds ?: null);
        $marks = collect(app(FestJudgeScoreService::class)->scoresForJudge($event, $user->id, $itemIds ?: null));

        $headNav = app(PortalEventHeadNavService::class);
        $headContext = $headNav->contextForAssignedItems($event, $request, $itemIds ?: []);
        $registrations = $headNav->filterRegistrations(
            $registrations,
            $headContext['selectedHeadId'],
            $headContext['selectedItemId'],
        );

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
            'assignedItems'     => collect($itemIds)->values(),
            'maskNames'         => $maskNames,
            'participantLabels' => $participantLabels,
            'judgeMode'         => true,
            ...$headContext,
        ]);
    }

    public function storeMark(Request $request, string $tenantId, FestEvent $event, FestJudgeScoreService $judgeScores)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        abort_if($event->event_type === 'sports', 404);

        $user = $request->user();
        $itemIds = app(\App\Services\Events\FestMarkEntryScopeService::class)->judgeItemIds($user, $event);

        $data = $request->validate([
            'participant_id'    => 'required|exists:fest_participants,id',
            'item_id'           => 'required|exists:fest_event_items,id',
            'grade'             => 'nullable|in:A,A+,B,C',
            'position'          => 'nullable|integer|min:1|max:255',
            'score'             => 'nullable|numeric|min:0',
            'measurement_value' => 'nullable|string|max:50',
            'measurement_unit'  => 'nullable|string|max:20',
        ]);

        if ($itemIds !== [] && ! in_array((int) $data['item_id'], $itemIds, true)
            && ! $user->hasAnyRole(['sahodaya_admin', 'mark_entry_admin']) && ! $user->isSuperAdmin()) {
            abort(403, 'You are not assigned to this item.');
        }

        EventLifecycleGate::allowMarkEntry($event);

        $result = $judgeScores->save($event, $data, $user->id);

        app(PlatformAuditLogger::class)->judgeMarkEntered(
            $event,
            (int) $data['participant_id'],
            (int) $data['item_id'],
        );

        return back()->with('success', $result['message']);
    }
}
