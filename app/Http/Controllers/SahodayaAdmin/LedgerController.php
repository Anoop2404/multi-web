<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AccountHead;
use App\Models\AcademicYearRecord;
use App\Models\LedgerTransaction;
use App\Services\Ledger\LedgerPostingService;
use App\Services\Ledger\LedgerReportingService;
use App\Support\AcademicYear;
use App\Support\FinancialYear;
use App\Support\LedgerAccountCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LedgerController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        app(LedgerPostingService::class)->ensureDefaultHeads($this->sahodaya->id);

        $financialYearId = $this->resolveFinancialYearId($request);

        $heads = AccountHead::where('tenant_id', $this->sahodaya->id)
            ->when($financialYearId, fn ($q) => $q->where(function ($q2) use ($financialYearId) {
                $q2->where('financial_year_id', $financialYearId)->orWhereNull('financial_year_id');
            }))
            ->orderBy('code')
            ->get();

        $transactionQuery = $this->scopedTransactions($financialYearId);

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
            'heads'                 => $heads,
            'transactions'          => $transactions,
            'summary'               => $summary,
            'categoryLabels'        => LedgerAccountCatalog::categoryLabels(),
            'academicYears'         => $this->academicYearOptions(),
            'filterFinancialYearId' => $financialYearId,
        ]);
    }

    public function reports(Request $request, LedgerReportingService $reporting)
    {
        $from = $request->get('from');
        $to   = $request->get('to');
        $category = $request->get('category');
        $financialYearId = $this->resolveFinancialYearId($request);

        $base = $this->scopedTransactions($financialYearId)
            ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from))
            ->when($to,   fn ($q) => $q->where('transaction_date', '<=', $to));

        $byHead = (clone $base)
            ->join('account_heads', 'account_heads.id', '=', 'ledger_transactions.account_head_id')
            ->when($category, fn ($q) => $q->where('account_heads.category', $category))
            ->selectRaw('account_heads.code, account_heads.name, account_heads.category, ledger_transactions.entry_type, SUM(ledger_transactions.amount) as total')
            ->groupBy('account_heads.code', 'account_heads.name', 'account_heads.category', 'ledger_transactions.entry_type')
            ->orderBy('account_heads.code')
            ->get();

        $monthExpr = DB::connection()->getDriverName() === 'pgsql'
            ? "to_char(transaction_date, 'YYYY-MM')"
            : "DATE_FORMAT(transaction_date, '%Y-%m')";

        $monthly = (clone $base)
            ->join('account_heads', 'account_heads.id', '=', 'ledger_transactions.account_head_id')
            ->when($category, fn ($q) => $q->where('account_heads.category', $category))
            ->selectRaw("{$monthExpr} as month, ledger_transactions.entry_type, SUM(ledger_transactions.amount) as total")
            ->groupByRaw("{$monthExpr}, ledger_transactions.entry_type")
            ->orderByRaw("{$monthExpr} desc")
            ->get();

        $byCategory = $reporting->summaryByCategory($this->sahodaya->id, $from, $to, $financialYearId);

        return $this->inertia('Sahodaya/Ledger/Reports', [
            'byHead'                => $byHead,
            'byCategory'            => $byCategory,
            'monthly'               => $monthly,
            'eventHeads'            => $reporting->eventIncomeHeads($this->sahodaya->id),
            'sportsHeads'           => $reporting->sportsIncomeHeads($this->sahodaya->id),
            'filterFrom'            => $from,
            'filterTo'              => $to,
            'filterCategory'        => $category,
            'categoryLabels'        => LedgerAccountCatalog::categoryLabels(),
            'academicYears'         => $this->academicYearOptions(),
            'filterFinancialYearId' => $financialYearId,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $from = $request->get('from');
        $to   = $request->get('to');
        $financialYearId = $this->resolveFinancialYearId($request);

        $transactions = $this->scopedTransactions($financialYearId)
            ->with('accountHead')
            ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from))
            ->when($to,   fn ($q) => $q->where('transaction_date', '<=', $to))
            ->when($request->get('category'), function ($q) use ($request) {
                $q->whereHas('accountHead', fn ($h) => $h->where('category', $request->get('category')));
            })
            ->orderByDesc('transaction_date')
            ->get();

        $filename = 'ledger-'.($from ?? 'all').'-to-'.($to ?? 'all').'.csv';

        return response()->streamDownload(function () use ($transactions) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Category', 'Head Code', 'Account Head', 'Type', 'Amount', 'Description']);
            foreach ($transactions as $t) {
                fputcsv($out, [
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

    public function storeHead(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense,asset,liability',
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['financial_year_id'] = AcademicYear::activeId();
        AccountHead::create($data);

        return back()->with('success', 'Account head created.');
    }

    public function destroyHead(Request $request, string $tenantId, AccountHead $head)
    {
        abort_if($head->tenant_id !== $this->sahodaya->id, 403);
        abort_if(LedgerTransaction::where('account_head_id', $head->id)->exists(), 422, 'Cannot delete a head that has transactions.');

        $head->delete();

        return back()->with('success', 'Account head deleted.');
    }

    public function storeTransaction(Request $request, LedgerPostingService $posting)
    {
        $data = $request->validate([
            'account_head_id'         => 'required|exists:account_heads,id',
            'counter_account_head_id' => 'nullable|exists:account_heads,id',
            'entry_type'              => 'required|in:debit,credit',
            'amount'                  => 'required|numeric|min:0.01',
            'description'             => 'nullable|string|max:255',
            'transaction_date'        => 'required|date',
        ]);

        $head = AccountHead::findOrFail($data['account_head_id']);
        abort_if($head->tenant_id !== $this->sahodaya->id, 403);

        if ($head->code === 'CASH-BANK' && empty($data['counter_account_head_id'])) {
            return back()->withErrors(['counter_account_head_id' => 'Select a counter account when posting to Cash & Bank.']);
        }

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

        return back()->with('success', 'Balanced journal posted (debit + credit).');
    }

    public function storeExpense(Request $request, LedgerPostingService $posting)
    {
        $data = $request->validate([
            'account_head_id'  => 'required|exists:account_heads,id',
            'amount'           => 'required|numeric|min:0.01',
            'description'      => 'nullable|string|max:255',
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

    private function resolveFinancialYearId(Request $request): ?int
    {
        if ($request->has('financial_year_id')) {
            $id = $request->integer('financial_year_id');

            return $id > 0 ? $id : null;
        }

        return FinancialYear::currentId() ?? AcademicYear::activeId();
    }

    /** @return list<array{id: int, label: string, status: string}> */
    private function academicYearOptions(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('academic_years')) {
            return [];
        }

        return AcademicYearRecord::orderByDesc('start_date')
            ->get(['id', 'label', 'status'])
            ->map(fn (AcademicYearRecord $y) => [
                'id'     => $y->id,
                'label'  => $y->label,
                'status' => $y->status,
            ])
            ->all();
    }

    private function scopedTransactions(?int $financialYearId)
    {
        return LedgerTransaction::where('tenant_id', $this->sahodaya->id)
            ->when($financialYearId, fn ($q) => $q->where('financial_year_id', $financialYearId));
    }
}
