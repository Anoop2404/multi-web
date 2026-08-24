<?php

namespace App\Services\Events;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestEventStaff;
use App\Models\FestParticipant;
use App\Models\FestVolunteer;
use App\Models\FestRecordBreak;
use App\Models\Tenant;
use App\Support\FestClassGroupScheme;
use App\Support\TenantBranding;
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
     * "eligible" (approved registration, not disqualified, not standby).
     */
    private function eligibleParticipantsForEvent(FestEvent $event): \Illuminate\Support\Collection
    {
        return FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('status', 'approved'))
            ->whereNull('disqualified_at')
            ->where('participant_role', '!=', 'standby')
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
     * proxy for them. certificate_date is deliberately excluded: it's `now()`-derived at
     * render time, so including it would flag every certificate stale every single day.
     *
     * @param  array<string, mixed>  $context  A renderContext() return value.
     */
    public function contentHash(array $context): string
    {
        $fieldValues = $context['fieldValues'] ?? [];
        unset($fieldValues['certificate_date']);

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
        $gradeSuffix = ($certType === 'winner' && $mark?->grade) ? ' with '.$mark->grade.' Grade' : '';

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

        return [
            'recipient_name'      => $recipientName,
            'school_name'         => $schoolName,
            'event_title'         => $event?->title ?? '',
            // Alias of event_title — some templates reference {event_name} instead.
            'event_name'          => $event?->title ?? '',
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
            'grade'               => $mark?->grade ?? '',
            // Pre-rendered HTML (see participationItemsBoxHtml()) — a bordered, 2-column
            // "Participated Items" box listing every item this person entered with its
            // category/type, for templates that want a structured list instead of
            // item_title's single run-on sentence. Empty string for non-participation
            // certs and templates that don't reference the {participation_items_box}
            // token at all.
            'participation_items_box' => $certType === 'participation' ? $this->participationItemsBoxHtml($items, $taxonomies) : '',
            'sahodaya_name'       => $sahodaya ? strtoupper($sahodaya->name) : '',
            'certificate_date'    => now()->format('j F Y'),
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
     */
    private function participationItemsBoxHtml(\Illuminate\Support\Collection $items, \Illuminate\Support\Collection $taxonomies): string
    {
        if ($items->isEmpty()) {
            return '';
        }

        $entries = $items->values()->map(function (FestEventItem $item, int $i) use ($taxonomies) {
            $tax = $taxonomies->get($i, ['category' => '', 'type' => '']);
            $meta = trim(implode('  •  ', array_filter([$tax['category'] ?? '', $tax['type'] ?? ''])));

            return '<span style="display:block;font-weight:700;font-size:11.5px;color:#172033;line-height:1.35;">&bull;&nbsp;'.e($item->title).'</span>'
                .($meta !== '' ? '<span style="display:block;font-size:9.5px;color:#64748b;line-height:1.3;margin-left:12px;">'.e($meta).'</span>' : '');
        });

        $rows = '';
        foreach ($entries->chunk(2) as $pair) {
            $cells = $pair->map(fn ($html) => '<td style="width:50%;vertical-align:top;padding:4px 10px 4px 0;">'.$html.'</td>')->implode('');
            if ($pair->count() === 1) {
                $cells .= '<td style="width:50%;"></td>';
            }
            $rows .= '<tr>'.$cells.'</tr>';
        }

        return '<div style="border:1px solid #d6a95c;border-radius:8px;padding:10px 16px;margin:8px auto 0;max-width:94%;background:rgba(180,83,9,0.04);">'
            .'<div style="text-align:center;font-size:10.5px;font-weight:700;letter-spacing:1.5px;color:#b45309;text-transform:uppercase;margin-bottom:6px;">&#10022;&nbsp;Participated Items&nbsp;&#10022;</div>'
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
}
