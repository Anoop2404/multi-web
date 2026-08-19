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
    ) {}

    public function usesBatchBilling(FestEvent $event): bool
    {
        return $event->rootEvent()->workflow_mode === FestPhasedWorkflowService::MODE;
    }

    /** @return Collection<int, FestSchoolEventFee> */
    public function recalculateAll(FestEvent $event, string $schoolId): Collection
    {
        $root = $event->rootEvent();
        $records = $root->registrationBatches()->get()
            ->map(fn (FestRegistrationBatch $batch) => $this->recalculateBatch($root, $schoolId, $batch));

        $this->syncRollup($root, $schoolId, $records);

        return $records;
    }

    public function recalculateBatch(FestEvent $event, string $schoolId, FestRegistrationBatch $batch): FestSchoolEventFee
    {
        $root = $event->rootEvent();
        abort_unless($batch->event_id === $root->id, 422, 'Payment level does not belong to this event.');

        return DB::transaction(function () use ($root, $schoolId, $batch) {
            $registrations = $this->registrations($root, $schoolId, $batch);
            $schedule = $this->fees->resolveSchedule($root);
            $feeModel = $schedule['fee_model'] ?? 'none';
            $itemCount = $registrations->count();
            $studentCount = $registrations->flatMap(fn (FestRegistration $registration) => $registration->participants)
                ->filter(fn (FestParticipant $participant) => $participant->participant_role !== 'standby')
                ->map(fn (FestParticipant $participant) => $participant->student_id
                    ? 'student:'.$participant->student_id
                    : 'teacher:'.$participant->teacher_id)
                ->filter(fn (string $key) => ! str_ends_with($key, ':'))
                ->unique()
                ->count();

            $itemLines = $registrations->map(function (FestRegistration $registration) use ($schedule, $root) {
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

            // This batch's own per-student rate, when set, takes over from the event-wide
            // fee_model='per_student' amount below rather than stacking with it — a batch
            // opting into its own rate means "this phase has its own explicit per-student
            // charge," not "add my rate on top of the shared one." Any other fee_model
            // (item_catalog, cksc_tiered, etc.) is unaffected either way: the batch rate is
            // always additive on top of those, since they're a different kind of charge.
            $batchStudentFeeSet = $batch->student_registration_fee !== null;

            $participationFee = match ($feeModel) {
                'item_catalog' => round((float) $itemLines->sum('amount'), 2),
                'cksc_tiered' => $this->fees->participationFee($itemCount, $schedule),
                'per_item' => round($itemCount * (float) ($schedule['per_item_amount'] ?? 0), 2),
                'per_student' => $batchStudentFeeSet ? 0.0 : round($studentCount * (float) ($schedule['per_student_amount'] ?? 0), 2),
                'student_count_slab' => $this->fees->studentCountSlabFee($studentCount, $schedule),
                default => round((float) $itemLines->sum('amount'), 2),
            };

            // Additive, independent of fee_model — see FestRegistrationBatch::
            // student_registration_fee migration docblock. Null (every batch before this
            // feature existed, and any batch that hasn't opted in) contributes 0, so
            // existing events keep billing exactly as before.
            $batchStudentFee = $batchStudentFeeSet
                ? round($studentCount * (float) $batch->student_registration_fee, 2)
                : 0.0;

            $school = \App\Models\Tenant::find($schoolId);
            $categoryBaseFee = $school ? $this->fees->schoolRegistrationAmount($school, $schedule) : 0.0;
            $baseFee = $registrations->isNotEmpty()
                ? ($categoryBaseFee > 0 ? $categoryBaseFee : (float) $batch->school_base_fee)
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

            if ($record->exists && (float) $record->amount_paid > 0 && round((float) $record->total_due, 2) !== $total) {
                // Paid invoices are immutable. Registration changes require the existing
                // credit/adjustment workflow rather than silently rewriting history.
                return $record->fresh(['lines', 'registrationBatch']);
            }

            $record->fill([
                'head_id' => null,
                'phase_id' => null,
                'school_registration_fee' => $baseFee,
                'participation_item_count' => $feeModel === 'per_student' ? $studentCount : $itemCount,
                'participation_fee' => $participationFee,
                'total_due' => $total,
            ]);
            $record->save();

            $lines = $itemLines;
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

            if ($feeModel !== 'item_catalog' && $participationFee > 0) {
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

            if ($batchStudentFee > 0) {
                $lines->push([
                    'line_type' => 'student_registration',
                    'label' => $batch->name.' student registration fee',
                    'quantity' => $studentCount,
                    'unit_amount' => $batch->student_registration_fee,
                    'amount' => $batchStudentFee,
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
        $rollup = FestSchoolEventFee::firstOrNew([
            'event_id' => $root->id,
            'school_id' => $schoolId,
            'registration_batch_id' => null,
            'phase_id' => null,
            'head_id' => null,
        ]);
        $rollup->fill([
            'school_registration_fee' => round((float) $records->sum('school_registration_fee'), 2),
            'participation_item_count' => (int) $records->sum('participation_item_count'),
            'participation_fee' => round((float) $records->sum('participation_fee'), 2),
            'total_due' => round((float) $records->sum('total_due'), 2),
            'amount_paid' => round((float) $records->sum('amount_paid'), 2),
            'status' => $records->isNotEmpty() && $records->every(fn (FestSchoolEventFee $fee) => $fee->isFullyPaid())
                ? 'approved'
                : ((float) $records->sum('amount_paid') > 0 ? 'partial' : 'pending'),
        ]);
        $rollup->save();
    }
}
