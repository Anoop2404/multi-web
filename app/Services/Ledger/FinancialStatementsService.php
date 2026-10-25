<?php

namespace App\Services\Ledger;

use App\Models\AccountHead;
use App\Models\LedgerOpeningBalance;
use App\Models\LedgerTransaction;

class FinancialStatementsService
{
    public function trialBalance(string $tenantId, ?int $financialYearId = null, ?string $to = null)
    {
        $heads = AccountHead::where('tenant_id', $tenantId)
            ->orderBy('code')
            ->get();

        return $heads->map(function (AccountHead $head) use ($tenantId, $financialYearId, $to) {
            $opening = $this->openingBalance($tenantId, $head->id, $financialYearId);
            $debits = $this->sumForHead($tenantId, $head->id, 'debit', $financialYearId, $to);
            $credits = $this->sumForHead($tenantId, $head->id, 'credit', $financialYearId, $to);

            return (object) [
                'code' => $head->code,
                'name' => $head->name,
                'type' => $head->type,
                'category' => $head->category,
                'opening' => $opening,
                'debit' => $debits,
                'credit' => $credits,
                'balance' => $opening + $debits - $credits,
            ];
        });
    }

    public function cashBook(string $tenantId, ?int $financialYearId = null, ?string $from = null, ?string $to = null)
    {
        $heads = AccountHead::where('tenant_id', $tenantId)->where('type', 'asset')->where(fn ($q) => $q->where('code', 'CASH-BANK')->orWhere('code', 'like', 'BANK-%'))->get();
        if ($heads->isEmpty()) {
            return collect();
        }

        $txns = LedgerTransaction::where('tenant_id', $tenantId)
            ->whereIn('account_head_id', $heads->pluck('id'))
            ->with('accountHead')
            ->when($financialYearId, fn ($q) => $q->where('financial_year_id', $financialYearId))
            ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('transaction_date', '<=', $to))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $running = $heads->sum(fn ($head) => $this->carryIn($tenantId, $head->id, $financialYearId, $from));

        return $txns->map(function (LedgerTransaction $txn) use (&$running) {
            $running += $txn->entry_type === 'debit' ? (float) $txn->amount : -(float) $txn->amount;
            $txn->setAttribute('running_balance', $running);

            return $txn;
        });
    }

    public function generalLedger(string $tenantId, int $headId, ?int $financialYearId = null, ?string $from = null, ?string $to = null)
    {
        $running = $this->carryIn($tenantId, $headId, $financialYearId, $from);

        return LedgerTransaction::where('tenant_id', $tenantId)
            ->where('account_head_id', $headId)
            ->when($financialYearId, fn ($q) => $q->where('financial_year_id', $financialYearId))
            ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('transaction_date', '<=', $to))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(function (LedgerTransaction $txn) use (&$running) {
                $running += $txn->entry_type === 'debit' ? (float) $txn->amount : -(float) $txn->amount;
                $txn->setAttribute('running_balance', $running);

                return $txn;
            });
    }

    public function incomeAndExpenditure(string $tenantId, ?int $financialYearId = null, ?string $from = null, ?string $to = null): array
    {
        $income = $this->sumByType($tenantId, 'income', $financialYearId, $from, $to, 'credit') - $this->sumByType($tenantId, 'income', $financialYearId, $from, $to, 'debit');
        $expense = $this->sumByType($tenantId, 'expense', $financialYearId, $from, $to, 'debit') - $this->sumByType($tenantId, 'expense', $financialYearId, $from, $to, 'credit');

        return ['income' => $income, 'expense' => $expense, 'surplus' => $income - $expense];
    }

    public function balanceSheet(string $tenantId, ?int $financialYearId = null, ?string $to = null): array
    {
        $rows = $this->trialBalance($tenantId, $financialYearId, $to);
        $assets = (float) $rows->where('type', 'asset')->sum('balance');
        $liabilities = -(float) $rows->where('type', 'liability')->sum('balance');

        return ['assets' => $assets, 'liabilities' => $liabilities, 'equity' => $assets - $liabilities];
    }

    private function carryIn(string $tenantId, int $headId, ?int $yearId, ?string $from): float
    {
        $balance = $this->openingBalance($tenantId, $headId, $yearId);
        if ($from) {
            $balance += (float) LedgerTransaction::where('tenant_id', $tenantId)->where('account_head_id', $headId)
                ->when($yearId, fn ($q) => $q->where('financial_year_id', $yearId))
                ->where('transaction_date', '<', $from)
                ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE -amount END), 0) AS balance")
                ->value('balance');
        }

        return $balance;
    }

    private function openingBalance(string $tenantId, int $headId, ?int $financialYearId): float
    {
        if (! $financialYearId) {
            return 0;
        }

        $row = LedgerOpeningBalance::where('tenant_id', $tenantId)
            ->where('account_head_id', $headId)
            ->whereNull('journal_id')
            ->where('financial_year_id', $financialYearId)
            ->first();

        return (float) ($row?->amount ?? 0) * ($row?->entry_type === 'credit' ? -1 : 1);
    }

    private function sumForHead(string $tenantId, int $headId, string $entryType, ?int $financialYearId, ?string $to = null): float
    {
        return (float) LedgerTransaction::where('tenant_id', $tenantId)
            ->where('account_head_id', $headId)
            ->where('entry_type', $entryType)
            ->when($to, fn ($q) => $q->where('transaction_date', '<=', $to))
            ->when($financialYearId, fn ($q) => $q->where('financial_year_id', $financialYearId))
            ->sum('amount');
    }

    private function sumByType(string $tenantId, string $type, ?int $financialYearId, ?string $from, ?string $to, string $entryType): float
    {
        return (float) LedgerTransaction::query()
            ->where('ledger_transactions.tenant_id', $tenantId)
            ->when($financialYearId, fn ($q) => $q->where('ledger_transactions.financial_year_id', $financialYearId))
            ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('transaction_date', '<=', $to))
            ->join('account_heads', 'account_heads.id', '=', 'ledger_transactions.account_head_id')
            ->where('account_heads.type', $type)
            ->where('ledger_transactions.entry_type', $entryType)
            ->sum('ledger_transactions.amount');
    }
}
