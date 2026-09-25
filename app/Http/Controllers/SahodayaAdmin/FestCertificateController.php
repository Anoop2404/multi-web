<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Jobs\BuildCertificateZipChunkJob;
use App\Jobs\RenderCertificateChunkJob;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateBatch;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestEventNotifier;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use App\Support\FestPageActivity;
use App\Support\PdfGenerator;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class FestCertificateController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        // A whole hub has thousands of certificates; building every payload plus the grouped
        // views below is the heaviest read in the module.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $certificates = $this->withParticipationItems($this->certificatesForEvent($event), $event);
        $schoolResults = $this->schoolResultsStatus($event);

        return $this->inertia('Sahodaya/Events/Certificates', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event' => $event,
            'certificates' => $this->slimCertificates($certificates),
            'publishedItems' => $this->publishedItemsForEvent($event),
            'schools' => $this->schoolsFromCertificates($certificates),
            'winnersByItem' => $this->winnersByItem($certificates, $event),
            'winnersBySchool' => $this->withSchoolResults($this->winnersBySchool($certificates, $event), $schoolResults),
            'participationBySchool' => $this->withSchoolResults($this->participationBySchool($certificates, $event), $schoolResults),
            'recentBatches' => $this->recentBatchesForEvent($event),
            'staleCount' => $certificates->filter(fn ($c) => $c['is_stale'] ?? false)->count(),
            'certificateSignatories' => $this->signatoriesForUi($event),
            'signatoryLabelSuggestions' => $this->signatoryLabelSuggestions(),
        ]));
    }

    /**
     * Dedicated Merit certificates workspace — same underlying data as index(), scoped
     * to cert_type=winner, with its own item-wise/school-wise filtering and bulk actions
     * on the frontend rather than sharing the combined page's tabs.
     */
    public function meritCertificates(string $tenantId, FestEvent $event)
    {
        // A whole hub has thousands of certificates; building every payload plus the grouped
        // views below is the heaviest read in the module.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $certificates = $this->certificatesForEvent($event, 'winner');

        return $this->inertia('Sahodaya/Events/MeritCertificates', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event' => $event,
            'certificates' => $this->slimCertificates($certificates),
            'publishedItems' => $this->publishedItemsForEvent($event),
            'schools' => $this->schoolsFromCertificates($certificates),
            'recentBatches' => $this->recentBatchesForEvent($event, 'winner'),
            'certificateSignatories' => $this->signatoriesForUi($event),
            'signatoryLabelSuggestions' => $this->signatoryLabelSuggestions(),
            'staleCount' => $certificates->filter(fn ($c) => $c['is_stale'] ?? false)->count(),
        ]));
    }

    /** Dedicated Participation certificates workspace — same idea as meritCertificates(). */
    public function participationCertificatesPage(string $tenantId, FestEvent $event)
    {
        // A whole hub has thousands of certificates; building every payload plus the grouped
        // views below is the heaviest read in the module.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $certificates = $this->withParticipationItems($this->certificatesForEvent($event, 'participation'), $event);

        return $this->inertia('Sahodaya/Events/ParticipationCertificates', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event' => $event,
            'certificates' => $this->slimCertificates($certificates),
            'publishedItems' => $this->publishedItemsForEvent($event),
            'schools' => $this->schoolsFromCertificates($certificates),
            'recentBatches' => $this->recentBatchesForEvent($event, 'participation'),
            'certificateSignatories' => $this->signatoriesForUi($event),
            'signatoryLabelSuggestions' => $this->signatoryLabelSuggestions(),
            'staleCount' => $certificates->filter(fn ($c) => $c['is_stale'] ?? false)->count(),
        ]));
    }

    /**
     * Only what the certificate list pages actually read. certificatesForEvent() merges each
     * certificate's full payloadFor() shape (participant, registration, event, item, mark
     * ... as whole Eloquent models with their loaded relations) into every row, which the
     * grouped views below need server-side but which serialized straight into the Inertia
     * page was ~30 KB per certificate -- a ~3,000-certificate hub rendered a page payload of
     * ~98 MB and exhausted memory building the root view (production, 2026-09-25).
     *
     * @param  Collection<int, array<string, mixed>>  $certificates
     * @return Collection<int, array<string, mixed>>
     */
    private function slimCertificates(Collection $certificates): Collection
    {
        return $certificates->map(function (array $c) {
            $school = $c['registration']?->school ?? $c['participant']?->registration?->school;

            return [
                'id' => $c['id'],
                'uuid' => $c['uuid'],
                'cert_type' => $c['cert_type'],
                'is_stale' => $c['is_stale'],
                'is_rendered' => $c['is_rendered'],
                'rendered_at' => $c['rendered_at'],
                'student' => ['name' => $c['student']?->name ?? $c['participant']?->student?->name],
                'item' => ! empty($c['item']) ? ['id' => $c['item']->id, 'title' => $c['item']->title] : null,
                'items' => $c['participation_items'] ?? null,
                'mark' => $c['mark'] ? ['position' => $c['mark']->position] : null,
                'registration' => ['school' => $school ? ['id' => $school->id, 'name' => $school->name] : null],
            ];
        })->values();
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
        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

        return FestEventItem::whereIn('event_id', $event->reportableEventIds())
            ->whereNotNull('results_published_at')
            ->orderBy('title')
            ->get(['id', 'title', 'item_code', 'class_group', 'category', 'gender', 'participant_type'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'item_code' => $item->item_code,
                'category_label' => FestItemCategoryLabel::shortLabel($item, $classGroupLabels, $artsCategoryLabels),
                'gender_label' => $this->itemGenderLabel($item),
                'type_label' => $this->itemTypeLabel($item),
            ])
            ->values();
    }

    private function itemGenderLabel(FestEventItem $item): ?string
    {
        return FestItemCategoryLabel::genderLabel($item->gender);
    }

    private function itemTypeLabel(FestEventItem $item): string
    {
        return FestItemCategoryLabel::typeLabel($item->participant_type);
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
     * A participation certificate is one per person (anchored to an arbitrary one of their
     * FestParticipant rows — see FestCertificateService::generateParticipationForEvent()),
     * handed out per school, so there is deliberately no grouped-by-item participation
     * view: grouping by the anchor's item misfiled multi-item students under whichever item
     * they happened to register first. Each row carries the person's full item list
     * (withParticipationItems()) — what the certificate itself prints.
     */
    private function participationBySchool(Collection $certificates, FestEvent $currentEvent): Collection
    {
        return $this->groupCertificatesBySchool($certificates, 'participation', $currentEvent);
    }

    /**
     * Per school: how many of the items it has approved registrations in have their
     * results published (FestEventItem.results_published_at — the per-item flag, same as
     * publishedItemsForEvent(); the event-wide results_published only flips once the whole
     * fest is final). A school with none pending has every merit result it will ever get,
     * and every grade its participation certificates print, so its certificates are safe
     * to bulk-download.
     *
     * @return Collection<string, array{total: int, published: int, pending: list<string>}> keyed by school id
     */
    private function schoolResultsStatus(FestEvent $event): Collection
    {
        $registrations = FestRegistration::query()
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('status', 'approved')
            ->whereNotNull('item_id')
            ->whereNotNull('school_id')
            ->distinct()
            ->get(['school_id', 'item_id']);

        if ($registrations->isEmpty()) {
            return collect();
        }

        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);
        $items = FestEventItem::whereIn('id', $registrations->pluck('item_id')->unique())
            ->with('event:id,tenant_id')
            ->get(['id', 'event_id', 'title', 'class_group', 'category', 'age_group', 'results_published_at'])
            ->keyBy('id');

        return $registrations->groupBy('school_id')->map(function (Collection $rows) use ($items, $classGroupLabels, $artsCategoryLabels) {
            $schoolItems = $rows->pluck('item_id')->unique()->map(fn ($id) => $items->get($id))->filter();
            $pending = $schoolItems->reject(fn (FestEventItem $item) => $item->results_published_at !== null);

            return [
                'total' => $schoolItems->count(),
                'published' => $schoolItems->count() - $pending->count(),
                'pending' => $pending
                    ->map(function (FestEventItem $item) use ($classGroupLabels, $artsCategoryLabels) {
                        $category = FestItemCategoryLabel::shortLabel($item, $classGroupLabels, $artsCategoryLabels);

                        return $category ? "{$item->title} ({$category})" : $item->title;
                    })
                    ->sort()
                    ->values()
                    ->all(),
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $groups  groupCertificatesBySchool() rows
     * @param  Collection<string, array{total: int, published: int, pending: list<string>}>  $schoolResults
     */
    private function withSchoolResults(Collection $groups, Collection $schoolResults): Collection
    {
        return $groups->map(fn (array $group) => $group + [
            'results' => $schoolResults->get($group['school_id']) ?? ['total' => 0, 'published' => 0, 'pending' => []],
        ]);
    }

    /**
     * Adds 'participation_items' (every item the person's aggregated certificate lists, as
     * [id, title, category_label]) to each participation certificate row; winner rows are
     * left untouched since they are always exactly their own single item.
     *
     * @param  Collection<int, array<string, mixed>>  $certificates
     * @return Collection<int, array<string, mixed>>
     */
    private function withParticipationItems(Collection $certificates, FestEvent $event): Collection
    {
        if (! $certificates->contains(fn ($c) => ($c['cert_type'] ?? null) === 'participation')) {
            return $certificates;
        }

        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);
        $itemsByPerson = app(FestCertificateService::class)->participationItemsByPerson($event)
            ->map(fn (Collection $items) => $items->map(fn (FestEventItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'category_label' => FestItemCategoryLabel::shortLabel($item, $classGroupLabels, $artsCategoryLabels),
            ])->values()->all());

        return $certificates->map(function (array $c) use ($itemsByPerson) {
            if (($c['cert_type'] ?? null) !== 'participation' || empty($c['participant'])) {
                return $c;
            }

            $c['participation_items'] = $itemsByPerson->get(FestCertificateService::participationPersonKey($c['participant']), []);

            return $c;
        });
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
        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
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
                    'gender_label' => $this->itemGenderLabel($first['item']),
                    'type_label' => $this->itemTypeLabel($first['item']),
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
            ->values()
            // Sl No against each ITEM row in this grouped-by-item listing (not the
            // students inside it) — added after the sort above so numbering matches the
            // on-screen order.
            ->map(fn ($group, $index) => array_merge($group, ['sl_no' => $index + 1]))
            ->values();
    }

    private function groupCertificatesBySchool(Collection $certificates, string $certType, FestEvent $event): Collection
    {
        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

        return $certificates
            ->filter(fn ($c) => ($c['cert_type'] ?? null) === $certType && ! empty($c['item']))
            ->groupBy(fn ($c) => $c['registration']?->school_id ?? $c['participant']?->registration?->school_id ?? 0)
            ->map(function ($group) use ($classGroupLabels, $artsCategoryLabels, $certType) {
                $first = $group->first();
                $school = $first['registration']?->school ?? $first['participant']?->registration?->school;

                $rows = $group->map(fn ($c) => [
                    'id' => $c['id'],
                    'uuid' => $c['uuid'],
                    'name' => $c['student']?->name ?? $c['participant']?->student?->name ?? 'Participant',
                    'item_title' => $c['item']?->title ?? '',
                    'category_label' => FestItemCategoryLabel::shortLabel($c['item'], $classGroupLabels, $artsCategoryLabels),
                    // Participation: every item on the person's one certificate, not
                    // just the anchor row's item.
                    'items' => $c['participation_items'] ?? null,
                    'position' => $c['mark']?->position ?? $c['position'] ?? null,
                    'is_rendered' => $c['is_rendered'] ?? false,
                    'is_stale' => $c['is_stale'] ?? false,
                ]);

                return [
                    'school_id' => $school?->id ?? 0,
                    'school_name' => $school?->name ?? 'Unknown School',
                    // Participation has no positions — alphabetical, for handing out.
                    'winners' => ($certType === 'participation'
                        ? $rows->sortBy(fn ($r) => mb_strtolower($r['name']))
                        : $rows->sortBy(fn ($r) => $r['position'] ?? 99))
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
            'childEvents' => $this->scopedChildEventOptions($event),
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

    /**
     * Sets or clears the event's certificate_date override — FestCertificateService::
     * resolveFieldValues() falls back to event_end/event_start (then now()) when this is
     * null. Doesn't proactively mark existing certificates stale; that follows the same
     * lazy, scheduled staleness check every other upstream-data change already relies on
     * (see contentHash()'s docblock) rather than a special case for this one field.
     */
    public function updateCertificateDate(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $validated = $request->validate(['certificate_date' => 'nullable|date']);

        $event->update(['certificate_date' => $validated['certificate_date'] ?? null]);

        return back()->with('success', $validated['certificate_date']
            ? 'Certificate date updated.'
            : 'Certificate date cleared — back to the event\'s own dates.');
    }

    /**
     * Sets this event's certificate signatories (venue convenor, host principal, ...): a
     * free list of {label, name, designation, school, signature image}. Who signs differs
     * per host venue, so it can't be baked into a shared template; the template decides
     * where each labelled block is printed (Certificate templates -> Signature blocks) and
     * matches it to an entry here by the label.
     */
    public function updateSignatories(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $validated = $request->validate([
            'signatories'                    => 'nullable|array|max:12',
            'signatories.*.label'            => 'nullable|required_with:signatories.*.name,signatories.*.designation,signatories.*.school,signatories.*.signature,signatories.*.signature_path|string|max:80',
            'signatories.*.name'             => 'nullable|string|max:120',
            'signatories.*.designation'      => 'nullable|string|max:120',
            'signatories.*.school'           => 'nullable|string|max:160',
            'signatories.*.signature'        => 'nullable|image|max:1024',
            'signatories.*.signature_path'   => 'nullable|string|max:255',
            'signatories.*.remove_signature' => 'nullable|boolean',
        ]);

        // An existing image path may only be kept if it is one this event already holds --
        // never trust a client-supplied storage path.
        $held = collect($event->certificate_signatories ?? [])->pluck('signature_path')->filter()->all();
        $disk = TenantStorage::uploadDisk();
        $seen = [];
        $entries = [];

        foreach ($validated['signatories'] ?? [] as $i => $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $key = CertificateTemplate::signatureKey($label);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $path = in_array($row['signature_path'] ?? null, $held, true) ? $row['signature_path'] : null;
            if ($request->hasFile("signatories.$i.signature")) {
                $path = $request->file("signatories.$i.signature")->store('sahodaya/'.$this->sahodaya->id.'/certificate-signatures', $disk);
                if ($path === false) {
                    return back()->withErrors(['signatories' => "Could not store the signature image for \"{$label}\" — storage is unavailable. Nothing was saved."]);
                }
            } elseif (! empty($row['remove_signature'])) {
                $path = null;
            }

            $entries[] = [
                'key'            => $key,
                'label'          => $label,
                'name'           => $row['name'] ?? null,
                'designation'    => $row['designation'] ?? null,
                'school'         => $row['school'] ?? null,
                'signature_path' => $path,
            ];
        }

        $event->update(['certificate_signatories' => $entries ?: null]);

        return back()->with('success', 'Signatories saved. Existing certificates refresh the next time they are regenerated.');
    }

    /** @return list<array<string, mixed>> */
    private function signatoriesForUi(FestEvent $event): array
    {
        return collect($event->certificate_signatories ?? [])->map(fn ($s) => [
            'label'          => $s['label'] ?? '',
            'name'           => $s['name'] ?? '',
            'designation'    => $s['designation'] ?? '',
            'school'         => $s['school'] ?? '',
            'signature_path' => $s['signature_path'] ?? null,
            'signature_url'  => ! empty($s['signature_path'])
                ? TenantStorage::logoUrl($this->sahodaya, $s['signature_path'])
                : null,
        ])->values()->all();
    }

    /**
     * Labels already used as signature blocks on this Sahodaya's templates, so the event
     * form can offer them (the label is what ties an entry to a template's block).
     *
     * @return list<string>
     */
    private function signatoryLabelSuggestions(): array
    {
        return CertificateTemplate::query()
            ->where('tenant_id', $this->sahodaya->id)
            ->get(['layout_json'])
            ->flatMap(fn ($t) => collect($t->layout_json['signature_blocks'] ?? [])->pluck('label'))
            ->filter()
            ->push('Venue Convenor')
            ->unique()
            ->values()
            ->all();
    }

    public function downloadZip(Request $request, string $tenantId, FestEvent $event)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $publishedOnly = $request->boolean('published_only');
        $itemId = $request->query('item_id') ? (int) $request->query('item_id') : null;
        $schoolId = $this->schoolIdFrom($request);
        $certType = $request->query('cert_type');
        $certIds = $request->query('certificate_ids')
            ? array_filter(array_map('intval', explode(',', (string) $request->query('certificate_ids'))))
            : null;
        $groupBy = in_array($request->query('group_by'), ['item', 'school'], true) ? $request->query('group_by') : null;

        $service = app(FestCertificateService::class);
        $payloads = $service->exportPayloadsForEvent(
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

        // Only needed for group_by=item — resolved once for the whole export rather than
        // per certificate, matching groupCertificatesByItem()'s own cost-avoidance.
        $classGroupLabels = $groupBy === 'item' ? FestClassGroupScheme::labels(null, $event->rootEvent()) : [];
        $artsCategoryLabels = $groupBy === 'item' ? config('fest_item_taxonomy.arts_category', []) : [];

        $entryName = function (array $payload) use ($service, $groupBy, $classGroupLabels, $artsCategoryLabels) {
            $name = FestCertificateService::archiveFileName($payload['student']?->name, $payload['certificate']->verification_uuid);
            if ($groupBy) {
                $folder = $service->archiveGroupFolder($payload, $groupBy, $classGroupLabels, $artsCategoryLabels) ?? 'Other';
                $name = FestCertificateService::sanitizeArchiveSegment($folder).'/'.$name;
            }

            return $name;
        };

        // Already-rendered PDFs go straight in; the rest render through the converter
        // with a rolling window of concurrent requests (PdfGenerator::renderEach())
        // instead of one blocking round-trip per certificate — this direct download is
        // what a per-school ZIP uses while a long render run is still occupying the queue.
        $misses = [];
        foreach ($payloads as $payload) {
            $pdf = $service->cachedPdf($payload['certificate'], $plain);
            if ($pdf === null) {
                $misses[$payload['certificate']->id] = $payload;

                continue;
            }
            $zip->addFromString($entryName($payload), $pdf);
        }

        PdfGenerator::renderEach(
            (function () use ($misses, $service, $plain) {
                foreach ($misses as $certificateId => $payload) {
                    yield $certificateId => $service->pdfDocument($payload, $plain);
                }
            })(),
            function ($certificateId, $pdf) use ($zip, $misses, $entryName) {
                if ($pdf instanceof \Throwable) {
                    throw $pdf;
                }
                $zip->addFromString($entryName($misses[$certificateId]), $pdf);
            },
        );

        $zip->close();

        $filename = str($event->title)->slug()
            .($publishedOnly ? '-published-winners' : ($certType ? '-'.$certType : '-certificates'))
            .($groupBy ? '-by-'.$groupBy : '')
            .($request->boolean('plain') ? '-plain' : '').'.zip';

        return response()->download($zipPath, $filename)->deleteFileAfterSend();
    }

    /**
     * Async counterpart to downloadZip() above, for the scopes big enough to blow past the
     * web request/proxy timeout — the whole-event and merit/participation-only dropdown
     * options, the school-filtered ZIP, and ad-hoc bulk selection. The small per-item/
     * per-school download-zip links in the grouped views stay on the synchronous route,
     * since those scopes are naturally small (a handful to a few dozen certificates).
     */
    public function queueZipExport(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $publishedOnly = $request->boolean('published_only');
        $itemId = $request->input('item_id') ? (int) $request->input('item_id') : null;
        $schoolId = $this->schoolIdFrom($request);
        $certType = $request->input('cert_type') ?: null;
        $certIds = $request->input('certificate_ids')
            ? array_values(array_filter(array_map('intval', explode(',', (string) $request->input('certificate_ids')))))
            : null;
        $plain = $request->boolean('plain');
        $groupBy = in_array($request->input('group_by'), ['item', 'school'], true) ? $request->input('group_by') : null;

        $service = app(FestCertificateService::class);
        $certificates = $service->resolveCertificateScope($event, $itemId, $schoolId, $certType, $certIds);

        // Mirrors downloadZip()/exportPayloadsForEvent()'s own publishedOnly filter, just
        // up front — so total_count (and the empty-scope 404 below) reflect the same set
        // the job will actually export, not the pre-filter winner count.
        if ($publishedOnly) {
            $payloads = $service->payloadsFor($certificates);
            $certificates = $service->publishedOnlyWinners($certificates, $payloads);
        }

        abort_if($certificates->isEmpty(), 404, $publishedOnly ? 'No published winner certificates to download.' : 'No certificates to download.');

        $this->deleteSupersededBatches($event, 'zip_export', $certType, $itemId, $schoolId, $certIds, $publishedOnly, $groupBy, $plain);

        $batchRow = CertificateBatch::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $event->id,
            'batch_type' => 'zip_export',
            'cert_type' => $certType,
            'published_only' => $publishedOnly,
            'group_by' => $groupBy,
            'plain' => $plain,
            'item_id' => $itemId,
            'school_id' => $schoolId,
            'certificate_ids_json' => $certIds,
            'scope_description' => $this->describeScope($event, $itemId, $schoolId, $certType, $certIds, $publishedOnly).($groupBy ? ' (grouped by '.$groupBy.')' : ''),
            'total_count' => $certificates->count(),
            'status' => CertificateBatch::STATUS_PROCESSING,
            'created_by_user_id' => $request->user()?->id,
            'started_at' => now(),
        ]);

        $resultFilename = str($event->title)->slug()
            .($publishedOnly ? '-published-winners' : ($certType ? '-'.$certType : '-certificates'))
            .($groupBy ? '-by-'.$groupBy : '')
            .($plain ? '-plain' : '').'.zip';

        // Bus::chain(), not Bus::batch() — chunks must append to the same on-disk ZIP
        // sequentially (see BuildCertificateZipChunkJob's docblock), never concurrently.
        $sahodayaId = $this->sahodaya->id;
        $eventId = $event->id;
        $chunks = $certificates->pluck('id')->chunk(40)->values();
        $jobs = $chunks->map(fn ($chunk, $index) => new BuildCertificateZipChunkJob(
            $batchRow->id,
            $sahodayaId,
            $eventId,
            $chunk->values()->all(),
            $index === $chunks->count() - 1,
            $plain,
            $resultFilename,
            $groupBy,
        ))->all();

        Bus::chain($jobs)
            ->catch(function (\Throwable $e) use ($batchRow) {
                $batchRow->update([
                    'status' => CertificateBatch::STATUS_FAILED,
                    'error' => mb_substr($e->getMessage(), 0, 2000),
                    'completed_at' => now(),
                ]);
            })
            ->dispatch();

        return back()
            ->with('success', "Preparing a ZIP of {$certificates->count()} certificate(s) in the background.")
            ->with('certificate_batch_id', $batchRow->id);
    }

    public function downloadZipResult(string $tenantId, FestEvent $event, CertificateBatch $batch)
    {
        abort_if($batch->tenant_id !== $this->sahodaya->id || $batch->event_id !== $event->id, 403);
        abort_if($batch->batch_type !== 'zip_export', 404);
        abort_if(
            ! in_array($batch->status, [CertificateBatch::STATUS_COMPLETED, CertificateBatch::STATUS_COMPLETED_WITH_ERRORS], true)
                || ! $batch->file_path,
            404,
            'This export is not ready yet.'
        );

        return TenantStorage::downloadPrivate($batch->file_path, $batch->storage_disk, $batch->result_filename);
    }

    public function printAll(Request $request, string $tenantId, FestEvent $event)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $publishedOnly = $request->boolean('published_only');
        $itemId = $request->query('item_id') ? (int) $request->query('item_id') : null;
        $schoolId = $this->schoolIdFrom($request);
        $certType = $request->query('cert_type');
        $certIds = $request->query('certificate_ids')
            ? array_filter(array_map('intval', explode(',', (string) $request->query('certificate_ids'))))
            : null;

        $payloads = app(FestCertificateService::class)->exportPayloadsForEvent(
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

    public function generateAndRenderBatch(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->input('item_id') ? (int) $request->input('item_id') : null;
        $schoolId = $this->schoolIdFrom($request);
        $certType = $request->input('cert_type') ?: null;
        $certIds = $request->input('certificate_ids')
            ? array_values(array_filter(array_map('intval', explode(',', (string) $request->input('certificate_ids')))))
            : null;

        $certificates = app(FestCertificateService::class)->resolveCertificateScope($event, $itemId, $schoolId, $certType, $certIds);

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
     * Creates the tracking row, chunks the certificate set into ~30-id slices (see
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
        ?string $schoolId,
        ?string $certType,
        ?array $certIds,
    ): CertificateBatch {
        $this->deleteSupersededBatches($event, $batchType, $certType, $itemId, $schoolId, $certIds);

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
        $jobs = $certificates->pluck('id')->chunk(30)
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

    /**
     * Removes any FINISHED batch(es) that match the exact same signature as the one
     * about to be created — re-running "the same kind" of operation (e.g. "Render whole
     * event" a second time, or "Merit winners only (ZIP)" again) replaces its own entry
     * in Recent render & export runs instead of piling up alongside every earlier run of
     * it. Deliberately narrow — matches every scope-defining column, including an exact
     * (order-independent) match on an ad-hoc certificate_ids_json selection — so a
     * genuinely different scope (a different item, a different bulk selection) is never
     * touched. Only CertificateBatch::TERMINAL_STATUSES rows are candidates: a batch
     * that's still STATUS_PROCESSING is left alone, since deleting its row out from
     * under the still-running job would silently abandon it rather than replace it. A
     * superseded zip_export's underlying storage file is deleted too — nothing else
     * references it once the batch row is gone, so leaving it would just orphan it.
     */
    private function deleteSupersededBatches(
        FestEvent $event,
        string $batchType,
        ?string $certType,
        ?int $itemId,
        ?string $schoolId,
        ?array $certIds,
        bool $publishedOnly = false,
        ?string $groupBy = null,
        bool $plain = false,
    ): void {
        $candidates = CertificateBatch::where('event_id', $event->id)
            ->where('batch_type', $batchType)
            ->where('cert_type', $certType)
            ->where('published_only', $publishedOnly)
            ->where('group_by', $groupBy)
            ->where('plain', $plain)
            ->where('item_id', $itemId)
            ->where('school_id', $schoolId)
            ->whereIn('status', CertificateBatch::TERMINAL_STATUSES)
            ->get();

        $normalizedCertIds = $certIds ? collect($certIds)->sort()->values()->all() : null;

        foreach ($candidates as $candidate) {
            $candidateCertIds = $candidate->certificate_ids_json
                ? collect($candidate->certificate_ids_json)->sort()->values()->all()
                : null;

            if ($candidateCertIds !== $normalizedCertIds) {
                continue;
            }

            if ($candidate->batch_type === 'zip_export' && $candidate->file_path) {
                try {
                    Storage::disk($candidate->storage_disk ?? TenantStorage::uploadDisk())->delete($candidate->file_path);
                } catch (\Throwable) {
                    // Best-effort cleanup — an orphaned storage object isn't worth
                    // failing the new run over.
                }
            }

            $candidate->delete();
        }
    }

    /**
     * School ids are tenant UUIDs. These used to be read with an (int) cast, which turned
     * "6b240f41-…" into 6 — and MySQL's string→number comparison then matched every school
     * whose id starts with "6" — while a letter-first id became 0, silently dropping the
     * filter so a single school's print/ZIP/render ran over the whole event.
     */
    private function schoolIdFrom(Request $request): ?string
    {
        $schoolId = trim((string) $request->input('school_id', ''));

        return $schoolId !== '' ? $schoolId : null;
    }

    private function describeScope(FestEvent $event, ?int $itemId, ?string $schoolId, ?string $certType, ?array $certIds, bool $publishedOnly = false): string
    {
        if (! empty($certIds)) {
            $description = count($certIds).' selected certificate(s)';
        } elseif ($itemId) {
            $item = FestEventItem::find($itemId);
            $description = 'Item: '.($item?->title ?? "#{$itemId}");
        } elseif ($schoolId) {
            $school = Tenant::find($schoolId);
            $description = 'School: '.($school?->name ?? $schoolId);
        } elseif ($certType) {
            $description = ucfirst($certType).' certificates — whole event';
        } else {
            $description = 'Whole event';
        }

        // published_only isn't its own cert_type — it filters winner certs down to
        // publish-visible ones (see publishedOnlyWinners()) — so without this suffix,
        // "Merit winners only (ZIP)" and "All certificates (ZIP)" produced the identical
        // "Whole event" description, indistinguishable in the Recent runs list and (more
        // importantly) in dispatchRenderBatch()/queueZipExport()'s own superseded-batch
        // lookup, which would otherwise treat the two as the same run and delete one for
        // the other.
        return $publishedOnly ? $description.' (published winners only)' : $description;
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
        [$pageWidthMm, $pageHeightMm] = FestCertificateService::customPageDimensionsMm($context['overlayLayout'] ?? []);
        $html = view('fest.certificate-print', $context)->render();

        return PdfGenerator::download($html, 'certificate-preview.pdf', true, $isLandscape, pageWidthMm: $pageWidthMm, pageHeightMm: $pageHeightMm);
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
