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

        $scoreboards = app(\App\Services\Events\PublicFestScoreboardService::class);
        $items = FestEventItem::where('event_id', $event->id)
            ->orderBy('display_order')
            ->orderBy('title')
            ->get()
            ->map(fn (FestEventItem $it) => [
                'id'        => $it->id,
                'title'     => $it->title,
                'item_code' => $it->item_code,
                'category'  => $it->class_group ? $scoreboards->categoryLabel($event, $it->class_group) : ($it->age_group ? $scoreboards->categoryLabel($event, $it->age_group) : null),
            ]);

        $schoolIds = \App\Models\FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->pluck('school_id')
            ->filter()
            ->unique()
            ->values();

        $schools = Tenant::whereIn('id', $schoolIds)
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
