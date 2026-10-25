<?php

namespace App\Services\Ledger;

use App\Models\BankAccount;
use App\Models\LedgerTransaction;
use App\Models\SahodayaPayable;
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
        ], SahodayaPayable::class, $payable->id, $date, $postedBy, false, $payable->financial_year_id);

        $payable->update([
            'obligation_journal_id' => $rows[0]->journal_id ?? null,
            'expense_head_id' => $expenseHead->id,
        ]);

        return $payable->fresh();
    }

    public function markPaid(SahodayaPayable $payable, float|string $amount, int $postedBy, ?string $paymentDate = null, ?int $bankId = null, ?string $reference = null, ?string $submissionKey = null): SahodayaPayable
    {
        return DB::transaction(function () use ($payable, $amount, $postedBy, $paymentDate, $bankId, $reference, $submissionKey) {
            $payable = SahodayaPayable::whereKey($payable->id)->lockForUpdate()->firstOrFail();
            if ($submissionKey) {
                $existing = DB::table('finance_payment_requests')->where('tenant_id', $payable->tenant_id)->where('submission_key', $submissionKey)->first();
                if ($existing) {
                    abort_unless((int) $existing->payable_id === $payable->id, 422);

                    return $payable;
                }
            }
            $amount = round((float) $amount, 2);
            if (in_array($payable->status, ['cancelled', 'paid'], true)) {
                throw new \InvalidArgumentException('This payable is no longer open.');
            }
            if ($amount <= 0 || $amount > $payable->balanceDue()) {
                throw new \InvalidArgumentException('Payment amount exceeds balance due.');
            }
            if ($bankId) {
                abort_unless(BankAccount::where('tenant_id', $payable->tenant_id)->whereKey($bankId)->where('is_active', true)->exists(), 403);
            }
            if (! $payable->obligation_journal_id) {
                $payable = $this->recordObligation($payable, $postedBy);
            }
            $cash = $bankId ? 'BANK-'.$bankId : 'CASH-BANK';
            if ($bankId) {
                $this->posting->ensureHead($payable->tenant_id, $cash, BankAccount::findOrFail($bankId)->account_name);
            }
            $rows = $this->posting->postJournal($payable->tenant_id, [
                ['code' => 'ACC-PAYABLE', 'entry_type' => 'debit', 'amount' => $amount],
                ['code' => $cash, 'entry_type' => 'credit', 'amount' => $amount],
            ], null, null, $paymentDate ?? now()->toDateString(), $postedBy);
            LedgerTransaction::where('journal_id', $rows[0]->journal_id)->update([
                'reference_type' => 'payable_payment', 'reference_id' => $payable->id,
                'description' => 'Payment — '.$payable->vendor_name.($reference ? ' · '.$reference : ''),
            ]);
            if ($bankId) {
                $rows[1]->update(['bank_account_id' => $bankId]);
            }
            $newPaid = round((float) $payable->amount_paid + $amount, 2);
            $payable->update(['amount_paid' => $newPaid, 'status' => $newPaid >= (float) $payable->amount ? 'paid' : 'partial',
                'paid_at' => $newPaid >= (float) $payable->amount ? now() : null, 'payment_journal_id' => $rows[0]->journal_id]);
            if ($submissionKey) {
                DB::table('finance_payment_requests')->insert(['tenant_id' => $payable->tenant_id, 'submission_key' => $submissionKey, 'payable_id' => $payable->id, 'journal_id' => $rows[0]->journal_id, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $payable->fresh();
        });
    }

    public function cancel(SahodayaPayable $payable, int $userId, string $reason): void
    {
        DB::transaction(function () use ($payable, $userId, $reason) {
            $payable = SahodayaPayable::whereKey($payable->id)->lockForUpdate()->firstOrFail();
            abort_if($payable->status === 'paid', 422, 'Paid bills cannot be cancelled.');
            if ($payable->status === 'cancelled') {
                return;
            }
            $balance = $payable->balanceDue();
            if ($payable->obligation_journal_id && $balance > 0) {
                $this->posting->postJournal($payable->tenant_id, [
                    ['code' => 'ACC-PAYABLE', 'entry_type' => 'debit', 'amount' => $balance],
                    ['code' => $payable->expenseHead?->code ?? 'ADMIN-EXP', 'entry_type' => 'credit', 'amount' => $balance],
                ], 'payable_cancel', $payable->id, now()->toDateString(), $userId);
            }
            $payable->update(['status' => 'cancelled', 'description' => trim(($payable->description ?? '').' · Cancelled: '.$reason)]);
        });
    }
}
