<?php

namespace App\Services\Ledger;

use App\Models\FeeReceipt;
use App\Models\LedgerTransaction;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Support\LedgerAccountCatalog;

class McqFeeLedgerService
{
    private function incomeCodeForExam(McqExam $exam): string
    {
        return LedgerAccountCatalog::mcqExamFeeCode($exam->id);
    }

    private function ensureExamHead(McqExam $exam): void
    {
        app(LedgerAccountSetupService::class)->ensureMcqExamHead($exam);
    }

    public function postApprovedReceipt(FeeReceipt $receipt, bool $forceRepost = false): ?LedgerTransaction
    {
        if ($receipt->status !== 'approved') {
            return null;
        }

        return match ($receipt->feeable_type) {
            McqSchoolFee::class => $this->postSchoolBatchReceipt($receipt, $forceRepost),
            McqRegistration::class => $this->postRegistrationReceipt($receipt, $forceRepost),
            default => null,
        };
    }

    private function postRegistrationReceipt(FeeReceipt $receipt, bool $forceRepost): ?LedgerTransaction
    {
        $registration = McqRegistration::with(['exam', 'student'])->find($receipt->feeable_id);
        $exam = $registration?->exam;
        $sahodayaId = $exam?->tenant_id;
        if (! $sahodayaId || ! $exam) {
            return null;
        }

        $this->ensureExamHead($exam);
        $incomeCode = $this->incomeCodeForExam($exam);

        $student = $registration->student?->name ?? 'Student';
        $description = "Talent Search fee — {$student} — {$exam->title}";

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
        $schoolFee = McqSchoolFee::with(['exam', 'school'])->find($receipt->feeable_id);
        $exam = $schoolFee?->exam;
        $sahodayaId = $exam?->tenant_id;
        if (! $sahodayaId || ! $exam) {
            return null;
        }

        $this->ensureExamHead($exam);
        $incomeCode = $this->incomeCodeForExam($exam);

        $school = $schoolFee->school?->name ?? 'School';
        $description = "Talent Search batch fee — {$school} — {$exam->title} ({$schoolFee->student_count} students)";

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
