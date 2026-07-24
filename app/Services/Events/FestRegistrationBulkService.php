<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\EventLifecycleGate;
use Illuminate\Support\Facades\DB;

class FestRegistrationBulkService
{
    /** @return array{approved: int, rejected: int, skipped: int, errors: list<string>} */
    public function approveMany(FestEvent $event, array $registrationIds, ?int $schoolId = null, bool $overrideLifecycle = false, ?int $itemId = null): array
    {
        EventLifecycleGate::allowRegistrationReview($event, $overrideLifecycle);

        $approved = 0;
        $skipped = 0;
        $errors = [];

        $policy = app(FestParticipationPolicyService::class)->resolveForEvent($event);
        $feeService = app(FestSchoolEventFeeService::class);
        $approvalService = app(FestRegistrationApprovalService::class);
        $notifier = app(FestEventNotifier::class);
        $audit = app(PlatformAuditLogger::class);

        $query = FestRegistration::where('event_id', $event->id)
            ->where('status', 'submitted')
            ->when($registrationIds !== [], fn ($q) => $q->whereIn('id', $registrationIds))
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId));

        foreach ($query->with(['participants', 'item', 'event'])->get() as $registration) {
            if (($policy['require_fee_before_approval'] ?? true) && $feeService->feeRequired($event)) {
                if (! $feeService->isPaidForRegistration($event, $registration)) {
                    $errors[] = "Registration #{$registration->id}: Event Head fee not approved.";
                    $skipped++;

                    continue;
                }
            }

            $approvalService->approve($registration);
            $notifier->registrationApproved($registration);
            $audit->festRegistrationApproved($registration);
            $approved++;
        }

        return ['approved' => $approved, 'rejected' => 0, 'skipped' => $skipped, 'errors' => $errors];
    }

    /** @return array{approved: int, rejected: int, skipped: int, errors: list<string>} */
    public function rejectMany(FestEvent $event, array $registrationIds, ?int $schoolId = null, bool $overrideLifecycle = false, ?int $itemId = null, string $reason = ''): array
    {
        EventLifecycleGate::allowRegistrationReview($event, $overrideLifecycle);

        $rejected = 0;
        $skipped = 0;
        $errors = [];

        $feeService = app(FestSchoolEventFeeService::class);
        $notifier = app(FestEventNotifier::class);
        $audit = app(PlatformAuditLogger::class);

        $query = FestRegistration::where('event_id', $event->id)
            ->where('status', 'submitted')
            ->when($registrationIds !== [], fn ($q) => $q->whereIn('id', $registrationIds))
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId));

        foreach ($query->get() as $registration) {
            // Only the DB-mutating snapshot/update/credit critical section is locked and
            // transactional — notifier/audit calls happen after commit, outside the lock, so a
            // slow mail/notification dispatch never holds the row lock open. See
            // docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13.4.
            DB::transaction(function () use ($event, $registration, $feeService, $reason) {
                // Lock the school's aggregate fee record (if one exists yet) for the duration
                // of the before/after snapshot below, so two reject/cancel actions racing on
                // the same school can't both read the same "before" state and either compute
                // a wrong delta or double-issue a credit.
                FestSchoolEventFee::where('event_id', $event->id)
                    ->where('school_id', $registration->school_id)
                    ->whereNull('head_id')
                    ->lockForUpdate()
                    ->first();

                // Snapshot the fee record before rejecting, so we can measure what this
                // rejection actually reduced total_due by — fee-model-agnostic, so it works
                // the same for flat/tiered/per-item/composite billing. See
                // docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §9.2 for the full rationale.
                $feeBefore = $feeService->currentFeeRecordFor($event, $registration->school_id);
                $dueBefore = (float) ($feeBefore?->total_due ?? 0);
                $paidBefore = (float) ($feeBefore?->amount_paid ?? 0);

                $registration->update([
                    'status'               => 'rejected',
                    'rejection_reason'     => $reason ?: null,
                    'rejected_at'          => now(),
                    'rejected_by_user_id'  => auth()->id(),
                ]);
                $feeAfter = $feeService->recalculate($event, $registration->school_id);

                // If the school had already paid something and this rejection freed up part of
                // what they owe, record the freed amount (capped at what was actually paid) as
                // an outstanding credit rather than letting it silently disappear into an
                // "overpaid" balance nobody tracks. Deliberately does NOT touch total_due,
                // amount_paid, or receipt status — this is purely an additive record.
                $reduction = round($dueBefore - (float) $feeAfter->total_due, 2);
                if ($reduction > 0 && $paidBefore > 0) {
                    $credit = FestFeeCredit::create([
                        'fest_school_event_fee_id' => $feeAfter->id,
                        'source_registration_id' => $registration->id,
                        'amount' => min($reduction, $paidBefore),
                        'reason' => 'Registration rejected after payment'.($reason ? ': '.$reason : ''),
                        'created_by_user_id' => auth()->id(),
                    ]);

                    // Reduce recognized income for this event by the credited amount and record
                    // the liability now owed back to the school — see
                    // FestFeeLedgerService::postCreditIssued() and
                    // docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13 for why this does NOT touch
                    // CASH-BANK (no cash has moved).
                    app(FestFeeLedgerService::class)->postCreditIssued($credit);

                    // Document-only; never blocks the rejection itself. See
                    // docs/FLOW_GAP_FIX_PLAN.md Phase 3b.2.
                    try {
                        app(\App\Services\Fees\CreditNoteService::class)->issue($credit);
                    } catch (\Throwable) {
                        // credit is already recorded + posted; the note can be regenerated later
                    }
                }
            });

            $notifier->registrationRejected($registration, $reason);
            $audit->festRegistrationRejected($registration);
            $rejected++;
        }

        return ['approved' => 0, 'rejected' => $rejected, 'skipped' => $skipped, 'errors' => $errors];
    }
}
