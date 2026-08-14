<?php

namespace App\Services\Events;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestEventStaff;
use App\Models\FestParticipant;
use App\Models\FestVolunteer;
use App\Models\FestRecordBreak;
use App\Models\Tenant;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Illuminate\Support\Str;

class FestCertificateService
{
    /** @return list<Certificate> */
    public function generateForEvent(FestEvent $event): array
    {
        $created = [];

        $marks = FestMark::whereIn('event_id', $event->reportableEventIds())
            ->whereNotNull('position')
            ->where('position', '<=', 3)
            ->with(['participant.student', 'participant.registration.item'])
            ->get();

        foreach ($marks as $mark) {
            $participant = $mark->participant;
            if (! $participant || $participant->disqualified_at) {
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

    /** @return list<Certificate> */
    public function generateParticipationForEvent(FestEvent $event): array
    {
        $created = [];

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('status', 'approved'))
            ->whereNull('disqualified_at')
            ->with('registration.item')
            ->get();

        foreach ($participants as $participant) {
            $template = $this->resolveTemplate($event, $participant->registration?->item?->id, 'participation');

            $cert = Certificate::firstOrCreate(
                [
                    'entity_type' => FestParticipant::class,
                    'entity_id'   => $participant->id,
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
     * @return array<string, mixed>
     */
    public function renderContext(Certificate $certificate, ?array $payload = null, array &$templateCache = []): array
    {
        $payload ??= $this->payloadFor($certificate);

        /** @var ?FestEvent $event */
        $event = $payload['event'] ?? null;
        $itemId = $payload['item']?->id ?? null;

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

        $signatories = collect($template?->signatories ?? CertificateTemplate::defaultTrainingSignatories())
            ->map(fn ($s) => [
                'name'          => $s['name'] ?? '',
                'designation'   => $s['designation'] ?? '',
                'signature_url' => (! empty($s['signature_path']) && $sahodaya)
                    ? TenantStorage::logoUrl($sahodaya, $s['signature_path'])
                    : null,
            ])->values()->all();

        $fieldValues = $this->resolveFieldValues($payload, $sahodaya, $certificate->cert_type);

        return array_merge($payload, [
            'sahodaya'      => $sahodaya,
            'template'      => $template,
            'fieldValues'   => $fieldValues,
            'logoUrl'       => $logoUrl,
            'sealUrl'       => $sealUrl,
            'backgroundUrl' => $backgroundUrl,
            'overlayLayout' => $overlayLayout,
            'signatories'   => $signatories,
        ]);
    }

    /** @return array<string, string> */
    private function resolveFieldValues(array $payload, ?Tenant $sahodaya, string $certType): array
    {
        $event = $payload['event'] ?? null;
        $item = $payload['item'] ?? null;
        $student = $payload['student'] ?? null;
        $recordBreak = $payload['recordBreak'] ?? null;
        $mark = $payload['mark'] ?? null;

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

        return [
            'recipient_name'   => $recipientName,
            'school_name'      => $schoolName,
            'event_title'      => $event?->title ?? '',
            'item_title'       => $item?->title ?? '',
            'event_dates'      => $eventDates,
            'achievement_line' => $achievementLine,
            'sahodaya_name'    => $sahodaya ? strtoupper($sahodaya->name) : '',
            'certificate_date' => now()->format('j F Y'),
        ];
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
