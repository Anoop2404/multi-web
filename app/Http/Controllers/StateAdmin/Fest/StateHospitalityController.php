<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\State\StateEventStaff;
use App\Models\State\StateFestEvent;
use App\Models\State\StateMealSession;
use App\Models\State\StateSahodaya;
use App\Models\State\StateStaffDuty;
use App\Models\State\StateVenue;
use App\Services\State\Fest\StateHospitalityService;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Phase 10 of the State Kalotsav module — catering sittings and volunteer duty rosters. */
class StateHospitalityController extends Controller
{
    public function catering(Request $request, StateFestEvent $event, StateHospitalityService $hospitality)
    {
        StateScope::assertOwns($event->state_id);

        $filters = $request->validate(['session_id' => 'nullable|uuid']);
        $session = ($filters['session_id'] ?? null)
            ? StateMealSession::where('state_event_id', $event->id)->find($filters['session_id'])
            : null;

        return Inertia::render('State/Fest/Catering', $this->shell($request, $event) + [
            'summary' => $hospitality->cateringSummary($event),
            'filters' => $filters,
            'selected' => $session ? [
                'id' => $session->id,
                'day' => $session->served_on?->format('D, d M Y'),
                'session' => $session->session,
                'menu' => $session->menu,
                'capacity' => $session->capacity,
            ] : null,
            'entitlement' => $session ? $hospitality->entitlement($event, $session) : [],
            'venues' => StateVenue::where('state_event_id', $event->id)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name', 'kind']),
            'sessionNames' => StateMealSession::SESSIONS,
            'actionUrls' => [
                'saveSession' => "/admin/state/fest/{$event->id}/catering/sessions",
                'freeze' => "/admin/state/fest/{$event->id}/catering/freeze",
                'issue' => "/admin/state/fest/{$event->id}/catering/issue",
            ],
            'baseUrl' => "/admin/state/fest/{$event->id}/catering",
        ]);
    }

    public function saveSession(Request $request, StateFestEvent $event, StateHospitalityService $hospitality)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'id' => 'nullable|uuid',
            'served_on' => 'required|date',
            'session' => 'required|string|max:40',
            'menu' => 'nullable|string|max:255',
            'venue_id' => 'nullable|uuid',
            'capacity' => 'nullable|integer|min:0|max:100000',
            'is_active' => 'nullable|boolean',
        ]);

        $hospitality->saveSession($event, $data, $data['id'] ?? null);

        return back()->with('success', 'Sitting saved.');
    }

    public function freeze(Request $request, StateFestEvent $event, StateHospitalityService $hospitality)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate(['session_id' => 'required|uuid']);
        $session = StateMealSession::where('state_event_id', $event->id)->findOrFail($data['session_id']);

        $result = $hospitality->freezeEntitlement($event, $session);

        return back()->with(
            $result['rows'] ? 'success' : 'warning',
            $result['rows']
                ? "{$result['meals']} meals across {$result['rows']} Sahodaya(s) recorded for this sitting."
                : 'Nothing is scheduled on that date, so no Sahodaya is entitled to this sitting. Check the date.',
        );
    }

    public function issue(Request $request, StateFestEvent $event, StateHospitalityService $hospitality)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'session_id' => 'required|uuid',
            'sahodaya_id' => 'required|uuid',
            'issued_count' => 'required|integer|min:0|max:100000',
            'notes' => 'nullable|string|max:500',
        ]);

        $session = StateMealSession::where('state_event_id', $event->id)->findOrFail($data['session_id']);

        $allocation = $hospitality->issue($event, $session, $data, [
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
        ]);

        $variance = $allocation->variance();

        return back()->with(
            $variance > 0 ? 'warning' : 'success',
            "Recorded {$allocation->issued_count} meals for {$allocation->sahodaya_name}."
                .($variance > 0 ? " That is {$variance} more than the entitlement." : ''),
        );
    }

    // ── Volunteers and officials ───────────────────────────────────────────────────────────

    public function volunteers(Request $request, StateFestEvent $event, StateHospitalityService $hospitality)
    {
        StateScope::assertOwns($event->state_id);

        $filters = $request->validate(['date' => 'nullable|date']);

        return Inertia::render('State/Fest/Volunteers', $this->shell($request, $event) + [
            'filters' => $filters,
            'roster' => $hospitality->roster($event, $filters['date'] ?? null),
            'uncovered' => $hospitality->uncoveredVenues($event),
            'staff' => StateEventStaff::where('state_event_id', $event->id)
                ->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'role', 'phone']),
            'venues' => StateVenue::where('state_event_id', $event->id)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name', 'kind']),
            'sessionNames' => StateStaffDuty::SESSIONS,
            'actionUrls' => [
                'assign' => "/admin/state/fest/{$event->id}/volunteers/duties",
                'remove' => "/admin/state/fest/{$event->id}/volunteers/duties",
                'staff' => "/admin/state/fest/{$event->id}/staff",
            ],
            'baseUrl' => "/admin/state/fest/{$event->id}/volunteers",
        ]);
    }

    public function assignDuty(Request $request, StateFestEvent $event, StateHospitalityService $hospitality)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'staff_id' => 'required|uuid',
            'duty_on' => 'required|date',
            'session' => 'required|string|max:40',
            'venue_id' => 'nullable|uuid',
            'duty' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:500',
        ]);

        $hospitality->assignDuty($event, $data);

        return back()->with('success', 'Duty assigned.');
    }

    public function removeDuty(Request $request, StateFestEvent $event, string $duty, StateHospitalityService $hospitality)
    {
        StateScope::assertOwns($event->state_id);

        $hospitality->removeDuty($event, $duty);

        return back()->with('success', 'Duty removed.');
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
