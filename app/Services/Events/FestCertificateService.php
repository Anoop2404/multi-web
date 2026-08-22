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
    public function generateForEvent(FestEvent $event): array
    {
        if ($event->usesPhasedRegionalBilling()) {
            abort_if(! $event->parent_event_id || ! $event->source_phase_id, 422, 'Generate certificates from a published operational phase/region event.');
            abort_unless($event->results_published, 422, 'Publish this phase/region before generating certificates.');
        }

        $created = [];

        $marks = FestMark::whereIn('event_id', $event->reportableEventIds())
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
     */
    public function resolveTemplate(FestEvent $event, ?int $itemId, string $certType): ?CertificateTemplate
    {
        $tenantId = $event->tenant_id;

        if ($itemId) {
            $template = $this->templateQuery($tenantId, $certType)
                ->where('event_id', $event->id)
                ->where('item_id', $itemId)
                ->first();
            if ($template) {
                return $template;
            }
        }

        $template = $this->templateQuery($tenantId, $certType)
            ->where('event_id', $event->id)
            ->whereNull('item_id')
            ->first();
        if ($template) {
            return $template;
        }

        $template = $this->templateQuery($tenantId, $certType)
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

    private function templateQuery(string $tenantId, string $certType)
    {
        return CertificateTemplate::where('tenant_id', $tenantId)
            ->where('event_type', 'fest')
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
    public function renderContext(Certificate $certificate, ?array $payload = null, array &$templateCache = [], array &$participantsCache = []): array
    {
        $payload ??= $this->payloadFor($certificate);

        /** @var ?FestEvent $event */
        $event = $payload['event'] ?? null;

        // An aggregated participation certificate must resolve the event-level (or
        // tenant-wide) template, never the narrow template of whichever single item its
        // anchor FestParticipant row happens to belong to.
        $itemId = $certificate->cert_type === 'participation' ? null : ($payload['item']?->id ?? null);

        $sahodaya = $event ? Tenant::find($event->tenant_id) : null;

        $templateCacheKey = $event ? $event->id.':'.($itemId ?? '0').':'.$certificate->cert_type : null;
        if ($templateCacheKey !== null && array_key_exists($templateCacheKey, $templateCache)) {
            $template = $templateCache[$templateCacheKey];
        } else {
            $template = $event ? $this->resolveTemplate($event, $itemId, $certificate->cert_type) : null;
            if ($templateCacheKey !== null) {
                $templateCache[$templateCacheKey] = $template;
            }
        }

        $logoUrl = $template?->logo_path && $sahodaya
            ? TenantStorage::logoUrl($sahodaya, $template->logo_path)
            : ($sahodaya ? TenantBranding::logoUrl($sahodaya) : null);

        $sealUrl = $template?->seal_path && $sahodaya
            ? TenantStorage::logoUrl($sahodaya, $template->seal_path)
            : null;

        $backgroundUrl = $template?->background_path && $sahodaya
            ? TenantStorage::logoUrl($sahodaya, $template->background_path)
            : null;

        $overlayLayout = $template?->overlayLayout() ?? CertificateTemplate::defaultBackgroundLayout();

        $participant = $payload['participant'] ?? null;
        $photoUrl = ($overlayLayout['show_photo'] ?? false) && $participant
            ? $this->participantPhotoUrl($participant)
            : null;

        $signatories = collect($template?->signatories ?? CertificateTemplate::defaultTrainingSignatories())
            ->map(fn ($s) => [
                'name'          => $s['name'] ?? '',
                'designation'   => $s['designation'] ?? '',
                'signature_url' => (! empty($s['signature_path']) && $sahodaya)
                    ? TenantStorage::logoUrl($sahodaya, $s['signature_path'])
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
     * Reuses FestIdCardService's existing photo-resolution chain (public-disk URL, then
     * a self-contained data URI, then a gender-appropriate placeholder avatar — never
     * the auth-gated school-admin photo route, since certificates are public pages)
     * instead of duplicating that logic here.
     */
    private function participantPhotoUrl(FestParticipant $participant): string
    {
        $rawGender = strtolower((string) ($participant->student?->gender ?? $participant->teacher?->gender ?? ''));
        $gender = match (true) {
            str_starts_with($rawGender, 'f') || $rawGender === 'girl' || $rawGender === 'female' => 'female',
            str_starts_with($rawGender, 'm') || $rawGender === 'boy' || $rawGender === 'male' => 'male',
            default => 'neutral',
        };

        return app(\App\Services\Events\FestIdCardService::class)
            ->resolveParticipantPhotoSrc($participant, $gender, includeDataUris: false);
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

        $achievementLine = match (true) {
            $recordBreak !== null => 'set a new record',
            $certType === 'winner' && $mark?->position === 1 => 'secured the 1st position',
            $certType === 'winner' && $mark?->position === 2 => 'secured the 2nd position',
            $certType === 'winner' && $mark?->position === 3 => 'secured the 3rd position',
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
            'category_name'       => $categoryName,
            'participation_type'  => $participationType,
            'event_dates'         => $eventDates,
            'achievement_line'    => $achievementLine,
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

        $participants = FestParticipant::with(['student', 'registration.item', 'registration.event'])
            ->whereIn('id', $participantEntityIds)
            ->get()
            ->keyBy('id');

        // Same "one mark per participant" semantics as payloadFor()'s per-participant
        // FestMark::where('participant_id', ...)->first() call — ordered by id so the
        // batched result is deterministic. payloadFor()'s original query had no explicit
        // order, so this doesn't change which mark is picked in practice for the common
        // case (a participant with zero or one mark row), only makes it well-defined for
        // the rare case of more than one.
        $marksByParticipant = FestMark::whereIn('participant_id', $participants->keys())
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
                    'certificate' => $certificate,
                    'participant' => $break?->participant,
                    'student'     => $break?->participant?->student,
                    'event'       => $break?->event,
                    'item'        => $break?->item,
                    'mark'        => null,
                    'recordBreak' => $break,
                ]];
            }

            $participant = $participants->get($certificate->entity_id);

            return [$certificate->id => [
                'certificate' => $certificate,
                'participant' => $participant,
                'student'     => $participant?->student,
                'event'       => $participant?->registration?->event,
                'item'        => $participant?->registration?->item,
                'mark'        => $participant ? $marksByParticipant->get($participant->id) : null,
                'recordBreak' => null,
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
