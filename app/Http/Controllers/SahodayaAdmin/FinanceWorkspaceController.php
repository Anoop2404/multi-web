<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\FestEvent;
use App\Models\FinanceDocument;
use App\Models\FinanceVendor;
use App\Models\FinancialYear;
use App\Models\LedgerTransaction;
use App\Services\Ledger\FinanceWorkspaceService;
use App\Services\Ledger\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FinanceWorkspaceController extends SahodayaAdminController
{
    public function callAction($method, $parameters)
    {
        abort_unless(request()->user()?->hasAnyRole(['sahodaya_admin', 'sahodaya_finance', 'superadmin']), 403, 'Accounts workspace requires Sahodaya finance access.');

        return $this->{$method}(...array_values($parameters));
    }

    private function owned(string $table)
    {
        return Rule::exists($table, 'id')->where('tenant_id', $this->sahodaya->id);
    }

    public function index(Request $r)
    {
        $r->validate(['from' => 'nullable|date', 'to' => $r->filled('from') ? 'nullable|date|after_or_equal:from' : 'nullable|date']);
        app(LedgerPostingService::class)->ensureDefaultHeads($this->sahodaya->id);
        $tenant = $this->sahodaya->id;
        $documents = FinanceDocument::where('tenant_id', $tenant)->with(['vendor', 'accountHead', 'bankAccount'])
            ->when($r->filled('kind'), fn ($q) => $q->where('kind', $r->kind))->when($r->filled('status'), fn ($q) => $q->where('status', $r->status))
            ->when($r->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $r->vendor_id))
            ->when($r->filled('financial_year_id'), fn ($q) => $q->where('financial_year_id', $r->financial_year_id))
            ->when($r->filled('from'), fn ($q) => $q->whereDate('document_date', '>=', $r->from))
            ->when($r->filled('to'), fn ($q) => $q->whereDate('document_date', '<=', $r->to))
            ->when($r->filled('event_id'), fn ($q) => $q->where('event_id', $r->event_id))
            ->when($r->filled('region'), fn ($q) => $q->where('region', $r->region))
            ->orderByDesc('document_date')->orderByDesc('id')->paginate(30)->withQueryString();
        $budgets = DB::table('finance_budgets')->where('tenant_id', $tenant)->get()->map(function ($b) use ($tenant) {
            $q = LedgerTransaction::where('tenant_id', $tenant)->where('account_head_id', $b->account_head_id)->where('financial_year_id', $b->financial_year_id);
            if ($b->event_id) {
                $q->whereIn('reference_type', [FinanceDocument::class, 'finance_document_reversal'])->whereIn('reference_id', FinanceDocument::where('tenant_id', $tenant)->where('event_id', $b->event_id)->select('id'));
            }
            $b->actual = (float) (clone $q)->where('entry_type', 'debit')->sum('amount') - (float) (clone $q)->where('entry_type', 'credit')->sum('amount');
            $b->remaining = round((float) $b->amount - $b->actual, 2);

            return $b;
        });

        return $this->inertia('Sahodaya/Finance/Workspace', [
            'documents' => $documents, 'vendors' => FinanceVendor::where('tenant_id', $tenant)->orderBy('name')->get(),
            'heads' => AccountHead::where('tenant_id', $tenant)->where('is_active', true)->orderBy('name')->get(),
            'banks' => BankAccount::where('tenant_id', $tenant)->where('is_active', true)->get(['id', 'account_name', 'bank_name']),
            'years' => FinancialYear::orderByDesc('start_date')->get(), 'events' => FestEvent::where('tenant_id', $tenant)->get(['id', 'title']),
            'budgets' => $budgets, 'periodLocks' => DB::table('finance_period_locks')->where('tenant_id', $tenant)->get(),
            'advances' => FinanceDocument::where('tenant_id', $tenant)->where('kind', 'advance')->where('status', 'posted')->get(['id', 'amount', 'description']),
            'filters' => $r->only('kind', 'status', 'vendor_id', 'financial_year_id', 'from', 'to', 'event_id', 'region'),
        ]);
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'submission_key' => 'required|uuid', 'kind' => 'required|in:expense,income,transfer,advance,settlement,advance_return',
            'vendor_id' => ['nullable', $this->owned('finance_vendors')], 'account_head_id' => ['nullable', $this->owned('account_heads')],
            'bank_account_id' => ['nullable', $this->owned('bank_accounts')], 'destination_bank_id' => ['nullable', $this->owned('bank_accounts')],
            'event_id' => ['nullable', $this->owned('fest_events')], 'parent_id' => ['required_if:kind,settlement,advance_return', 'nullable', $this->owned('finance_documents')],
            'region' => 'nullable|string|max:100', 'amount' => 'required|numeric|min:0.01|max:999999999.99', 'document_date' => 'required|date',
            'reference' => 'nullable|string|max:255', 'description' => 'required|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:10240', 'recurring' => 'boolean', 'next_due' => 'nullable|date|after:document_date',
        ]);
        if (in_array($d['kind'], ['expense', 'income', 'settlement'])) {
            $head = AccountHead::where('tenant_id', $this->sahodaya->id)->findOrFail($d['account_head_id'] ?? 0);
            abort_unless($head->is_active && $head->type === ($d['kind'] === 'income' ? 'income' : 'expense'), 422, 'Choose the appropriate active account head.');
        }
        if (! in_array($d['kind'], ['settlement', 'advance_return'])) {
            $d['parent_id'] = null;
        }
        $year = FinancialYear::whereDate('start_date', '<=', $d['document_date'])->whereDate('end_date', '>=', $d['document_date'])->first();
        abort_unless($year, 422, 'Create a financial year covering this date first.');
        abort_if(DB::table('finance_period_locks')->where('tenant_id', $this->sahodaya->id)->where('financial_year_id', $year->id)->exists(), 422, 'This year is closed.');
        if (($d['recurring'] ?? false) && (empty($d['next_due']) || ! in_array($d['kind'], ['expense', 'income']))) {
            return back()->withErrors(['next_due' => 'Recurring income/expenses need a next due date.']);
        }
        unset($d['attachment']);
        $path = null;
        try {
            DB::transaction(function () use ($r, $d, $year, &$path) {
                // Serializes repeat submissions, including retried bill uploads.
                FinancialYear::whereKey($year->id)->lockForUpdate()->first();
                abort_if(DB::table('finance_period_locks')->where('tenant_id', $this->sahodaya->id)->where('financial_year_id', $year->id)->exists(), 422, 'This year is closed.');
                if (FinanceDocument::where('tenant_id', $this->sahodaya->id)->where('submission_key', $d['submission_key'])->exists()) {
                    return;
                }
                if ($r->hasFile('attachment')) {
                    $path = $r->file('attachment')->store('finance-bills/'.$this->sahodaya->id, 'local');
                }
                FinanceDocument::create($d + ['tenant_id' => $this->sahodaya->id, 'financial_year_id' => $year->id, 'created_by' => $r->user()->id, 'attachment_path' => $path, 'attachment_name' => $r->file('attachment')?->getClientOriginalName()]);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }

        return back()->with('success', 'Voucher submitted for approval.');
    }

    public function review(Request $r, string $tenantId, FinanceDocument $document, FinanceWorkspaceService $service)
    {
        abort_unless($document->tenant_id === $this->sahodaya->id, 403);
        $d = $r->validate(['action' => 'required|in:approve,reject,reverse', 'reason' => 'required_if:action,reject,reverse|nullable|string|max:1000']);
        if ($d['action'] === 'reverse') {
            $service->reverse($document, $r->user()->id, $d['reason']);
        } elseif ($d['action'] === 'approve') {
            $service->approve($document, $r->user()->id);
        } else {
            DB::transaction(function () use ($document, $r, $d) {
                $doc = FinanceDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
                abort_unless($doc->status === 'pending', 422);
                $doc->update(['status' => 'rejected', 'reviewed_by' => $r->user()->id, 'review_reason' => $d['reason']]);
            });
        }

        return back()->with('success', 'Voucher reviewed.');
    }

    public function attachment(string $tenantId, FinanceDocument $document)
    {
        abort_unless($document->tenant_id === $this->sahodaya->id, 403);
        abort_unless($document->attachment_path, 404);

        return Storage::disk('local')->download($document->attachment_path, basename($document->attachment_name ?: 'bill.pdf'));
    }

    public function voucher(string $tenantId, FinanceDocument $document)
    {
        abort_unless($document->tenant_id === $this->sahodaya->id, 403);

        return view('finance.voucher', ['document' => $document->load('vendor', 'accountHead', 'bankAccount'), 'sahodaya' => $this->sahodaya]);
    }

    public function vendor(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:255', 'email' => 'nullable|email', 'phone' => 'nullable|string|max:30', 'address' => 'nullable|string|max:1000']);
        FinanceVendor::updateOrCreate(['tenant_id' => $this->sahodaya->id, 'name' => $d['name']], $d);

        return back()->with('success', 'Vendor saved.');
    }

    public function budget(Request $r)
    {
        $d = $r->validate(['financial_year_id' => 'required|exists:financial_years,id', 'account_head_id' => ['required', $this->owned('account_heads')], 'event_id' => ['nullable', $this->owned('fest_events')], 'amount' => 'required|numeric|min:0|max:999999999.99']);
        abort_unless(AccountHead::findOrFail($d['account_head_id'])->type === 'expense', 422);
        DB::table('finance_budgets')->updateOrInsert(['tenant_id' => $this->sahodaya->id, 'financial_year_id' => $d['financial_year_id'], 'account_head_id' => $d['account_head_id'], 'event_id' => $d['event_id'] ?? 0], ['amount' => $d['amount'], 'updated_at' => now(), 'created_at' => now()]);

        return back()->with('success', 'Budget saved.');
    }

    public function bank(Request $r)
    {
        $d = $r->validate(['account_name' => 'required|string|max:255', 'bank_name' => 'required|string|max:255', 'account_no' => 'required|string|max:50', 'ifsc' => 'nullable|string|max:20']);
        $bank = BankAccount::firstOrCreate(['tenant_id' => $this->sahodaya->id, 'account_no' => $d['account_no']], $d + ['is_active' => true]);
        app(FinanceWorkspaceService::class)->bankCode($this->sahodaya->id, $bank->id);

        return back()->with('success', 'Bank account added.');
    }

    public function recurring(Request $r, FinanceWorkspaceService $s)
    {
        $count = $s->generateRecurring($this->sahodaya->id, $r->user()->id);

        return back()->with('success', $count.' recurring vouchers created for review.');
    }

    public function closeYear(Request $r, FinanceWorkspaceService $s)
    {
        $d = $r->validate(['financial_year_id' => 'required|exists:financial_years,id', 'carry_to_year_id' => 'nullable|exists:financial_years,id', 'reason' => 'required|string|max:1000']);
        $s->closeYear($this->sahodaya->id, $d['financial_year_id'], $d['carry_to_year_id'] ?? null, $r->user()->id, $d['reason']);

        return back()->with('success', 'Financial year closed. New postings to this year are blocked.');
    }
}
