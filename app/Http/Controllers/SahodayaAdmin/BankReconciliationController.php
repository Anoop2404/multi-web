<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\BankAccount;
use App\Models\LedgerTransaction;
use Illuminate\Http\Request;

class BankReconciliationController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $bankAccountId = $request->integer('bank_account_id') ?: null;

        $accounts = BankAccount::where('tenant_id', $this->sahodaya->id)
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get();

        $transactions = LedgerTransaction::where('tenant_id', $this->sahodaya->id)
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->with('accountHead')
            ->orderByDesc('transaction_date')
            ->paginate(50)
            ->withQueryString();

        return $this->inertia('Sahodaya/Ledger/BankReconciliation', [
            'bankAccounts'   => $accounts,
            'transactions'   => $transactions,
            'filterBankId'   => $bankAccountId,
        ]);
    }

    public function reconcile(Request $request, string $tenantId, LedgerTransaction $transaction)
    {
        abort_if($transaction->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'reconciled'      => 'required|boolean',
        ]);

        $transaction->update([
            'bank_account_id' => $data['bank_account_id'] ?? $transaction->bank_account_id,
            'reconciled_at'   => $data['reconciled'] ? now() : null,
        ]);

        return back()->with('success', $data['reconciled'] ? 'Transaction marked reconciled.' : 'Reconciliation cleared.');
    }
}
