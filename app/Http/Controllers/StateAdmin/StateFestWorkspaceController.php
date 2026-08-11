<?php

namespace App\Http\Controllers\StateAdmin;

use App\Http\Controllers\Controller;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateJudgeAssignment;
use App\Models\State\StateQualifierEntry;
use App\Models\User;
use App\Services\State\StateConductService;
use App\Services\State\StateEventLifecycleGate;
use App\Services\State\StateJudgeScoreService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StateFestWorkspaceController extends Controller
{
    public function index()
    {
        $events = StateFestEvent::orderByDesc('starts_on')->paginate(20);

        return Inertia::render('StateAdmin/Fest/Index', [
            'events' => $events,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'state_program_id' => 'required|uuid',
            'name'             => 'required|string|max:255',
            'starts_on'        => 'nullable|date',
            'ends_on'          => 'nullable|date',
        ]);

        $event = StateFestEvent::create(array_merge($data, ['status' => 'draft']));

        return redirect()->route('admin.state.fest.show', $event)->with('success', 'State fest event created.');
    }

    public function show(StateFestEvent $event)
    {
        $approvedQualifiers = StateQualifierEntry::where('status', 'approved')
            ->whereHas('intake', fn ($q) => $q->where('state_program_id', $event->state_program_id))
            ->orderBy('item_code')
            ->limit(100)
            ->get();

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->with('participants')
            ->orderBy('item_code')
            ->limit(200)
            ->get();

        $judgeAssignments = StateJudgeAssignment::where('state_event_id', $event->id)
            ->with('user:id,name,email')
            ->orderBy('item_code')
            ->get();

        return Inertia::render('StateAdmin/Fest/Show', [
            'event'              => $event,
            'approvedQualifiers' => $approvedQualifiers,
            'registrations'      => $registrations,
            'judgeAssignments'   => $judgeAssignments,
        ]);
    }

    /**
     * State Event Conduct, Phase 3 (docs/STATE_EVENT_CONDUCT_PLAN.md) — assign a user
     * (expected to hold the state_judge role, though not enforced here since a state_admin
     * can also judge) to an item for this event.
     */
    public function assignJudge(Request $request, StateFestEvent $event)
    {
        $data = $request->validate([
            'item_id'    => 'required|uuid',
            'item_code'  => 'nullable|string|max:64',
            'user_email' => 'required|email',
        ]);

        $user = User::where('email', $data['user_email'])->first();
        abort_unless($user, 404, "No user found with email {$data['user_email']}. They need an account before they can be assigned as a judge.");

        StateJudgeAssignment::firstOrCreate([
            'state_event_id' => $event->id,
            'item_id'        => $data['item_id'],
            'user_id'        => $user->id,
        ], [
            'item_code' => $data['item_code'] ?? null,
        ]);

        return back()->with('success', "Assigned {$user->name} as judge for this item.");
    }

    public function unassignJudge(StateFestEvent $event, StateJudgeAssignment $assignment)
    {
        abort_if($assignment->state_event_id !== $event->id, 403);

        $assignment->delete();

        return back()->with('success', 'Judge unassigned.');
    }

    /**
     * Coordinator direct-entry path (State Event Conduct Phase 4) — for items with no judge
     * panel, or as a state_admin override. Reuses the same StateEventLifecycleGate and
     * StateJudgeScoreService::syncAggregatedMark() write target, but bypasses the
     * judge-assignment requirement entirely: a coordinator-entered score is treated as
     * authoritative on its own, not averaged against anything.
     */
    public function enterMark(Request $request, StateFestEvent $event)
    {
        StateEventLifecycleGate::allowMarkEntry($event);

        $data = $request->validate([
            'participant_id' => 'required|integer|exists:state_fest_participants,id',
            'grade'          => 'nullable|in:A,A+,B,C',
            'score'          => 'nullable|numeric|min:0',
        ]);

        $participant = StateFestParticipant::with('registration')->findOrFail($data['participant_id']);
        abort_if($participant->registration->state_event_id !== $event->id, 403);

        \App\Models\State\StateFestMark::updateOrCreate(
            ['participant_id' => $participant->id],
            [
                'state_event_id'  => $event->id,
                'registration_id' => $participant->registration_id,
                'score'           => $data['score'] ?? null,
                'grade'           => $data['grade'] ?? null,
                'status'          => 'draft',
                'entered_by'      => $request->user()->id,
            ],
        );

        return back()->with('success', "Mark saved for {$participant->student_name}.");
    }

    /**
     * WP-08 (master plan §29.13) — assign sequential chest numbers (101+) to every
     * approved registration's participants that doesn't have one yet. Was built as
     * StateConductService::assignChestNumbers() but never wired to a route until now;
     * safe to call repeatedly — already-numbered participants are left untouched.
     */
    public function assignChestNumbers(StateFestEvent $event, StateConductService $service)
    {
        $count = $service->assignChestNumbers($event);

        return back()->with('success', $count > 0
            ? "Assigned chest numbers to {$count} participant(s)."
            : 'No unnumbered participants found — everyone already has a chest number.');
    }
}
