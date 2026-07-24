<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FestEventStatusService
{
    public function __construct(
        private FestEventNotifier $notifier,
        private \App\Services\Audit\PlatformAuditLogger $audit
    ) {}

    public function transitionToCancelled(FestEvent $event, bool $confirmCreditAll = false): void
    {
        $paidFees = FestSchoolEventFee::where('event_id', $event->id)
            ->where('amount_paid', '>', 0)
            ->get();

        if ($paidFees->isNotEmpty() && !$confirmCreditAll) {
            $count = $paidFees->count();
            $total = $paidFees->sum('amount_paid');

            throw ValidationException::withMessages([
                'status' => "This event has {$count} school(s) with approved payments totaling ₹{$total}. To proceed with cancellation and issue credits, you must confirm 'credit_all'.",
            ]);
        }

        DB::transaction(function () use ($event, $paidFees) {
            $registrations = FestRegistration::where('event_id', $event->id)
                ->whereIn('status', FestRegistration::ACTIVE_STATUSES)
                ->get();

            if ($registrations->isNotEmpty()) {
                FestRegistration::whereIn('id', $registrations->pluck('id'))->update([
                    'status' => 'withdrawn',
                ]);
            }

            $issuedCredits = collect();
            
            foreach ($paidFees as $fee) {
                $feeAfter = app(FestSchoolEventFeeService::class)->recalculate($event, $fee->school_id);
                $reduction = round((float)$fee->total_due - (float)$feeAfter->total_due, 2);
                $paidBefore = (float)$fee->amount_paid;
                
                $creditAmount = min($reduction, $paidBefore);
                
                if ($creditAmount > 0) {
                    $credit = FestFeeCredit::create([
                        'fest_school_event_fee_id' => $feeAfter->id,
                        'source_registration_id'   => null,
                        'amount'                   => $creditAmount,
                        'reason'                   => 'Event cancelled after payment',
                        'created_by_user_id'       => auth()->id(),
                    ]);
                    // Every other FestFeeCredit-creation site (FestSchoolEventFeeController::
                    // approve()'s overpayment reconciliation, FestRegistrationBulkService::
                    // rejectMany(), FestRegistrationService::cancelWithRefund()) posts this to
                    // the ledger — this site was the one gap where a credit could exist
                    // without the corresponding liability ever showing in Financial
                    // Statements. See docs/FLOW_GAP_FIX_PLAN.md Phase 3b / 4.3.
                    app(\App\Services\Events\FestFeeLedgerService::class)->postCreditIssued($credit);

                    try {
                        app(\App\Services\Fees\CreditNoteService::class)->issue($credit);
                    } catch (\Throwable) {
                        // credit is already recorded + posted; the note can be regenerated later
                    }

                    $issuedCredits->push($credit);
                }
            }

            $event->update(['status' => 'cancelled']);

            $this->notifier->eventCancelled($event, $issuedCredits);

            $this->audit->festEvent(
                $event,
                \App\Support\Enums\FestPageActivity::OVERVIEW,
                'fest.event.cancelled',
                "Event cancelled: {$event->title}",
                ['status' => 'cancelled']
            );
        });
    }
}
