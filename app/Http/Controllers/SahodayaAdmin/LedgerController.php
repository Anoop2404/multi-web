<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AccountHead;
use App\Models\FeeReceipt;
use App\Models\FinanceDocument;
use App\Models\LedgerOpeningBalance;
use App\Models\LedgerTransaction;
use App\Models\SahodayaPayable;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Ledger\FeeWaiverService;
use App\Services\Ledger\FinancialStatementsService;
use App\Services\Ledger\LedgerPostingService;
use App\Services\Ledger\LedgerReportingService;
use App\Services\Ledger\OpeningBalanceService;
use App\Support\CsvSafety;
use App\Support\FinancialYear;
use App\Support\LedgerAccountCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LedgerController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        app(LedgerPostingService::class)->ensureDefaultHeads($this->sahodaya->id);

        $financialYearId = $this->resolveFinancialYearId($request);

        $heads = AccountHead::where('tenant_id', $this->sahodaya->id)
            ->orderBy('code')
            ->get();

        $request->validate(['from' => 'nullable|date', 'to' => $request->filled('from') ? 'nullable|date|after_or_equal:from' : 'nullable|date']);
        $transactionQuery = $this->scopedTransactions($financialYearId)
            ->when($request->filled('from'), fn ($q) => $q->whereDate('transaction_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('transaction_date', '<=', $request->to))
            ->when($request->filled('account_head_id'), fn ($q) => $q->where('account_head_id', $request->integer('account_head_id')));

        $transactions = (clone $transactionQuery)
            ->with('accountHead')
            ->orderByDesc('transaction_date')
            ->paginate(50)
            ->withQueryString();

        $summary = (clone $transactionQuery)
            ->selectRaw('entry_type, SUM(amount) as total')
            ->groupBy('entry_type')
            ->pluck('total', 'entry_type');

        return $this->inertia('Sahodaya/Ledger/Index', [
            'heads' => $heads,
            'protectedHeadCodes' => LedgerAccountCatalog::defaultCodes(),
            'transactions' => $transactions,
            'summary' => $summary,
            'financialOverview' => app(FinancialStatementsService::class)->incomeAndExpenditure($this->sahodaya->id, $financialYearId) + ['cash' => app(FinancialStatementsService::class)->trialBalance($this->sahodaya->id, $financialYearId)->filter(fn ($h) => $h->code === 'CASH-BANK' || str_starts_with($h->code, 'BANK-'))->sum('balance')],
            'categoryLabels' => LedgerAccountCatalog::categoryLabels(),
            'academicYears' => $this->academicYearOptions(),
            'filterFinancialYearId' => $financialYearId,
            'transactionFilters' => $request->only('from', 'to', 'account_head_id'),
        ]);
    }

    public function reports(Request $request, LedgerReportingService $reporting)
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $category = $request->get('category');
        $financialYearId = $this->resolveFinancialYearId($request);

        $base = $this->scopedTransactions($financialYearId)
            ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('transaction_date', '<=', $to));

        $byHead = (clone $base)
            ->join('account_heads', 'account_heads.id', '=', 'ledger_transactions.account_head_id')
            ->when($category, fn ($q) => $q->where('account_heads.category', $category))
            ->selectRaw('account_heads.code, account_heads.name, account_heads.category, ledger_transactions.entry_type, SUM(ledger_transactions.amount) as total')
            ->groupBy('account_heads.code', 'account_heads.name', 'account_heads.category', 'ledger_transactions.entry_type')
            ->orderBy('account_heads.code')
            ->get();

        $monthExpr = match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char(transaction_date, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', transaction_date)",
            default => "DATE_FORMAT(transaction_date, '%Y-%m')",
        };

        $monthly = (clone $base)
            ->join('account_heads', 'account_heads.id', '=', 'ledger_transactions.account_head_id')
            ->when($category, fn ($q) => $q->where('account_heads.category', $category))
            ->selectRaw("{$monthExpr} as month, ledger_transactions.entry_type, SUM(ledger_transactions.amount) as total")
            ->groupByRaw("{$monthExpr}, ledger_transactions.entry_type")
            ->orderByRaw("{$monthExpr} desc")
            ->get();

        $byCategory = $reporting->summaryByCategory($this->sahodaya->id, $from, $to, $financialYearId);

        return $this->inertia('Sahodaya/Ledger/Reports', [
            'byHead' => $byHead,
            'byCategory' => $byCategory,
            'monthly' => $monthly,
            'eventHeads' => $reporting->eventIncomeHeads($this->sahodaya->id),
            'sportsHeads' => $reporting->sportsIncomeHeads($this->sahodaya->id),
            'mcqHeads' => $reporting->mcqExamIncomeHeads($this->sahodaya->id),
            'trainingHeads' => $reporting->trainingProgramIncomeHeads($this->sahodaya->id),
            'filterFrom' => $from,
            'filterTo' => $to,
            'filterCategory' => $category,
            'categoryLabels' => LedgerAccountCatalog::categoryLabels(),
            'academicYears' => $this->academicYearOptions(),
            'filterFinancialYearId' => $financialYearId,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $financialYearId = $this->resolveFinancialYearId($request);

        $transactions = $this->scopedTransactions($financialYearId)
            ->with('accountHead')
            ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('transaction_date', '<=', $to))
            ->when($request->get('category'), function ($q) use ($request) {
                $q->whereHas('accountHead', fn ($h) => $h->where('category', $request->get('category')));
            })
            ->orderByDesc('transaction_date')
            ->get();

        $filename = 'ledger-'.($from ?? 'all').'-to-'.($to ?? 'all').'.csv';

        app(PlatformAuditLogger::class)->reportDownloaded('ledger_export', ['from' => $from, 'to' => $to]);

        return response()->streamDownload(function () use ($transactions) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['Date', 'Category', 'Head Code', 'Account Head', 'Type', 'Amount', 'Description']);
            foreach ($transactions as $t) {
                CsvSafety::fputcsv($out, [
                    $t->transaction_date,
                    $t->accountHead?->category ?? '',
                    $t->accountHead?->code ?? '',
                    $t->accountHead?->name ?? '',
                    $t->entry_type,
                    $t->amount,
                    $t->description ?? '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function financialStatements(Request $request, FinancialStatementsService $statements)
    {
        $financialYearId = $this->resolveFinancialYearId($request);
        $from = $request->get('from');
        $to = $request->get('to');
        $request->validate(['from' => 'nullable|date', 'to' => $request->filled('from') ? 'nullable|date|after_or_equal:from' : 'nullable|date', 'head_id' => ['nullable', Rule::exists('account_heads', 'id')->where('tenant_id', $this->sahodaya->id)]]);
        $headId = $request->integer('head_id') ?: null;

        $data = [
            'trialBalance' => $statements->trialBalance($this->sahodaya->id, $financialYearId, $to),
            'cashBook' => $statements->cashBook($this->sahodaya->id, $financialYearId, $from, $to),
            'incomeExpenditure' => $statements->incomeAndExpenditure($this->sahodaya->id, $financialYearId, $from, $to),
            'balanceSheet' => $statements->balanceSheet($this->sahodaya->id, $financialYearId, $to),
            'generalLedger' => $headId ? $statements->generalLedger($this->sahodaya->id, $headId, $financialYearId, $from, $to) : collect(),
            'monthlyIncome' => $this->monthlyIncomeTrend($financialYearId),
            'heads' => AccountHead::where('tenant_id', $this->sahodaya->id)->orderBy('code')->get(['id', 'code', 'name']),
            'filterFrom' => $from,
            'filterTo' => $to,
            'filterHeadId' => $headId,
            'filterFinancialYearId' => $financialYearId,
            'academicYears' => $this->academicYearOptions(),
        ];
        if ($request->get('format') === 'csv') {
            $report = $request->get('report', 'trialBalance');
            abort_unless(in_array($report, ['trialBalance', 'cashBook', 'generalLedger'], true), 422);

            return response()->streamDownload(function () use ($data, $report) {
                $out = fopen('php://output', 'w');
                CsvSafety::fputcsv($out, $report === 'trialBalance' ? ['Code', 'Account', 'Debit', 'Credit', 'Balance'] : ['Date', 'Description', 'Type', 'Amount', 'Running balance']);
                foreach ($data[$report] as $row) {
                    CsvSafety::fputcsv($out, $report === 'trialBalance' ? [$row->code, $row->name, $row->debit, $row->credit, $row->balance] : [$row->transaction_date?->format('Y-m-d'), $row->description, $row->entry_type, $row->amount, $row->running_balance]);
                }
                fclose($out);
            }, $report.'.csv', ['Content-Type' => 'text/csv']);
        }

        return $this->inertia('Sahodaya/Ledger/FinancialStatements', $data);
    }

    public function waiveFee(Request $request, FeeWaiverService $waiver)
    {
        $data = $request->validate([
            'fee_receipt_id' => 'required|exists:fee_receipts,id',
            'waiver_amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
        ]);

        $receipt = FeeReceipt::findOrFail($data['fee_receipt_id']);
        $owner = $receipt->feeable;
        $tenantId = $owner?->tenant_id ?? $owner?->event?->tenant_id ?? $owner?->exam?->tenant_id ?? $owner?->school?->parent_id;
        abort_unless($tenantId === $this->sahodaya->id, 403);
        abort_if($receipt->status === FeeReceipt::STATUS_APPROVED, 422, 'Reverse an approved receipt before changing its amount.');
        $waiver->apply($receipt, (float) $data['waiver_amount'], $data['reason'], $request->user()?->id);

        return back()->with('success', 'Fee waiver applied.');
    }

    public function storeHead(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('account_heads')->where('tenant_id', $this->sahodaya->id)],
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense,asset,liability',
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['financial_year_id'] = FinancialYear::currentId();
        AccountHead::create($data);

        return back()->with('success', 'Account head created.');
    }

    public function destroyHead(Request $request, string $tenantId, AccountHead $head)
    {
        abort_if($head->tenant_id !== $this->sahodaya->id, 403);
        abort_if(LedgerTransaction::where('account_head_id', $head->id)->exists(), 422, 'Cannot delete a head that has transactions.');
        abort_if(in_array($head->code, LedgerAccountCatalog::defaultCodes()) || str_starts_with($head->code, 'BANK-') || $head->code === 'ADVANCES', 422, 'System accounts cannot be removed.');
        abort_if(FinanceDocument::where('account_head_id', $head->id)->exists() || SahodayaPayable::where('expense_head_id', $head->id)->exists() || DB::table('finance_budgets')->where('account_head_id', $head->id)->exists(), 422, 'This account is used by a voucher, bill or budget.');

        $head->delete();

        return back()->with('success', 'Account head deleted.');
    }

    public function storeTransaction(Request $request, LedgerPostingService $posting)
    {
        $data = $request->validate([
            'account_head_id' => 'required|exists:account_heads,id',
            'counter_account_head_id' => 'nullable|exists:account_heads,id',
            'entry_type' => 'required|in:debit,credit',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
        ]);

        $head = AccountHead::findOrFail($data['account_head_id']);
        abort_if($head->tenant_id !== $this->sahodaya->id, 403);

        if ($head->code === 'CASH-BANK' && empty($data['counter_account_head_id'])) {
            return back()->withErrors(['counter_account_head_id' => 'Select a counter account when posting to Cash & Bank.']);
        }

        try {
            $posting->postManualPair(
                $this->sahodaya->id,
                (int) $data['account_head_id'],
                $data['entry_type'],
                $data['amount'],
                $data['description'] ?? null,
                $data['transaction_date'],
                $request->user()->id,
                isset($data['counter_account_head_id']) ? (int) $data['counter_account_head_id'] : null,
            );

        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['counter_account_head_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Balanced journal posted (debit + credit).');
    }

    public function storeExpense(Request $request, LedgerPostingService $posting)
    {
        $data = $request->validate([
            'account_head_id' => 'required|exists:account_heads,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
        ]);

        $head = AccountHead::findOrFail($data['account_head_id']);
        abort_if($head->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($head->type === 'expense', 422, 'Select an expense account head.');

        $posting->postManualPair(
            $this->sahodaya->id,
            (int) $data['account_head_id'],
            'debit',
            $data['amount'],
            $data['description'] ?? null,
            $data['transaction_date'],
            $request->user()->id,
            null,
        );

        return back()->with('success', 'Expense posted.');
    }

    public function openingBalances(Request $request, OpeningBalanceService $openings)
    {
        app(LedgerPostingService::class)->ensureDefaultHeads($this->sahodaya->id);

        $financialYearId = $request->integer('financial_year_id') ?: (FinancialYear::currentId());

        $heads = AccountHead::where('tenant_id', $this->sahodaya->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return $this->inertia('Sahodaya/Ledger/OpeningBalances', [
            'heads' => $heads,
            'openingBalances' => $openings->listForTenant($this->sahodaya->id, $financialYearId),
            'academicYears' => $this->academicYearOptions(),
            'filterFinancialYearId' => $financialYearId,
        ]);
    }

    public function storeOpeningBalance(Request $request, OpeningBalanceService $openings)
    {
        $data = $request->validate([
            'financial_year_id' => 'required|integer|exists:financial_years,id',
            'account_head_id' => 'required|exists:account_heads,id',
            'entry_type' => 'required|in:debit,credit',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:255',
        ]);

        $openings->save(
            $this->sahodaya->id,
            (int) $data['financial_year_id'],
            (int) $data['account_head_id'],
            $data['entry_type'],
            $data['amount'],
            $data['notes'] ?? null,
            $request->user()->id,
        );

        return back()->with('success', 'Opening balance posted.');
    }

    public function destroyOpeningBalance(string $tenantId, LedgerOpeningBalance $openingBalance, OpeningBalanceService $openings)
    {
        abort_if($openingBalance->tenant_id !== $this->sahodaya->id, 403);

        $openings->remove($openingBalance);

        return back()->with('success', 'Opening balance removed.');
    }

    public function updateAccountHead(Request $request, string $tenantId, AccountHead $head)
    {
        abort_if($head->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $head->update(['name' => $data['name']]);

        return back()->with('success', 'Ledger account name updated.');
    }

    private function resolveFinancialYearId(Request $request): ?int
    {
        if ($request->has('financial_year_id')) {
            $id = $request->integer('financial_year_id');

            return $id > 0 ? $id : null;
        }

        return FinancialYear::currentId();
    }

    /** @return list<array{id: int, label: string, status: string}> */
    private function academicYearOptions(): array
    {
        return \App\Models\FinancialYear::orderByDesc('start_date')->get()
            ->map(fn ($y) => ['id' => $y->id, 'label' => $y->label, 'status' => $y->is_current ? 'current' : 'other'])->all();
    }

    private function scopedTransactions(?int $financialYearId)
    {
        // Columns qualified with the table name: reports() joins account_heads, which also has
        // tenant_id and financial_year_id columns — unqualified names are ambiguous once joined.
        return LedgerTransaction::where('ledger_transactions.tenant_id', $this->sahodaya->id)
            ->when($financialYearId, fn ($q) => $q->where('ledger_transactions.financial_year_id', $financialYearId));
    }

    /** @return list<array{month: string, income: float, expense: float}> */
    private function monthlyIncomeTrend(?int $financialYearId): array
    {
        $from = now()->subMonths(11)->startOfMonth();

        return LedgerTransaction::query()
            ->where('tenant_id', $this->sahodaya->id)
            ->when($financialYearId, fn ($q) => $q->where('financial_year_id', $financialYearId))
            ->where('transaction_date', '>=', $from)
            ->with('accountHead:id,type')
            ->get()
            ->groupBy(fn ($t) => $t->transaction_date?->format('Y-m') ?? 'unknown')
            ->map(fn ($group, $month) => [
                'month' => $month,
                'income' => round((float) $group->filter(fn ($t) => $t->accountHead?->type === 'income')->sum(fn ($t) => $t->entry_type === 'credit' ? $t->amount : -$t->amount), 2),
                'expense' => round((float) $group->filter(fn ($t) => $t->accountHead?->type === 'expense')->sum(fn ($t) => $t->entry_type === 'debit' ? $t->amount : -$t->amount), 2),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }
}
