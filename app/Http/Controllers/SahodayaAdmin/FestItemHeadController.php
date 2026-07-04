<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Services\Events\FestItemHeadService;
use App\Support\FestPageActivity;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FestItemHeadController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        app(FestItemHeadService::class)->syncEventHeads($event);

        $heads = FestItemHead::forTenant($this->sahodaya->id)
            ->forEvent($event->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['items' => fn ($q) => $q->where('event_id', $event->id)->orderBy('display_order')])
            ->get();

        return $this->inertia('Sahodaya/Events/ItemHeads', $this->withEventActivity($event, FestPageActivity::SETTINGS, [
            'sahodaya' => $this->sahodaya->only('id', 'name'),
            'event' => $event,
            'heads' => $heads,
            'disciplines' => app(\App\Services\Events\FestTaxonomyRegistry::class)
                ->forTenant($this->sahodaya->id)->labels('sport_discipline'),
            'taxonomyMastersUrl' => "/sahodaya-admin/{$this->sahodaya->id}/taxonomy-masters?dimension=sport_discipline",
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'sport_discipline' => 'nullable|string|max:60',
            'is_team_heading' => 'nullable|boolean',
            'parent_id' => 'nullable|exists:fest_item_heads,id',
        ]);

        $order = (int) FestItemHead::forTenant($this->sahodaya->id)->forEvent($event->id)->max('sort_order') + 1;

        FestItemHead::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $event->id,
            'event_type' => $event->event_type,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'sport_discipline' => $data['sport_discipline'] ?? null,
            'is_team_heading' => (bool) ($data['is_team_heading'] ?? true),
            'sort_order' => $order,
        ]);

        $audit->festEvent($event, FestPageActivity::SETTINGS, 'fest.item_head.created', 'Item head created');

        return back()->with('success', 'Item head added.');
    }

    public function sync(string $tenantId, FestEvent $event, FestItemHeadService $service)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $service->ensureCatalogHeads($this->sahodaya->id, $event->event_type);
        $count = $service->syncEventHeads($event);

        return back()->with('success', "Synced {$count} item head(s) to this event.");
    }
}
