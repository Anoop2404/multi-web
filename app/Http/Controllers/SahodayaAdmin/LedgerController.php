<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AccountHead;
use App\Models\LedgerTransaction;
use Illuminate\Http\Request;

class LedgerController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $heads = AccountHead::where('tenant_id', $this->sahodaya->id)
            ->orderBy('code')
            ->get();

        $transactions = LedgerTransaction::where('tenant_id', $this->sahodaya->id)
            ->with('accountHead')
            ->orderByDesc('transaction_date')
            ->limit(100)
            ->get();

        $summary = LedgerTransaction::where('tenant_id', $this->sahodaya->id)
            ->selectRaw('entry_type, SUM(amount) as total')
            ->groupBy('entry_type')
            ->pluck('total', 'entry_type');

        return $this->inertia('Sahodaya/Ledger/Index', [
            'heads'        => $heads,
            'transactions' => $transactions,
            'summary'      => $summary,
        ]);
    }

    public function storeHead(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense,asset,liability',
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        AccountHead::create($data);

        return back()->with('success', 'Account head created.');
    }

    public function storeTransaction(Request $request)
    {
        $data = $request->validate([
            'account_head_id'  => 'required|exists:account_heads,id',
            'entry_type'       => 'required|in:debit,credit',
            'amount'           => 'required|numeric|min:0.01',
            'description'      => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
        ]);

        $head = AccountHead::findOrFail($data['account_head_id']);
        abort_if($head->tenant_id !== $this->sahodaya->id, 403);

        LedgerTransaction::create(array_merge($data, [
            'tenant_id' => $this->sahodaya->id,
            'posted_by' => $request->user()->id,
        ]));

        return back()->with('success', 'Transaction posted.');
    }
}
