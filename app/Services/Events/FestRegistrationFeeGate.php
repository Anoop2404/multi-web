<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistrationBatch;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class FestRegistrationFeeGate
{
    public function __construct(
        private FestSchoolEventFeeService $feeService,
        private FestRegistrationBatchFeeService $batchFeeService,
    ) {}

    public function requiredBeforeRegistration(FestEvent $event): bool
    {
        if (! $this->feeService->feeRequired($event)) {
            return false;
        }

        $schedule = $this->feeService->resolveSchedule($event);

        return (bool) ($schedule['require_fee_before_registration'] ?? ($event->event_type === 'sports'));
    }

    public function isSchoolFeeCleared(FestEvent $event, string $schoolId): bool
    {
        if (! $this->feeService->feeRequired($event)) {
            return true;
        }

        return $this->feeService->isPaid($event, $schoolId);
    }

    /** Block registration when the fee schedule requires prior payment. */
    public function assertCanRegister(FestEvent $event, Tenant $school): void
    {
        if ($this->requiredBeforeRegistration($event)) {
            abort_unless(
                $this->isSchoolFeeCleared($event, $school->id),
                422,
                'Fee payment must be approved before registering for this event.'
            );
        }
    }

    /**
     * Opt-in sequencing for phased_regional_billing events (fee_settings.
     * require_prior_batch_payment, default off — zero behavior change unless a Sahodaya
     * explicitly turns it on): a school may not register for a later payment level (e.g.
     * "Level 2") until every earlier level ("Level 1") is fully paid. No-op for events not
     * using registration-batch billing, or for an item whose phase has no batch.
     */
    public function assertPriorBatchesPaid(FestEvent $event, FestEventItem $item, Tenant $school): void
    {
        $root = $event->rootEvent();
        if (! $this->batchFeeService->usesBatchBilling($root)) {
            return;
        }

        $schedule = $this->feeService->resolveSchedule($root);
        if (! ($schedule['require_prior_batch_payment'] ?? false)) {
            return;
        }

        $item->loadMissing('phase.sourcePhase.registrationBatch');
        $phase = $item->phase;
        $sourcePhase = $phase?->sourcePhase ?: $phase;
        $batch = $sourcePhase?->registrationBatch;
        if (! $batch) {
            return;
        }

        $priorBatches = FestRegistrationBatch::where('event_id', $root->id)
            ->where('sort_order', '<', $batch->sort_order)
            ->orderBy('sort_order')
            ->get();

        foreach ($priorBatches as $priorBatch) {
            $fee = $this->batchFeeService->recalculateBatch($root, $school->id, $priorBatch);
            if (! $fee->isFullyPaid()) {
                throw ValidationException::withMessages([
                    'registration' => "Please complete the {$priorBatch->name} payment before registering for {$batch->name} items.",
                ]);
            }
        }
    }
}
