<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgramItem;
use App\Models\State\StateFestEvent;
use App\Models\State\StateSahodaya;
use App\Models\State\StateVenue;
use App\Services\State\Fest\StateChestNumberService;
use App\Services\State\Fest\StateScheduleService;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Phase 5 of the State Kalotsav module — schedule, clashes, green room and chest numbers. */
class StateScheduleController extends Controller
{
    public function schedule(Request $request, StateFestEvent $event, StateScheduleService $schedule)
    {
        StateScope::assertOwns($event->state_id);

        $rows = $schedule->scheduleFor($event);

        return Inertia::render('State/Fest/Schedule', $this->shell($request, $event) + [
            'items' => $rows,
            'scheduled' => $rows->where('is_scheduled', true)->count(),
            'venues' => StateVenue::where('state_event_id', $event->id)
                ->orderBy('name')->get(['id', 'name', 'kind'])
                // Only a stage or a room can hold an item; a venue is the building around them.
                ->filter(fn (StateVenue $v) => $v->canHostItems())->values(),
            'actionUrls' => ['save' => "/admin/state/fest/{$event->id}/schedule"],
        ]);
    }

    public function saveSchedule(Request $request, StateFestEvent $event, StateScheduleService $schedule)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'item_id'          => 'required|uuid',
            'scheduled_on'     => 'nullable|date',
            'reporting_at'     => 'nullable|date_format:H:i',
            'starts_at'        => 'nullable|date_format:H:i',
            'ends_at'          => 'nullable|date_format:H:i|after:starts_at',
            'venue_id'         => 'nullable|uuid',
            'duration_minutes' => 'nullable|integer|min:1|max:600',
            'is_public'        => 'boolean',
            'notes'            => 'nullable|string|max:500',
        ]);

        $item = FestStateProgramItem::where('state_program_id', $event->state_program_id)->findOrFail($data['item_id']);

        if (! empty($data['venue_id'])) {
            abort_unless(
                StateVenue::where('state_event_id', $event->id)->where('id', $data['venue_id'])->exists(),
                422,
                'That venue belongs to another event.',
            );
        }

        $schedule->save($event, $item, $data);

        return back()->with('success', "Schedule saved for {$item->title}.");
    }

    public function clashes(Request $request, StateFestEvent $event, StateScheduleService $schedule)
    {
        StateScope::assertOwns($event->state_id);

        $clashes = $schedule->clashes($event);

        return Inertia::render('State/Fest/Clashes', $this->shell($request, $event) + [
            'clashes' => $clashes,
            'totals' => [
                'participant' => count($clashes['participant']),
                'venue' => count($clashes['venue']),
                'sahodaya' => count($clashes['sahodaya']),
            ],
            'scheduleUrl' => "/admin/state/fest/{$event->id}/schedule",
        ]);
    }

    public function greenRoom(Request $request, StateFestEvent $event, StateScheduleService $schedule)
    {
        StateScope::assertOwns($event->state_id);

        $date = $request->query('date');

        return Inertia::render('State/Fest/GreenRoom', $this->shell($request, $event) + [
            'slots' => $schedule->greenRoom($event, $date),
            'date' => $date,
            'dates' => $schedule->scheduleFor($event)->pluck('scheduled_on')->filter()->unique()->sort()->values(),
            'baseUrl' => "/admin/state/fest/{$event->id}/green-room",
        ]);
    }

    public function chestNumbers(Request $request, StateFestEvent $event, StateChestNumberService $chest)
    {
        StateScope::assertOwns($event->state_id);

        $filters = $request->validate(['sahodaya_id' => 'nullable|uuid']);

        return Inertia::render('State/Fest/ChestNumbers', $this->shell($request, $event) + [
            'register' => $chest->register($event, $filters['sahodaya_id'] ?? null),
            'summary' => $chest->summary($event),
            'filters' => $filters,
            'actionUrls' => [
                'assign' => "/admin/state/fest/{$event->id}/chest-numbers/assign",
                'set'    => "/admin/state/fest/{$event->id}/chest-numbers/set",
            ],
            'baseUrl' => "/admin/state/fest/{$event->id}/chest-numbers",
        ]);
    }

    public function assignChestNumbers(Request $request, StateFestEvent $event, StateChestNumberService $chest)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'start'       => 'nullable|integer|min:1|max:99999',
            'block_size'  => 'nullable|integer|min:0|max:1000',
            'sahodaya_id' => 'nullable|uuid',
        ]);

        $result = $chest->assignMissing($event, $data);

        return back()->with('success', "{$result['assigned']} number(s) assigned, {$result['skipped']} already had one.");
    }

    public function setChestNumber(Request $request, StateFestEvent $event, StateChestNumberService $chest)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'participant_id' => 'required|integer',
            'chest_number'   => 'nullable|string|max:32',
        ]);

        $chest->setNumber($event, $data['participant_id'], $data['chest_number'] ?? null);

        return back()->with('success', 'Chest number updated.');
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
