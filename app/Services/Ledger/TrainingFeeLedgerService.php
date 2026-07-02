<?php

namespace App\Services\Ledger;

use App\Models\FeeReceipt;
use App\Models\LedgerTransaction;
use App\Models\TrainingRegistration;

class TrainingFeeLedgerService
{
    public function postApprovedReceipt(FeeReceipt $receipt, bool $forceRepost = false): ?LedgerTransaction
    {
        if ($receipt->status !== 'approved' || $receipt->feeable_type !== TrainingRegistration::class) {
            return null;
        }

        $registration = TrainingRegistration::with('program', 'teacher', 'teacher.tenant')->find($receipt->feeable_id);
        $sahodayaId = $registration?->program?->tenant_id;
        if (! $sahodayaId) {
            return null;
        }

        $teacher = $registration->teacher?->name ?? 'Teacher';
        $program = $registration->program?->title ?? 'Training';
        $description = "Training fee — {$teacher} — {$program}";

        app(LedgerPostingService::class)->ensureHead($sahodayaId, 'TRAINING-FEE', null, 'training');

        $rows = app(LedgerPostingService::class)->postIncomeReceipt(
            $receipt,
            $sahodayaId,
            'TRAINING-FEE',
            $description,
            $forceRepost
        );

        return $rows[1] ?? $rows[0] ?? null;
    }
}
