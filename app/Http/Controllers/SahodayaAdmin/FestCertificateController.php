<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Jobs\RenderCertificateChunkJob;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestEventNotifier;
use App\Services\Events\FestIdCardQrService;
use App\Services\Events\FestItemResultsService;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use App\Support\FestPageActivity;
use App\Support\PdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

class FestCertificateController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $certificates = $this->certificatesForEvent($event);

        return $this->inertia('Sahodaya/Events/Certificates', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event' => $event,
            'certificates' => $certificates,
            'publishedItems' => $this->publishedItemsForEvent($event),
            'schools' => $this->schoolsFromCertificates($certificates),
            'winnersByItem' => $this->winnersByItem($certificates, $event),
            'winnersBySchool' => $this->winnersBySchool($certificates, $event),
            'participationByItem' => $this->participationByItem($certificates, $event),
            'participationBySchool' => $this->participationBySchool($certificates, $event),
            'recentBatches' => $this->recentBatchesForEvent($event),
            'staleCount' => $certificates->filter(fn ($c) => $c['is_stale'] ?? false)->count(),
        ]));
    }

    /**
     * Dedicated Merit certificates workspace — same underlying data as index(), scoped
     * to cert_type=winner, with its own item-wise/school-wise filtering and bulk actions
     * on the frontend rather than sharing the combined page's tabs.
     */
    public function meritCertificates(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $certificates = $this->certificatesForEvent($event, 'winner');

        return $this->inertia('Sahodaya/Events/MeritCertificates', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event' => $event,
            'certificates' => $certificates,
            'publishedItems' => $this->publishedItemsForEvent($event),
            'schools' => $this->schoolsFromCertificates($certificates),
            'recentBatches' => $this->recentBatchesForEvent($event, 'winner'),
            'staleCount' => $certificates->filter(fn ($c) => $c['is_stale'] ?? false)->count(),
        ]));
    }

    /** Dedicated Participation certificates workspace — same idea as meritCertificates(). */
    public function participationCertificatesPage(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $certificates = $this->certificatesForEvent($event, 'participation');

        return $this->inertia('Sahodaya/Events/ParticipationCertificates', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event' => $event,
            'certificates' => $certificates,
            'publishedItems' => $this->publishedItemsForEvent($event),
            'schools' => $this->schoolsFromCertificates($certificates),
            'recentBatches' => $this->recentBatchesForEvent($event, 'participation'),
            'staleCount' => $certificates->filter(fn ($c) => $c['is_stale'] ?? false)->count(),
        ]));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function certificatesForEvent(FestEvent $event, ?string $certType = null): Collection
    {
        $participantIds = FestParticipant::where(function ($q) use ($event) {
            $q->whereIn('event_id', $event->reportableEventIds())
                ->orWhereHas('registration', fn ($rq) => $rq->whereIn('event_id', $event->reportableEventIds()));
        })->pluck('id');

        $service = app(FestCertificateService::class);
        $certificates = Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->when($certType, fn ($q) => $q->where('cert_type', $certType))
            ->orderByDesc('generated_at')
            ->get();

        // Batched instead of one payloadFor() (2 queries each) per row — see
        // FestCertificateService::payloadsFor().
        $payloads = $service->payloadsFor($certificates);

        return $certificates->map(fn ($c) => array_merge(
            [
                'id' => $c->id,
                'uuid' => $c->verification_uuid,
                'cert_type' => $c->cert_type,
                'is_stale' => $c->is_stale,
                'is_rendered' => $c->file_path !== null,
                'rendered_at' => $c->rendered_at,
            ],
            $payloads->get($c->id) ?? []
        ));
    }

    private function publishedItemsForEvent(FestEvent $event): Collection
    {
        $classGroupLabels = FestClassGroupScheme::labels(null, $event);
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

        return FestEventItem::whereIn('event_id', $event->reportableEventIds())
            ->whereNotNull('results_published_at')
            ->orderBy('title')
            ->get(['id', 'title', 'item_code', 'class_group', 'category'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'item_code' => $item->item_code,
                'category_label' => FestItemCategoryLabel::shortLabel($item, $classGroupLabels, $artsCategoryLabels),
            ])
            ->values();
    }

    /** @param  Collection<int, array<string, mixed>>  $certificates */
    private function schoolsFromCertificates(Collection $certificates): Collection
    {
        return $certificates->map(fn ($c) => [
            'id' => $c['registration']?->school?->id ?? $c['participant']?->registration?->school?->id,
            'name' => $c['registration']?->school?->name ?? $c['participant']?->registration?->school?->name ?? 'Unknown School',
        ])
            ->filter(fn ($s) => ! empty($s['id']))
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function recentBatchesForEvent(FestEvent $event, ?string $certType = null): Collection
    {
        return CertificateBatch::where('event_id', $event->id)
            ->when($certType, fn ($q) => $q->where('cert_type', $certType))
            ->latest()
            ->limit(10)
            ->get([
                'id', 'batch_type', 'scope_description', 'status', 'total_count',
                'processed_count', 'succeeded_count', 'failed_count', 'created_at', 'completed_at',
            ]);
    }

    private function winnersByItem(Collection $certificates, FestEvent $currentEvent): Collection
    {
        return $this->groupCertificatesByItem($certificates, 'winner', $currentEvent);
    }

    private function winnersBySchool(Collection $certificates, FestEvent $currentEvent): Collection
    {
        return $this->groupCertificatesBySchool($certificates, 'winner', $currentEvent);
    }

    /**
     * A participation certificate is anchored to one arbitrary FestParticipant row (see
     * FestCertificateService::generateParticipationForEvent()'s $anchor), so grouping by
     * $c['item'] here groups by that person's *first* registered item, same simplification
     * ParticipationCertificates.vue's item filter already makes — not every item they
     * participated in. Multi-item participants aren't fanned out into multiple groups.
     */
    private function participationByItem(Collection $certificates, FestEvent $currentEvent): Collection
    {
        return $this->groupCertificatesByItem($certificates, 'participation', $currentEvent);
    }

    private function participationBySchool(Collection $certificates, FestEvent $currentEvent): Collection
    {
        return $this->groupCertificatesBySchool($certificates, 'participation', $currentEvent);
    }

    /**
     * Distinguishes same-titled items (e.g. three separate "Book Review" items, one per
     * class-group category) that would otherwise be indistinguishable in the grouped-by-
     * item admin view — see FestItemCategoryLabel's own docblock for why class_group
     * takes priority. shortLabel(), not resolve() — this is exactly the "compact,
     * certificate-context" use case its own docblock calls out.
     */
    private function groupCertificatesByItem(Collection $certificates, string $certType, FestEvent $event): Collection
    {
        $classGroupLabels = FestClassGroupScheme::labels(null, $event);
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

        return $certificates
            ->filter(fn ($c) => ($c['cert_type'] ?? null) === $certType && ! empty($c['item']))
            ->groupBy(fn ($c) => $c['item']->id)
            ->map(function ($group) use ($classGroupLabels, $artsCategoryLabels) {
                $first = $group->first();

                return [
                    'item_id' => $first['item']->id,
                    'item_title' => $first['item']->title,
                    'item_code' => $first['item']->item_code,
                    'category_label' => FestItemCategoryLabel::shortLabel($first['item'], $classGroupLabels, $artsCategoryLabels),
                    'winners' => $group->sortBy(fn ($c) => $c['mark']?->position ?? $c['position'] ?? 99)
                        ->map(fn ($c) => [
                            'id' => $c['id'],
                            'uuid' => $c['uuid'],
                            'name' => $c['student']?->name ?? $c['participant']?->student?->name ?? 'Participant',
                            'position' => $c['mark']?->position ?? $c['position'] ?? null,
                            'is_rendered' => $c['is_rendered'] ?? false,
                            'is_stale' => $c['is_stale'] ?? false,
                        ])
                        ->values(),
                ];
            })
            ->sortBy('item_title')
            ->values();
    }

    private function groupCertificatesBySchool(Collection $certificates, string $certType, FestEvent $event): Collection
    {
        $classGroupLabels = FestClassGroupScheme::labels(null, $event);
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

        return $certificates
            ->filter(fn ($c) => ($c['cert_type'] ?? null) === $certType && ! empty($c['item']))
            ->groupBy(fn ($c) => $c['registration']?->school_id ?? $c['participant']?->registration?->school_id ?? 0)
            ->map(function ($group) use ($classGroupLabels, $artsCategoryLabels) {
                $first = $group->first();
                $school = $first['registration']?->school ?? $first['participant']?->registration?->school;

                return [
                    'school_id' => $school?->id ?? 0,
                    'school_name' => $school?->name ?? 'Unknown School',
                    'winners' => $group->sortBy(fn ($c) => $c['mark']?->position ?? $c['position'] ?? 99)
                        ->map(fn ($c) => [
                            'id' => $c['id'],
                            'uuid' => $c['uuid'],
                            'name' => $c['student']?->name ?? $c['participant']?->student?->name ?? 'Participant',
                            'item_title' => $c['item']?->title ?? '',
                            'category_label' => FestItemCategoryLabel::shortLabel($c['item'], $classGroupLabels, $artsCategoryLabels),
                            'position' => $c['mark']?->position ?? $c['position'] ?? null,
                            'is_rendered' => $c['is_rendered'] ?? false,
                            'is_stale' => $c['is_stale'] ?? false,
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
            'event' => $event,
            'rows' => $tally['rows'],
            'totals' => $tally['totals'],
            'childEvents' => $event->sportEventDropdownOptions(),
        ]));
    }

    public function generate(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->input('item_id') ? (int) $request->input('item_id') : null;
        $created = app(FestCertificateService::class)->generateForEvent($event, $itemId);

        $audit->festEvent($event, FestPageActivity::CERTIFICATES, 'fest.certificates.generated', count($created).' certificate(s) generated', [
            'count' => count($created),
            'item_id' => $itemId,
        ]);

        try {
            app(FestEventNotifier::class)->certificatesAvailable($event, count($created));
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

        $plain = $request->boolean('plain');
        $service = app(FestCertificateService::class);

        foreach ($payloads as $payload) {
            $certificate = $payload['certificate'];

            // $payload is already the final, ready-to-render shape (exportPayloadsForEvent()
            // above built it with embedAssets+plain baked in for every certificate, cache
            // status notwithstanding), so the closure has no computation to defer — it
            // still gets us the shared cache-check + orientation-correct render on a miss.
            $pdf = $service->cachedOrFreshPdf($certificate, fn () => $payload, $plain);

            $name = str($payload['student']?->name ?? 'participant')->slug().'-'.$certificate->verification_uuid.'.pdf';
            $zip->addFromString($name, $pdf);
        }

        $zip->close();

        $filename = str($event->title)->slug()
            .($publishedOnly ? '-published-winners' : ($certType ? '-'.$certType : '-certificates'))
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
            'event' => $event,
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
    ): Collection {
        $certificates = $this->resolveCertificateScope($event, $itemId, $schoolId, $certType, $certIds);

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

    /**
     * Shared certificate-set resolution behind exportPayloadsForEvent() (single/bulk
     * print+download) and the batch-rendering actions below — item-wise, school-wise,
     * whole-event, and ad-hoc-selection all resolve through the exact same scope logic,
     * so "what a Generate/Render run covers" and "what a Download covers" never drift
     * apart for the same filters.
     */
    private function resolveCertificateScope(
        FestEvent $event,
        ?int $itemId = null,
        ?int $schoolId = null,
        ?string $certType = null,
        ?array $certIds = null,
    ): Collection {
        if (! empty($certIds)) {
            return Certificate::whereIn('id', $certIds)->get();
        }

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

        return Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->when($certType, fn ($q) => $q->where('cert_type', $certType))
            ->get();
    }

    public function generateAndRenderBatch(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->input('item_id') ? (int) $request->input('item_id') : null;
        $schoolId = $request->input('school_id') ? (int) $request->input('school_id') : null;
        $certType = $request->input('cert_type') ?: null;
        $certIds = $request->input('certificate_ids')
            ? array_values(array_filter(array_map('intval', explode(',', (string) $request->input('certificate_ids')))))
            : null;

        $certificates = $this->resolveCertificateScope($event, $itemId, $schoolId, $certType, $certIds);

        abort_if($certificates->isEmpty(), 404, 'No certificates match this scope — generate the certificate rows first.');

        $batch = $this->dispatchRenderBatch($request, $event, $certificates, 'generate', $itemId, $schoolId, $certType, $certIds);

        return back()
            ->with('success', "Rendering {$certificates->count()} certificate(s) in the background.")
            ->with('certificate_batch_id', $batch->id);
    }

    public function regenerateStale(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $participantIds = FestParticipant::where(function ($q) use ($event) {
            $q->whereIn('event_id', $event->reportableEventIds())
                ->orWhereHas('registration', fn ($rq) => $rq->whereIn('event_id', $event->reportableEventIds()));
        })->pluck('id');

        $certificates = Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->where('is_stale', true)
            ->get();

        abort_if($certificates->isEmpty(), 404, 'No stale certificates to regenerate.');

        $batch = $this->dispatchRenderBatch($request, $event, $certificates, 'regenerate_stale', null, null, null, null);

        return back()
            ->with('success', "Regenerating {$certificates->count()} stale certificate(s) in the background.")
            ->with('certificate_batch_id', $batch->id);
    }

    /**
     * Creates the tracking row, chunks the certificate set into ~150-id slices (see
     * RenderCertificateChunkJob), and dispatches them as one Bus::batch() run — per-chunk
     * failure isolation and progress counters come from Laravel's own job_batches
     * machinery rather than anything bespoke here.
     */
    private function dispatchRenderBatch(
        Request $request,
        FestEvent $event,
        Collection $certificates,
        string $batchType,
        ?int $itemId,
        ?int $schoolId,
        ?string $certType,
        ?array $certIds,
    ): CertificateBatch {
        $batchRow = CertificateBatch::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $event->id,
            'batch_type' => $batchType,
            'cert_type' => $certType,
            'item_id' => $itemId,
            'school_id' => $schoolId,
            'certificate_ids_json' => $certIds,
            'scope_description' => $this->describeScope($event, $itemId, $schoolId, $certType, $certIds),
            'total_count' => $certificates->count(),
            'status' => CertificateBatch::STATUS_PROCESSING,
            'created_by_user_id' => $request->user()?->id,
            'started_at' => now(),
        ]);

        $tenantId = $this->sahodaya->id;
        $jobs = $certificates->pluck('id')->chunk(150)
            ->map(fn ($chunk) => new RenderCertificateChunkJob($batchRow->id, $chunk->values()->all(), $tenantId))
            ->all();

        $laravelBatch = Bus::batch($jobs)
            ->allowFailures()
            ->then(function () use ($batchRow) {
                $batchRow->refresh();
                $batchRow->update([
                    'status' => $batchRow->failed_count > 0
                        ? CertificateBatch::STATUS_COMPLETED_WITH_ERRORS
                        : CertificateBatch::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            })
            ->catch(function ($_, \Throwable $e) use ($batchRow) {
                $batchRow->update([
                    'status' => CertificateBatch::STATUS_FAILED,
                    'error' => mb_substr($e->getMessage(), 0, 2000),
                    'completed_at' => now(),
                ]);
            })
            ->name('certificate-batch-'.$batchRow->id)
            ->dispatch();

        $batchRow->update(['queued_job_batch_id' => $laravelBatch->id]);

        return $batchRow;
    }

    private function describeScope(FestEvent $event, ?int $itemId, ?int $schoolId, ?string $certType, ?array $certIds): string
    {
        if (! empty($certIds)) {
            return count($certIds).' selected certificate(s)';
        }
        if ($itemId) {
            $item = FestEventItem::find($itemId);

            return 'Item: '.($item?->title ?? "#{$itemId}");
        }
        if ($schoolId) {
            $school = Tenant::find($schoolId);

            return 'School: '.($school?->name ?? $schoolId);
        }
        if ($certType) {
            return ucfirst($certType).' certificates — whole event';
        }

        return 'Whole event';
    }

    public function batchProgress(string $tenantId, FestEvent $event, CertificateBatch $batch)
    {
        abort_if($batch->tenant_id !== $this->sahodaya->id || $batch->event_id !== $event->id, 403);

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'batch_type' => $batch->batch_type,
            'scope' => $batch->scope_description,
            'total_count' => $batch->total_count,
            'processed_count' => $batch->processed_count,
            'succeeded_count' => $batch->succeeded_count,
            'failed_count' => $batch->failed_count,
            'error' => $batch->error,
        ]);
    }

    public function batches(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return response()->json(
            CertificateBatch::where('event_id', $event->id)->latest()->limit(10)->get()
        );
    }

    public function previewSample(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // embedAssets: false — viewed on-site in a normal browser tab, so the cheaper
        // site-relative /storage/... URLs resolve fine (see renderContext()'s docblock).
        $context = $this->buildPreviewContext($request, $event, embedAssets: false);

        return view('fest.certificate-print', $context);
    }

    public function previewSamplePdf(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // embedAssets: true — DomPDF (and the external Chromium service) render the HTML
        // outside the site's own browser origin, so a relative /storage/... URL for the
        // background/logo/seal never resolves; every image must be a self-contained
        // base64 data URI instead, same as every other real PDF-producing path
        // (downloadZip(), RenderCertificateChunkJob) already does.
        $context = $this->buildPreviewContext($request, $event, embedAssets: true);
        $isLandscape = ($context['overlayLayout']['orientation'] ?? 'landscape') !== 'portrait';
        $html = view('fest.certificate-print', $context)->render();

        return PdfGenerator::download($html, 'certificate-preview.pdf', true, $isLandscape);
    }

    /**
     * Renders the real person most likely to expose an overflowing field — most distinct
     * items for a participation certificate (the unbounded item_title list), longest name
     * for a winner certificate — through the exact same renderContext() pipeline real
     * certificates use, rather than CertificateTemplateController::preview()'s canned
     * "Sample Student Name" values. Lets an admin catch a layout problem before
     * committing to a full bulk render.
     */
    private function buildPreviewContext(Request $request, FestEvent $event, bool $embedAssets): array
    {
        $certType = $request->query('cert_type', 'participation');
        $itemId = $request->query('item_id') ? (int) $request->query('item_id') : null;

        $service = app(FestCertificateService::class);

        // Explicit override so an admin (or this preview screen itself, later) can check
        // one specific person's certificate rather than only ever seeing the automatic
        // worst-case pick — useful once the worst case itself looks right and you want
        // to spot-check someone with a very different item count/name length instead.
        $participant = $request->query('participant_id')
            ? FestParticipant::find((int) $request->query('participant_id'))
            : $service->worstCaseParticipantForPreview($event, $certType, $itemId);

        abort_if(! $participant, 404, 'No eligible participants yet to preview — register participants for this event first.');

        $template = $service->resolveTemplate($event, $certType === 'winner' ? $itemId : null, $certType);

        $certificate = new Certificate([
            'entity_type' => FestParticipant::class,
            'entity_id' => $participant->id,
            'cert_type' => $certType,
            'template_id' => $template?->id,
            'verification_uuid' => 'PREVIEW-'.$participant->id,
        ]);

        $templateCache = [];
        $participantsCache = [];
        $context = $service->renderContext($certificate, null, $templateCache, $participantsCache, embedAssets: $embedAssets);
        $context['isSample'] = true;
        $context['qr_src'] = null;

        return $context;
    }
}
