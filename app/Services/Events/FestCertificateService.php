<?php

namespace App\Services\Events;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestEventStaff;
use App\Models\FestParticipant;
use App\Models\FestVolunteer;
use App\Models\FestRecordBreak;
use App\Models\Tenant;
use App\Support\FestClassGroupScheme;
use App\Support\PdfGenerator;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
use App\Support\TenantStorage;
use Illuminate\Support\Str;

class FestCertificateService
{
    /** @return list<Certificate> */
    public function generateForEvent(FestEvent $event, ?int $itemId = null): array
    {
        if ($event->usesPhasedRegionalBilling()) {
            abort_if(! $event->parent_event_id || ! $event->source_phase_id, 422, 'Generate certificates from a published operational phase/region event.');
            if (! $event->results_published && ! $itemId) {
                $hasPublishedItems = FestEventItem::whereIn('event_id', $event->reportableEventIds())
                    ->whereNotNull('results_published_at')
                    ->exists();
                abort_unless($hasPublishedItems, 422, 'Publish item results or release phase/region results before generating certificates.');
            }
        }

        $created = [];

        $marks = FestMark::whereIn('event_id', $event->reportableEventIds())
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
            ->whereNotNull('position')
            ->where('position', '<=', 3)
            ->with(['participant.student', 'participant.registration.item'])
            ->get();

        foreach ($marks as $mark) {
            $participant = $mark->participant;
            if (! $participant || $participant->disqualified_at || $participant->participant_role === 'standby') {
                continue;
            }

            $template = $this->resolveTemplate($event, $participant->registration?->item?->id, 'winner');

            $cert = Certificate::firstOrCreate(
                [
                    'entity_type' => FestParticipant::class,
                    'entity_id'   => $participant->id,
                    'cert_type'   => 'winner',
                ],
                [
                    'template_id'       => $template?->id,
                    'verification_uuid' => (string) Str::uuid(),
                    'generated_at'      => now(),
                ]
            );

            if ($template && $cert->template_id !== $template->id) {
                $cert->update(['template_id' => $template->id]);
            }

            $created[] = $cert;
        }

        return $created;
    }

    /**
     * One certificate per person for the whole event, not per item — a person who enters
     * several items gets a single aggregated certificate (see resolveFieldValues(), which
     * lists every item on it). entity_id anchors to whichever of that person's
     * FestParticipant rows has the lowest id; the item list itself is computed live at
     * render time, never stored on the Certificate row.
     *
     * @return list<Certificate>
     */
    public function generateParticipationForEvent(FestEvent $event): array
    {
        if ($event->usesPhasedRegionalBilling()) {
            abort_if(! $event->parent_event_id || ! $event->source_phase_id, 422, 'Generate certificates from a published operational phase/region event.');
            abort_unless($event->results_published, 422, 'Publish this phase/region before generating certificates.');
        }

        $created = [];

        // itemId is deliberately null: an aggregate cert must resolve the event-level
        // (or tenant-wide) participation template, never one item's narrow one.
        $template = $this->resolveTemplate($event, null, 'participation');

        foreach ($this->participationGroupsForEvent($event) as $group) {
            $anchor = $group->sortBy('id')->first();

            $cert = Certificate::firstOrCreate(
                [
                    'entity_type' => FestParticipant::class,
                    'entity_id'   => $anchor->id,
                    'cert_type'   => 'participation',
                ],
                [
                    'template_id'        => $template?->id,
                    'verification_uuid' => (string) Str::uuid(),
                    'generated_at'      => now(),
                ]
            );

            $created[] = $cert;
        }

        return $created;
    }

