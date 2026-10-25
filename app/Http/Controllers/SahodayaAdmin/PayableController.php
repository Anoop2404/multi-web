<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\LedgerTransaction;
use App\Models\SahodayaPayable;
use App\Services\Ledger\LedgerPostingService;
use App\Services\Ledger\PayableLedgerService;
use App\Support\FinancialYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayableController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        app(LedgerPostingService::class)->ensureDefaultHeads($this->sahodaya->id);

        $status = $request->get('status', 'open');
        $financialYearId = $request->has('financial_year_id') ? ($request->integer('financial_year_id') ?: null) : null;

        $query = SahodayaPayable::where('tenant_id', $this->sahodaya->id)
            ->with('expenseHead:id,code,name')
            ->when($financialYearId, fn ($q) => $q->where('financial_year_id', $financialYearId))
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['pending', 'partial']))
            ->when($status === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($status === 'all', fn ($q) => $q)
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderByDesc('created_at');

        $payables = $query->paginate(50)->withQueryString();

        $expenseHeads = AccountHead::where('tenant_id', $this->sahodaya->id)
            ->where('type', 'expense')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $totals = [
            'open_amount' => (float) SahodayaPayable::where('tenant_id', $this->sahodaya->id)
                ->when($financialYearId, fn ($q) => $q->where('financial_year_id', $financialYearId))
                ->whereIn('status', ['pending', 'partial'])
                ->get()
                ->sum(fn (SahodayaPayable $p) => $p->balanceDue()),
            'overdue_count' => SahodayaPayable::where('tenant_id', $this->sahodaya->id)
                ->when($financialYearId, fn ($q) => $q->where('financial_year_id', $financialYearId))
                ->whereIn('status', ['pending', 'partial'])
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->count(),
        ];

        return $this->inertia('Sahodaya/Finance/Payables', [
            'payables' => $payables,
            'expenseHeads' => $expenseHeads,
            'totals' => $totals,
            'filters' => ['status' => $status, 'financial_year_id' => $financialYearId],
            'academicYears' => $this->academicYearOptions(),
            'bankAccounts' => BankAccount::where('tenant_id', $this->sahodaya->id)->where('is_active', true)->get(['id', 'account_name']),
            'paymentHistory' => LedgerTransaction::where('tenant_id', $this->sahodaya->id)->where('reference_type', 'payable_payment')->where('entry_type', 'debit')->whereIn('reference_id', $payables->pluck('id'))->get()->groupBy('reference_id'),
        ]);
    }

    public function store(Request $request, PayableLedgerService $ledger)
    {
        $data = $request->validate([
            'vendor_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'nullable|date',
            'incurred_date' => 'nullable|date',
            'expense_head_id' => 'nullable|exists:account_heads,id',
            'record_ledger' => 'boolean',
            'submission_key' => 'nullable|uuid',
        ]);

        if (! empty($data['expense_head_id'])) {
            abort_if(AccountHead::find($data['expense_head_id'])?->tenant_id !== $this->sahodaya->id, 403);
            abort_unless(AccountHead::find($data['expense_head_id'])->type === 'expense', 422, 'Select an expense head.');
        }

        DB::transaction(function () use ($data, $request, $ledger) {
            \App\Models\FinancialYear::whereKey(FinancialYear::currentId())->lockForUpdate()->first();
            if (! empty($data['submission_key']) && SahodayaPayable::where('tenant_id', $this->sahodaya->id)->where('submission_key', $data['submission_key'])->exists()) {
                return;
            }
            $payable = SahodayaPayable::create([
                'submission_key' => $data['submission_key'] ?? null,
                'tenant_id' => $this->sahodaya->id,
                'financial_year_id' => ! empty($data['incurred_date']) ? \App\Models\FinancialYear::whereDate('start_date', '<=', $data['incurred_date'])->whereDate('end_date', '>=', $data['incurred_date'])->value('id') : FinancialYear::currentId(),
                'vendor_name' => $data['vendor_name'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'due_date' => $data['due_date'] ?? null,
                'incurred_date' => $data['incurred_date'] ?? null,
                'expense_head_id' => $data['expense_head_id'] ?? null,
                'status' => 'pending',
                'created_by' => $request->user()?->id,
            ]);

            if ($data['record_ledger'] ?? true) {
                $ledger->recordObligation($payable, $request->user()->id);
            }

        });

        return back()->with('success', 'Payable recorded.');
    }

    public function markPaid(Request $request, string $tenantId, SahodayaPayable $payable, PayableLedgerService $ledger)
    {
        abort_if($payable->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'payment_date' => 'nullable|date',
            'bank_account_id' => 'nullable|integer',
            'reference' => 'nullable|string|max:255',
            'submission_key' => 'nullable|uuid',
        ]);

        $amount = $data['amount'] ?? $payable->balanceDue();

        try {
            $ledger->markPaid($payable, $amount, $request->user()->id, $data['payment_date'] ?? null, $data['bank_account_id'] ?? null, $data['reference'] ?? null, $data['submission_key'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Payment recorded in ledger.');
    }

    public function cancel(Request $request, string $tenantId, SahodayaPayable $payable, PayableLedgerService $ledger)
    {
        abort_if($payable->tenant_id !== $this->sahodaya->id, 403);
        $data = $request->validate(['reason' => 'nullable|string|max:500']);
        $ledger->cancel($payable, $request->user()->id, $data['reason'] ?? 'Cancelled by administrator');

        return back()->with('success', 'Unpaid obligation reversed; payment history retained.');
    }

    /** @return list<array{id: int, label: string, status: string}> */
    private function academicYearOptions(): array
    {
        return \App\Models\FinancialYear::orderByDesc('start_date')->get()->map(fn ($y) => ['id' => $y->id, 'label' => $y->label, 'status' => $y->is_current ? 'current' : 'other'])->all();
    }
}
