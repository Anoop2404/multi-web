<?php

namespace App\Services\Ledger;

use App\Models\AccountHead;
use App\Models\SahodayaPayable;
use App\Support\FinancialYear;
use Illuminate\Support\Facades\DB;

class PayableLedgerService
{
    public function __construct(
        private LedgerPostingService $posting,
    ) {}

    public function recordObligation(SahodayaPayable $payable, int $postedBy): SahodayaPayable
    {
        if ($payable->obligation_journal_id) {
            return $payable;
        }

        $expenseHead = $payable->expenseHead
            ?? $this->posting->ensureHead($payable->tenant_id, 'ADMIN-EXP');

        $this->posting->ensureHead($payable->tenant_id, 'ACC-PAYABLE');

        $description = "Payable — {$payable->vendor_name}".($payable->description ? " — {$payable->description}" : '');
        $date = $payable->incurred_date?->format('Y-m-d') ?? now()->toDateString();

        $rows = $this->posting->postJournal($payable->tenant_id, [
            ['code' => $expenseHead->code, 'entry_type' => 'debit', 'amount' => $payable->amount, 'description' => $description],
            ['code' => 'ACC-PAYABLE', 'entry_type' => 'credit', 'amount' => $payable->amount, 'description' => $description],
        ], SahodayaPayable::class, $payable->id, $date, $postedBy);

        $payable->update([
            'obligation_journal_id' => $rows[0]->journal_id ?? null,
            'expense_head_id'       => $expenseHead->id,
        ]);

        return $payable->fresh();
    }

    public function markPaid(SahodayaPayable $payable, float|string $amount, int $postedBy, ?string $paymentDate = null): SahodayaPayable
    {
        $amount = round((float) $amount, 2);
        $balance = $payable->balanceDue();

        if ($amount <= 0 || $amount > $balance + 0.001) {
            throw new \InvalidArgumentException('Payment amount exceeds balance due.');
        }

        if (! $payable->obligation_journal_id) {
            $this->recordObligation($payable, $postedBy);
            $payable->refresh();
        }

        return DB::transaction(function () use ($payable, $amount, $postedBy, $paymentDate, $balance) {
            $this->posting->ensureHead($payable->tenant_id, 'ACC-PAYABLE');

            $description = "Payment — {$payable->vendor_name}";
            $date = $paymentDate ?? now()->toDateString();

            $rows = $this->posting->postJournal($payable->tenant_id, [
                ['code' => 'ACC-PAYABLE', 'entry_type' => 'debit', 'amount' => $amount, 'description' => $description],
                ['code' => 'CASH-BANK', 'entry_type' => 'credit', 'amount' => $amount, 'description' => $description],
            ], null, null, $date, $postedBy, false, $payable->financial_year_id);

            $newPaid = round((float) $payable->amount_paid + $amount, 2);
            $status = $newPaid >= (float) $payable->amount - 0.001 ? 'paid' : 'partial';

            $payable->update([
                'amount_paid'         => $newPaid,
                'status'              => $status,
                'paid_at'             => $status === 'paid' ? now() : $payable->paid_at,
                'payment_journal_id'  => $rows[0]->journal_id ?? $payable->payment_journal_id,
            ]);

            return $payable->fresh();
        });
    }
}
