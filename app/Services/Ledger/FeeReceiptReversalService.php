<?php

namespace App\Services\Ledger;

use App\Models\FeeReceipt;
use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\TrainingRegistration;
use App\Models\User;
use App\Observers\FeeReceiptObserver;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeReceiptReversalService
{
    public function reverse(FeeReceipt $receipt, User $actor, ?string $reason = null): FeeReceipt
    {
        return DB::transaction(function () use ($receipt, $actor, $reason) {
            /** @var FeeReceipt $locked */
            $locked = FeeReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === FeeReceipt::STATUS_REVERSED) {
                return $locked;
            }

            if ($locked->status !== FeeReceipt::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'receipt' => 'Only approved fee receipts can be reversed.',
                ]);
            }

            $tenantId = app(FeeReceiptObserver::class)->resolveTenantIdPublic($locked);
            if (! $tenantId) {
                throw ValidationException::withMessages([
                    'receipt' => 'Cannot resolve tenant for ledger reversal. Fix the feeable link and retry.',
                ]);
            }

            app(FeeReceiptLedgerDispatcher::class)->postReversal($locked, $tenantId);

            $locked->update([
                'status'          => FeeReceipt::STATUS_REVERSED,
                'reversed_by'     => $actor->id,
                'reversed_at'     => now(),
                'reversal_reason' => $reason,
            ]);

            $this->syncFeeableAfterReversal($locked->fresh(['feeable']), $reason);

            app(PlatformAuditLogger::class)->log(
                action: 'fee_receipt.reversed',
                description: "Fee receipt #{$locked->id} reversed".($reason ? ": {$reason}" : ''),
                subject: $locked,
                properties: [
                    'feeable_type' => $locked->feeable_type,
                    'feeable_id'   => $locked->feeable_id,
                    'amount'       => $locked->amount,
                    'reason'       => $reason,
                ],
                category: 'finance',
            );

            try {
                $schoolId = app(\App\Services\Fees\ProgramFeeReceiptService::class)->schoolIdForReceipt($locked);
                if ($schoolId) {
                    $notifier = app(\App\Services\Notifications\NotificationService::class);
                    foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
                        $notifier->notifyFromTemplate($user, 'fee.receipt.reversed', [
                            'receipt_number' => $locked->receipt_number ?? "#{$locked->id}",
                            'amount'         => number_format((float) $locked->amount, 2),
                            'reason'         => $reason ?? 'Contact Sahodaya office for details.',
                        ], "/school-admin/{$schoolId}/payments");
                    }
                }
            } catch (\Throwable) {
                // non-blocking
            }

            return $locked->fresh();
        });
    }

    private function syncFeeableAfterReversal(FeeReceipt $receipt, ?string $reason): void
    {
        $feeable = $receipt->feeable;
        if (! $feeable) {
            return;
        }

        if (method_exists($feeable, 'refreshPaidState')) {
            $feeable->refresh();
            $feeable->refreshPaidState(
                $feeable instanceof TrainingRegistration ? 'fee_status' : 'status'
            );
        }

        if ($feeable instanceof MembershipPayment && $feeable->status === 'verified') {
            $reasonText = 'Payment reversed'.($reason ? ": {$reason}" : '');
            $feeable->update([
                'status'           => 'rejected',
                'rejection_reason' => $reasonText,
            ]);

            $receipt = $feeable->feeReceipt ?? $receipt;
            if ($receipt && filled($reasonText)) {
                $receipt->update([
                    'rejection_history' => $receipt->appendRejectionHistory(
                        $reasonText,
                        $receipt->reversed_by,
                    ),
                ]);
            }

            $registration = $feeable->registration
                ?? Registration::query()
                    ->where('school_id', $feeable->school_id)
                    ->where('academic_year', $feeable->academic_year)
                    ->first();

            if ($registration && in_array($registration->registration_status, ['completed', 'approved'], true)) {
                $regBefore = $registration->registration_status;
                $registration->update(['registration_status' => 'payment_rejected']);
                app(DataChangeLogger::class)->updated(
                    $registration,
                    "Registration reverted after membership payment reversal for {$feeable->school?->name}",
                    ['registration_status' => ['old' => $regBefore, 'new' => 'payment_rejected']],
                    $feeable->school_id,
                    'membership',
                    ['reason' => $reasonText],
                );
            }
        }
    }
}
