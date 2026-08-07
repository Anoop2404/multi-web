<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Services\Events\FestEventPhaseService;
use App\Support\FestPageActivity;

use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class FestEventPhaseController extends SahodayaAdminController
{
    public function index(Request $request, string $tenantId, FestEvent $event, FestEventPhaseService $service)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $phases = $service->getPhases($event);
        $items = $event->items()->with('phase')->get()->map(fn ($item) => [
            'id' => $item->id,
            'title' => $item->title,
            'item_code' => $item->item_code,
            'category' => $item->category,
            'phase_id' => $item->phase_id,
            'phase_name' => $item->phase?->name,
        ]);

        return $this->inertia('Sahodaya/Events/Phases', $this->withEventActivity($event, FestPageActivity::ITEMS, [
            'event' => $event,
            'phases' => $phases,
            'items' => $items,
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, FestEventPhaseService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:64',
            'sort_order' => 'nullable|integer',
            'is_default' => 'nullable|boolean',
        ]);

        $phase = $service->createPhase($event, $data);

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.phase.created', "Created phase {$phase->name}", [
            'phase_id' => $phase->id,
            'name' => $phase->name,
        ]);

        return back()->with('success', "Phase '{$phase->name}' created.");
    }

    public function update(Request $request, string $tenantId, FestEvent $event, FestEventPhase $phase, FestEventPhaseService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($phase->event_id !== $event->id, 403);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:64',
            'sort_order' => 'nullable|integer',
            'is_default' => 'nullable|boolean',
        ]);

        $updated = $service->updatePhase($phase, $data);

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.phase.updated', "Updated phase {$updated->name}", [
            'phase_id' => $updated->id,
        ]);

        return back()->with('success', "Phase '{$updated->name}' updated.");
    }

    public function destroy(Request $request, string $tenantId, FestEvent $event, FestEventPhase $phase, FestEventPhaseService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($phase->event_id !== $event->id, 403);

        $name = $phase->name;
        $service->deletePhase($phase, $request->boolean('force'));

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.phase.deleted', "Deleted phase {$name}", [
            'phase_id' => $phase->id,
        ]);

        return back()->with('success', "Phase '{$name}' deleted.");
    }

    public function assignItems(Request $request, string $tenantId, FestEvent $event, FestEventPhaseService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            // Scoped to THIS event — previously any phase id anywhere (including another
            // event's) passed the bare exists() check; FestEventPhaseService::
            // assignItemsToPhase() re-checks this too (defense in depth), but the request
            // should fail validation before it even reaches the service. Phase 5 audit item 2.
            'phase_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('fest_event_phases', 'id')->where('event_id', $event->id)],
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer|exists:fest_event_items,id',
        ]);

        $count = $service->assignItemsToPhase($event, $data['phase_id'] ?? null, $data['item_ids']);

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.phase.items_assigned', "Assigned {$count} item(s) to phase", [
            'phase_id' => $data['phase_id'] ?? null,
            'count' => $count,
        ]);

        return back()->with('success', "Assigned {$count} item(s) to phase.");
    }
}
