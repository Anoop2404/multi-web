<?php

namespace App\Services\Ledger;

use App\Models\AccountHead;
use App\Models\FeeReceipt;
use App\Models\LedgerTransaction;
use App\Support\AcademicYear;
use App\Support\FinancialYear;
use App\Support\LedgerAccountCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerPostingService
{
    public function ensureHead(
        string $tenantId,
        string $code,
        ?string $nameOverride = null,
        ?string $category = null,
        int|string|null $eventId = null,
        int|string|null $mcqExamId = null,
        int|string|null $trainingProgramId = null,
    ): AccountHead {
        $def = LedgerAccountCatalog::definition($code);

        $head = AccountHead::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => $code],
            [
                'name'                 => $nameOverride ?? $def['name'],
                'type'                 => $def['type'],
                'category'             => $category ?? $def['category'],
                'event_id'             => $eventId,
                'mcq_exam_id'          => $mcqExamId,
                'training_program_id'  => $trainingProgramId,
                'is_active'            => true,
            ]
        );

        $updates = [];
        if ($nameOverride && $head->name !== $nameOverride) {
            $updates['name'] = $nameOverride;
        }
        if ($category && $head->category !== $category) {
            $updates['category'] = $category;
        }
        if ($eventId && $head->event_id !== $eventId) {
            $updates['event_id'] = $eventId;
        }
        if ($mcqExamId && $head->mcq_exam_id !== $mcqExamId) {
            $updates['mcq_exam_id'] = $mcqExamId;
        }
        if ($trainingProgramId && $head->training_program_id != $trainingProgramId) {
            $updates['training_program_id'] = $trainingProgramId;
        }
        if ($updates !== []) {
            $head->update($updates);
        }

        return $head->fresh();
    }

    public function ensureDefaultHeads(string $tenantId): void
    {
        foreach (LedgerAccountCatalog::defaultCodes() as $code) {
            $def = LedgerAccountCatalog::definition($code);
            $this->ensureHead($tenantId, $code, null, $def['category']);
        }
    }

    /**
     * @param  array<int, array{code: string, entry_type: 'debit'|'credit', amount: float|string, description?: ?string}>  $lines
     * @return list<LedgerTransaction>
     */
    public function postJournal(
        string $tenantId,
        array $lines,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $transactionDate = null,
        ?int $postedBy = null,
        bool $forceRepost = false,
        ?int $financialYearId = null,
    ): array {
        return DB::transaction(function () use ($tenantId, $lines, $referenceType, $referenceId, $transactionDate, $postedBy, $forceRepost, $financialYearId) {
            // Serialize concurrent approve/repost races for the same receipt (#FRD-06 #4).
            if ($referenceType === FeeReceipt::class && $referenceId) {
                FeeReceipt::query()->whereKey($referenceId)->lockForUpdate()->first();
            }

            if ($referenceType && $referenceId) {
                $existing = LedgerTransaction::where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($existing->isNotEmpty()) {
                    if (! $forceRepost) {
                        return $existing->all();
                    }

                    LedgerTransaction::where('reference_type', $referenceType)
                        ->where('reference_id', $referenceId)
                        ->delete();
                }
            }

            $journalId = (string) Str::uuid();
            $date = $transactionDate ?? now()->toDateString();
            $yearId = $financialYearId ?? FinancialYear::currentId();
            $created = [];

            foreach ($lines as $line) {
                $head = $this->ensureHead($tenantId, $line['code']);

                $created[] = LedgerTransaction::create([
                    'tenant_id'         => $tenantId,
                    'journal_id'        => $journalId,
                    'financial_year_id' => $yearId,
                    'account_head_id'   => $head->id,
                    'reference_type'    => $referenceType,
                    'reference_id'      => $referenceId,
                    'entry_type'        => $line['entry_type'],
                    'amount'            => $line['amount'],
                    'description'       => $line['description'] ?? null,
                    'transaction_date'  => $date,
                    'posted_by'         => $postedBy,
                ]);
            }

            return $created;
        });
    }

    /** @return list<LedgerTransaction> */
    public function postIncomeReceipt(FeeReceipt $receipt, string $tenantId, string $incomeCode, string $description, bool $forceRepost = false): array
    {
        $amount = $receipt->amount;
        $date = $receipt->payment_date?->format('Y-m-d') ?? now()->toDateString();

        return $this->postJournal($tenantId, [
            ['code' => 'CASH-BANK', 'entry_type' => 'debit', 'amount' => $amount, 'description' => $description],
            ['code' => $incomeCode, 'entry_type' => 'credit', 'amount' => $amount, 'description' => $description],
        ], FeeReceipt::class, $receipt->id, $date, $receipt->reviewed_by, $forceRepost);
    }

    /**
     * Post compensating entries that invert an existing FeeReceipt income journal.
     *
     * @return list<LedgerTransaction>
     */
    public function postReceiptReversal(FeeReceipt $receipt, string $tenantId, ?int $postedBy = null): array
    {
        return DB::transaction(function () use ($receipt, $tenantId, $postedBy) {
            FeeReceipt::query()->whereKey($receipt->id)->lockForUpdate()->first();

            $existingReversal = LedgerTransaction::where('reference_type', FeeReceipt::REVERSAL_REFERENCE)
                ->where('reference_id', $receipt->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($existingReversal->isNotEmpty()) {
                return $existingReversal->all();
            }

            $original = LedgerTransaction::where('reference_type', FeeReceipt::class)
                ->where('reference_id', $receipt->id)
                ->with('accountHead:id,code')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($original->isEmpty()) {
                throw new \RuntimeException("No ledger rows found to reverse for fee receipt #{$receipt->id}.");
            }

            $lines = $original->map(function (LedgerTransaction $row) {
                $code = $row->accountHead?->code;
                if (! $code) {
                    throw new \RuntimeException("Missing account head for ledger row #{$row->id}.");
                }

                return [
                    'code'        => $code,
                    'entry_type'  => $row->entry_type === 'debit' ? 'credit' : 'debit',
                    'amount'      => $row->amount,
                    'description' => 'Reversal of receipt #'.$row->reference_id.($row->description ? " — {$row->description}" : ''),
                ];
            })->all();

            return $this->postJournal(
                $tenantId,
                $lines,
                FeeReceipt::REVERSAL_REFERENCE,
                $receipt->id,
                now()->toDateString(),
                $postedBy ?? $receipt->reversed_by ?? $receipt->reviewed_by,
            );
        });
    }

    /** @return list<LedgerTransaction> */
    public function postExpense(
        string $tenantId,
        string $expenseCode,
        float|string $amount,
        string $description,
        string $referenceType,
        int $referenceId,
        ?string $transactionDate = null,
        ?int $postedBy = null,
    ): array {
        return $this->postJournal($tenantId, [
            ['code' => $expenseCode, 'entry_type' => 'debit', 'amount' => $amount, 'description' => $description],
            ['code' => 'CASH-BANK', 'entry_type' => 'credit', 'amount' => $amount, 'description' => $description],
        ], $referenceType, $referenceId, $transactionDate, $postedBy);
    }

    /** @return list<LedgerTransaction> */
    public function postManualPair(
        string $tenantId,
        int $primaryHeadId,
        string $entryType,
        float|string $amount,
        ?string $description,
        string $transactionDate,
        int $postedBy,
        ?int $counterHeadId = null,
    ): array {
        $primary = AccountHead::findOrFail($primaryHeadId);

        if ($primary->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Account head does not belong to this tenant.');
        }

        $counterHead = $counterHeadId
            ? AccountHead::findOrFail($counterHeadId)
            : $this->ensureHead($tenantId, 'CASH-BANK');

        if ($counterHead->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Counter account does not belong to this tenant.');
        }

        if ($primary->id === $counterHead->id) {
            throw new \InvalidArgumentException('Primary and counter accounts must differ.');
        }

        $counterType = $entryType === 'credit' ? 'debit' : 'credit';

        return $this->postJournal($tenantId, [
            ['code' => $primary->code, 'entry_type' => $entryType, 'amount' => $amount, 'description' => $description],
            ['code' => $counterHead->code, 'entry_type' => $counterType, 'amount' => $amount, 'description' => $description],
        ], null, null, $transactionDate, $postedBy);
    }
}
