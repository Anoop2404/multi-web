<?php

namespace App\Http\Controllers\StateAdmin;

use App\Http\Controllers\Controller;
use App\Models\State\StateAttendance;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Services\State\StateEventLifecycleGate;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

/**
 * State Event Conduct, Phase 2 (docs/STATE_EVENT_CONDUCT_PLAN.md) — item-scoped attendance,
 * mirroring App\Http\Controllers\SahodayaAdmin\FestAttendanceController's shape. Team-expand
 * uses StateFestRegistration's participants() (every participant on the same registration is
 * one team), simpler than the tenant-level group_id lookup since State's schema already
 * groups team members under one registration row.
 */
class StateAttendanceController extends Controller
{
    public function index(StateFestEvent $event)
    {
        StateScope::assertOwns($event->state_id);
        $routePrefix = request()->routeIs('state.portal.*') ? 'state.portal' : 'admin.state';
        $registrations = $event->registrations()
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->with('participants')
            ->orderBy('item_code')
            ->get();

        $attendance = StateAttendance::where('state_event_id', $event->id)
            ->get()
            ->keyBy(fn ($a) => $a->item_id.'-'.$a->participant_id);

        return Inertia::render('StateAdmin/Fest/Attendance', [
            'event'         => $event,
            'registrations' => $registrations,
            'attendance'    => $attendance,
            'actionUrls'    => [
                'workspace' => route("{$routePrefix}.fest.show", $event, false),
                'store' => route("{$routePrefix}.fest.attendance.store", $event, false),
            ],
        ]);
    }

    public function store(Request $request, StateFestEvent $event)
    {
        StateScope::assertOwns($event->state_id);
        StateEventLifecycleGate::allowAttendanceEntry($event);

        if ($request->boolean('bulk')) {
            return $this->bulkStore($request, $event);
        }

        $data = $request->validate([
            'item_id'        => 'required|uuid',
            'participant_id' => ['required', 'integer', Rule::exists('state.state_fest_participants', 'id')],
            'status'         => 'required|in:present,absent',
        ]);

        $participant = StateFestParticipant::with('registration')->findOrFail($data['participant_id']);
        abort_if($participant->registration->state_event_id !== $event->id, 422, 'Participant does not belong to this State event.');
        abort_if($participant->registration->item_id !== $data['item_id'], 422, 'Participant does not belong to this item.');

        $participantIds = $this->expandToTeam($participant);

        foreach ($participantIds as $participantId) {
            StateAttendance::updateOrCreate(
                ['item_id' => $data['item_id'], 'participant_id' => $participantId],
                [
                    'state_event_id'  => $event->id,
                    'registration_id' => $participant->registration_id,
                    'item_code'       => $participant->registration->item_code,
                    'status'          => $data['status'],
                    'marked_by'       => $request->user()->id,
                    'marked_at'       => now(),
                ]
            );
        }

        return back()->with('success', 'Attendance saved.'.(count($participantIds) > 1 ? ' ('.count($participantIds).' team members)' : ''));
    }

    private function bulkStore(Request $request, StateFestEvent $event)
    {
        $data = $request->validate([
            'item_id'            => 'required|uuid',
            'participant_ids'    => 'required|array|min:1',
            'participant_ids.*'  => ['integer', Rule::exists('state.state_fest_participants', 'id')],
            'status'             => 'required|in:present,absent',
        ]);

        $participants = StateFestParticipant::with('registration')
            ->whereIn('id', $data['participant_ids'])
            ->get();

        $mismatch = $participants->contains(fn ($p) => $p->registration->state_event_id !== $event->id || $p->registration->item_id !== $data['item_id']);
        abort_if($mismatch, 422, 'One or more participants do not belong to this State event or item.');

        $expandedIds = $participants->flatMap(fn ($p) => $this->expandToTeam($p))->unique()->values()->all();

        foreach ($expandedIds as $participantId) {
            $participant = $participants->firstWhere('id', $participantId) ?? StateFestParticipant::with('registration')->find($participantId);

            StateAttendance::updateOrCreate(
                ['item_id' => $data['item_id'], 'participant_id' => $participantId],
                [
                    'state_event_id'  => $event->id,
                    'registration_id' => $participant->registration_id,
                    'item_code'       => $participant->registration->item_code,
                    'status'          => $data['status'],
                    'marked_by'       => $request->user()->id,
                    'marked_at'       => now(),
                ]
            );
        }

        return back()->with('success', count($expandedIds).' attendance record(s) saved.');
    }

    /**
     * Every participant sharing the same registration is a team member — marking one marks
     * all of them the same way, same rationale as the tenant-level system's group-item
     * expansion.
     *
     * @return list<int>
     */
    private function expandToTeam(StateFestParticipant $participant): array
    {
        return StateFestParticipant::where('registration_id', $participant->registration_id)
            ->pluck('id')
            ->all();
    }
}
