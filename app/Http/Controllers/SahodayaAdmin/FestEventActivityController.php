<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\Tenant;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;

class FestEventActivityController extends SahodayaAdminController
{
    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $page = $request->input('page') ?: null;
        $itemId = $request->integer('item_id') ?: null;
        $search = $request->input('q') ?: null;

        $logs = app(\App\Services\Audit\FestEventActivityService::class)
            ->forEvent($event, 200, $page, $itemId, $search)
            ->map(fn (array $log) => array_merge($log, [
                'page_label' => FestPageActivity::label($log['page'] ?? null),
            ]))
            ->values()
            ->all();

        $items = FestEventItem::where('event_id', $event->id)
            ->orderBy('display_order')
            ->orderBy('title')
            ->get(['id', 'title', 'item_code', 'class_group', 'age_group']);

        $schools = Tenant::whereHas('registrations', fn ($q) => $q->where('event_id', $event->id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->inertia('Sahodaya/Events/Activity', [
            'event'        => $event->only('id', 'title', 'event_type', 'status'),
            'activityLogs' => $logs,
            'pageLabels'   => FestPageActivity::labels(),
            'items'        => $items,
            'schools'      => $schools,
            'filters'      => [
                'page'    => $page,
                'item_id' => $itemId,
                'q'       => $search,
            ],
        ]);
    }
}
