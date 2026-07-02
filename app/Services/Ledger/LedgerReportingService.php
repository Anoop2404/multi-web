<?php

namespace App\Services\Ledger;

use App\Models\AccountHead;
use App\Models\FestEvent;
use App\Models\FestSchoolEventFee;
use App\Models\LedgerTransaction;
use App\Support\LedgerAccountCatalog;
use Illuminate\Support\Collection;

class LedgerReportingService
{
    /** @return Collection<int, object> */
    public function summaryByCategory(string $tenantId, ?string $from = null, ?string $to = null, ?int $financialYearId = null): Collection
    {
        $base = LedgerTransaction::query()
            ->where('ledger_transactions.tenant_id', $tenantId)
            ->when($financialYearId, fn ($q) => $q->where('ledger_transactions.financial_year_id', $financialYearId))
            ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('transaction_date', '<=', $to))
            ->join('account_heads', 'account_heads.id', '=', 'ledger_transactions.account_head_id');

        return (clone $base)
            ->selectRaw("COALESCE(account_heads.category, 'other') as category, ledger_transactions.entry_type, SUM(ledger_transactions.amount) as total")
            ->groupByRaw("COALESCE(account_heads.category, 'other'), ledger_transactions.entry_type")
            ->orderBy('category')
            ->get();
    }

    /** @return Collection<int, object> */
    public function eventIncomeHeads(string $tenantId): Collection
    {
        return AccountHead::query()
            ->where('tenant_id', $tenantId)
            ->where('category', 'event')
            ->whereNotNull('event_id')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'event_id']);
    }

    /** @return Collection<int, object> */
    public function sportsIncomeHeads(string $tenantId): Collection
    {
        return AccountHead::query()
            ->where('tenant_id', $tenantId)
            ->where('category', 'sports')
            ->whereNotNull('event_id')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'event_id']);
    }

    /** @return array{head: ?AccountHead, transactions: Collection, school_payments: Collection, summary: array<string, mixed>} */
    public function eventPaymentLedger(FestEvent $event): array
    {
        $code = LedgerAccountCatalog::festIncomeCode($event);
        $head = AccountHead::where('tenant_id', $event->tenant_id)
            ->where('code', $code)
            ->first();

        $transactions = $head
            ? LedgerTransaction::where('tenant_id', $event->tenant_id)
                ->where('account_head_id', $head->id)
                ->orderByDesc('transaction_date')
                ->limit(200)
                ->get()
            : collect();

        $schoolPayments = FestSchoolEventFee::where('event_id', $event->id)
            ->with(['school', 'feeReceipt'])
            ->orderBy('school_id')
            ->get()
            ->map(fn (FestSchoolEventFee $fee) => [
                'school'          => $fee->school?->name ?? $fee->school_id,
                'status'          => $fee->status,
                'total_due'       => (float) $fee->total_due,
                'receipt_number'  => $fee->feeReceipt?->receipt_number,
                'payment_date'    => $fee->feeReceipt?->payment_date?->toDateString(),
                'transaction_ref' => $fee->feeReceipt?->transaction_ref,
                'ledger_posted'   => $fee->status === 'approved' && $fee->feeReceipt?->status === 'approved',
            ]);

        $collected = (float) $schoolPayments->where('status', 'approved')->sum('total_due');

        return [
            'head'             => $head,
            'account_code'     => $code,
            'account_name'     => $head?->name ?? LedgerAccountCatalog::festIncomeHeadName($event),
            'transactions'     => $transactions,
            'school_payments'  => $schoolPayments,
            'summary'          => [
                'total_due'      => (float) $schoolPayments->sum('total_due'),
                'collected'      => $collected,
                'pending'        => $schoolPayments->where('status', 'pending')->count(),
                'awaiting'       => $schoolPayments->where('status', 'proof_uploaded')->count(),
                'ledger_credits' => (float) $transactions->where('entry_type', 'credit')->sum('amount'),
            ],
        ];
    }
}