    /**
     * Base eligible-for-participation-certificate query, shared by
     * generateParticipationForEvent(), certificateTally(), and resolveFieldValues()'s
     * per-person item list — so all three can never drift out of sync on what counts as
     * "eligible" (approved registration, not disqualified, not standby, not marked absent
     * for their item). A FestParticipant row is already scoped to one item (via its
     * registration), and fest_attendance has a unique [item_id, participant_id] — so
     * excluding by participant id here correctly drops just the absent item for someone
     * who attended some of their items and missed others, not their whole certificate;
     * participationGroupsForEvent() only stops generating one for a person at all once
     * every one of their FestParticipant rows is excluded (i.e. absent everywhere).
     * Attendance is opt-in per item (marked via the mark-entry screen or the dedicated
     * attendance page, both writing the same table) — someone with no attendance record
     * at all is treated as present, not excluded.
     */
    private function eligibleParticipantsForEvent(FestEvent $event): \Illuminate\Support\Collection
    {
        $absentParticipantIds = FestAttendance::query()
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('status', 'absent')
            ->pluck('participant_id');

        return FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('status', 'approved'))
            ->whereNull('disqualified_at')
            ->where('participant_role', '!=', 'standby')
            ->whereNotIn('id', $absentParticipantIds)
            ->with(['registration.item', 'group'])
            ->get();
    }

    /**
     * Eligible participants grouped by person (student or teacher) — the unit a single
     * aggregated participation certificate is issued to. One FestParticipant row is
     * scoped to a single item (via FestRegistration), but one person can enter several.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, FestParticipant>>
     */
    private function participationGroupsForEvent(FestEvent $event): \Illuminate\Support\Collection
    {
        return $this->eligibleParticipantsForEvent($event)
            ->groupBy(fn (FestParticipant $p) => $p->student_id ? 'student:'.$p->student_id : 'teacher:'.$p->teacher_id);
    }

    /**
     * The real (not canned-dummy) participant whose certificate is most likely to expose
     * an overflowing field — used by FestCertificateController's preview-before-batch
     * actions so an admin can sanity-check actual layout before committing to a full
     * render run. For a participation certificate that's the person with the most
     * distinct items, since item_title is an unbounded comma-joined list of every item
     * they entered (see resolveFieldValues()/participationItems()); for a winner
     * certificate — always a single item — it's simply the longest recipient name.
     */
    public function worstCaseParticipantForPreview(FestEvent $event, string $certType, ?int $itemId = null): ?FestParticipant
    {
        if ($certType === 'participation') {
            $groups = $this->participationGroupsForEvent($event);
            if ($groups->isEmpty()) {
                return null;
            }

            $widestGroup = $groups->sortByDesc(
                fn (\Illuminate\Support\Collection $group) => $group->pluck('registration.item_id')->filter()->unique()->count()
            )->first();

            return $widestGroup->sortBy('id')->first();
        }

        $participants = $this->eligibleParticipantsForEvent($event)
            ->when($itemId, fn (\Illuminate\Support\Collection $c) => $c->filter(
                fn (FestParticipant $p) => $p->registration?->item_id === $itemId
            ));

        if ($participants->isEmpty()) {
            return null;
        }

        return $participants->sortByDesc(fn (FestParticipant $p) => mb_strlen($p->student?->name ?? ''))->first();
    }

    /**
     * Project how many winner and participation certificates an event needs, per item,
     * without generating anything — for checking print-shop quantities before or after
     * actually running generateForEvent()/generateParticipationForEvent(). Mirrors those
     * two methods' exact eligibility rules (same position<=3 cutoff, same disqualified/
     * standby exclusions) so the count this returns always matches what generation would
     * actually produce.
     *
     * Team/group items count certificates per individual member, not per team — a squad
     * win writes the same position to every member's own FestMark row (see
     * FestMarkEntryController::expandToTeam()), so counting FestMark/FestParticipant rows
     * directly already yields the right per-member number with no special-casing here.
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function certificateTally(FestEvent $event): array
    {
        $eventIds = $event->reportableEventIds();

        $winnerMarks = FestMark::whereIn('event_id', $eventIds)
            ->whereNotNull('position')
            ->where('position', '<=', 3)
            ->with(['participant.registration.item', 'participant.group'])
            ->get()
            ->filter(fn (FestMark $mark) => $mark->participant
                && ! $mark->participant->disqualified_at
                && $mark->participant->participant_role !== 'standby'
                && $mark->participant->registration?->item);

        $participants = $this->eligibleParticipantsForEvent($event)
            ->filter(fn (FestParticipant $p) => $p->registration?->item);

        // Participation certificates are now issued once per person for the whole event
        // (see generateParticipationForEvent()), not once per item — so the grand total
        // below must count distinct people, not sum the per-item entry counts.
        $participationCertificateCount = $this->participationGroupsForEvent($event)->count();

        // Standbys never get a certificate (see the filters above) but a team's admin
        // still wants to see how many are sitting in reserve — tracked separately so it
        // never leaks into member/participation-cert counts.
        $standbys = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $eventIds)
            ->where('status', 'approved'))
            ->whereNull('disqualified_at')
            ->where('participant_role', 'standby')
            ->with('registration.item')
            ->get()
            ->filter(fn (FestParticipant $p) => $p->registration?->item);

        /** @var array<int, array{item: \App\Models\FestEventItem, winners: array, participants: array, standbys: array}> $byItem */
        $byItem = [];

        foreach ($winnerMarks as $mark) {
            $item = $mark->participant->registration->item;
            $byItem[$item->id]['item'] ??= $item;
            $byItem[$item->id]['winners'][] = $mark->participant;
        }

        foreach ($participants as $participant) {
            $item = $participant->registration->item;
            $byItem[$item->id]['item'] ??= $item;
            $byItem[$item->id]['participants'][] = $participant;
        }

        foreach ($standbys as $standby) {
            $item = $standby->registration->item;
            $byItem[$item->id]['item'] ??= $item;
            $byItem[$item->id]['standbys'][] = $standby;
        }

        $rows = [];
        foreach ($byItem as $itemId => $data) {
            $item = $data['item'];
            $winners = $data['winners'] ?? [];
            $entrants = $data['participants'] ?? [];
            $isTeam = $item->isTeamItem();

            $rows[] = [
                'item_id'             => $itemId,
                'title'               => $item->title,
                'head_name'           => $item->head?->name,
                'category'            => $item->age_group ?: $item->class_group,
                'is_team'             => $isTeam,
                'entry_count'         => $isTeam
                    ? collect($entrants)->pluck('group_id')->unique()->count()
                    : count($entrants),
                'member_count'        => count($entrants),
                'standby_count'       => $isTeam ? count($data['standbys'] ?? []) : 0,
                'winner_certs'        => count($winners),
                // Entries for this item, not certificates — one person's participation
                // certificate can cover several items, see totals.participation_certs.
                'participation_certs' => count($entrants),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['title'], $b['title']));

        $totals = [
            'items'               => count($rows),
            'winner_certs'        => array_sum(array_column($rows, 'winner_certs')),
            'participation_certs' => $participationCertificateCount,
        ];
        $totals['grand_total'] = $totals['winner_certs'] + $totals['participation_certs'];

        return ['rows' => $rows, 'totals' => $totals];
    }

    public function issueRecordBreakCertificate(FestRecordBreak $break): Certificate
    {
        $template = $break->event
            ? $this->resolveTemplate($break->event, $break->item_id, 'record_break')
            : null;

        return Certificate::firstOrCreate(
            [
                'entity_type' => FestRecordBreak::class,
                'entity_id'   => $break->id,
                'cert_type'   => 'record_break',
            ],
            [
                'template_id'        => $template?->id,
                'verification_uuid' => (string) Str::uuid(),
                'generated_at'      => now(),
            ]
        );
    }


    public function issueVolunteerCertificate(FestVolunteer $volunteer): Certificate
    {
        $event = $volunteer->event ?? FestEvent::find($volunteer->event_id);
        $template = $event ? $this->resolveTemplate($event, null, 'volunteer') : null;

        return Certificate::firstOrCreate(
            ['entity_type' => FestVolunteer::class, 'entity_id' => $volunteer->id, 'cert_type' => 'volunteer'],
            ['template_id' => $template?->id, 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now()]
        );
    }

    public function issueStaffCertificate(FestEventStaff $staff): Certificate
    {
        $event = $staff->event ?? FestEvent::find($staff->event_id);
        $template = $event ? $this->resolveTemplate($event, null, 'organizer') : null;

        return Certificate::firstOrCreate(
            ['entity_type' => FestEventStaff::class, 'entity_id' => $staff->id, 'cert_type' => 'organizer'],
            ['template_id' => $template?->id, 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now()]
        );
    }

    /**
     * Resolve the most specific active certificate template for a fest event/item/type.
     * Cascade: item-specific -> event-specific -> tenant-wide "fest" default.
     * Falls back to the 'participation' certificate_type at each level if the exact
     * type has no template configured.
     *
     * The event and its parent are deliberately tried as two separate, ordered passes
     * (this event's item-specific, then this event's event-wide, then the parent's
     * item-specific, then the parent's event-wide) rather than one `whereIn('event_id',
     * [...])` query relying on ->latest() to sort them out — a `whereIn` query has no
     * way to prefer "belongs to this exact event" over "belongs to the parent event"
     * when both exist at the same specificity tier; it would only ever prefer whichever
     * happened to be created more recently, which silently picked the parent region's
     * template over a region's own more specific one once both existed.
     */
    public function resolveTemplate(FestEvent $event, ?int $itemId, string $certType): ?CertificateTemplate
    {
        $tenantId = $event->tenant_id;

        foreach (array_filter([$event->id, $event->parent_event_id]) as $eventId) {
            if ($itemId) {
                $template = $this->templateQuery($tenantId, $certType, $event)
                    ->where('event_id', $eventId)
                    ->where('item_id', $itemId)
                    ->first();
                if ($template) {
                    return $template;
                }
            }

            $template = $this->templateQuery($tenantId, $certType, $event)
                ->where('event_id', $eventId)
                ->whereNull('item_id')
                ->first();
            if ($template) {
                return $template;
            }
        }

        $template = $this->templateQuery($tenantId, $certType, $event)
            ->whereNull('event_id')
            ->whereNull('item_id')
            ->first();
        if ($template) {
            return $template;
        }

        if ($certType !== 'participation') {
            return $this->resolveTemplate($event, $itemId, 'participation');
        }

        return null;
    }

    private function templateQuery(string $tenantId, string $certType, ?FestEvent $event = null)
    {
        $eventTypes = array_values(array_filter(array_unique([
            'fest',
            $event?->event_type,
        ])));

        return CertificateTemplate::where('tenant_id', $tenantId)
            ->whereIn('event_type', $eventTypes)
            ->where('certificate_type', $certType)
            ->where('is_active', true)
            ->latest();
    }

    /**
     * Build the template/background/field data needed to render a fest certificate,
     * merged with the existing entity payload from payloadFor().
     *
     * @param  ?array<string, mixed>  $payload  Precomputed payloadFor()/payloadsFor()-shaped
     *                                           data, to avoid a redundant find() query when
     *                                           rendering many certificates in a loop (see
     *                                           FestCertificateController::downloadZip()).
     *                                           Defaults to null, which computes it exactly as
     *                                           before — single-certificate callers unaffected.
     * @param  array<string, ?CertificateTemplate>  $templateCache  Pass the same array by
     *                                           reference across multiple renderContext() calls
     *                                           in a loop so the same event+item+type template
     *                                           isn't re-queried for every certificate. Defaults
     *                                           to a fresh (unshared) array, so single calls
     *                                           behave exactly as before.
     * @param  array<int, \Illuminate\Support\Collection>  $participantsCache  Same idea as
     *                                           $templateCache, keyed by event id, so a bulk
     *                                           render loop (e.g. downloadZip()) doesn't re-query
     *                                           every eligible participant in the event once per
     *                                           participation certificate.
     * @return array<string, mixed>
     */
    /**
     * @param  bool  $embedAssets  When true, logo/seal/background/signature/photo images
     *                             are embedded as self-contained base64 data URIs instead
     *                             of site-relative URLs. Needed for output that's read
     *                             outside the site's own browser origin (e.g. HTML/PDF
     *                             extracted from a downloaded ZIP) — a plain `/storage/...`
     *                             URL only resolves while viewed on-site. Left off by
     *                             default since the normal print/verify pages are always
     *                             viewed on-site, where a lighter URL is cheaper to render.
     * @param  array<string, ?string>  $assetCache  Same idea as $templateCache — pass the
     *                             same array by reference across many renderContext() calls
     *                             in a bulk render loop, keyed by "{template_id}:logo" etc.,
     *                             so ten certificates sharing one template don't each
     *                             independently re-read-and-base64-encode the same shared
     *                             logo/seal/background image. Only consulted when
     *                             $embedAssets is true, since the cheap URL path has no
     *                             equivalent repeated work to save.
     */
    public function renderContext(Certificate $certificate, ?array $payload = null, array &$templateCache = [], array &$participantsCache = [], bool $embedAssets = false, array &$assetCache = []): array
    {
        $payload ??= $this->payloadFor($certificate);

        /** @var ?FestEvent $event */
        $event = $payload['event'] ?? null;

        // An aggregated participation certificate must resolve the event-level (or
        // tenant-wide) template, never the narrow template of whichever single item its
        // anchor FestParticipant row happens to belong to.
        $itemId = $certificate->cert_type === 'participation' ? null : ($payload['item']?->id ?? null);

        $sahodaya = $event ? Tenant::find($event->tenant_id) : null;

        $templateCacheKey = $certificate->template_id
            ? 'id:'.$certificate->template_id
            : ($event ? $event->id.':'.($itemId ?? '0').':'.$certificate->cert_type : null);

        if ($templateCacheKey !== null && array_key_exists($templateCacheKey, $templateCache)) {
            $template = $templateCache[$templateCacheKey];
        } else {
            $template = $event ? $this->resolveTemplate($event, $itemId, $certificate->cert_type) : null;
            $template ??= $certificate->template_id
                ? CertificateTemplate::where('tenant_id', $sahodaya?->id)->find($certificate->template_id)
                : null;

            if ($templateCacheKey !== null) {
                $templateCache[$templateCacheKey] = $template;
            }
        }

        // Small header/footer assets (logo, seal, signatures) are never shown above a
        // few dozen px, so a modest cap keeps the export light even at retina density.
        // The background spans the full certificate canvas (1123×794 CSS px), so it
        // gets a much larger cap to stay print-quality.
        if ($embedAssets) {
            $assetCacheKey = 'tpl:'.($template?->id ?? 'none').':'.($sahodaya?->id ?? 'none');

            $logoUrl = $this->cachedAssetDataUri($assetCache, $assetCacheKey.':logo', fn () => $template?->logo_path && $sahodaya
                ? TenantStorage::photoBase64DataUri($sahodaya, $template->logo_path, 400)
                : ($sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null));

            $sealUrl = $this->cachedAssetDataUri($assetCache, $assetCacheKey.':seal', fn () => $template?->seal_path && $sahodaya
                ? TenantStorage::photoBase64DataUri($sahodaya, $template->seal_path, 400)
                : null);

            $backgroundUrl = $this->cachedAssetDataUri($assetCache, $assetCacheKey.':background', fn () => $template?->background_path && $sahodaya
                ? TenantStorage::photoBase64DataUri($sahodaya, $template->background_path, 1600)
                : null);
        } else {
            $logoUrl = $template?->logo_path && $sahodaya
                ? TenantStorage::logoUrl($sahodaya, $template->logo_path)
                : ($sahodaya ? TenantBranding::logoUrl($sahodaya) : null);

            $sealUrl = $template?->seal_path && $sahodaya
                ? TenantStorage::logoUrl($sahodaya, $template->seal_path)
                : null;

            $backgroundUrl = $template?->background_path && $sahodaya
                ? TenantStorage::logoUrl($sahodaya, $template->background_path)
                : null;
        }

        $overlayLayout = $template?->overlayLayout() ?? CertificateTemplate::defaultBackgroundLayout();

        $participant = $payload['participant'] ?? null;
        $photoUrl = ($overlayLayout['show_photo'] ?? false) && $participant
            ? $this->participantPhotoUrl($participant, $embedAssets)
            : null;

        $signatories = collect($template?->signatories ?? CertificateTemplate::defaultTrainingSignatories())
            ->map(fn ($s) => [
                'name'          => $s['name'] ?? '',
                'designation'   => $s['designation'] ?? '',
                'signature_url' => (! empty($s['signature_path']) && $sahodaya)
                    ? ($embedAssets
                        ? TenantStorage::photoBase64DataUri($sahodaya, $s['signature_path'], 400)
                        : TenantStorage::logoUrl($sahodaya, $s['signature_path']))
                    : null,
            ])->values()->all();

        $eventParticipants = null;
        if ($certificate->cert_type === 'participation' && $event) {
            if (! array_key_exists($event->id, $participantsCache)) {
                $participantsCache[$event->id] = $this->eligibleParticipantsForEvent($event);
            }
            $eventParticipants = $participantsCache[$event->id];
        }

        $fieldValues = $this->resolveFieldValues($payload, $sahodaya, $certificate->cert_type, $eventParticipants);

        return array_merge($payload, [
            'sahodaya'      => $sahodaya,
            'template'      => $template,
            'fieldValues'   => $fieldValues,
            'logoUrl'       => $logoUrl,
            'sealUrl'       => $sealUrl,
            'backgroundUrl' => $backgroundUrl,
            'photoUrl'      => $photoUrl,
            'overlayLayout' => $overlayLayout,
            'signatories'   => $signatories,
        ]);
    }

    /**
     * Serves RenderCertificateChunkJob's cached PDF when one exists and is fresh,
     * otherwise renders on the spot — the exact cache-check duplicated across
     * FestCertificateController::downloadZip() and
     * SchoolAdmin\FestEventPortalController::downloadCertificatesZip() before this
     * extraction. $buildContext is only invoked on a cache miss — building a full
     * embedAssets renderContext() is the expensive part callers were already avoiding
     * on cache hits, so this keeps that optimization rather than forcing it eagerly.
     *
     * @param  \Closure(): array  $buildContext  Returns a renderContext()-shaped array
     *                                            (embedAssets: true, qr_src set) —
     *                                            same shape RenderCertificateChunkJob
     *                                            used to produce the cached file, so a
     *                                            cache-miss render matches a cache-hit
     *                                            one.
     */
    public function cachedOrFreshPdf(Certificate $certificate, \Closure $buildContext, bool $plain = false): string
    {
        $cachedPath = $plain ? $certificate->plain_file_path : $certificate->file_path;

        if ($cachedPath && ! $certificate->is_stale && TenantStorage::exists($cachedPath, $certificate->storage_disk)) {
            return TenantStorage::get($cachedPath, $certificate->storage_disk);
        }

        $context = $buildContext();
        // Defaulting to landscape (PdfGenerator::render()'s own default) silently
        // mis-renders any portrait template on a cache miss — RenderCertificateChunkJob
        // always derives this from the template instead of relying on the default.
        $isLandscape = ($context['overlayLayout']['orientation'] ?? 'landscape') !== 'portrait';
        $html = view('fest.certificate-print', array_merge($context, $plain ? ['plainMode' => true] : []))->render();

        return PdfGenerator::render($html, $isLandscape);
    }

    /** @param  array<string, ?string>  $cache */
    private function cachedAssetDataUri(array &$cache, string $key, \Closure $resolve): ?string
    {
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = $resolve();
        }

        return $cache[$key];
    }

    /**
     * Fingerprint of what renderContext() actually resolved for this certificate — used
     * by RenderCertificateChunkJob (written at render time) and
     * VerifyCertificateStalenessCommand (recomputed later for comparison) to detect when
     * a certificate's rendered output would now differ from what's cached, without having
     * to enumerate every upstream row (participant, mark, registration, template) that
     * could have changed — any real change to name/school/items/template necessarily
     * changes this hash, since it's the same resolved fieldValues being hashed, not a
     * proxy for them. certificate_date is included now that it's stable (event_end/
     * event_start, or an explicit override) rather than always now()-derived, so setting
     * or changing it correctly flags already-cached certificates stale — the one
     * remaining edge case is an event with no dates configured at all (still falls back
     * to now() in resolveFieldValues()), which stays permanently stale until it has one.
     *
     * @param  array<string, mixed>  $context  A renderContext() return value.
     */
    public function contentHash(array $context): string
    {
        $fieldValues = $context['fieldValues'] ?? [];

        $template = $context['template'] ?? null;

        return hash('sha256', json_encode([
            'template_id'          => $template?->id,
            'template_updated_at'  => $template?->updated_at?->toISOString(),
            'fieldValues'          => $fieldValues,
        ]));
    }

    /**
     * Reuses FestIdCardService's existing photo-resolution chain (public-disk URL, then
     * a self-contained data URI, then a gender-appropriate placeholder avatar — never
     * the auth-gated school-admin photo route, since certificates are public pages)
     * instead of duplicating that logic here.
     */
    private function participantPhotoUrl(FestParticipant $participant, bool $includeDataUris = false): string
    {
        $rawGender = strtolower((string) ($participant->student?->gender ?? $participant->teacher?->gender ?? ''));
        $gender = match (true) {
            str_starts_with($rawGender, 'f') || $rawGender === 'girl' || $rawGender === 'female' => 'female',
            str_starts_with($rawGender, 'm') || $rawGender === 'boy' || $rawGender === 'male' => 'male',
            default => 'neutral',
        };

        return app(\App\Services\Events\FestIdCardService::class)
            ->resolveParticipantPhotoSrc($participant, $gender, includeDataUris: $includeDataUris);
    }

    /** @return array<string, string> */
    private function resolveFieldValues(array $payload, ?Tenant $sahodaya, string $certType, ?\Illuminate\Support\Collection $eventParticipants = null): array
    {
        $event = $payload['event'] ?? null;
        $item = $payload['item'] ?? null;
        $student = $payload['student'] ?? null;
        $recordBreak = $payload['recordBreak'] ?? null;
        $mark = $payload['mark'] ?? null;
        $participant = $payload['participant'] ?? null;

        $recipientName = $recordBreak?->participant?->student?->name
            ?? $student?->name
            ?? '';

        $schoolName = $recordBreak?->participant?->registration?->school?->name
            ?? $payload['participant']?->registration?->school?->name
            ?? '';

        $positionLabel = match ($mark?->position) {
            1 => 'First Prize',
            2 => 'Second Prize',
            3 => 'Third Prize',
            default => null,
        };
        $winnerGrade = ($certType === 'winner' && $event && $item) ? $this->effectiveGrade($mark, $event, $item->id) : $mark?->grade;
        $gradeSuffix = ($certType === 'winner' && $winnerGrade) ? ' with '.$winnerGrade.' Grade' : '';

        $achievementLine = match (true) {
            $recordBreak !== null => 'set a new record',
            $certType === 'winner' && $positionLabel !== null => 'secured '.$positionLabel.$gradeSuffix,
            $certType === 'volunteer' => 'served as a volunteer',
            $certType === 'organizer' => 'served as an organizer',
            default => 'participated',
        };

        $eventDates = $event
            ? trim(collect([
                $event->event_start?->format('d M Y'),
                $event->event_end && $event->event_end->ne($event->event_start) ? $event->event_end->format('d M Y') : null,
            ])->filter()->implode(' - '))
            : '';

        // certificate_date: an explicit per-event override (FestEventSettingsController's
        // certificate tab) takes priority, then the event's own end/start date, then
        // now() as a last resort for an event with no dates configured at all. Was
        // unconditionally now() before — every re-render silently back-dated already-
        // issued certificates to whatever day an admin happened to regenerate them on.
        $certDate = $event?->certificate_date ?? $event?->event_end ?? $event?->event_start ?? now();

        // Participation certificates are aggregated per person (see
        // generateParticipationForEvent()) — these become every item this person took
        // part in, not just the one their anchor FestParticipant row belongs to. Winner
        // (and every other) cert type is always exactly the payload's single item.
        $items = ($certType === 'participation' && $participant && $event)
            ? $this->participationItems($participant, $event, $eventParticipants)
            : collect($item ? [$item] : []);

        $itemTitle = $this->humanJoin($items->pluck('title')->all());

        // Category/type are deduped across items rather than repeated per item — a
        // student whose items are all "Category 1" sees it once, not three times.
        $taxonomies = $event ? $items->map(fn (FestEventItem $i) => $this->itemTaxonomyLabels($i, $event)) : collect();
        $categoryName = $this->humanJoin($taxonomies->pluck('category')->unique()->values()->all());
        $participationType = $this->humanJoin($taxonomies->pluck('type')->unique()->values()->all());

        // Same "aggregate across the person's full participant group" need as $items
        // above — a participation certificate's own payload only carries the single
        // anchor FestParticipant/FestMark (see generateParticipationForEvent()), which
        // would silently miss a grade recorded against any of the person's OTHER items.
        // Not every item a person enters gets graded, hence "if the user has grade" —
        // this only ever holds entries for items that actually have one.
        $itemGrades = ($certType === 'participation' && $participant && $event)
            ? $this->participationGradesByItem($participant, $event, $eventParticipants)
            : (($item && $event) ? collect(array_filter([$item->id => $this->effectiveGrade($mark, $event, $item->id)])) : collect());

        // Same gender-normalization pattern as participantPhotoUrl() above, applied to a
        // Master/Miss honorific instead of an avatar choice. Falls back to the
        // non-committal "Master/Miss" (rather than guessing) for teachers, an unset
        // gender, or 'other' — student.gender is a nullable enum, not guaranteed present.
        $rawGender = strtolower((string) ($participant?->student?->gender ?? ''));
        $salutation = match (true) {
            str_starts_with($rawGender, 'f') => 'Miss',
            str_starts_with($rawGender, 'm') => 'Master',
            default => 'Master/Miss',
        };

        return [
            'salutation'          => $salutation,
            'recipient_name'      => $recipientName,
            'school_name'         => $schoolName,
            'event_title'         => $event?->title ?? '',
            // Alias of event_title — some templates reference {event_name} instead.
            'event_name'          => $event?->title ?? '',
            // Falls back to event_title when no venue is configured (resolvedVenueName()
            // already handles the region-vs-hub venue-assignment split — see its own
            // docblock) — a template using {venue} shouldn't render an empty gap just
            // because this particular event never had one set.
            'venue'               => $event?->resolvedVenueName() ?: ($event?->title ?? ''),
            'item_title'          => $itemTitle,
            // Kept as an alias of item_title for templates already using {item_details}
            // as the bold item-name line — category/type are now their own placeholders.
            'item_details'        => $itemTitle,
            // Raw (unjoined) titles behind item_title/item_details — never substituted as
            // its own {token}, only read by certificate-body.blade.php to give the
            // client-side fit-text script something to truncate ("first 3 and N more")
            // when a multi-item participation certificate's joined sentence overflows.
            'item_titles'         => $items->pluck('title')->all(),
            'category_name'       => $categoryName,
            'participation_type'  => $participationType,
            'event_dates'         => $eventDates,
            'achievement_line'    => $achievementLine,
            // Distinct grade(s) across every item that actually has one — empty when
            // nobody graded any of this person's items, one value for a single grade,
            // joined ("A and B") if different items landed different grades.
            'grade'               => $this->humanJoin($itemGrades->unique()->values()->all()),
            // Pre-rendered HTML (see participationItemsBoxHtml()) — a bordered, 2-column
            // "Participated Items" box listing every item this person entered with its
            // category/type (and grade, when that item has one), for templates that want
            // a structured list instead of item_title's single run-on sentence. Empty
            // string for non-participation certs and templates that don't reference the
            // {participation_items_box} token at all.
            'participation_items_box' => $certType === 'participation' ? $this->participationItemsBoxHtml($items, $taxonomies, $itemGrades) : '',
            'sahodaya_name'       => $sahodaya ? strtoupper($sahodaya->name) : '',
            // Ordinal suffix ("25th August 2026") to match the convention already used
            // elsewhere for fest dates (event_dates/conducted_on's sample values are
            // "21st - 23rd July 2026" style) — plain "j F Y" read as inconsistent next to
            // those on the same certificate. The suffix itself is wrapped in <sup> —
            // formal/certificate typography convention — which is why this is real HTML,
            // not plain text (see the certificate_date escaping exemption in
            // certificate-body.blade.php's substitution loop).
            // Plain bold, no color override — {recipient_name}/{school_name}/{venue} in
            // this same sentence are all bold-with-inherited-color (no special treatment
            // of their own), so giving numbers a distinct accent color made them read as
            // a mismatched, separate style rather than consistent emphasis. Bold alone
            // matches how every other emphasized token in the sentence is already styled.
            'certificate_date'    => '<strong>'.$certDate->format('j').'</strong><sup>'.$certDate->format('S').'</sup> '.$certDate->format('F').' <strong>'.$certDate->format('Y').'</strong>',
        ];
    }

    /**
     * Distinct items a person entered for an event, in display order — the shared list
     * behind item_title/item_details' aggregation and category_name/participation_type's
     * deduped taxonomy, so all four build from one query instead of several.
     *
     * @return \Illuminate\Support\Collection<int, FestEventItem>
     */
    private function participationItems(FestParticipant $participant, FestEvent $event, ?\Illuminate\Support\Collection $eventParticipants = null): \Illuminate\Support\Collection
    {
        $eventParticipants ??= $this->eligibleParticipantsForEvent($event);

        return $eventParticipants
            ->filter(fn (FestParticipant $p) => $participant->student_id
                ? $p->student_id === $participant->student_id
                : $p->teacher_id === $participant->teacher_id)
            ->map(fn (FestParticipant $p) => $p->registration?->item)
            ->filter()
            ->unique('id')
            ->sortBy(fn (FestEventItem $i) => $i->display_order ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * item_id => grade map across every item a person entered for this event, limited
     * to items that actually have one — same "aggregate across the person's full
     * participant group" need as participationItems(), since marks (and whether an
     * item was graded at all) are recorded per FestParticipant row, not per person.
     *
     * @return \Illuminate\Support\Collection<int, string> keyed by item_id
     */
    private function participationGradesByItem(FestParticipant $participant, FestEvent $event, ?\Illuminate\Support\Collection $eventParticipants = null): \Illuminate\Support\Collection
    {
        $eventParticipants ??= $this->eligibleParticipantsForEvent($event);

        $group = $eventParticipants->filter(fn (FestParticipant $p) => $participant->student_id
            ? $p->student_id === $participant->student_id
            : $p->teacher_id === $participant->teacher_id);

        // Not pre-filtered to whereNotNull('grade') — a mark can carry a real score/
        // position with grade left blank (see effectiveGrade()'s own docblock), so
        // fetch every mark for the group and let effectiveGrade() decide per row.
        return FestMark::whereIn('participant_id', $group->pluck('id'))
            ->get(['participant_id', 'item_id', 'grade', 'score'])
            ->mapWithKeys(function (FestMark $mark) use ($group, $event) {
                $itemId = $group->firstWhere('id', $mark->participant_id)?->registration?->item_id;
                $grade = $itemId ? $this->effectiveGrade($mark, $event, $itemId) : null;

                return $grade ? [$itemId => $grade] : [];
            });
    }

    /**
     * The grade a results screen would actually show for this mark — FestItemResultsService
     * ::resultRowsForItem() derives it live from score via FestGradePointService
     * ::resolveGradeFromScore() whenever a score is present, falling back to the raw
     * grade column only if that derivation comes back empty. FestMark.grade itself is
     * NOT reliably populated: a mark saved with only a score/position (no explicit
     * grade) can sit with grade='' indefinitely — confirmed against a real production
     * mark (score 168, position 4, grade '') whose results page still correctly showed
     * "Grade A", computed the same way this method now does. Certificates previously
     * read $mark->grade directly, so an item like that showed on the certificate with
     * no grade at all even though every results view for the same mark showed one.
     */
    private function effectiveGrade(?FestMark $mark, FestEvent $event, int $itemId): ?string
    {
        if (! $mark) {
            return null;
        }

        if ($mark->score !== null) {
            return app(FestGradePointService::class)->resolveGradeFromScore($event, $itemId, (float) $mark->score) ?: $mark->grade;
        }

        return $mark->grade;
    }

    /**
     * Human-readable category/type labels for a fest item — certificates show just these
     * two (not gender), each in its own parenthetical: "Item Name (Category 1) (Group)".
     * Same underlying taxonomy as attendance sheets/exports (see
     * FestPublicVisibilityService::formatReportRow(), which independently computes the
     * fuller category/type/gender set; kept as a separate copy here rather than a shared
     * call because that service has unrelated in-flight changes at the time of writing).
     *
     * @return array{category: string, type: string}
     */
    private function itemTaxonomyLabels(?FestEventItem $item, FestEvent $event): array
    {
        $classGroupLabels = FestClassGroupScheme::labels(null, $event);

        $category = match (true) {
            (bool) $item?->class_group && $item->class_group !== 'open' => $classGroupLabels[$item->class_group] ?? strtoupper($item->class_group),
            (bool) $item?->age_group => $item->age_group,
            (bool) $item?->category && $item->category !== 'general' => ucwords(str_replace(['_', '-'], ' ', $item->category)),
            default => 'General Category',
        };
        // Every scheme's label is "Short Name — longer elaboration" (e.g. "Category 1 —
        // Classes 3 & 4"); certificates want just the short part, attendance exports want
        // the full thing, hence trimming here rather than upstream in FestClassGroupScheme.
        $category = trim(explode(' — ', $category)[0]);

        // participant_type has 5 real values, not 3 (see FestTeamSquadRules::ALL_TYPES) —
        // 'pair'/'trio' items were previously falling through to the 'individual' default
        // and showing as "Individual" on the certificate, which is wrong.
        $type = match (strtolower((string) $item?->participant_type)) {
            'group' => 'Group',
            'team' => 'Team',
            'pair' => 'Pair',
            'trio' => 'Trio',
            default => 'Individual',
        };

        return ['category' => $category, 'type' => $type];
    }

    /**
     * A bordered, 2-column "Participated Items" box — bullet, item name, and
     * category/type per entry — for a multi-item participation certificate. Built as an
     * HTML <table> rather than CSS grid/flexbox specifically because DomPDF (the active
     * renderer until PDF_CONVERTER_URL/Chromium is configured, see PdfGenerator) has poor
     * support for modern CSS layout but solid, long-standing table support — the same
     * markup must render correctly on both engines. Every style is inlined rather than
     * relying on a shared stylesheet class, matching how template body HTML is already
     * hand-authored elsewhere in this codebase (fully self-contained, since a template's
     * body is free-text an admin can paste anywhere).
     *
     * @param  \Illuminate\Support\Collection<int, FestEventItem>  $items
     * @param  \Illuminate\Support\Collection<int, array{category: string, type: string}>  $taxonomies  Same order/keys as $items.
     * @param  \Illuminate\Support\Collection<int, string>|null  $gradesByItemId  item_id => grade, only for items that actually have one (see participationGradesByItem()).
     */
    private function participationItemsBoxHtml(\Illuminate\Support\Collection $items, \Illuminate\Support\Collection $taxonomies, ?\Illuminate\Support\Collection $gradesByItemId = null): string
    {
        if ($items->isEmpty()) {
            return '';
        }

        // A person can enter well beyond a handful of items (the event's roster can run
        // to ~140), but the background artwork only reserves a fixed-height zone for this
        // box (see the `bottom` boundary certificate-fit-text-script.blade.php enforces
        // via CertificateTemplate::overlayFieldStyle()) — font-shrinking alone runs out of
        // headroom well before that. Cap what's individually listed and summarize the
        // rest, so the box's own height stays bounded at 4 rows independent of how many
        // items a participant actually has, rather than growing into whatever artwork
        // (e.g. a "Congratulations" graphic) sits below the reserved zone.
        $cap = 7;
        $overflow = $items->count() > $cap + 1 ? $items->count() - $cap : 0;
        $shown = $overflow > 0 ? $items->take($cap) : $items;

        // font-size is em, not px, so this box shrinks along with the shrink-to-fit
        // script's font-size reduction on the parent .overlay-field.body — a fixed px
        // size here would otherwise be immune to that pass entirely (see
        // certificate-fit-text-script.blade.php's fitTextToBox()).
        //
        // Category/type sits inline after the item name (one line per item) rather than
        // stacked on its own line below — halves the box's height for the same item
        // count, which is what makes the larger font size here affordable within the
        // reserved zone.
        $entries = $shown->values()->map(function (FestEventItem $item, int $i) use ($taxonomies, $gradesByItemId) {
            $tax = $taxonomies->get($i, ['category' => '', 'type' => '']);
            $meta = trim(implode('  •  ', array_filter([$tax['category'] ?? '', $tax['type'] ?? ''])));
            // Bold just the digits ("Category 1" -> "Category <strong>1</strong>") —
            // split on digit runs first and escape only the surrounding text parts, so a
            // category/type label containing an apostrophe or similar can't have its
            // escaped HTML entity (e.g. &#039;) corrupted by the same digit-matching
            // regex matching *inside* the entity. Odd indices are the captured digit runs
            // (PREG_SPLIT_DELIM_CAPTURE alternates text, digits, text, digits, ...).
            $metaParts = preg_split('/(\d+)/', $meta, -1, PREG_SPLIT_DELIM_CAPTURE);
            $metaSafe = implode('', array_map(
                fn ($part, $partIndex) => $partIndex % 2 === 1 ? '<strong>'.$part.'</strong>' : e($part),
                $metaParts,
                array_keys($metaParts)
            ));
            // Meta stays a size step below the item name (still larger than the original
            // 0.65em) — keeping both at the same size left long category/type combos (e.g.
            // "General Category • Team") wide enough to wrap the row onto a second line,
            // which pushed the box past its reserved zone for exactly the item counts this
            // is supposed to protect.
            $metaInline = $meta !== '' ? ' <span style="font-size:0.86em;font-weight:400;color:#64748b;">('.$metaSafe.')</span>' : '';

            // Not every item a person enters gets graded — this is absent (no suffix at
            // all) far more often than it's present, hence checking per item rather than
            // assuming one grade covers the whole certificate.
            $grade = $gradesByItemId?->get($item->id);
            $gradeInline = $grade ? ' <span style="font-size:0.86em;font-weight:700;color:#b45309;">— Grade '.e($grade).'</span>' : '';

            return '<span style="display:block;font-size:0.95em;line-height:1.35;color:#172033;">&bull;&nbsp;<strong>'.e($item->title).'</strong>'.$metaInline.$gradeInline.'</span>';
        });

        if ($overflow > 0) {
            $entries->push('<span style="display:block;font-weight:700;font-size:0.95em;color:#b45309;line-height:1.35;">&bull;&nbsp;+ '.$overflow.' more</span>');
        }

        $rows = '';
        foreach ($entries->chunk(2) as $pair) {
            $cells = $pair->map(fn ($html) => '<td style="width:50%;vertical-align:top;padding:2px 6px 2px 0;">'.$html.'</td>')->implode('');
            if ($pair->count() === 1) {
                $cells .= '<td style="width:50%;"></td>';
            }
            $rows .= '<tr>'.$cells.'</tr>';
        }

        // &bull; over the &#10022; star used elsewhere — confirmed via DomPDF preview
        // testing that DomPDF's font set renders &bull; correctly but shows the star as
        // a missing-glyph "?"; Chromium renders both fine, but this box must degrade
        // correctly on the DomPDF fallback too.
        return '<div style="border:1px solid #d6a95c;border-radius:6px;padding:6px 10px;margin:5px auto 0;max-width:98%;background:rgba(180,83,9,0.04);">'
            .'<div style="text-align:center;font-size:0.85em;font-weight:700;letter-spacing:1.5px;color:#b45309;text-transform:uppercase;margin-bottom:5px;">&bull;&nbsp;Participated Items&nbsp;&bull;</div>'
            .'<table style="width:100%;border-collapse:collapse;">'.$rows.'</table>'
            .'</div>';
    }

    /** @param  list<string>  $items */
    private function humanJoin(array $items): string
    {
        if (count($items) === 0) {
            return '';
        }
        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }

    public function payloadFor(Certificate $certificate): array
    {
        if ($certificate->entity_type === FestRecordBreak::class) {
            return $this->recordBreakPayload($certificate);
        }

        $participant = FestParticipant::with(['student', 'registration.item', 'registration.event'])
            ->find($certificate->entity_id);

        return [
            'certificate' => $certificate,
            'participant' => $participant,
            'student'     => $participant?->student,
            'event'       => $participant?->registration?->event,
            'item'        => $participant?->registration?->item,
            'mark'        => $participant
                ? FestMark::where('participant_id', $participant->id)->first()
                : null,
            'recordBreak' => null,
        ];
    }

    /**
     * Batch-resolve payloadFor()-shaped data for many certificates in a fixed number of
     * queries instead of one (or two, for FestParticipant certs, since payloadFor() also
     * does a per-participant FestMark lookup) query per certificate. Same output shape as
     * payloadFor(), keyed by certificate id. Used by list/export endpoints that previously
     * called payloadFor() once per row inside a loop — see
     * FestCertificateController::index()/downloadZip().
     *
     * @param  \Illuminate\Support\Collection<int, Certificate>  $certificates
     * @return \Illuminate\Support\Collection<int, array<string, mixed>> keyed by certificate id
     */
    public function payloadsFor(\Illuminate\Support\Collection $certificates): \Illuminate\Support\Collection
    {
        $participantEntityIds = $certificates->where('entity_type', FestParticipant::class)->pluck('entity_id');
        $recordBreakEntityIds = $certificates->where('entity_type', FestRecordBreak::class)->pluck('entity_id');

        $participants = FestParticipant::with(['student', 'registration.item', 'registration.event', 'registration.school'])
            ->whereIn('id', $participantEntityIds)
            ->get()
            ->keyBy('id');

        // Same "one mark per participant" semantics as payloadFor()'s per-participant
        // FestMark::where('participant_id', ...)->first() call — ordered by id so the
        // batched result is deterministic.
        $marksByParticipant = FestMark::whereIn('participant_id', $participants->keys())
            ->with(['item'])
            ->orderBy('id')
            ->get()
            ->groupBy('participant_id')
            ->map(fn ($group) => $group->first());

        $recordBreaks = FestRecordBreak::with([
            'event',
            'item',
            'participant.student',
            'participant.registration.school',
        ])->whereIn('id', $recordBreakEntityIds)
            ->get()
            ->keyBy('id');

        return $certificates->mapWithKeys(function (Certificate $certificate) use ($participants, $marksByParticipant, $recordBreaks) {
            if ($certificate->entity_type === FestRecordBreak::class) {
                $break = $recordBreaks->get($certificate->entity_id);

                return [$certificate->id => [
                    'certificate'  => $certificate,
                    'participant'  => $break?->participant,
                    'student'      => $break?->participant?->student,
                    'event'        => $break?->event,
                    'item'         => $break?->item,
                    'mark'         => null,
                    'recordBreak'  => $break,
                    'registration' => $break?->participant?->registration,
                ]];
            }

            $participant = $participants->get($certificate->entity_id);
            $mark = $participant ? $marksByParticipant->get($participant->id) : null;
            $item = $participant?->registration?->item ?? $mark?->item;
            $event = $participant?->registration?->event ?? $mark?->event;

            return [$certificate->id => [
                'certificate'  => $certificate,
                'participant'  => $participant,
                'student'      => $participant?->student,
                'event'        => $event,
                'item'         => $item,
                'mark'         => $mark,
                'recordBreak'  => null,
                'registration' => $participant?->registration,
            ]];
        });
    }

    /** @return array<string, mixed> */
    private function recordBreakPayload(Certificate $certificate): array
    {
        $break = FestRecordBreak::with([
            'event',
            'item',
            'participant.student',
            'participant.registration.school',
        ])->find($certificate->entity_id);

        return [
            'certificate' => $certificate,
            'participant' => $break?->participant,
            'student'     => $break?->participant?->student,
            'event'       => $break?->event,
            'item'        => $break?->item,
            'mark'        => null,
            'recordBreak' => $break,
        ];
    }

    /**
     * Certificate rows matching an export scope (whole event, one item, one school, or an
     * explicit id list) — shared by the synchronous small-scope ZIP/print routes
     * (FestCertificateController::downloadZip()/printAll()) and the queued whole-event
     * export job (BuildCertificateZipJob), so "what a Generate/Render run covers" and
     * "what a Download covers" never drift apart for the same filters.
     */
    public function resolveCertificateScope(
        FestEvent $event,
        ?int $itemId = null,
        ?int $schoolId = null,
        ?string $certType = null,
        ?array $certIds = null,
    ): \Illuminate\Support\Collection {
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

    /**
     * Winner certificates whose item is actually publish-visible (its own
     * results_published_at, or the whole event's results_published flag) — a winner
     * Certificate row can already exist before either flag is set (generateForEvent()
     * doesn't itself gate on publish state), so cert_type='winner' alone doesn't mean
     * "published winner". Shared between exportPayloadsForEvent()'s inline filter and
     * BuildCertificateZipJob's own up-front count, so the two never disagree on what
     * counts.
     */
    public function publishedOnlyWinners(\Illuminate\Support\Collection $certificates, \Illuminate\Support\Collection $payloads): \Illuminate\Support\Collection
    {
        $itemResults = app(FestItemResultsService::class);

        return $certificates->filter(function ($certificate) use ($payloads, $itemResults) {
            $payload = $payloads->get($certificate->id) ?? [];
            $item = $payload['item'] ?? null;
            $itemEvent = $payload['event'] ?? null;

            return $certificate->cert_type === 'winner'
                && $item && $itemEvent
                && $itemResults->isItemVisible($item, $itemEvent);
        });
    }

    /**
     * Full render payloads for an export scope — used by the synchronous small-scope
     * download/print routes directly, and by BuildCertificateZipJob for the queued
     * whole-event export. $sahodaya, when given, builds each certificate's QR verify URL
     * from the tenant's own domain (TenantDomainSync::publicUrl()) instead of
     * route(..., absolute: true) — required for the job, which runs on a queue worker
     * with no HTTP request to derive a host from (route() would fall back to
     * config('app.url'), the platform's own domain, not the issuing Sahodaya's — the same
     * bug already fixed this session for RenderCertificateChunkJob/
     * TrainingCertificateService). Left null (the default) for the existing controller
     * call sites, which already run inside a real request on the tenant's own domain —
     * route(absolute: true) is correct there as-is.
     */
    public function exportPayloadsForEvent(
        FestEvent $event,
        bool $embedAssets,
        bool $plain,
        bool $publishedOnly = false,
        ?int $itemId = null,
        ?int $schoolId = null,
        ?string $certType = null,
        ?array $certIds = null,
        ?Tenant $sahodaya = null,
    ): \Illuminate\Support\Collection {
        $certificates = $this->resolveCertificateScope($event, $itemId, $schoolId, $certType, $certIds);

        $payloads = $this->payloadsFor($certificates);

        if ($publishedOnly) {
            $certificates = $this->publishedOnlyWinners($certificates, $payloads);
        }

        $templateCache = [];
        $participantsCache = [];

        return $certificates->map(function ($certificate) use ($payloads, &$templateCache, &$participantsCache, $embedAssets, $plain, $sahodaya) {
            $payload = $this->renderContext($certificate, $payloads->get($certificate->id), $templateCache, $participantsCache, embedAssets: $embedAssets);

            $verifyUrl = $sahodaya
                ? (TenantDomainSync::publicUrl($sahodaya) ?? url('/')).'/certificates/verify/'.$certificate->verification_uuid
                : route('certificates.verify', $certificate->verification_uuid, absolute: true);
            $payload['qr_src'] = app(FestIdCardQrService::class)->dataUri($verifyUrl);

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
