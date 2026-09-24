<?php

namespace App\Services\State\Fest;

use App\Models\State\StateCertificate;
use App\Models\State\StateCertificateBatch;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phase 9 of the State Kalotsav module — certificates.
 *
 * Three things decide the shape of this.
 *
 * A certificate is printed from a result, and results move — an appeal is upheld, a mark is
 * corrected. A certificate saying second place after the competitor was moved to first is worse than
 * no certificate at all, so each one stores a fingerprint of the result it came from and is marked
 * stale the moment that no longer matches. Detection is not a nightly sweep: it happens when the
 * result changes.
 *
 * Certificate numbers are allocated once and never reused, a regenerated certificate included. The
 * number is what a verification page is asked about, and two documents sharing one would make
 * verification meaningless — so the old one is superseded, not overwritten.
 *
 * Eligibility is computed, not asserted. Merit certificates need a published result and a position;
 * participation needs attendance. Generating for someone who does not qualify produces a document
 * that has to be withdrawn by hand, so the eligibility list is the thing the operator works from.
 */
class StateCertificateService
{
    /**
     * Who may receive a certificate of this type, and why not where they may not.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function eligibility(StateFestEvent $event, string $type, array $filters = []): Collection
    {
        $published = StateItemResult::where('state_event_id', $event->id)
            ->whereIn('status', [StateItemResult::PUBLISHED, StateItemResult::LOCKED])
            ->pluck('item_id');

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->where('status', 'approved')
            ->when($filters['sahodaya_id'] ?? null, fn ($q, $v) => $q->where('sahodaya_id', $v))
            ->when($filters['item_id'] ?? null, fn ($q, $v) => $q->where('item_id', $v))
            ->with('participants')->get();

        $marks = StateFestMark::where('state_event_id', $event->id)
            ->whereIn('registration_id', $registrations->pluck('id'))->get()->keyBy('participant_id');

        $issued = StateCertificate::where('state_event_id', $event->id)
            ->where('type', $type)
            ->whereIn('status', [StateCertificate::GENERATED, StateCertificate::STALE])
            ->get()->keyBy('participant_id');

        return $registrations->flatMap(function (StateFestRegistration $r) use ($marks, $published, $type, $issued) {
            return $r->participants
                ->filter(fn ($p) => $p->isCompeting())
                ->map(function ($p) use ($r, $marks, $published, $type, $issued) {
                    $mark = $marks->get($p->id);
                    $certificate = $issued->get($p->id);
                    $reason = $this->ineligibleReason($type, $r, $mark, $published);

                    return [
                        'participant_id' => $p->id,
                        'registration_id' => $r->id,
                        'name' => $p->student_name,
                        'sahodaya_id' => $r->sahodaya_id,
                        'sahodaya' => $r->sahodaya_name ?: $r->sahodaya_id,
                        'school' => $r->school_name ?: $r->school_id,
                        'item_id' => $r->item_id,
                        'item_code' => $r->item_code,
                        'position' => $mark?->position,
                        'grade' => $mark?->grade,
                        'eligible' => $reason === null,
                        'reason' => $reason,
                        'certificate_number' => $certificate?->certificate_number,
                        'certificate_status' => $certificate?->status,
                    ];
                });
        })->values();
    }

    /** Null when eligible; otherwise the reason, phrased for the operator. */
    private function ineligibleReason(string $type, StateFestRegistration $registration, ?StateFestMark $mark, Collection $published): ?string
    {
        if (! $published->contains($registration->item_id)) {
            return 'The item\'s result is not published yet.';
        }

        if ($type === 'merit') {
            if (! $mark || $mark->position === null) {
                return 'No ranked result.';
            }

            if ($mark->position > 3) {
                return "Placed {$mark->position} — merit certificates go to the first three.";
            }
        }

        return null;
    }

    /**
     * Generate certificates for everyone eligible.
     *
     * @return array{batch: StateCertificateBatch, generated: int, skipped: int}
     */
    public function generate(StateFestEvent $event, string $type, array $filters = [], array $context = []): array
    {
        if (! array_key_exists($type, StateCertificate::TYPES)) {
            throw ValidationException::withMessages(['type' => 'Unknown certificate type.']);
        }

        $eligible = $this->eligibility($event, $type, $filters)->where('eligible', true);

        $batch = StateCertificateBatch::create([
            'id' => (string) Str::uuid(),
            'state_event_id' => $event->id,
            'state_id' => $event->state_id,
            'type' => $type,
            'scope' => $this->describeScope($filters),
            'status' => 'running',
            'requested_count' => $eligible->count(),
            'requested_by_user_id' => $context['user_id'] ?? null,
            'requested_by_name' => $context['user_name'] ?? null,
        ]);

        $generated = 0;
        $skipped = 0;

        DB::connection('state')->transaction(function () use ($event, $type, $eligible, $batch, &$generated, &$skipped) {
            foreach ($eligible as $row) {
                $fingerprint = $this->fingerprint($row);

                $existing = StateCertificate::where('state_event_id', $event->id)
                    ->where('type', $type)
                    ->where('participant_id', $row['participant_id'])
                    ->whereIn('status', [StateCertificate::GENERATED, StateCertificate::STALE])
                    ->first();

                // Already holds a certificate printed from this exact result — regenerating would
                // issue a second number for an unchanged document.
                if ($existing && $existing->source_fingerprint === $fingerprint && ! $existing->isStale()) {
                    $skipped++;

                    continue;
                }

                // A stale or outdated one is superseded rather than edited, so the old number stays
                // resolvable and the verification page can say it was replaced.
                $existing?->forceFill(['status' => StateCertificate::SUPERSEDED])->save();

                StateCertificate::create([
                    'id' => (string) Str::uuid(),
                    'state_event_id' => $event->id,
                    'state_id' => $event->state_id,
                    'type' => $type,
                    'certificate_number' => $this->nextNumber($event, $type),
                    'verification_code' => Str::lower(Str::random(24)),
                    'registration_id' => $row['registration_id'],
                    'participant_id' => $row['participant_id'],
                    'sahodaya_id' => $row['sahodaya_id'],
                    'sahodaya_name' => $row['sahodaya'],
                    'school_name' => $row['school'],
                    'recipient_name' => $row['name'],
                    'item_id' => $row['item_id'],
                    'item_code' => $row['item_code'],
                    'position' => $row['position'],
                    'grade' => $row['grade'],
                    'source_fingerprint' => $fingerprint,
                    'status' => StateCertificate::GENERATED,
                    'batch_id' => $batch->id,
                    'generated_at' => now(),
                ]);

                $generated++;
            }
        });

        $batch->forceFill([
            'status' => 'completed',
            'generated_count' => $generated,
            'completed_at' => now(),
        ])->save();

        return ['batch' => $batch->fresh(), 'generated' => $generated, 'skipped' => $skipped];
    }

