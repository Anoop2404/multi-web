<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\State\StateFestEvent;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StateSlotService;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Phase 3 of the State Kalotsav module — the Sahodaya Slots tab.
 *
 * A tab of its own rather than a field buried in each item form, because slots are the dial that
 * decides who may compete: they need to be seen across every item at once, with usage beside them,
 * and every change recorded.
 */
class StateSlotController extends Controller
{
    public function index(Request $request, StateFestEvent $event, StateSlotService $slots)
    {
        StateScope::assertOwns($event->state_id);

        $program = FestStateProgram::findOrFail($event->state_program_id);
        $matrix = $slots->matrix($program);

        return Inertia::render('State/Fest/Slots', [
            'event' => [
                'id' => $event->id, 'name' => $event->name, 'status' => $event->status,
                'program_title' => $program->title, 'results_published' => (bool) $event->results_published,
                'scoring_locked' => (bool) $event->scoring_locked,
                'starts_on' => $event->starts_on?->toDateString(), 'ends_on' => $event->ends_on?->toDateString(),
            ],
            'events' => StateScope::apply(StateFestEvent::query())->orderByDesc('starts_on')
                ->get(['id', 'name', 'status'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'status' => $e->status, 'href' => "/admin/state/fest/{$e->id}"]),
            'permissions' => app(StateFestWorkspaceController::class)->permissionsForRequest($request),
            'items' => $matrix['items'],
            'sahodayas' => $matrix['sahodayas'],
            'history' => $slots->history($program)->map(fn ($h) => [
                'id' => $h->id,
                'scope' => $h->scope,
                'item_id' => $h->item_id,
                'sahodaya_id' => $h->sahodaya_id,
                'slots_from' => $h->slots_from,
                'slots_to' => $h->slots_to,
                'reason' => $h->reason,
                'by' => $h->changed_by_name,
                'at' => $h->created_at?->toDateTimeString(),
            ]),
            'actionUrls' => [
                'setSahodaya' => "/admin/state/fest/{$event->id}/slots/sahodaya",
                'setItem'     => "/admin/state/fest/{$event->id}/slots/item",
            ],
        ]);
    }

    public function setSahodayaSlots(Request $request, StateFestEvent $event, StateSlotService $slots)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'item_id'     => 'required|uuid',
            'sahodaya_id' => 'required|uuid',
            // Null clears the override and returns this Sahodaya to the item's own figure.
            'slots'       => 'nullable|integer|min:0|max:99',
            'reason'      => 'nullable|string|max:255',
        ]);

        $item = $this->itemFor($event, $data['item_id']);
        abort_unless(
            StateSahodaya::where('id', $data['sahodaya_id'])->exists(),
            422,
            'That Sahodaya is not in this state\'s directory.',
        );

        $slots->setSahodayaSlots(
            $item,
            $data['sahodaya_id'],
            $data['slots'] ?? null,
            $data['reason'] ?? null,
            $request->user()?->id,
            $request->user()?->name,
        );

        return back()->with('success', $data['slots'] === null
            ? 'Override cleared — this Sahodaya follows the item again.'
            : "Slots set to {$data['slots']} for this Sahodaya.");
    }

    public function setItemSlots(Request $request, StateFestEvent $event, StateSlotService $slots)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'item_id' => 'required|uuid',
            'slots'   => 'nullable|integer|min:0|max:99',
            'reason'  => 'nullable|string|max:255',
        ]);

        $slots->setItemSlots(
            $this->itemFor($event, $data['item_id']),
            $data['slots'] ?? null,
            $data['reason'] ?? null,
            $request->user()?->id,
            $request->user()?->name,
        );

        return back()->with('success', 'Item slots updated for every Sahodaya without an override.');
    }

    /** Items are only editable through the event that owns their program. */
    private function itemFor(StateFestEvent $event, string $itemId): FestStateProgramItem
    {
        $item = FestStateProgramItem::where('state_program_id', $event->state_program_id)->find($itemId);

        abort_unless($item, 404, 'That item does not belong to this State event.');

        return $item;
    }
}
