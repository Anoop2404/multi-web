<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Services\Events\FestExportService;

class FestExportController extends SahodayaAdminController
{
    public function registrations(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return app(FestExportService::class)->registrations($event);
    }

    public function results(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return app(FestExportService::class)->results($event);
    }

    public function attendance(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return app(FestExportService::class)->attendance($event);
    }

    public function fees(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return app(FestExportService::class)->fees($event);
    }
}
