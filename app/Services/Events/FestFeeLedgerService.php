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
     * Post the reverse leg when a previously issued credit is applied against a school's
     * new outstanding balance (FestSchoolEventFeeService::applyAvailableCredit()). Debits
     * FEE-CREDIT-PAYABLE (the liability has now been used) and credits the *consuming*
     * event's income head (this fee is now considered earned — funded by cash already
     * recorded in CASH-BANK back when the original, credit-generating receipt was posted,
     * so CASH-BANK is untouched here too, exactly as in postCreditIssued()). Idempotent
     * per synthetic receipt via postJournal()'s dedup (keyed on FeeReceipt::class +
     * $syntheticReceipt->id) — safe even if called more than once for the same receipt.
     */
    public function postCreditConsumed(FeeReceipt $syntheticReceipt, FestEvent $event, float $amount): ?LedgerTransaction
    {
        if ($amount <= 0) {
            return null;
        }

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

        $description = "Fee credit applied — {$event->title}";

        $rows = $posting->postJournal($event->tenant_id, [
            ['code' => 'FEE-CREDIT-PAYABLE', 'entry_type' => 'debit', 'amount' => $amount, 'description' => $description],
            ['code' => $incomeCode, 'entry_type' => 'credit', 'amount' => $amount, 'description' => $description],
        ], FeeReceipt::class, $syntheticReceipt->id, now()->toDateString(), null);

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
