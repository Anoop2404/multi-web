<?php

namespace App\Services\Ledger;

use App\Models\FeeReceipt;
use App\Models\LedgerTransaction;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;

class McqFeeLedgerService
{
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
        $sahodayaId = $registration?->exam?->tenant_id;
        if (! $sahodayaId) {
            return null;
        }

        app(LedgerPostingService::class)->ensureHead($sahodayaId, 'MCQ-FEE', null, 'mcq');

        $student = $registration->student?->name ?? 'Student';
        $exam = $registration->exam?->title ?? 'MCQ Exam';
        $description = "MCQ fee — {$student} — {$exam}";

        $rows = app(LedgerPostingService::class)->postIncomeReceipt(
            $receipt,
            $sahodayaId,
            'MCQ-FEE',
            $description,
            $forceRepost
        );

        return $rows[1] ?? $rows[0] ?? null;
    }

    private function postSchoolBatchReceipt(FeeReceipt $receipt, bool $forceRepost): ?LedgerTransaction
    {
        $schoolFee = McqSchoolFee::with(['exam', 'school'])->find($receipt->feeable_id);
        $sahodayaId = $schoolFee?->exam?->tenant_id;
        if (! $sahodayaId) {
            return null;
        }

        app(LedgerPostingService::class)->ensureHead($sahodayaId, 'MCQ-FEE', null, 'mcq');

        $school = $schoolFee->school?->name ?? 'School';
        $exam = $schoolFee->exam?->title ?? 'MCQ Exam';
        $description = "MCQ batch fee — {$school} — {$exam} ({$schoolFee->student_count} students)";

        $rows = app(LedgerPostingService::class)->postIncomeReceipt(
            $receipt,
            $sahodayaId,
            'MCQ-FEE',
            $description,
            $forceRepost
        );

        return $rows[1] ?? $rows[0] ?? null;
    }
}
