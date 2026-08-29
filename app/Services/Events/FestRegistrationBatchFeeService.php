<?php

namespace App\Services\Events;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestRegistrationBatch;
use App\Models\FestSchoolEventFee;
use App\Models\FestSchoolEventFeeLine;
use App\Services\Fees\FeeReceiptAttachmentService;
use App\Support\TenantStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FestRegistrationBatchFeeService
{
    public function __construct(
        private FestSchoolEventFeeService $fees,
        private FestItemFeeResolver $items,
        private FestSportsCompositeFeeService $compositeFeeService,
    ) {}

    public function usesBatchBilling(FestEvent $event): bool
    {
        return $event->rootEvent()->workflow_mode === FestPhasedWorkflowService::MODE;
    }

    /** @return Collection<int, FestSchoolEventFee> */
    public function recalculateAll(FestEvent $event, string $schoolId, bool $force = false): Collection
    {
        $root = $event->rootEvent();
        $schedule = $this->fees->resolveSchedule($root);
        $useComposite = in_array($schedule['fee_model'] ?? 'none', ['sports_composite', 'kalolsavam_composite'], true)
            && $this->fees->supportsSportsCompositeSchema();
        // Computed once, unfiltered, across the WHOLE event (every phase/batch combined) —
        // see compositeAttributionForBatch() below, mirrors FestSchoolEventFeeService::
        // recalculateForPhase()'s identical once-per-event composite() call.
        $composite = $useComposite ? $this->compositeFeeService->calculate($root, $schoolId, $schedule) : null;
        $primaryBatch = $this->primaryBatchForSchool($root, $schoolId);

        $records = $root->registrationBatches()->get()
            ->map(fn (FestRegistrationBatch $batch) => $this->recalculateBatch(
                $root, $schoolId, $batch, $schedule, $composite, $primaryBatch, $force,
            ));

        $this->syncRollup($root, $schoolId, $records);

        return $records;
    }

    public function recalculateBatch(
        FestEvent $event,
        string $schoolId,
        FestRegistrationBatch $batch,
        ?array $schedule = null,
        ?array $composite = null,
        ?FestRegistrationBatch $primaryBatch = null,
        bool $force = false,
    ): FestSchoolEventFee {
        $root = $event->rootEvent();
        abort_unless($batch->event_id === $root->id, 422, 'Payment level does not belong to this event.');

        return DB::transaction(function () use ($root, $schoolId, $batch, $schedule, $composite, $primaryBatch, $force) {
            $registrations = $this->registrations($root, $schoolId, $batch);
            $schedule ??= $this->fees->resolveSchedule($root);
            $feeModel = $schedule['fee_model'] ?? 'none';
            $useComposite = in_array($feeModel, ['sports_composite', 'kalolsavam_composite'], true)
                && $this->fees->supportsSportsCompositeSchema();
            $itemCount = $registrations->count();
            $studentCount = $registrations->flatMap(fn (FestRegistration $registration) => $registration->participants)
                ->filter(fn (FestParticipant $participant) => $participant->participant_role !== 'standby')
                ->map(fn (FestParticipant $participant) => $participant->student_id
                    ? 'student:'.$participant->student_id
                    : 'teacher:'.$participant->teacher_id)
                ->filter(fn (string $key) => ! str_ends_with($key, ':'))
                ->unique()
                ->count();

            // Composite models (kalolsavam_composite/sports_composite) get their item fees
            // and per-student fee from the whole-event quota engine below instead of this
            // per-registration item pricing — see the $useComposite branch further down.
            $itemLines = $useComposite ? collect() : $registrations->map(function (FestRegistration $registration) use ($schedule, $root) {
                $amount = $this->items->amountForItem($registration->item, $schedule, $root, registration: $registration);

                return [
                    'line_type' => 'item_fee',
                    'label' => $registration->item?->title ?? 'Registration #'.$registration->id,
                    'quantity' => 1,
                    'unit_amount' => $amount,
                    'amount' => $amount,
                    'meta' => [
                        'registration_id' => $registration->id,
                        'item_id' => $registration->item_id,
                        'operational_event_id' => $registration->event_id,
                        'source_phase_id' => $registration->event?->source_phase_id,
                    ],
                ];
            })->values();

            // Per-student rate: the batch's own rate wins outright when set (an explicit
            // admin override, applied once per unique student across the whole batch). Only
            // when the batch has no rate of its own do we fall back to each phase's own
            // rate — and since two phases in one batch can have different rates (e.g. Digi
            // Fest ₹50 vs Off Stage ₹75), that fallback must be computed PER PHASE, deduping
            // students within each phase, never by picking one phase's rate via a bare
            // ->value() and applying it to every student in the batch regardless of which
            // phase they actually registered under.
            // Composite models attribute the per-student fee back from the whole-event quota
            // walk below (see $attribution) instead of this batch/phase-rate mechanism, which
            // is specific to the non-composite per_student/student_count_slab billing style.
            $studentFeeLines = [];
            $batchStudentFee = 0.0;

            if (! $useComposite) {
                $batchFeeNum = $batch->student_registration_fee !== null ? (float) $batch->student_registration_fee : null;
                $batchRateSet = $batchFeeNum !== null && $batchFeeNum > 0;

                if ($batchRateSet) {
                    $batchStudentFee = round($studentCount * $batchFeeNum, 2);
                    if ($batchStudentFee > 0) {
                        $studentFeeLines[] = [
                            'label' => $batch->name.' student registration fee',
                            'quantity' => $studentCount,
                            'unit_amount' => $batchFeeNum,
                            'amount' => $batchStudentFee,
                        ];
                    }
                } else {
                    $studentKeysByPhase = [];
                    foreach ($registrations as $registration) {
                        $phase = $registration->item?->phase;
                        $sourcePhase = $phase?->sourcePhase ?: $phase;
                        $phaseId = $sourcePhase?->id ?? 0;
                        foreach ($registration->participants as $participant) {
                            if ($participant->participant_role === 'standby') {
                                continue;
                            }
                            $key = $participant->student_id
                                ? 'student:'.$participant->student_id
                                : ($participant->teacher_id ? 'teacher:'.$participant->teacher_id : null);
                            if ($key === null) {
                                continue;
                            }
                            $studentKeysByPhase[$phaseId][$key] = true;
                        }
                    }

                    $phaseNames = FestEventPhase::whereIn('id', array_keys($studentKeysByPhase))->pluck('name', 'id');
                    $phaseRates = FestEventPhase::whereIn('id', array_keys($studentKeysByPhase))->pluck('student_registration_fee', 'id');

                    foreach ($studentKeysByPhase as $phaseId => $keys) {
                        $rate = (float) ($phaseRates[$phaseId] ?? 0);
                        if ($rate <= 0) {
                            continue;
                        }
                        $count = count($keys);
                        $amount = round($count * $rate, 2);
                        $batchStudentFee += $amount;
                        $studentFeeLines[] = [
                            'label' => ($phaseNames[$phaseId] ?? $batch->name).' student registration fee',
                            'quantity' => $count,
                            'unit_amount' => $rate,
                            'amount' => $amount,
                        ];
                    }
                    $batchStudentFee = round($batchStudentFee, 2);
                }
            }

            $batchStudentFeeSet = $batchStudentFee > 0;
            $compositeAttribution = null;
            $compositeParticipationCount = null;

            if ($useComposite) {
                // Computed once, unfiltered, across the WHOLE event (all phases/batches
                // combined) — never reset per batch — so the included-item quota and
                // per-student fee apply once per student for the whole event, not once per
                // batch. See compositeAttributionForBatch() and its phase-scoped twin
                // FestSchoolEventFeeService::compositeAttributionForPhase().
                $composite ??= $this->compositeFeeService->calculate($root, $schoolId, $schedule);
                $phaseIds = $batch->phases()->pluck('id');
                $compositeAttribution = $this->compositeAttributionForBatch($composite, $batch, $phaseIds);
                $participationFee = round(
                    $compositeAttribution['student_reg_amount'] + $compositeAttribution['extra_item_amount'], 2
                );
                $compositeParticipationCount = $compositeAttribution['student_reg_count'];
            } else {
                $participationFee = match ($feeModel) {
                    'item_catalog' => round((float) $itemLines->sum('amount'), 2),
                    'cksc_tiered' => $this->fees->participationFee($itemCount, $schedule),
                    'per_item' => round($itemCount * (float) ($schedule['per_item_amount'] ?? 0), 2),
                    'per_student' => $batchStudentFeeSet ? 0.0 : round($studentCount * (float) ($schedule['per_student_amount'] ?? 0), 2),
                    'student_count_slab' => $this->fees->studentCountSlabFee($studentCount, $schedule),
                    default => round((float) $itemLines->sum('amount'), 2),
                };
            }

            $school = \App\Models\Tenant::find($schoolId);
            // The flat/tiered school registration fee (fee_settings.school_registration /
            // school_registration_flat) is charged once for the WHOLE event, attributed to
            // whichever batch the school first has activity in — never per batch. The two
            // fallback tiers below (a phase's own school_registration_fee_share, and a
            // batch's own flat school_base_fee) are untouched: those are an admin's explicit,
            // deliberate per-batch/per-phase split, not this bug.
            $primaryBatch ??= $this->primaryBatchForSchool($root, $schoolId);
            $isPrimaryBatch = $primaryBatch && $primaryBatch->id === $batch->id;
            // Composite events (kalolsavam_composite/sports_composite) configure their school
            // fee through their OWN dedicated flat/tiered/student-count-slab field
            // (school_registration_flat / school_fee_mode — see FestSportsCompositeFeeService::
            // schoolRegistrationAmount()), a completely separate config from the generic
            // include_school_registration tier map FestSchoolEventFeeService::
            // schoolRegistrationAmount() reads (used by item_catalog/cksc_tiered events) —
            // calling the wrong one silently ignores whatever the admin actually configured.
            $categoryBaseFee = ($school && $isPrimaryBatch)
                ? ($useComposite
                    ? $this->compositeFeeService->schoolRegistrationAmount($school, $schedule, $root)
                    : $this->fees->schoolRegistrationAmount($school, $schedule))
                : 0.0;
            $phaseBaseFee = FestEventPhase::where('event_id', $root->id)
                ->where('registration_batch_id', $batch->id)
                ->whereNotNull('school_registration_fee_share')
                ->sum('school_registration_fee_share');

            $baseFee = $registrations->isNotEmpty()
                ? ($categoryBaseFee > 0 ? $categoryBaseFee : ($phaseBaseFee > 0 ? (float) $phaseBaseFee : (float) $batch->school_base_fee))
                : 0.0;
            $total = round($baseFee + $participationFee + $batchStudentFee, 2);

            $record = FestSchoolEventFee::where('event_id', $root->id)
                ->where('school_id', $schoolId)
                ->where('registration_batch_id', $batch->id)
                ->lockForUpdate()
                ->first() ?? new FestSchoolEventFee([
                    'event_id' => $root->id,
                    'school_id' => $schoolId,
                    'registration_batch_id' => $batch->id,
                ]);

            if (! $force && $record->exists && (float) $record->amount_paid > 0 && round((float) $record->total_due, 2) !== $total) {
                // Paid invoices are immutable. Registration changes require the existing
                // credit/adjustment workflow rather than silently rewriting history. A
                // deliberate corrective recalculation (see FestRecalculateBatchBilling) may
                // pass force: true to push a fixed total_due through anyway — amount_paid
                // itself is never touched by this method regardless, so money already
                // recorded as paid is unaffected either way.
                return $record->fresh(['lines', 'registrationBatch']);
            }

            $record->fill(array_filter([
                'head_id' => null,
                'phase_id' => null,
                'school_registration_fee' => $baseFee,
                'participation_item_count' => $useComposite ? $compositeParticipationCount : ($feeModel === 'per_student' ? $studentCount : $itemCount),
                'participation_fee' => $participationFee,
                'student_registration_fee' => ($useComposite && $this->fees->supportsSportsCompositeSchema())
                    ? $compositeAttribution['student_reg_amount'] : null,
                'extra_item_fee' => ($useComposite && $this->fees->supportsSportsCompositeSchema())
                    ? $compositeAttribution['extra_item_amount'] : null,
                'total_due' => $total,
            ], fn ($value) => $value !== null));
            $record->save();

            $lines = $useComposite ? collect($compositeAttribution['lines']) : $itemLines;
            if ($baseFee > 0) {
                $lines->prepend([
                    'line_type' => 'school_registration',
                    'label' => $batch->name.' school registration fee',
                    'quantity' => 1,
                    'unit_amount' => $baseFee,
                    'amount' => $baseFee,
                    'meta' => ['registration_batch_id' => $batch->id],
                ]);
            }

            if (! $useComposite && $feeModel !== 'item_catalog' && $participationFee > 0) {
                $lines = $lines->reject(fn (array $line) => $line['line_type'] === 'item_fee')->values();
                $lines->push([
                    'line_type' => $feeModel,
                    'label' => $batch->name.' participation fee',
                    'quantity' => in_array($feeModel, ['per_student', 'student_count_slab'], true) ? $studentCount : $itemCount,
                    'unit_amount' => $participationFee,
                    'amount' => $participationFee,
                    'meta' => ['registration_batch_id' => $batch->id],
                ]);
            }

            foreach ($studentFeeLines as $studentFeeLine) {
                $lines->push([
                    'line_type' => 'student_registration',
                    'label' => $studentFeeLine['label'],
                    'quantity' => $studentFeeLine['quantity'],
                    'unit_amount' => $studentFeeLine['unit_amount'],
                    'amount' => $studentFeeLine['amount'],
                    'meta' => ['registration_batch_id' => $batch->id],
                ]);
            }

            $record->lines()->delete();
            foreach ($lines as $line) {
                FestSchoolEventFeeLine::create(array_merge($line, [
                    'fest_school_event_fee_id' => $record->id,
                ]));
            }

            $record->refreshPaidState();

            return $record->fresh(['lines', 'registrationBatch']);
        });
    }

    public function batchForRegistration(FestEvent $event, FestRegistration $registration): ?FestRegistrationBatch
    {
        $registration->loadMissing('item.phase.sourcePhase');
        $phase = $registration->item?->phase;
        $source = $phase?->sourcePhase ?: $phase;

        return $source?->registrationBatch;
    }

    public function isPaidForRegistration(FestEvent $event, FestRegistration $registration): bool
    {
        $batch = $this->batchForRegistration($event, $registration);
        if (! $batch) {
            return false;
        }

        $fee = $this->recalculateBatch($event, $registration->school_id, $batch);

        return $fee->isFullyPaid();
    }

    public function attachPayment(
        FestEvent $event,
        string $schoolId,
        int $batchId,
        UploadedFile $proof,
        int $userId,
        ?string $transactionRef = null,
        ?string $bankName = null,
        ?float $amount = null,
        array $extraProofs = [],
    ): FestSchoolEventFee {
        $root = $event->rootEvent();
        $batch = FestRegistrationBatch::where('event_id', $root->id)->findOrFail($batchId);
        $fee = $this->recalculateBatch($root, $schoolId, $batch);
        abort_if($fee->total_due <= 0, 422, 'No fee is due for this payment level.');
        abort_if($fee->isFullyPaid(), 422, 'This payment level is already fully paid.');

        $outstanding = $fee->outstandingBalance();
        $payAmount = $amount !== null ? round($amount, 2) : $outstanding;
        abort_if($payAmount <= 0 || $payAmount > $outstanding, 422, 'Payment amount must be within the outstanding balance.');

        $path = TenantStorage::storeUploadedFile($proof, "fest-payments/{$schoolId}");
        FeeReceipt::supersedePriorForFeeable($fee);
        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'file_path' => $path,
            'transaction_ref' => $transactionRef,
            'bank_name' => $bankName,
            'payment_date' => now()->toDateString(),
            'amount' => $payAmount,
            'status' => 'uploaded',
            'uploaded_by_user_id' => $userId,
        ]);

        if ($extraProofs !== []) {
            app(FeeReceiptAttachmentService::class)
                ->attachExtra($receipt, $extraProofs, "fest-payments/{$schoolId}");
        }

        $fee->update(['fee_receipt_id' => $receipt->id, 'status' => 'proof_uploaded']);

        return $fee->fresh(['feeReceipt', 'registrationBatch', 'lines']);
    }

    /** @return Collection<int, FestRegistration> */
    private function registrations(FestEvent $root, string $schoolId, FestRegistrationBatch $batch): Collection
    {
        $phaseIds = FestEventPhase::where('event_id', $root->id)
            ->where('registration_batch_id', $batch->id)
            ->pluck('id');
        $leafIds = FestEvent::where('parent_event_id', $root->id)
            ->whereIn('source_phase_id', $phaseIds)
            ->pluck('id');

        return FestRegistration::where('school_id', $schoolId)
            ->whereIn('status', FestRegistration::ACTIVE_STATUSES)
            ->where(function ($query) use ($root, $phaseIds, $leafIds) {
                $query->whereIn('event_id', $leafIds)
                    ->orWhere(function ($legacy) use ($root, $phaseIds) {
                        $legacy->where('event_id', $root->id)
                            ->whereHas('item', fn ($item) => $item->whereIn('phase_id', $phaseIds));
                    });
            })
            ->with(['event:id,parent_event_id,source_phase_id', 'item.phase.sourcePhase.registrationBatch', 'item.head', 'participants', 'groups.participants'])
            ->get();
    }

    /** @param Collection<int, FestSchoolEventFee> $records */
    private function syncRollup(FestEvent $root, string $schoolId, Collection $records): void
    {
        // firstOrNew() + save() alone is a check-then-write race: two overlapping requests
        // recalculating the same school at once (e.g. two admin actions moments apart, since
        // this runs synchronously inline — QUEUE_CONNECTION=sync, no worker serializes these)
        // can both miss the existing rollup row and both insert, leaving duplicate
        // registration_batch_id=null rows that then double-count in every dashboard/report
        // that sums this table. lockForUpdate() inside a transaction matches the pattern
        // recalculateBatch() already uses for the same reason. See Documents/Path_breaks.md.
        DB::transaction(function () use ($root, $schoolId, $records) {
            $rollup = FestSchoolEventFee::where([
                'event_id' => $root->id,
                'school_id' => $schoolId,
                'registration_batch_id' => null,
                'phase_id' => null,
                'head_id' => null,
            ])->lockForUpdate()->first() ?? new FestSchoolEventFee([
                'event_id' => $root->id,
                'school_id' => $schoolId,
                'registration_batch_id' => null,
                'phase_id' => null,
                'head_id' => null,
            ]);
            // A pre-batch-conversion payment lives as this rollup row's OWN receipts
            // (feeable_id = $rollup->id) — a disjoint set from each batch row's own receipts
            // (feeable_id = that batch row's id), so folding approvedPaidTotal() in here never
            // double-counts. Deliberately NOT calling $rollup->refreshPaidState() — that only
            // knows this row's own receipts and would discard the batch-row sum entirely.
            $combinedPaid = round($rollup->approvedPaidTotal() + (float) $records->sum('amount_paid'), 2);
            $rollup->fill([
                'school_registration_fee' => round((float) $records->sum('school_registration_fee'), 2),
                'participation_item_count' => (int) $records->sum('participation_item_count'),
                'participation_fee' => round((float) $records->sum('participation_fee'), 2),
                'total_due' => round((float) $records->sum('total_due'), 2),
                'amount_paid' => $combinedPaid,
                'status' => $records->isNotEmpty() && $records->every(fn (FestSchoolEventFee $fee) => $fee->isFullyPaid())
                    ? 'approved'
                    : ($combinedPaid > 0 ? 'partial' : 'pending'),
            ]);
            $rollup->save();
        });
    }

    /**
     * Batch-scoped twin of FestSchoolEventFeeService::compositeAttributionForPhase() — sums
     * the phase_attribution buckets (already computed once, unfiltered, for the whole event
     * by FestSportsCompositeFeeService::calculate()) across every phase belonging to this
     * batch, instead of just one phase, so a batch grouping several phases gets its full share.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $phaseIds
     * @return array{student_reg_amount: float, student_reg_count: int, extra_item_amount: float, lines: list<array>}
     */
    private function compositeAttributionForBatch(array $composite, FestRegistrationBatch $batch, Collection $phaseIds): array
    {
        $attribution = $composite['phase_attribution'] ?? [];
        $perStudentRate = (float) ($attribution['per_student_rate'] ?? 0.0);

        $studentRegAmount = 0.0;
        $studentRegCount = 0;
        $extraItemAmount = 0.0;
        foreach ($phaseIds as $phaseId) {
            $bucket = $attribution['student_reg']['by_phase'][$phaseId] ?? ['amount' => 0.0, 'student_count' => 0];
            $studentRegAmount += (float) ($bucket['amount'] ?? 0);
            $studentRegCount += (int) ($bucket['student_count'] ?? 0);
            $extraItemAmount += (float) ($attribution['extra_item']['by_phase'][$phaseId] ?? 0.0);
        }
        $studentRegAmount = round($studentRegAmount, 2);
        $extraItemAmount = round($extraItemAmount, 2);

        $lines = [];
        if ($studentRegAmount > 0) {
            $lines[] = [
                'line_type' => 'student_reg',
                'label' => "Student registration ({$batch->name}) — {$studentRegCount} × ₹".number_format($perStudentRate, 0),
                'quantity' => $studentRegCount,
                'unit_amount' => $perStudentRate,
                'amount' => $studentRegAmount,
                'meta' => ['registration_batch_id' => $batch->id],
            ];
        }

        $phaseIdList = $phaseIds->all();
        foreach ($composite['lines'] ?? [] as $line) {
            if (in_array($line['line_type'] ?? null, ['item_fee', 'extra_item'], true)
                && in_array($line['meta']['phase_id'] ?? null, $phaseIdList, true)) {
                $lines[] = $line;
            }
        }

        return [
            'student_reg_amount' => $studentRegAmount,
            'student_reg_count' => $studentRegCount,
            'extra_item_amount' => $extraItemAmount,
            'lines' => $lines,
        ];
    }

    /**
     * Which batch "owns" the school's flat/tiered school registration fee — the
     * earliest-sort_order batch the school has any active registration in. Charged once for
     * the whole event, not once per batch (see plan doc). Returns null if the school has no
     * activity in any batch yet (no batch is charged until they've registered something).
     */
    private function primaryBatchForSchool(FestEvent $root, string $schoolId): ?FestRegistrationBatch
    {
        foreach ($root->registrationBatches()->get() as $batch) {
            if ($this->registrations($root, $schoolId, $batch)->isNotEmpty()) {
                return $batch;
            }
        }

        return null;
    }
}
