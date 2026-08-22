<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Support\PdfGenerator;
use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestIdCardQrService;
use App\Services\Events\FestItemResultsService;
use Illuminate\Http\Request;

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
            'event'         => $event,
            'certificates'  => $certificates,
            'winnersByItem' => $this->winnersByItem($certificates),
        ]));
    }

    /**
     * Winner certificates grouped by item, rank 1-3, restricted to items whose results
     * are actually published — a winner Certificate row can exist (generateForEvent()
     * doesn't itself check the publish flag) before its item's results go live, so
     * presence of a certificate alone isn't a safe "published" signal. Mirrors the same
     * item.results_published_at-or-event.results_published predicate the public results
     * page uses (FestItemResultsService::isItemVisible(), also FestPortalController::
     * results()'s "item" tab), applied per-certificate's own item/event rather than the
     * page's top-level $event — a hub page's certificates can belong to a child region
     * event with its own independent publish state.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $certificates  already payload-merged, as built in index()
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function winnersByItem(\Illuminate\Support\Collection $certificates): \Illuminate\Support\Collection
    {
        $itemResults = app(FestItemResultsService::class);

        return $certificates
            ->filter(fn ($c) => $c['cert_type'] === 'winner' && ($c['item'] ?? null) && ($c['event'] ?? null))
            ->filter(fn ($c) => $itemResults->isItemVisible($c['item'], $c['event']))
            ->groupBy(fn ($c) => $c['item']->id)
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'item_id'    => $first['item']->id,
                    'item_title' => $first['item']->title,
                    'winners'    => $group->sortBy(fn ($c) => $c['mark']->position ?? 99)
                        ->map(fn ($c) => [
                            'id'       => $c['id'],
                            'uuid'     => $c['uuid'],
                            'name'     => $c['student']?->name ?? 'Participant',
                            'position' => $c['mark']->position ?? null,
                        ])
                        ->values(),
                ];
            })
            ->sortBy('item_title')
            ->values();
    }

    public function tally(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $tally = app(FestCertificateService::class)->certificateTally($event);

        return $this->inertia('Sahodaya/Events/CertificateTally', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event'       => $event,
            'rows'        => $tally['rows'],
            'totals'      => $tally['totals'],
            'childEvents' => $event->sportEventDropdownOptions(),
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

    public function downloadZip(Request $request, string $tenantId, FestEvent $event)
    {
        // Same override the single-item ID-card export already applies (see
        // FestIdCardController::pdf()) — this loop can run to hundreds/thousands of
        // certificates and previously had no headroom above container defaults.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $publishedOnly = $request->boolean('published_only');

        // embedAssets: true — the zip is extracted and opened outside the site's own
        // browser origin, so logo/seal/background/signature/photo images must be
        // self-contained data URIs, not site-relative URLs (see renderContext()).
        $payloads = $this->exportPayloadsForEvent($event, embedAssets: true, plain: $request->boolean('plain'), publishedOnly: $publishedOnly);

        abort_if($payloads->isEmpty(), 404, $publishedOnly ? 'No published winner certificates to download.' : 'No certificates to download.');

        $zipPath = storage_path('app/tmp/fest-certs-'.$event->id.'-'.time().'.zip');
        @mkdir(dirname($zipPath), 0755, true);

        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($payloads as $payload) {
            $html = view('fest.certificate-print', $payload)->render();
            $pdf = PdfGenerator::render($html);

            $name = str($payload['student']?->name ?? 'participant')->slug().'-'.$payload['certificate']->verification_uuid.'.pdf';
            $zip->addFromString($name, $pdf);
        }

        $zip->close();

        $filename = str($event->title)->slug()
            .($publishedOnly ? '-published-winners' : '-certificates')
            .($request->boolean('plain') ? '-plain' : '').'.zip';

        return response()->download($zipPath, $filename)->deleteFileAfterSend();
    }

    /**
     * Every certificate for an event, rendered on one page so the browser's own print
     * dialog can save them all as a single multi-page PDF — an alternative to
     * downloadZip() for admins who'd rather not extract a zip of individual files.
     */
    public function printAll(Request $request, string $tenantId, FestEvent $event)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // embedAssets: true here too — some browsers apply the same origin/session
        // requirements to images inside a print/"save as PDF" flow as a fresh
        // navigation would, so embedding avoids any dependence on the viewer still
        // being logged in with cookies that can load /storage/... URLs.
        $payloads = $this->exportPayloadsForEvent($event, embedAssets: true, plain: $request->boolean('plain'));

        abort_if($payloads->isEmpty(), 404, 'No certificates to print.');

        return view('fest.certificate-print-all', [
            'event'        => $event,
            'certificates' => $payloads,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>> renderContext()-shaped payloads, one per certificate
     */
    private function exportPayloadsForEvent(FestEvent $event, bool $embedAssets, bool $plain, bool $publishedOnly = false): \Illuminate\Support\Collection
    {
        $participantIds = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $event->reportableEventIds()))
            ->pluck('id');

        $certificates = Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->get();

        $service = app(FestCertificateService::class);

        // Batched instead of a per-certificate payloadFor() + resolveTemplate() (up to
        // 3 queries, doubled on fallback) inside the loop below — see
        // FestCertificateService::payloadsFor() and renderContext()'s $templateCache param.
        $payloads = $service->payloadsFor($certificates);

        if ($publishedOnly) {
            // Same "winner cert existing doesn't mean the item is published" caveat as
            // winnersByItem() above — filter on the item/event's own publish state, not
            // just cert_type.
            $itemResults = app(FestItemResultsService::class);
            $certificates = $certificates->filter(function ($certificate) use ($payloads, $itemResults) {
                $payload = $payloads->get($certificate->id) ?? [];
                $item = $payload['item'] ?? null;
                $itemEvent = $payload['event'] ?? null;

                return $certificate->cert_type === 'winner'
                    && $item && $itemEvent
                    && $itemResults->isItemVisible($item, $itemEvent);
            });
        }

        $templateCache = [];
        $participantsCache = [];

        return $certificates->map(function ($certificate) use ($service, $payloads, &$templateCache, &$participantsCache, $embedAssets, $plain) {
            $payload = $service->renderContext($certificate, $payloads->get($certificate->id), $templateCache, $participantsCache, embedAssets: $embedAssets);
            $payload['qr_src'] = app(FestIdCardQrService::class)->dataUri(route('certificates.verify', $certificate->verification_uuid, absolute: true));

            // "Plain" drops the uploaded background image only — the template's own
            // title/body/logo/seal/signatories still render via the same partial's
            // existing no-background branch, just without the ink-heavy backdrop, for
            // admins printing physical copies in bulk.
            if ($plain) {
                $payload['backgroundUrl'] = null;
            }

            return $payload;
        });
    }
}
