<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AccountHead;
use App\Models\LedgerOpeningBalance;
use App\Models\SahodayaPayable;
use App\Services\Ledger\OpeningBalanceService;
use App\Services\Ledger\PayableLedgerService;
use App\Support\AcademicYear;
use App\Support\FinancialYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayableController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        app(\App\Services\Ledger\LedgerPostingService::class)->ensureDefaultHeads($this->sahodaya->id);

        $status = $request->get('status', 'open');
        $financialYearId = $request->integer('financial_year_id') ?: FinancialYear::currentId();

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
            ->whereIn('type', ['expense', 'liability'])
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $totals = [
            'open_amount'   => (float) SahodayaPayable::where('tenant_id', $this->sahodaya->id)
                ->whereIn('status', ['pending', 'partial'])
                ->get()
                ->sum(fn (SahodayaPayable $p) => $p->balanceDue()),
            'overdue_count'=> SahodayaPayable::where('tenant_id', $this->sahodaya->id)
                ->whereIn('status', ['pending', 'partial'])
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->count(),
        ];

        return $this->inertia('Sahodaya/Finance/Payables', [
            'payables'          => $payables,
            'expenseHeads'      => $expenseHeads,
            'totals'            => $totals,
            'filters'           => ['status' => $status, 'financial_year_id' => $financialYearId],
            'academicYears'     => $this->academicYearOptions(),
        ]);
    }

    public function store(Request $request, PayableLedgerService $ledger)
    {
        $data = $request->validate([
            'vendor_name'     => 'required|string|max:255',
            'description'     => 'nullable|string|max:500',
            'amount'          => 'required|numeric|min:0.01',
            'due_date'        => 'nullable|date',
            'incurred_date'   => 'nullable|date',
            'expense_head_id' => 'nullable|exists:account_heads,id',
            'record_ledger'   => 'boolean',
        ]);

        if (! empty($data['expense_head_id'])) {
            abort_if(AccountHead::find($data['expense_head_id'])?->tenant_id !== $this->sahodaya->id, 403);
        }

        $payable = SahodayaPayable::create([
            'tenant_id'         => $this->sahodaya->id,
            'financial_year_id' => FinancialYear::currentId() ?? AcademicYear::activeId(),
            'vendor_name'       => $data['vendor_name'],
            'description'       => $data['description'] ?? null,
            'amount'            => $data['amount'],
            'due_date'          => $data['due_date'] ?? null,
            'incurred_date'     => $data['incurred_date'] ?? null,
            'expense_head_id'   => $data['expense_head_id'] ?? null,
            'status'            => 'pending',
            'created_by'        => $request->user()?->id,
        ]);

        if ($data['record_ledger'] ?? true) {
            $ledger->recordObligation($payable, $request->user()->id);
        }

        return back()->with('success', 'Payable recorded.');
    }

    public function markPaid(Request $request, string $tenantId, SahodayaPayable $payable, PayableLedgerService $ledger)
    {
        abort_if($payable->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'amount'        => 'nullable|numeric|min:0.01',
            'payment_date'  => 'nullable|date',
        ]);

        $amount = $data['amount'] ?? $payable->balanceDue();

        $ledger->markPaid($payable, $amount, $request->user()->id, $data['payment_date'] ?? null);

        return back()->with('success', 'Payment recorded in ledger.');
    }

    public function cancel(string $tenantId, SahodayaPayable $payable)
    {
        abort_if($payable->tenant_id !== $this->sahodaya->id, 403);
        abort_if($payable->status === 'paid', 422, 'Paid payables cannot be cancelled.');

        $payable->update(['status' => 'cancelled']);

        return back()->with('success', 'Payable cancelled.');
    }

    /** @return list<array{id: int, label: string, status: string}> */
    private function academicYearOptions(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('academic_years')) {
            return [];
        }

        return \App\Models\AcademicYearRecord::orderByDesc('start_date')
            ->get(['id', 'label', 'status'])
            ->map(fn ($y) => ['id' => $y->id, 'label' => $y->label, 'status' => $y->status])
            ->all();
    }
}
