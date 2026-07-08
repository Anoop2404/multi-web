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

        return redirect()->route('sahodaya.events.competition.index', [
            'tenantId' => $tenantId,
            'event' => $event->id,
        ]);
    }

    public function updateWindows(Request $request, string $tenantId, FestEvent $event, FestItemHead $head, FestItemHeadService $headService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if((int) $head->event_id !== (int) $event->id, 404);

        $data = $request->validate([
            'name'              => 'sometimes|required|string|max:120',
            'sport_discipline'  => 'nullable|string|max:60',
            'is_team_heading'   => 'nullable|boolean',
            'reg_start'         => 'nullable|date',
            'reg_end'           => 'nullable|date|after_or_equal:reg_start',
            'competition_start' => 'nullable|date',
            'competition_end'   => 'nullable|date|after_or_equal:competition_start',
            'schedule_mode'     => 'nullable|in:same_time,different_days',
            'competition_time'  => 'nullable|date_format:H:i',
            'apply_to_items'    => 'nullable|boolean',
            'default_item_fee'  => 'nullable|numeric|min:0',
            'extra_item_fee'    => 'nullable|numeric|min:0',
        ]);

        $hasSchedulePayload = $request->hasAny([
            'reg_start',
            'reg_end',
            'competition_start',
            'competition_end',
            'competition_time',
            'schedule_mode',
        ]);

        $applyToItems = $hasSchedulePayload && (bool) ($data['apply_to_items'] ?? true);
        unset($data['apply_to_items']);

        if (array_key_exists('name', $data)) {
            $data['slug'] = Str::slug($data['name']);
        }

        $scheduleMode = $data['schedule_mode'] ?? $head->schedule_mode ?? 'different_days';
        if ($request->has('schedule_mode')) {
            $data['schedule_mode'] = $scheduleMode;
        }

        // In same-time mode all items run together on one day: normalise the head's
        // competition window to a single day so downstream displays stay consistent.
        if ($scheduleMode === 'same_time' && ! empty($data['competition_start'])) {
            $data['competition_end'] = $data['competition_start'];
        }

        if (array_key_exists('default_item_fee', $data)) {
            $data['default_item_fee'] = isset($data['default_item_fee']) && $data['default_item_fee'] !== ''
                ? (float) $data['default_item_fee']
                : null;
        }
        if (array_key_exists('extra_item_fee', $data)) {
            $data['extra_item_fee'] = isset($data['extra_item_fee']) && $data['extra_item_fee'] !== ''
                ? (float) $data['extra_item_fee']
                : null;
        }

        $head->update($data);

        // What we push down to items depends on the mode:
        //  - same_time      → the single date + time for every item (so they run together)
        //  - different_days → only the registration window; each item keeps its own day/time
        $itemPayload = $hasSchedulePayload
            ? ($scheduleMode === 'same_time'
                ? [
                    'reg_start'         => $data['reg_start'] ?? null,
                    'reg_end'           => $data['reg_end'] ?? null,
                    'competition_start' => $data['competition_start'] ?? null,
                    'competition_end'   => $data['competition_end'] ?? null,
                    'competition_time'  => $data['competition_time'] ?? null,
                ]
                : [
                    'reg_start' => $data['reg_start'] ?? null,
                    'reg_end'   => $data['reg_end'] ?? null,
                ])
            : [];

        $updated = $applyToItems
            ? $headService->applyWindowToItems($head, $event, $itemPayload, true)
            : 0;

        return back()->with('success', $updated > 0
            ? "Head schedule saved and applied to {$updated} item(s)."
            : 'Head schedule saved.');
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

    public function destroy(string $tenantId, FestEvent $event, FestItemHead $head, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if((int) $head->event_id !== (int) $event->id, 404);
        abort_if($head->catalog_key, 422, 'Catalog heads cannot be removed — disable items instead.');

        $name = $head->name;

        $head->items()->where('event_id', $event->id)->update(['head_id' => null]);
        $head->delete();

        $audit->festEvent($event, FestPageActivity::COMPETITION, 'fest.item_head.deleted', "Item head removed: {$name}");

        return redirect()->route('sahodaya.events.competition.index', [
            'tenantId' => $tenantId,
            'event' => $event->id,
        ])->with('success', 'Item head removed.');
    }
}
