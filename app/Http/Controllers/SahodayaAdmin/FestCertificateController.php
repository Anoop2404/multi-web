<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Services\Events\FestCertificateService;

class FestCertificateController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $participantIds = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id))
            ->pluck('id');

        $service = app(FestCertificateService::class);
        $certificates = Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->orderByDesc('generated_at')
            ->get()
            ->map(fn ($c) => array_merge(
                ['id' => $c->id, 'uuid' => $c->verification_uuid],
                $service->payloadFor($c)
            ));

        return $this->inertia('Sahodaya/Events/Certificates', [
            'event'        => $event,
            'certificates' => $certificates,
        ]);
    }

    public function generate(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $created = app(FestCertificateService::class)->generateForEvent($event);

        return back()->with('success', count($created).' certificate(s) generated.');
    }
}
