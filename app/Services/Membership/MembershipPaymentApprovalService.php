<?php

namespace App\Services\Membership;

use App\Models\MembershipPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Fees\FeeReceiptEmailTracker;

class MembershipPaymentApprovalService
{
    public function verify(
        MembershipPayment $payment,
        User $verifier,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
    ): MembershipPayment {
        abort_unless($payment->status === 'submitted', 403);

        $beforeStatus = $payment->status;
        $payment->update([
            'status'              => 'verified',
            'verified_by_user_id' => $verifier->id,
            'verified_at'         => now(),
        ]);

        app(FeeReceiptService::class)->syncFromMembershipPayment($payment->fresh());

        $receiptService = app(MembershipReceiptService::class);
        $receiptService->issueForPayment($payment->fresh());
        $freshReceipt = $payment->fresh()->feeReceipt;
        $receiptNo = $freshReceipt?->receipt_number;
        $receiptHtml = $freshReceipt
            ? $receiptService->readGeneratedReceipt($freshReceipt)
            : null;

        app(DataChangeLogger::class)->updated(
            $payment,
            "Payment verified for {$payment->school?->name}",
            ['status' => ['old' => $beforeStatus, 'new' => 'verified']],
            $payment->school_id,
            'membership',
        );

        $school = $payment->school;
        $firstMembershipApproval = $school && $school->membership_status === 'pending';

        if ($firstMembershipApproval) {
            $school->update([
                'membership_status' => 'approved',
                'is_active'         => true,
            ]);
        }

        $registration = $payment->registration;
        if ($registration) {
            $registration = app(RegistrationStatusService::class)
                ->ensureMembershipNumber($registration->load('school'));
            $regBefore = $registration->registration_status;
            $registration->increment('amount_paid', (float) $payment->amount);
            $registration->refresh();
            $newStatus = $registration->outstandingBalance() > 0 ? 'payment_pending' : 'completed';
            $registration->update(['registration_status' => $newStatus]);
            $registration->refresh();
            app(DataChangeLogger::class)->updated(
                $registration,
                $newStatus === 'completed'
                    ? "Registration completed for {$payment->school?->name}"
                    : "Payment verified for {$payment->school?->name}; balance of ₹{$registration->outstandingBalance()} still due",
                ['registration_status' => ['old' => $regBefore, 'new' => $newStatus]],
                $payment->school_id,
                'membership',
                ['membership_no' => $registration->reg_no, 'amount_paid' => (float) $registration->amount_paid],
            );
            if ($newStatus === 'completed') {
                $notifier->registrationCompleted(
                    $payment->school,
                    $payment->academic_year,
                    $registration->reg_no,
                    $firstMembershipApproval,
                    $receiptHtml,
                    $receiptNo,
                );
                if ($freshReceipt && $receiptHtml) {
                    app(FeeReceiptEmailTracker::class)->markSent($freshReceipt->fresh());
                }
            }
        } elseif ($firstMembershipApproval) {
            $notifier->schoolApproved($school);
        }

        $audit->paymentVerified($payment->fresh());

        return $payment->fresh();
    }

    public function reject(
        MembershipPayment $payment,
        User $verifier,
        string $reason,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
    ): MembershipPayment {
        abort_unless($payment->status === 'submitted', 403);

        $beforeStatus = $payment->status;
        $payment->update([
            'status'              => 'rejected',
            'rejection_reason'    => $reason,
            'verified_by_user_id' => $verifier->id,
            'verified_at'         => now(),
        ]);

        app(FeeReceiptService::class)->syncFromMembershipPayment($payment->fresh());

        app(DataChangeLogger::class)->updated(
            $payment,
            "Payment rejected for {$payment->school?->name}",
            ['status' => ['old' => $beforeStatus, 'new' => 'rejected']],
            $payment->school_id,
            'membership',
            ['reason' => $reason],
        );

        $registration = $payment->registration;
        if ($registration) {
            $regBefore = $registration->registration_status;
            $registration->update(['registration_status' => 'payment_rejected']);
            app(DataChangeLogger::class)->updated(
                $registration,
                "Registration payment rejected for {$payment->school?->name}",
                ['registration_status' => ['old' => $regBefore, 'new' => 'payment_rejected']],
                $payment->school_id,
                'membership',
            );
        }

        $notifier->paymentRejected($payment->school, $payment->academic_year, $reason);
        $audit->paymentRejected($payment->fresh(), $reason);

        return $payment->fresh();
    }
}
