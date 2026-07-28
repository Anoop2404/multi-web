<?php

namespace App\Services\Ledger;

use App\Models\CreditPayout;
use App\Models\McqSchoolFee;
use App\Models\ProgramFeeCredit;
use App\Models\Tenant;
use App\Models\TrainingSchoolFee;
use App\Support\LedgerAccountCatalog;

class ProgramFeeCreditLedgerService
{
    public function postIssued(ProgramFeeCredit $credit): void
    {
        $context = $this->context($credit);
        if (! $context) {
            return;
        }

        [$tenantId, $incomeCode, $description] = $context;
        $posting = app(LedgerPostingService::class);
        $posting->ensureHead($tenantId, $incomeCode);
        $posting->ensureHead($tenantId, 'FEE-CREDIT-PAYABLE');

        $posting->postJournal($tenantId, [
            [
                'code' => $incomeCode,
                'entry_type' => 'debit',
                'amount' => $credit->amount,
                'description' => $description,
            ],
            [
                'code' => 'FEE-CREDIT-PAYABLE',
                'entry_type' => 'credit',
                'amount' => $credit->amount,
                'description' => $description,
            ],
        ], ProgramFeeCredit::class, $credit->id, now()->toDateString(), $credit->created_by_user_id);
    }

    public function postPayout(CreditPayout $payout, string $tenantId): void
    {
        $description = "School fee-credit payout — {$payout->school?->name} — {$payout->bank_ref}";

        app(LedgerPostingService::class)->postJournal($tenantId, [
            [
                'code' => 'FEE-CREDIT-PAYABLE',
                'entry_type' => 'debit',
                'amount' => $payout->amount,
                'description' => $description,
            ],
            [
                'code' => 'CASH-BANK',
                'entry_type' => 'credit',
                'amount' => $payout->amount,
                'description' => $description,
            ],
        ], CreditPayout::class, $payout->id, now()->toDateString(), $payout->recorded_by_user_id);
    }

    public function tenantIdForCredit(\App\Models\FestFeeCredit|ProgramFeeCredit $credit): ?string
    {
        if ($credit instanceof \App\Models\FestFeeCredit) {
            return $credit->schoolEventFee?->event?->tenant_id;
        }

        return $this->context($credit)[0] ?? null;
    }

    /** @return array{0: string, 1: string, 2: string}|null */
    private function context(ProgramFeeCredit $credit): ?array
    {
        $creditable = $credit->creditable;

        if ($creditable instanceof McqSchoolFee && $creditable->exam) {
            return [
                $creditable->exam->tenant_id,
                LedgerAccountCatalog::mcqExamFeeCode($creditable->exam->id),
                "Talent Search fee credit — school {$creditable->school_id} — {$credit->reason}",
            ];
        }

        if ($creditable instanceof TrainingSchoolFee && $creditable->program) {
            return [
                $creditable->program->tenant_id,
                LedgerAccountCatalog::trainingProgramFeeCode($creditable->program->id),
                "Training fee credit — school {$creditable->school_id} — {$credit->reason}",
            ];
        }

        if ($creditable instanceof Tenant && $creditable->type === 'school' && $creditable->parent_id) {
            return [
                $creditable->parent_id,
                'MEMBERSHIP',
                "Membership fee credit — {$creditable->name} — {$credit->reason}",
            ];
        }

        return null;
    }
}
