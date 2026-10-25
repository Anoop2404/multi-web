<?php

namespace App\Services\Ledger;

use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\FinancialYear;
use App\Models\LedgerOpeningBalance;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;

class OpeningBalanceService
{
    public function __construct(
        private LedgerPostingService $posting,
    ) {}

    /** @return list<LedgerOpeningBalance> */
    public function listForTenant(string $tenantId, ?int $financialYearId = null): array
    {
        return LedgerOpeningBalance::query()
            ->where('tenant_id', $tenantId)
            ->when($financialYearId, fn ($q) => $q->where('financial_year_id', $financialYearId))
            ->with('accountHead:id,code,name,type')
            ->orderBy('account_head_id')
            ->get()
            ->all();
    }

    public function save(
        string $tenantId,
        int $financialYearId,
        int $accountHeadId,
        string $entryType,
        float|string $amount,
        ?string $notes,
        int $postedBy,
    ): LedgerOpeningBalance {
        $head = AccountHead::findOrFail($accountHeadId);
        abort_unless($head->tenant_id === $tenantId, 403);
        abort_unless(in_array($head->type, ['asset', 'liability']) && $head->code !== 'OPENING-BAL', 422, 'Opening balances require an asset or liability account.');

        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Opening balance amount must be greater than zero.');
        }

        return DB::transaction(function () use ($tenantId, $financialYearId, $head, $entryType, $amount, $notes, $postedBy) {
            $balance = LedgerOpeningBalance::firstOrNew([
                'tenant_id' => $tenantId,
                'financial_year_id' => $financialYearId,
                'account_head_id' => $head->id,
            ]);

            if ($balance->journal_id) {
                LedgerTransaction::where('journal_id', $balance->journal_id)->delete();
            }

            $balance->fill([
                'entry_type' => $entryType,
                'amount' => $amount,
                'notes' => $notes,
                'posted_by' => $postedBy,
            ]);
            $balance->save();

            $this->posting->ensureHead($tenantId, 'OPENING-BAL');

            $counterType = $entryType === 'credit' ? 'debit' : 'credit';
            $description = $notes ?: "Opening balance — {$head->name}";

            $rows = $this->posting->postJournal(
                $tenantId,
                [
                    ['code' => $head->code, 'entry_type' => $entryType, 'amount' => $amount, 'description' => $description],
                    ['code' => 'OPENING-BAL', 'entry_type' => $counterType, 'amount' => $amount, 'description' => $description],
                ],
                LedgerOpeningBalance::class,
                $balance->id,
                $this->openingDate($financialYearId),
                $postedBy,
                true,
                $financialYearId,
            );

            if (preg_match('/^BANK-(\d+)$/', $head->code, $matches)) {
                $bank = BankAccount::where('tenant_id', $tenantId)->findOrFail((int) $matches[1]);
                $rows[0]->update(['bank_account_id' => $bank->id]);
            }

            $balance->update(['journal_id' => $rows[0]->journal_id ?? null]);

            return $balance->fresh('accountHead');
        });
    }

    public function remove(LedgerOpeningBalance $balance): void
    {
        DB::transaction(function () use ($balance) {
            FinancialYear::whereKey($balance->financial_year_id)->lockForUpdate()->firstOrFail();
            abort_if(DB::table('finance_period_locks')->where('tenant_id', $balance->tenant_id)->where('financial_year_id', $balance->financial_year_id)->exists(), 422, 'This financial year is closed.');
            if ($balance->journal_id) {
                LedgerTransaction::where('tenant_id', $balance->tenant_id)->where('journal_id', $balance->journal_id)->delete();
            }
            $balance->delete();
        });
    }

    private function openingDate(int $financialYearId): string
    {
        $record = FinancialYear::findOrFail($financialYearId);

        return $record?->start_date?->format('Y-m-d') ?? now()->startOfYear()->toDateString();
    }
}
