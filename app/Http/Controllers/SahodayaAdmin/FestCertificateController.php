<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Support\PdfGenerator;
use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestEventItem;
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

        $participantIds = FestParticipant::where(function ($q) use ($event) {
            $q->whereIn('event_id', $event->reportableEventIds())
              ->orWhereHas('registration', fn ($rq) => $rq->whereIn('event_id', $event->reportableEventIds()));
        })->pluck('id');

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

        $publishedItems = FestEventItem::whereIn('event_id', $event->reportableEventIds())
            ->whereNotNull('results_published_at')
            ->orderBy('title')
            ->get(['id', 'title', 'item_code'])
            ->map(fn ($item) => [
                'id'        => $item->id,
                'title'     => $item->title,
                'item_code' => $item->item_code,
            ])
            ->values();

        $schools = $certificates->map(fn ($c) => [
            'id'   => $c['registration']?->school?->id ?? $c['participant']?->registration?->school?->id,
            'name' => $c['registration']?->school?->name ?? $c['participant']?->registration?->school?->name ?? 'Unknown School',
        ])
        ->filter(fn ($s) => ! empty($s['id']))
        ->unique('id')
        ->sortBy('name')
        ->values();

        return $this->inertia('Sahodaya/Events/Certificates', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event'           => $event,
            'certificates'    => $certificates,
            'publishedItems'  => $publishedItems,
            'schools'         => $schools,
            'winnersByItem'   => $this->winnersByItem($certificates, $event),
            'winnersBySchool' => $this->winnersBySchool($certificates, $event),
        ]));
    }

    private function winnersByItem(\Illuminate\Support\Collection $certificates, FestEvent $currentEvent): \Illuminate\Support\Collection
    {
        return $certificates
            ->filter(fn ($c) => ($c['cert_type'] ?? null) === 'winner' && ! empty($c['item']))
            ->groupBy(fn ($c) => $c['item']->id)
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'item_id'    => $first['item']->id,
                    'item_title' => $first['item']->title,
                    'winners'    => $group->sortBy(fn ($c) => $c['mark']?->position ?? $c['position'] ?? 99)
                        ->map(fn ($c) => [
                            'id'       => $c['id'],
                            'uuid'     => $c['uuid'],
                            'name'     => $c['student']?->name ?? $c['participant']?->student?->name ?? 'Participant',
                            'position' => $c['mark']?->position ?? $c['position'] ?? null,
                        ])
                        ->values(),
                ];
            })
            ->sortBy('item_title')
            ->values();
    }

    private function winnersBySchool(\Illuminate\Support\Collection $certificates, FestEvent $currentEvent): \Illuminate\Support\Collection
    {
        return $certificates
            ->filter(fn ($c) => ($c['cert_type'] ?? null) === 'winner' && ! empty($c['item']))
            ->groupBy(fn ($c) => $c['registration']?->school_id ?? $c['participant']?->registration?->school_id ?? 0)
            ->map(function ($group) {
                $first = $group->first();
                $school = $first['registration']?->school ?? $first['participant']?->registration?->school;

                return [
                    'school_id'   => $school?->id ?? 0,
                    'school_name' => $school?->name ?? 'Unknown School',
                    'winners'     => $group->sortBy(fn ($c) => $c['mark']?->position ?? $c['position'] ?? 99)
                        ->map(fn ($c) => [
                            'id'         => $c['id'],
                            'uuid'       => $c['uuid'],
                            'name'       => $c['student']?->name ?? $c['participant']?->student?->name ?? 'Participant',
                            'item_title' => $c['item']?->title ?? '',
                            'position'   => $c['mark']?->position ?? $c['position'] ?? null,
                        ])
                        ->values(),
                ];
            })
            ->sortBy('school_name')
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

    public function generate(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->input('item_id') ? (int) $request->input('item_id') : null;
        $created = app(FestCertificateService::class)->generateForEvent($event, $itemId);

        $audit->festEvent($event, FestPageActivity::CERTIFICATES, 'fest.certificates.generated', count($created).' certificate(s) generated', [
            'count'   => count($created),
            'item_id' => $itemId,
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
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $publishedOnly = $request->boolean('published_only');
        $itemId = $request->query('item_id') ? (int) $request->query('item_id') : null;
        $schoolId = $request->query('school_id') ? (int) $request->query('school_id') : null;
        $certType = $request->query('cert_type');
        $certIds = $request->query('certificate_ids')
            ? array_filter(array_map('intval', explode(',', (string) $request->query('certificate_ids'))))
            : null;

        $payloads = $this->exportPayloadsForEvent(
            $event,
            embedAssets: true,
            plain: $request->boolean('plain'),
            publishedOnly: $publishedOnly,
            itemId: $itemId,
            schoolId: $schoolId,
            certType: $certType,
            certIds: $certIds
        );

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

    public function printAll(Request $request, string $tenantId, FestEvent $event)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $publishedOnly = $request->boolean('published_only');
        $itemId = $request->query('item_id') ? (int) $request->query('item_id') : null;
        $schoolId = $request->query('school_id') ? (int) $request->query('school_id') : null;
        $certType = $request->query('cert_type');
        $certIds = $request->query('certificate_ids')
            ? array_filter(array_map('intval', explode(',', (string) $request->query('certificate_ids'))))
            : null;

        $payloads = $this->exportPayloadsForEvent(
            $event,
            embedAssets: false,
            plain: $request->boolean('plain'),
            publishedOnly: $publishedOnly,
            itemId: $itemId,
            schoolId: $schoolId,
            certType: $certType,
            certIds: $certIds
        );

        abort_if($payloads->isEmpty(), 404, 'No certificates to print.');

        return view('fest.certificate-print-all', [
            'event'        => $event,
            'certificates' => $payloads,
        ]);
    }

    private function exportPayloadsForEvent(
        FestEvent $event,
        bool $embedAssets,
        bool $plain,
        bool $publishedOnly = false,
        ?int $itemId = null,
        ?int $schoolId = null,
        ?string $certType = null,
        ?array $certIds = null
    ): \Illuminate\Support\Collection {
        if (! empty($certIds)) {
            $certificates = Certificate::whereIn('id', $certIds)->get();
        } else {
            $participantIds = FestParticipant::where(function ($q) use ($event) {
                $q->whereIn('event_id', $event->reportableEventIds())
                  ->orWhereHas('registration', fn ($rq) => $rq->whereIn('event_id', $event->reportableEventIds()));
            })
            ->when($itemId, fn ($q) => $q->where(function ($iq) use ($itemId) {
                $iq->whereHas('registration', fn ($rq) => $rq->where('item_id', $itemId))
                   ->orWhereHas('mark', fn ($mq) => $mq->where('item_id', $itemId));
            }))
            ->when($schoolId, fn ($q) => $q->whereHas('registration', fn ($sq) => $sq->where('school_id', $schoolId)))
            ->pluck('id');

            $certificates = Certificate::where('entity_type', FestParticipant::class)
                ->whereIn('entity_id', $participantIds)
                ->when($certType, fn ($q) => $q->where('cert_type', $certType))
                ->get();
        }

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
                $payload['plainMode'] = true;
            }

            return $payload;
        });
    }
}
