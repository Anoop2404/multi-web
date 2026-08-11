<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateJudgeAssignment;
use App\Models\State\StateJudgeScore;
use App\Services\State\StateEventLifecycleGate;
use App\Services\State\StateJudgeScoreService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * State Event Conduct, Phase 3 (docs/STATE_EVENT_CONDUCT_PLAN.md) — mirrors
 * App\Http\Controllers\Portal\JudgeDashboardController's shape, simplified: no tenant_id
 * scoping (State isn't a tenant), no anonymity-masking or head-nav (tenant-specific
 * concerns without a State equivalent yet).
 */
class StateJudgeDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $assignments = StateJudgeAssignment::where('user_id', $user->id)
            ->with('stateEvent')
            ->get();

        $events = StateFestEvent::whereIn('id', $assignments->pluck('state_event_id')->unique())->get();
        $assignmentsByEvent = $assignments->groupBy('state_event_id')->map(fn ($group) => $group->values());

        $itemProgress = $assignments->groupBy('item_id')->map(function ($group) use ($user) {
            $first = $group->first();
            $eventId = $first->state_event_id;
            $itemId = $first->item_id;

            $participantIds = StateFestParticipant::whereHas('registration', fn ($q) => $q
                ->where('state_event_id', $eventId)
                ->where('item_id', $itemId)
                ->where('status', 'approved'))
                ->get()
                ->groupBy('registration_id')
                ->map(fn ($participants) => $participants->min('id'))
                ->values();

            $marked = StateJudgeScore::where('state_event_id', $eventId)
                ->where('item_id', $itemId)
                ->where('judge_user_id', $user->id)
                ->whereIn('participant_id', $participantIds)
                ->whereNotNull('score')
                ->count();

            return [
                'item_id'    => $itemId,
                'item_title' => $first->item_code,
                'event_id'   => $eventId,
                'marked'     => $marked,
                'total'      => $participantIds->count(),
            ];
        })->values();

        return inertia('Portal/StateJudge/Dashboard', [
            'events'       => $events,
            'assignments'  => $assignmentsByEvent,
            'itemProgress' => $itemProgress,
        ]);
    }

    public function marks(Request $request, StateFestEvent $event)
    {
        $user = $request->user();

        $itemIds = StateJudgeAssignment::where('state_event_id', $event->id)
            ->where('user_id', $user->id)
            ->pluck('item_id')
            ->all();

        if ($itemIds === [] && ! $user->hasAnyRole(['state_admin', 'state_staff']) && ! $user->isSuperAdmin()) {
            abort(403, 'No items assigned to you for this event.');
        }

        $registrations = $event->registrations()
            ->when($itemIds !== [], fn ($q) => $q->whereIn('item_id', $itemIds))
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->with('participants')
            ->orderBy('item_code')
            ->get();

        // A team/group is one competitive result. Keep every roster member in State
        // for attendance, but present only the first member as the scoring target.
        $registrations->each(function ($registration) {
            $registration->setRelation('participants', $registration->participants->take(1)->values());
        });

        $marks = collect(app(StateJudgeScoreService::class)->scoresForJudge($event, $user->id, $itemIds ?: null));

        return inertia('Portal/StateJudge/MarkEntry', [
            'event'         => $event,
            'registrations' => $registrations,
            'marks'         => $marks,
            'assignedItems' => collect($itemIds)->values(),
        ]);
    }

    public function storeMark(Request $request, StateFestEvent $event, StateJudgeScoreService $judgeScores)
    {
        $user = $request->user();

        $itemIds = StateJudgeAssignment::where('state_event_id', $event->id)
            ->where('user_id', $user->id)
            ->pluck('item_id')
            ->all();

        $data = $request->validate([
            'participant_id' => ['required', 'integer', Rule::exists('state.state_fest_participants', 'id')],
            'item_id'        => 'required|uuid',
            'item_code'      => 'nullable|string|max:64',
            'grade'          => 'nullable|in:A,A+,B,C',
            'score'          => 'nullable|numeric|min:0|max:100',
            'notes'          => 'nullable|string|max:2000',
        ]);

        if ($itemIds !== [] && ! in_array($data['item_id'], $itemIds, true)
            && ! $user->hasAnyRole(['state_admin', 'state_staff']) && ! $user->isSuperAdmin()) {
            abort(403, 'You are not assigned to this item.');
        }

        StateEventLifecycleGate::allowMarkEntry($event);

        $result = $judgeScores->save($event, $data, $user->id);

        return back()->with('success', $result['message']);
    }
}
