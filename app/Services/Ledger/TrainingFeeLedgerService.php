<?php

namespace App\Services\Ledger;

use App\Models\FeeReceipt;
use App\Models\LedgerTransaction;
use App\Models\TrainingRegistration;
use App\Support\LedgerAccountCatalog;

class TrainingFeeLedgerService
{
    private function incomeCodeForProgram(int $programId): string
    {
        return LedgerAccountCatalog::trainingProgramFeeCode($programId);
    }

    private function ensureProgramHead(TrainingRegistration $registration): void
    {
        $program = $registration->program;
        if (! $program) {
            return;
        }

        app(LedgerAccountSetupService::class)->ensureTrainingProgramHead($program);
    }

    public function postApprovedReceipt(FeeReceipt $receipt, bool $forceRepost = false): ?LedgerTransaction
    {
        if ($receipt->status !== 'approved' || $receipt->feeable_type !== TrainingRegistration::class) {
            return null;
        }

        $registration = TrainingRegistration::with(['program', 'teacher', 'school'])->find($receipt->feeable_id);
        $program = $registration?->program;
        $sahodayaId = $program?->tenant_id;
        if (! $sahodayaId || ! $program) {
            return null;
        }

        $this->ensureProgramHead($registration);
        $incomeCode = $this->incomeCodeForProgram($program->id);

        $teacher = $registration->teacher?->name ?? 'Teacher';
        $description = "Training fee — {$teacher} — {$program->title}";

        $rows = app(LedgerPostingService::class)->postIncomeReceipt(
            $receipt,
            $sahodayaId,
            $incomeCode,
            $description,
            $forceRepost
        );

        return $rows[1] ?? $rows[0] ?? null;
    }
}
