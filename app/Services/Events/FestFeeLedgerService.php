<?php

namespace App\Services\Events;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\LedgerTransaction;
use App\Services\Ledger\LedgerPostingService;
use App\Support\LedgerAccountCatalog;

class FestFeeLedgerService
{
    public function postApprovedReceipt(FeeReceipt $receipt, bool $forceRepost = false): ?LedgerTransaction
    {
        if ($receipt->status !== 'approved') {
            return null;
        }

        [$sahodayaId, $description, $eventId] = match ($receipt->feeable_type) {
            FestSchoolEventFee::class => $this->schoolEventFeeContext($receipt),
            FestRegistration::class => $this->registrationContext($receipt),
            default => [null, null, null],
        };

        if (! $sahodayaId || ! $eventId) {
            return null;
        }

        $event = FestEvent::find($eventId);
        if (! $event) {
            return null;
        }

        $incomeCode = LedgerAccountCatalog::festIncomeCode($event);
        app(LedgerPostingService::class)->ensureHead(
            $sahodayaId,
            $incomeCode,
            LedgerAccountCatalog::festIncomeHeadName($event),
            LedgerAccountCatalog::festIncomeCategory($event),
            $event->id,
        );

        $rows = app(LedgerPostingService::class)->postIncomeReceipt(
            $receipt,
            $sahodayaId,
            $incomeCode,
            $description ?? 'Event fee receipt',
            $forceRepost
        );

        return $rows[1] ?? $rows[0] ?? null;
    }

    public function postReversal(FeeReceipt $receipt, string $tenantId): void
    {
        app(LedgerPostingService::class)->postReceiptReversal($receipt, $tenantId);
    }

    /**
     * Post the income-reducing / liability-creating leg for a newly issued FestFeeCredit
     * (a paid item rejected, or a paid registration cancelled-with-refund). Debits this
     * event's income head — undoing recognition of income for money now owed back to the
     * school — and credits the dedicated FEE-CREDIT-PAYABLE liability head. Deliberately
     * does NOT touch CASH-BANK: no cash has moved, the school's money is still sitting in
     * the bank exactly as it was when the original receipt posted, only no longer earned
     * against this item. Idempotent per credit row via postJournal()'s existing
     * reference_type/reference_id dedup (keyed on FestFeeCredit::class + $credit->id), so
     * calling this twice for the same credit is a no-op the second time.
     * See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13.
     */
    public function postCreditIssued(FestFeeCredit $credit): ?LedgerTransaction
    {
        $fee = $credit->schoolEventFee()->with('event', 'school')->first();
        if (! $fee?->event) {
            return null;
        }

        $event = $fee->event;
        $incomeCode = LedgerAccountCatalog::festIncomeCode($event);
        $posting = app(LedgerPostingService::class);

        $posting->ensureHead(
            $event->tenant_id,
            $incomeCode,
            LedgerAccountCatalog::festIncomeHeadName($event),
            LedgerAccountCatalog::festIncomeCategory($event),
            $event->id,
        );
        $posting->ensureHead($event->tenant_id, 'FEE-CREDIT-PAYABLE');

        $description = "Fee credit issued — {$fee->school?->name} — {$event->title} ({$credit->reason})";

        $rows = $posting->postJournal($event->tenant_id, [
            ['code' => $incomeCode, 'entry_type' => 'debit', 'amount' => $credit->amount, 'description' => $description],
            ['code' => 'FEE-CREDIT-PAYABLE', 'entry_type' => 'credit', 'amount' => $credit->amount, 'description' => $description],
        ], FestFeeCredit::class, $credit->id, now()->toDateString(), $credit->created_by_user_id);

        return $rows[1] ?? $rows[0] ?? null;
    }

    /**
     * Post the reverse leg when a previously issued credit ROW is consumed against a
     * school's new outstanding balance — called once per FestFeeCredit row actually marked
     * applied_at (see FestSchoolEventFeeService::markCreditsApplied(), the single place this
     * should be called from, so every consumption path — auto-apply via
     * applyAvailableCredit() AND the pre-existing manual forceApprove() waiver — posts
     * consistently). Debits FEE-CREDIT-PAYABLE (the liability has now been used) and
     * credits the *consuming* event's income head (this fee is now considered earned —
     * funded by cash already recorded in CASH-BANK back when the original, credit-
     * generating receipt was posted, so CASH-BANK is untouched here too, exactly as in
     * postCreditIssued()).
     *
     * Referenced by FestFeeCredit::CONSUMPTION_REFERENCE + $credit->id — deliberately NOT
     * FestFeeCredit::class (that's postCreditIssued()'s reference for the SAME row) and NOT
     * FeeReceipt::class (that's postApprovedReceipt()'s reference for a real receipt) —
     * postJournal()'s reference_type/reference_id dedup would otherwise treat this as an
     * exact duplicate of one of those and silently skip posting anything. Idempotent per
     * credit row: calling this twice for the same $credit is a no-op the second time.
     */
    public function postCreditConsumed(FestFeeCredit $credit, FestEvent $consumingEvent, float $amount): ?LedgerTransaction
    {
        if ($amount <= 0) {
            return null;
        }

        $incomeCode = LedgerAccountCatalog::festIncomeCode($consumingEvent);
        $posting = app(LedgerPostingService::class);

        $posting->ensureHead(
            $consumingEvent->tenant_id,
            $incomeCode,
            LedgerAccountCatalog::festIncomeHeadName($consumingEvent),
            LedgerAccountCatalog::festIncomeCategory($consumingEvent),
            $consumingEvent->id,
        );
        $posting->ensureHead($consumingEvent->tenant_id, 'FEE-CREDIT-PAYABLE');

        $description = "Fee credit applied — {$consumingEvent->title}";

        $rows = $posting->postJournal($consumingEvent->tenant_id, [
            ['code' => 'FEE-CREDIT-PAYABLE', 'entry_type' => 'debit', 'amount' => $amount, 'description' => $description],
            ['code' => $incomeCode, 'entry_type' => 'credit', 'amount' => $amount, 'description' => $description],
        ], FestFeeCredit::CONSUMPTION_REFERENCE, $credit->id, now()->toDateString(), null);

        return $rows[1] ?? $rows[0] ?? null;
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} */
    private function schoolEventFeeContext(FeeReceipt $receipt): array
    {
        $fee = FestSchoolEventFee::with('event', 'school')->find($receipt->feeable_id);
        if (! $fee?->event) {
            return [null, null, null];
        }

        return [
            $fee->event->tenant_id,
            "Event fee — {$fee->school?->name} — {$fee->event->title}",
            $fee->event->id,
        ];
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} */
    private function registrationContext(FeeReceipt $receipt): array
    {
        $registration = FestRegistration::with('event')->find($receipt->feeable_id);
        if (! $registration?->event) {
            return [null, null, null];
        }

        return [
            $registration->event->tenant_id,
            "Event fee — registration #{$registration->id}",
            $registration->event->id,
        ];
    }
}
