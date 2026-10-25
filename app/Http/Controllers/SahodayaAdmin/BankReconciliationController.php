<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\BankAccount;
use App\Models\LedgerTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankReconciliationController extends SahodayaAdminController
{
    private function bank(int $id)
    {
        return BankAccount::where('tenant_id', $this->sahodaya->id)->where('is_active', true)->findOrFail($id);
    }

    private function cashRows()
    {
        return LedgerTransaction::where('tenant_id', $this->sahodaya->id)->whereHas('accountHead', fn ($q) => $q->where('type', 'asset')->where(fn ($q) => $q->where('code', 'CASH-BANK')->orWhere('code', 'like', 'BANK-%')));
    }

    public function index(Request $r)
    {
        $bankId = $r->integer('bank_account_id') ?: null;
        if ($bankId) {
            $this->bank($bankId);
        }
        $base = $this->cashRows()->when($bankId, fn ($q) => $q->where('bank_account_id', $bankId));
        $filtered = (clone $base)->when($r->status === 'open', fn ($q) => $q->whereNull('reconciled_at'))->when($r->status === 'reconciled', fn ($q) => $q->whereNotNull('reconciled_at'));
        $lines = DB::table('finance_statement_lines')->where('tenant_id', $this->sahodaya->id)->when($bankId, fn ($q) => $q->where('bank_account_id', $bankId))->orderByDesc('transaction_date')->paginate(30, ['*'], 'statement_page')->withQueryString();
        $lines->getCollection()->transform(function ($line) {
            $line->suggestions = $line->ledger_transaction_id ? [] : $this->cashRows()->whereNull('reconciled_at')->where(fn ($q) => $q->where('bank_account_id', $line->bank_account_id)->orWhereNull('bank_account_id'))
                ->where('entry_type', $line->amount > 0 ? 'debit' : 'credit')->where('amount', abs($line->amount))->whereBetween('transaction_date', [Carbon::parse($line->transaction_date)->subDays(3)->toDateString(), Carbon::parse($line->transaction_date)->addDays(3)->toDateString()])->limit(10)->get(['id', 'amount', 'entry_type', 'transaction_date', 'description'])->all();

            return $line;
        });

        return $this->inertia('Sahodaya/Ledger/BankReconciliation', [
            'bankAccounts' => BankAccount::where('tenant_id', $this->sahodaya->id)->where('is_active', true)->get(['id', 'account_name']),
            'transactions' => $filtered->with('accountHead')->orderByDesc('transaction_date')->paginate(50)->withQueryString(),
            'filterBankId' => $bankId, 'filterStatus' => $r->status ?? 'all', 'statementLines' => $lines,
            'totals' => ['debits' => (float) (clone $base)->where('entry_type', 'debit')->sum('amount'), 'credits' => (float) (clone $base)->where('entry_type', 'credit')->sum('amount'), 'unreconciled' => (clone $base)->whereNull('reconciled_at')->count()],
        ]);
    }

    public function reconcile(Request $r, string $tenantId, LedgerTransaction $transaction)
    {
        abort_if($transaction->tenant_id !== $this->sahodaya->id, 403);
        $d = $r->validate(['bank_account_id' => 'nullable|exists:bank_accounts,id', 'reconciled' => 'required|boolean']);
        if (! empty($d['bank_account_id'])) {
            abort_unless(BankAccount::where('tenant_id', $this->sahodaya->id)->whereKey($d['bank_account_id'])->where('is_active', true)->exists(), 403);
        }
        abort_unless($this->cashRows()->whereKey($transaction->id)->exists(), 422, 'Only cash/bank journal lines can be reconciled.');
        if ($transaction->bank_account_id && ! empty($d['bank_account_id'])) {
            abort_unless((int) $transaction->bank_account_id === (int) $d['bank_account_id'], 422, 'Use the bank already assigned to this entry.');
        }
        if (! $d['reconciled']) {
            DB::table('finance_statement_lines')->where('tenant_id', $this->sahodaya->id)->where('ledger_transaction_id', $transaction->id)->update(['ledger_transaction_id' => null, 'matched_by' => null, 'updated_at' => now()]);
        }
        $code = $transaction->accountHead?->code;
        if (! empty($d['bank_account_id']) && str_starts_with($code ?? '', 'BANK-')) {
            abort_unless($code === 'BANK-'.$d['bank_account_id'], 422, 'Bank ledger does not match the selected bank.');
        }
        $transaction->update(['bank_account_id' => $d['bank_account_id'] ?? $transaction->bank_account_id, 'reconciled_at' => $d['reconciled'] ? now() : null]);

        return back()->with('success', 'Reconciliation updated.');
    }

    public function import(Request $r)
    {
        $r->validate(['bank_account_id' => 'required|integer', 'statement' => 'required|file|max:2048']);
        $bank = $this->bank($r->integer('bank_account_id'));
        $file = $r->file('statement');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $expected = ['date', 'description', 'amount'];
        if (! $header || array_map(fn ($v) => strtolower(trim(ltrim($v, "\xEF\xBB\xBF"))), $header) !== $expected) {
            fclose($handle);
            throw ValidationException::withMessages(['statement' => 'CSV columns must be date,description,amount. Use YYYY-MM-DD and signed amounts: deposits positive, withdrawals negative.']);
        }
        $rows = [];
        $hash = hash_file('sha256', $file->getRealPath());
        $i = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $i++;
            if ($i > 5000) {
                fclose($handle);
                throw ValidationException::withMessages(['statement' => 'Import up to 5,000 rows at a time.']);
            }
            if (count($row) !== 3 || ! Carbon::canBeCreatedFromFormat($row[0], 'Y-m-d') || ! is_numeric($row[2]) || ! is_finite((float) $row[2]) || abs((float) $row[2]) < 0.005 || abs((float) $row[2]) > 999999999.99 || mb_strlen($row[1]) > 255) {
                fclose($handle);
                throw ValidationException::withMessages(['statement' => 'Invalid date, description or signed amount on row '.($i + 1).'.']);
            }
            $rows[] = ['tenant_id' => $this->sahodaya->id, 'bank_account_id' => $bank->id, 'import_key' => hash('sha256', $bank->id.'|'.$hash.'|'.$i), 'transaction_date' => $row[0], 'description' => $row[1], 'amount' => round((float) $row[2], 2), 'created_at' => now(), 'updated_at' => now()];
        }fclose($handle);
        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('finance_statement_lines')->insertOrIgnore($chunk);
            }
        });

        return back()->with('success', 'Statement imported. Exact repeat imports are ignored. Review matching suggestions.');
    }

    public function match(Request $r, string $tenantId, int $line)
    {
        $d = $r->validate(['ledger_transaction_id' => 'required|integer']);
        DB::transaction(function () use ($d, $line, $r) {
            $statement = DB::table('finance_statement_lines')->where('tenant_id', $this->sahodaya->id)->where('id', $line)->lockForUpdate()->first();
            abort_unless($statement, 404);
            abort_if($statement->ledger_transaction_id, 422, 'Already matched.');
            $tx = $this->cashRows()->whereKey($d['ledger_transaction_id'])->lockForUpdate()->firstOrFail();
            abort_if($tx->reconciled_at, 422, 'Entry is already reconciled.');
            abort_if($tx->bank_account_id && (int) $tx->bank_account_id !== (int) $statement->bank_account_id, 422, 'Bank does not match.');
            $code = $tx->accountHead?->code;
            if (str_starts_with($code ?? '', 'BANK-')) {
                abort_unless($code === 'BANK-'.$statement->bank_account_id, 422, 'Bank ledger does not match.');
            }
            $signed = $tx->entry_type === 'debit' ? (float) $tx->amount : -(float) $tx->amount;
            abort_if(abs($signed - (float) $statement->amount) > 0.005, 422, 'Amount or direction does not match.');
            DB::table('finance_statement_lines')->where('id', $line)->update(['ledger_transaction_id' => $tx->id, 'matched_by' => $r->user()->id, 'updated_at' => now()]);
            $tx->update(['bank_account_id' => $statement->bank_account_id, 'reconciled_at' => now()]);
        });

        return back()->with('success', 'Statement line matched.');
    }
}
