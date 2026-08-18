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
use App\Services\State\StatePublicResultsProjectionService;
use App\Services\State\StateResultPublicationService;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StateFestWorkspaceController extends Controller
{
    public function index()
    {
        $events = StateScope::apply(StateFestEvent::orderByDesc('starts_on'))->paginate(20);
        $routePrefix = request()->routeIs('state.portal.*') ? 'state.portal' : 'admin.state';
        $events->getCollection()->each(fn (StateFestEvent $event) => $event->setAttribute(
            'show_url',
            route("{$routePrefix}.fest.show", $event, false),
        ));

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

        StateScope::assertOwns(\App\Models\FestStateProgram::find($data['state_program_id'])?->state_id);

        $event = StateFestEvent::create(array_merge($data, [
            'status' => 'draft',
            'state_id' => StateScope::shouldScope() ? StateScope::id() : \App\Models\FestStateProgram::find($data['state_program_id'])?->state_id,
        ]));

        return redirect()->route('admin.state.fest.show', $event)->with('success', 'State fest event created.');
    }

    public function show(
        Request $request,
        StateFestEvent $event,
        StatePublicResultsProjectionService $publicResults,
        StateResultPublicationService $resultPublication,
    )
    {
        StateScope::assertOwns($event->state_id);
        $routePrefix = $request->routeIs('state.portal.*') ? 'state.portal' : 'admin.state';
        $approvedQualifiers = StateQualifierEntry::where('status', 'approved')
            ->whereHas('intake', fn ($q) => $q->where('state_program_id', $event->state_program_id))
            ->orderBy('item_code')
            ->limit(100)
            ->get();

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->with('participants.mark')
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
            'publishedResults'   => $publicResults->getPublicResults($event),
            'schoolRankings'     => $resultPublication->schoolRankings($event),
            'actionUrls'         => [
                'attendance' => route("{$routePrefix}.fest.attendance.index", $event, false),
                'chestNumbers' => route("{$routePrefix}.fest.assign-chest-numbers", $event, false),
                'judges' => route("{$routePrefix}.fest.judges.assign", $event, false),
                'marks' => route("{$routePrefix}.fest.marks.enter", $event, false),
                'publishResults' => route("{$routePrefix}.fest.results.publish", $event, false),
            ],
        ]);
    }

    /**
     * State Event Conduct, Phase 3 (docs/STATE_EVENT_CONDUCT_PLAN.md) — assign a user
     * (expected to hold the state_judge role, though not enforced here since a state_admin
     * can also judge) to an item for this event.
     */
    public function assignJudge(Request $request, StateFestEvent $event)
    {
        StateScope::assertOwns($event->state_id);
        $data = $request->validate([
            'item_id'    => [
                'required',
                'uuid',
                Rule::exists('state.state_fest_registrations', 'item_id')
                    ->where(fn ($query) => $query->where('state_event_id', $event->id)),
            ],
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
        StateScope::assertOwns($event->state_id);
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
        StateScope::assertOwns($event->state_id);
        StateEventLifecycleGate::allowMarkEntry($event);

        $data = $request->validate([
            'participant_id' => ['required', 'integer', Rule::exists('state.state_fest_participants', 'id')],
            'grade'          => 'nullable|in:A,A+,B,C',
            'score'          => 'nullable|numeric|min:0|max:100',
        ]);

        $participant = StateFestParticipant::with('registration')->findOrFail($data['participant_id']);
        abort_if($participant->registration->state_event_id !== $event->id, 403);
        abort_if(
            $participant->registration->participants()->min('id') !== $participant->id,
            422,
            'Enter one result for the registration using its first listed participant.',
        );

        \App\Models\State\StateFestMark::updateOrCreate(
            ['registration_id' => $participant->registration_id],
            [
                'state_event_id'  => $event->id,
                'registration_id' => $participant->registration_id,
                'participant_id'  => $participant->id,
                'score'           => $data['score'] ?? null,
                'grade'           => $data['grade'] ?? null,
                'status'          => 'draft',
                'entered_by'      => $request->user()->id,
            ],
        );

        return back()->with('success', "Mark saved for {$participant->student_name}.");
    }

    public function publishResults(StateFestEvent $event, StateResultPublicationService $service)
    {
        StateScope::assertOwns($event->state_id);
        $result = $service->publish($event);

        return back()->with('success', "Published {$result['marks']} State result(s) across {$result['items']} item(s).");
    }

    /**
     * WP-08 (master plan §29.13) — assign sequential chest numbers (101+) to every
     * approved registration's participants that doesn't have one yet. Was built as
     * StateConductService::assignChestNumbers() but never wired to a route until now;
     * safe to call repeatedly — already-numbered participants are left untouched.
     */
    public function assignChestNumbers(StateFestEvent $event, StateConductService $service)
    {
        StateScope::assertOwns($event->state_id);
        $count = $service->assignChestNumbers($event);

        return back()->with('success', $count > 0
            ? "Assigned chest numbers to {$count} participant(s)."
            : 'No unnumbered participants found — everyone already has a chest number.');
    }
}
