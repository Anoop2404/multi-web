<?php

namespace App\Services\Ledger;

use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\FinanceDocument;
use App\Models\FinancialYear;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceWorkspaceService
{
    public function __construct(private LedgerPostingService $posting) {}

    public function bankCode(string $tenant, ?int $id): string
    {
        if (! $id) {
            return 'CASH-BANK';
        }
        $bank = BankAccount::where('tenant_id', $tenant)->where('is_active', true)->findOrFail($id);
        $head = $this->posting->ensureHead($tenant, 'BANK-'.$id, $bank->account_name);
        abort_unless($head->type === 'asset', 422, 'The bank ledger account must be an asset.');

        return 'BANK-'.$id;
    }

    public function approve(FinanceDocument $document, int $userId): FinanceDocument
    {
        return DB::transaction(function () use ($document, $userId) {
            $d = FinanceDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($d->status === 'posted') {
                return $d;
            }
            abort_unless($d->status === 'pending', 422, 'Only pending vouchers can be approved.');
            $cash = $this->bankCode($d->tenant_id, $d->bank_account_id);
            $head = $d->account_head_id ? AccountHead::where('tenant_id', $d->tenant_id)->findOrFail($d->account_head_id) : null;
            $amount = $d->amount;
            $primary = $head?->code;
            $debit = $primary;
            $credit = $cash;
            if ($d->kind === 'income') {
                abort_unless($head?->type === 'income', 422);
                $debit = $cash;
                $credit = $primary;
            } elseif ($d->kind === 'transfer') {
                $debit = $this->bankCode($d->tenant_id, $d->destination_bank_id);
                abort_if($debit === $cash, 422, 'Choose different source and destination accounts.');
            } elseif ($d->kind === 'advance') {
                $this->posting->ensureHead($d->tenant_id, 'ADVANCES', 'Advances & reimbursements');
                $debit = 'ADVANCES';
            } else {
                if ($d->kind !== 'advance_return') {
                    abort_unless($head?->type === 'expense', 422, 'Select an expense account.');
                }
                if (in_array($d->kind, ['settlement', 'advance_return'])) {
                    $parent = FinanceDocument::where('tenant_id', $d->tenant_id)->where('kind', 'advance')->where('status', 'posted')->whereKey($d->parent_id)->lockForUpdate()->firstOrFail();
                    $settled = FinanceDocument::where('parent_id', $parent->id)->where('status', 'posted')->sum('amount');
                    if ((float) $amount > round((float) $parent->amount - (float) $settled, 2)) {
                        throw ValidationException::withMessages(['amount' => 'Settlement exceeds the unsettled advance.']);
                    }
                    $credit = 'ADVANCES';
                    if ($d->kind === 'advance_return') {
                        $debit = $cash;
                    }
                }
            }
            $rows = $this->posting->postJournal($d->tenant_id, [['code' => $debit, 'entry_type' => 'debit', 'amount' => $amount, 'description' => $d->description], ['code' => $credit, 'entry_type' => 'credit', 'amount' => $amount, 'description' => $d->description]], FinanceDocument::class, $d->id, $d->document_date->format('Y-m-d'), $userId, false, $d->financial_year_id);
            foreach ($rows as $row) {
                if ($row->accountHead?->code === $cash && $d->bank_account_id) {
                    $row->update(['bank_account_id' => $d->bank_account_id]);
                }
                if ($d->kind === 'transfer' && $row->entry_type === 'debit' && $d->destination_bank_id) {
                    $row->update(['bank_account_id' => $d->destination_bank_id]);
                }
            }
            $d->update(['status' => 'posted', 'journal_id' => $rows[0]->journal_id, 'reviewed_by' => $userId]);

            return $d->fresh();
        });
    }

    public function reverse(FinanceDocument $document, int $userId, string $reason): void
    {
        DB::transaction(function () use ($document, $userId, $reason) {
            $document = FinanceDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($document->status === 'reversed') {
                return;
            }
            abort_unless($document->status === 'posted', 422, 'Only posted vouchers can be reversed.');
            if ($document->kind === 'advance') {
                abort_if(FinanceDocument::where('parent_id', $document->id)->where('status', 'posted')->exists(), 422, 'Reverse settlements before reversing this advance.');
            }
            $original = LedgerTransaction::where('tenant_id', $document->tenant_id)->where('journal_id', $document->journal_id)->with('accountHead')->get();
            $lines = $original->map(fn ($row) => ['code' => $row->accountHead->code, 'entry_type' => $row->entry_type === 'debit' ? 'credit' : 'debit', 'amount' => $row->amount, 'description' => 'Reversal #'.$document->id.': '.$reason])->all();
            $rows = $this->posting->postJournal($document->tenant_id, $lines, 'finance_document_reversal', $document->id, now()->toDateString(), $userId);
            foreach ($rows as $i => $row) {
                if ($original[$i]->bank_account_id) {
                    $row->update(['bank_account_id' => $original[$i]->bank_account_id]);
                }
            }
            $document->update(['status' => 'reversed', 'reviewed_by' => $userId, 'review_reason' => $reason, 'recurring' => false]);
        });
    }

    public function generateRecurring(string $tenant, int $userId): int
    {
        return DB::transaction(function () use ($tenant, $userId) {
            $count = 0;
            foreach (FinanceDocument::where('tenant_id', $tenant)->where('status', 'posted')->where('recurring', true)->whereDate('next_due', '<=', now())->lockForUpdate()->get() as $template) {
                $due = $template->next_due->format('Y-m-d');
                $year = FinancialYear::whereDate('start_date', '<=', $due)->whereDate('end_date', '>=', $due)->first();
                if (! $year) {
                    throw ValidationException::withMessages(['recurring' => 'Set up the financial year for '.$due.' first.']);
                }
                FinanceDocument::firstOrCreate(['tenant_id' => $tenant, 'submission_key' => 'recurring-'.$template->id.'-'.$due], array_merge($template->only(['kind', 'vendor_id', 'account_head_id', 'bank_account_id', 'destination_bank_id', 'event_id', 'region', 'amount', 'description']), ['financial_year_id' => $year->id, 'status' => 'pending', 'document_date' => $due, 'created_by' => $userId]));
                $template->update(['next_due' => $template->next_due->addMonthNoOverflow()]);
                $count++;
            }

            return $count;
        });
    }

    public function closeYear(string $tenant, int $yearId, ?int $targetId, int $userId, string $reason): void
    {
        DB::transaction(function () use ($tenant, $yearId, $targetId, $userId, $reason) {
            $year = FinancialYear::whereKey($yearId)->lockForUpdate()->firstOrFail();
            abort_if(DB::table('finance_period_locks')->where('tenant_id', $tenant)->where('financial_year_id', $yearId)->exists(), 422, 'Year already closed.');
            abort_if(FinanceDocument::where('tenant_id', $tenant)->where('financial_year_id', $yearId)->where('status', 'pending')->exists(), 422, 'Review pending vouchers first.');
            $imbalance = LedgerTransaction::where('tenant_id', $tenant)->where('financial_year_id', $yearId)->selectRaw("SUM(CASE WHEN entry_type='debit' THEN amount ELSE -amount END) AS difference")->value('difference');
            abort_if(abs((float) $imbalance) > 0.005, 422, 'Resolve unbalanced journals before closing.');
            if ($targetId) {
                $target = FinancialYear::whereKey($targetId)->lockForUpdate()->firstOrFail();
                abort_unless($target->start_date->gt($year->end_date), 422, 'Carry forward to a later financial year.');
                abort_if(LedgerTransaction::where('tenant_id', $tenant)->where('financial_year_id', $targetId)->exists(), 422, 'Target year already has entries; review opening balances manually.');
                foreach (app(FinancialStatementsService::class)->trialBalance($tenant, $yearId) as $row) {
                    if (! in_array($row->type, ['asset', 'liability']) || $row->code === 'OPENING-BAL' || abs($row->balance) < 0.005) {
                        continue;
                    }
                    $head = AccountHead::where('tenant_id', $tenant)->where('code', $row->code)->firstOrFail();
                    app(OpeningBalanceService::class)->save($tenant, $targetId, $head->id, $row->balance > 0 ? 'debit' : 'credit', abs($row->balance), 'Carry forward '.$year->label, $userId);
                }
            }
            DB::table('finance_period_locks')->insert(['tenant_id' => $tenant, 'financial_year_id' => $yearId, 'carry_to_year_id' => $targetId, 'closed_by' => $userId, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
