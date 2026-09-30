<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestStateNominationSelection;
use App\Models\FestStateProgram;
use App\Services\State\FestStateSlotCollectionService;
use App\Support\SchoolFestProgram;
use Illuminate\Http\Request;

/**
 * A school's own page for the slots it has been offered before the Sahodaya registers its winners
 * with State (see FestStateSlotCollectionService). Read-only once the Sahodaya has approved the
 * list, or before it has opened collection at all.
 *
 * `$program` is deliberately read via `$request->route('program')` rather than as a typed method
 * parameter: this route has a real `{selection}` wildcard *after* the `defaults('program', $slug)`
 * value (see routes/includes/school_event_programs.php), and on a route shaped like that Laravel's
 * controller dispatch binds plain scalar parameters by *position* among the route's remaining
 * (non-model) values, not by name — verified directly (`$request->route('selection')` returned the
 * right value while the `string $selection` method parameter did not). A `string $program`/
 * `string $selection` parameter pair here silently received each other's values. Reading both
 * straight off the route avoids relying on that ordering ever again.
 */
class FestStateSlotController extends SchoolAdminController
{
    public function index(Request $request, string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $program = (string) $request->route('program');
        $stateProgram = $this->stateProgram($event);
        $meta = SchoolFestProgram::meta($program);
        $prefix = \App\Support\ProgramRouteMap::prefixFromSlug($meta['slug']);
        $base = \App\Support\ProgramRouteMap::schoolBase($this->school->id, $prefix)."/events/{$event->id}/state-slots";

        return $this->inertia('School/Events/StateSlots', [
            'school' => $this->school->only('id', 'name'),
            'program' => $meta['slug'],
            'programMeta' => $meta,
            'event' => $event->only(['id', 'title', 'state_slot_collection_open', 'state_slot_collection_approved_at']),
            'stateProgram' => $stateProgram?->only(['id', 'title']),
            'slots' => $stateProgram ? $collection->forSchool($stateProgram, $event, $this->school->id) : [],
            'actionUrlBase' => $base,
        ]);
    }

    public function accept(Request $request, string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection)
    {
        $selection = $this->authorizeSelection($request, $event);

        $collection->respond($selection, 'accept', null, $request->user());

        return back()->with('success', 'Accepted. Thank you for confirming.');
    }

    public function optOut(Request $request, string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection)
    {
        $selection = $this->authorizeSelection($request, $event);

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        $collection->respond($selection, 'opt_out', $data['reason'], $request->user());

        return back()->with('success', 'Recorded. The next rank will be offered this slot.');
    }

    private function authorizeSelection(Request $request, FestEvent $event): FestStateNominationSelection
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $selection = FestStateNominationSelection::findOrFail((int) $request->route('selection'));
        abort_unless($selection->school_id === $this->school->id, 403);
        abort_unless($selection->batch->hub_event_id === $event->id, 404);

        return $selection;
    }

    private function stateProgram(FestEvent $event): ?FestStateProgram
    {
        return $event->state_program_id ? FestStateProgram::find($event->state_program_id) : null;
    }
}
