<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateSahodaya;
use App\Models\State\StateSubstitution;
use App\Services\State\Fest\StateEventSettings;
use App\Services\State\Fest\StateTeamService;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Phase 4 of the State Kalotsav module — Teams & Squads, and Substitutions.
 */
class StateTeamController extends Controller
{
    public function teams(Request $request, StateFestEvent $event, StateTeamService $teams)
    {
        StateScope::assertOwns($event->state_id);

        $filters = $request->validate(['sahodaya_id' => 'nullable|uuid']);
        $rows = $teams->teamsFor($event, $filters['sahodaya_id'] ?? null);

        return Inertia::render('State/Fest/Teams', $this->shell($request, $event) + [
            'teams' => $rows,
            'filters' => $filters,
            'problems' => $rows->whereNotNull('size_problem')->count(),
            'actionUrls' => [
                'leader'  => "/admin/state/fest/{$event->id}/teams/leader",
                'standby' => "/admin/state/fest/{$event->id}/teams/standby",
                'substitute' => "/admin/state/fest/{$event->id}/substitutions",
            ],
            'baseUrl' => "/admin/state/fest/{$event->id}/teams",
        ]);
    }

    public function setLeader(Request $request, StateFestEvent $event, StateTeamService $teams)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'registration_id' => 'required|integer',
            'participant_id'  => 'required|integer',
        ]);

        $teams->setLeader($this->registration($event, $data['registration_id']), $data['participant_id']);

        return back()->with('success', 'Team leader updated.');
    }

    public function setStandby(Request $request, StateFestEvent $event, StateTeamService $teams)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'registration_id' => 'required|integer',
            'participant_id'  => 'required|integer',
            'is_standby'      => 'required|boolean',
        ]);

        $teams->setStandby($this->registration($event, $data['registration_id']), $data['participant_id'], $data['is_standby']);

        return back()->with('success', $data['is_standby'] ? 'Marked as standby.' : 'Moved into the competing team.');
    }

    public function substitutions(Request $request, StateFestEvent $event, StateEventSettings $settings)
    {
        StateScope::assertOwns($event->state_id);

        $rows = StateSubstitution::where('state_event_id', $event->id)->orderByDesc('created_at')->get();
        $directory = StateSahodaya::whereIn('id', $rows->pluck('sahodaya_id')->filter()->unique())->get()->keyBy('id');

        return Inertia::render('State/Fest/Substitutions', $this->shell($request, $event) + [
            'substitutions' => $rows->map(fn (StateSubstitution $s) => [
                'id' => $s->id,
                'sahodaya' => $directory->get($s->sahodaya_id)?->name ?? '—',
                'school' => $s->school_name ?: $s->school_id,
                'item_code' => $s->item_code,
                'original_name' => $s->original_name,
                'substitute_name' => $s->substitute_name,
                'substitute_class' => $s->substitute_class,
                'reason' => $s->reason,
                'status' => $s->status,
                'decision_note' => $s->decision_note,
                'requested_by' => $s->requested_by_name,
                'requested_at' => $s->created_at?->toDateTimeString(),
                'decided_by' => $s->decided_by_name,
                'decided_at' => $s->decided_at?->toDateTimeString(),
            ]),
            'windowOpen' => $settings->windowIsOpen($event, StateEventSettings::WINDOW_SCRUTINY),
            'windowNote' => $settings->windowClosedReason($event, StateEventSettings::WINDOW_SCRUTINY),
            'actionUrls' => [
                'decide' => "/admin/state/fest/{$event->id}/substitutions/decide",
            ],
        ]);
    }

    public function requestSubstitution(Request $request, StateFestEvent $event, StateTeamService $teams)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'registration_id'         => 'required|integer',
            'original_participant_id' => 'required|integer',
            'substitute_name'         => 'required|string|max:255',
            'substitute_class'        => 'nullable|string|max:60',
            // A substitution without a reason is an untraceable edit.
            'reason'                  => 'required|string|max:1000',
            'evidence_path'           => 'nullable|string|max:255',
        ]);

        $teams->requestSubstitution($event, $this->registration($event, $data['registration_id']), $data + [
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
        ]);

        return back()->with('success', 'Substitution requested. It takes effect once a State officer approves it.');
    }

    public function decideSubstitution(Request $request, StateFestEvent $event, StateTeamService $teams)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'substitution_id' => 'required|uuid',
            'decision'        => 'required|in:approved,rejected',
            'note'            => 'nullable|string|max:1000',
        ]);

        $substitution = StateSubstitution::where('state_event_id', $event->id)->findOrFail($data['substitution_id']);

        $context = [
            'note' => $data['note'] ?? null,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
        ];

        $data['decision'] === 'approved'
            ? $teams->approveSubstitution($substitution, $context)
            : $teams->rejectSubstitution($substitution, $context);

        return back()->with('success', "Substitution {$data['decision']}.");
    }

    private function registration(StateFestEvent $event, int $id): StateFestRegistration
    {
        $registration = StateFestRegistration::where('state_event_id', $event->id)->find($id);

        abort_unless($registration, 404, 'That registration does not belong to this State event.');

        return $registration;
    }

    /** @return array<string, mixed> */
    private function shell(Request $request, StateFestEvent $event): array
    {
        return [
            'event' => [
                'id' => $event->id, 'name' => $event->name, 'status' => $event->status,
                'starts_on' => $event->starts_on?->toDateString(), 'ends_on' => $event->ends_on?->toDateString(),
                'results_published' => (bool) $event->results_published,
                'scoring_locked' => (bool) $event->scoring_locked,
            ],
            'events' => StateScope::apply(StateFestEvent::query())->orderByDesc('starts_on')
                ->get(['id', 'name', 'status'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'status' => $e->status, 'href' => "/admin/state/fest/{$e->id}"]),
            'sahodayas' => StateSahodaya::query()
                ->when(StateScope::shouldScope(), fn ($q) => $q->forState(StateScope::id()))
                ->orderBy('name')->get(['id', 'name', 'district', 'origin']),
            'permissions' => app(StateFestWorkspaceController::class)->permissionsForRequest($request),
        ];
    }
}
