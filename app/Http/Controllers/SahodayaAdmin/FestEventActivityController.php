<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Support\FestPageActivity;

class FestEventActivityController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $logs = app(\App\Services\Audit\FestEventActivityService::class)
            ->forEvent($event, 100)
            ->map(fn (array $log) => array_merge($log, [
                'page_label' => FestPageActivity::label($log['page'] ?? null),
            ]))
            ->values()
            ->all();

        return $this->inertia('Sahodaya/Events/Activity', [
            'event'        => $event->only('id', 'title', 'event_type', 'status'),
            'activityLogs' => $logs,
            'pageLabels'   => FestPageActivity::labels(),
        ]);
    }
}
