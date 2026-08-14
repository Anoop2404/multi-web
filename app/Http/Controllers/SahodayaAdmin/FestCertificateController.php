<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestCertificateService;

class FestCertificateController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $participantIds = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $event->reportableEventIds()))
            ->pluck('id');

        $service = app(FestCertificateService::class);
        $certificates = Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->orderByDesc('generated_at')
            ->get();

        // Batched instead of one payloadFor() (2 queries each) per row — see
        // FestCertificateService::payloadsFor().
        $payloads = $service->payloadsFor($certificates);
        $certificates = $certificates->map(fn ($c) => array_merge(
            [
                'id' => $c->id,
                'uuid' => $c->verification_uuid,
                'cert_type' => $c->cert_type,
            ],
            $payloads->get($c->id) ?? []
        ));

        return $this->inertia('Sahodaya/Events/Certificates', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event'        => $event,
            'certificates' => $certificates,
        ]));
    }

    public function generate(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $created = app(FestCertificateService::class)->generateForEvent($event);

        $audit->festEvent($event, FestPageActivity::CERTIFICATES, 'fest.certificates.generated', count($created).' certificate(s) generated', [
            'count' => count($created),
        ]);

        try {
            app(\App\Services\Events\FestEventNotifier::class)->certificatesAvailable($event, count($created));
        } catch (\Throwable) {
            // ignore notification failures
        }

        return back()->with('success', count($created).' certificate(s) generated.');
    }

    public function downloadZip(string $tenantId, FestEvent $event)
    {
        // Same override the single-item ID-card export already applies (see
        // FestIdCardController::pdf()) — this loop can run to hundreds/thousands of
        // certificates and previously had no headroom above container defaults.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $participantIds = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $event->reportableEventIds()))
            ->pluck('id');

        $certificates = Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->get();

        abort_if($certificates->isEmpty(), 404, 'No certificates to download.');

        $service = app(FestCertificateService::class);

        // Batched instead of a per-certificate payloadFor() + resolveTemplate() (up to
        // 3 queries, doubled on fallback) inside the loop below — see
        // FestCertificateService::payloadsFor() and renderContext()'s $templateCache param.
        $payloads = $service->payloadsFor($certificates);
        $templateCache = [];

        $zipPath = storage_path('app/tmp/fest-certs-'.$event->id.'-'.time().'.zip');
        @mkdir(dirname($zipPath), 0755, true);

        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($certificates as $certificate) {
            $payload = $service->renderContext($certificate, $payloads->get($certificate->id), $templateCache);
            $name = str($payload['student']?->name ?? 'participant')->slug().'-'.$certificate->verification_uuid.'.html';
            $html = view('fest.certificate-print', $payload)->render();
            $zip->addFromString($name, $html);
        }

        $zip->close();

        return response()->download($zipPath, str($event->title)->slug().'-certificates.zip')->deleteFileAfterSend();
    }
}
