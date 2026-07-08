<?php

namespace App\Services\Ledger;

use App\Models\AccountHead;
use App\Models\LedgerOpeningBalance;
use App\Models\LedgerTransaction;

class FinancialStatementsService
{
    public function trialBalance(string $tenantId, ?int $financialYearId = null)
    {
        $heads = AccountHead::where('tenant_id', $tenantId)
            ->when($financialYearId, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('financial_year_id', $financialYearId)->orWhereNull('financial_year_id')))
            ->orderBy('code')
            ->get();

        return $heads->map(function (AccountHead $head) use ($tenantId, $financialYearId) {
            $opening = $this->openingBalance($tenantId, $head->id, $financialYearId);
            $debits = $this->sumForHead($tenantId, $head->id, 'debit', $financialYearId);
            $credits = $this->sumForHead($tenantId, $head->id, 'credit', $financialYearId);

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
        $head = AccountHead::where('tenant_id', $tenantId)->where('code', 'CASH-BANK')->first();
        if (! $head) {
            return collect();
        }

        $txns = LedgerTransaction::where('tenant_id', $tenantId)
            ->where('account_head_id', $head->id)
            ->when($financialYearId, fn ($q) => $q->where('financial_year_id', $financialYearId))
            ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('transaction_date', '<=', $to))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $running = $this->openingBalance($tenantId, $head->id, $financialYearId);

        return $txns->map(function (LedgerTransaction $txn) use (&$running) {
            $running += $txn->entry_type === 'debit' ? (float) $txn->amount : -(float) $txn->amount;
            $txn->setAttribute('running_balance', $running);
            return $txn;
        });
    }

    public function generalLedger(string $tenantId, int $headId, ?int $financialYearId = null, ?string $from = null, ?string $to = null)
    {
        $running = $this->openingBalance($tenantId, $headId, $financialYearId);

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
        $income = $this->sumByType($tenantId, 'income', $financialYearId, $from, $to, 'credit');
        $expense = $this->sumByType($tenantId, 'expense', $financialYearId, $from, $to, 'debit');

        return ['income' => $income, 'expense' => $expense, 'surplus' => $income - $expense];
    }

    public function balanceSheet(string $tenantId, ?int $financialYearId = null): array
    {
        $assets = $this->sumByType($tenantId, 'asset', $financialYearId, null, null, 'debit')
            - $this->sumByType($tenantId, 'asset', $financialYearId, null, null, 'credit');
        $liabilities = $this->sumByType($tenantId, 'liability', $financialYearId, null, null, 'credit')
            - $this->sumByType($tenantId, 'liability', $financialYearId, null, null, 'debit');

        return ['assets' => $assets, 'liabilities' => $liabilities, 'equity' => $assets - $liabilities];
    }

    private function openingBalance(string $tenantId, int $headId, ?int $financialYearId): float
    {
        if (! $financialYearId) {
            return 0;
        }

        $row = LedgerOpeningBalance::where('tenant_id', $tenantId)
            ->where('account_head_id', $headId)
            ->where('financial_year_id', $financialYearId)
            ->first();

        return (float) ($row?->amount ?? 0);
    }

    private function sumForHead(string $tenantId, int $headId, string $entryType, ?int $financialYearId): float
    {
        return (float) LedgerTransaction::where('tenant_id', $tenantId)
            ->where('account_head_id', $headId)
            ->where('entry_type', $entryType)
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