    /**
     * Mark every certificate printed from an item stale. Called when a result changes — an appeal
     * upheld, a mark corrected — rather than swept for later.
     */
    public function markItemStale(StateFestEvent $event, string $itemId, string $reason): int
    {
        return StateCertificate::where('state_event_id', $event->id)
            ->where('item_id', $itemId)
            ->where('status', StateCertificate::GENERATED)
            ->update(['status' => StateCertificate::STALE]);
    }

    /**
     * Certificates whose stored fingerprint no longer matches the result — a safety net for changes
     * that did not route through the appeal flow.
     *
     * @return Collection<int, StateCertificate>
     */
    public function detectStale(StateFestEvent $event): Collection
    {
        $certificates = StateCertificate::where('state_event_id', $event->id)
            ->where('status', StateCertificate::GENERATED)->get();

        $marks = StateFestMark::where('state_event_id', $event->id)
            ->whereIn('participant_id', $certificates->pluck('participant_id')->filter())
            ->get()->keyBy('participant_id');

        $stale = $certificates->filter(function (StateCertificate $c) use ($marks) {
            $mark = $marks->get($c->participant_id);

            return $c->source_fingerprint !== $this->fingerprint([
                'participant_id' => $c->participant_id,
                'position' => $mark?->position,
                'grade' => $mark?->grade,
                'name' => $c->recipient_name,
                'school' => $c->school_name,
                'sahodaya' => $c->sahodaya_name,
            ]);
        });

        StateCertificate::whereIn('id', $stale->pluck('id'))->update(['status' => StateCertificate::STALE]);

        return $stale;
    }

    /** @return array<string, mixed> */
    public function tally(StateFestEvent $event): array
    {
        $certificates = StateCertificate::where('state_event_id', $event->id)->get();

        return [
            'total' => $certificates->count(),
            'by_type' => $certificates->groupBy('type')->map->count(),
            'by_status' => $certificates->groupBy('status')->map->count(),
            'by_sahodaya' => $certificates->groupBy('sahodaya_name')->map->count()->sortDesc(),
            'stale' => $certificates->where('status', StateCertificate::STALE)->count(),
            'printed' => $certificates->whereNotNull('printed_at')->count(),
        ];
    }

    /** Look up a certificate by its number or its verification code. */
    public function verify(string $token): ?StateCertificate
    {
        return StateCertificate::where('certificate_number', $token)
            ->orWhere('verification_code', $token)
            ->first();
    }

    /**
     * What the certificate was printed from. Deliberately includes the recipient's name, School and
     * Sahodaya as well as the result: a corrected spelling is also a reason to reprint.
     *
     * @param  array<string, mixed>  $row
     */
    private function fingerprint(array $row): string
    {
        return hash('sha256', implode('|', [
            $row['participant_id'] ?? '',
            $row['position'] ?? '',
            $row['grade'] ?? '',
            $row['name'] ?? '',
            $row['school'] ?? '',
            $row['sahodaya'] ?? '',
        ]));
    }

    /** Sequential per event and type, and never reused. */
    private function nextNumber(StateFestEvent $event, string $type): string
    {
        $prefix = strtoupper(substr($type, 0, 3));
        $sequence = StateCertificate::where('state_event_id', $event->id)->where('type', $type)->count() + 1;

        do {
            $number = sprintf('%s-%d-%05d', $prefix, $event->id, $sequence);
            $sequence++;
        } while (StateCertificate::where('certificate_number', $number)->exists());

        return $number;
    }

    private function describeScope(array $filters): ?string
    {
        $parts = array_filter([
            ($filters['sahodaya_id'] ?? null) ? 'one Sahodaya' : null,
            ($filters['item_id'] ?? null) ? 'one item' : null,
        ]);

        return $parts ? implode(', ', $parts) : 'all eligible';
    }
}
