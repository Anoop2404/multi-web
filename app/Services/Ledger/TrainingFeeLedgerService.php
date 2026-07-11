<?php

namespace App\Services\Ledger;

use App\Models\FeeReceipt;
use App\Models\LedgerTransaction;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSchoolFee;
use App\Support\LedgerAccountCatalog;

class TrainingFeeLedgerService
{
    private function incomeCodeForProgram(int $programId): string
    {
        return LedgerAccountCatalog::trainingProgramFeeCode($programId);
    }

    private function ensureProgramHead(TrainingProgram $program): void
    {
        app(LedgerAccountSetupService::class)->ensureTrainingProgramHead($program);
    }

    public function postApprovedReceipt(FeeReceipt $receipt, bool $forceRepost = false): ?LedgerTransaction
    {
        if ($receipt->status !== 'approved') {
            return null;
        }

        return match ($receipt->feeable_type) {
            TrainingRegistration::class => $this->postRegistrationReceipt($receipt, $forceRepost),
            TrainingSchoolFee::class => $this->postSchoolBatchReceipt($receipt, $forceRepost),
            default => null,
        };
    }

    private function postRegistrationReceipt(FeeReceipt $receipt, bool $forceRepost): ?LedgerTransaction
    {
        $registration = TrainingRegistration::with(['program', 'teacher', 'school'])->find($receipt->feeable_id);
        $program = $registration?->program;
        $sahodayaId = $program?->tenant_id;
        if (! $sahodayaId || ! $program) {
            return null;
        }

        $this->ensureProgramHead($program);
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

    private function postSchoolBatchReceipt(FeeReceipt $receipt, bool $forceRepost): ?LedgerTransaction
    {
        $schoolFee = TrainingSchoolFee::with(['program', 'school'])->find($receipt->feeable_id);
        $program = $schoolFee?->program;
        $sahodayaId = $program?->tenant_id;
        if (! $sahodayaId || ! $program) {
            return null;
        }

        $this->ensureProgramHead($program);
        $incomeCode = $this->incomeCodeForProgram($program->id);

        $school = $schoolFee->school?->name ?? 'School';
        $description = "Training batch fee — {$school} — {$program->title} ({$schoolFee->teacher_count} teachers)";

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
