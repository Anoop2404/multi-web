<?php

namespace App\Services\Fees;

use App\Models\FeeReceipt;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\SahodayaMailer;
use App\Support\Mail\EmailBranding;
use Illuminate\Support\Facades\Log;

/**
 * Common post-approval/rejection notification steps for program fee receipts
 * (fest, MCQ, training).
 */
class OfflineProgramFeeOrchestrator
{
    public function __construct(
        private ProgramFeeReceiptMailer $mailer,
    ) {}

    public function notifyApproved(
        Tenant $school,
        FeeReceipt $receipt,
        string $feeTypeLabel,
        string $contextTitle,
        ?string $receiptHtml = null,
        string $adminPath = 'payments',
    ): bool {
        return $this->mailer->sendApproved(
            $school,
            $receipt->fresh(),
            $feeTypeLabel,
            $contextTitle,
            $receiptHtml,
            $adminPath,
        );
    }

    /**
     * Notify school admin users that their fee-payment proof was rejected.
     * Uses the in-app NotificationService so it goes through the same channels
     * as other fee-status notifications (email + in-app), matching the pattern
     * already used by McqExamNotifier::schoolBatchFeeRejected().
     */
    public function notifyRejected(
        Tenant $school,
        string $feeTypeLabel,
        string $contextTitle,
        ?string $rejectionReason = null,
        string $adminPath = 'payments',
    ): void {
        $sahodaya = $school->parent_id ? Tenant::find($school->parent_id) : null;
        if (! $sahodaya) {
            return;
        }

        try {
            $recipients = User::query()
                ->where('tenant_id', $school->id)
                ->whereNotNull('email')
                ->get();

            if ($recipients->isEmpty()) {
                return;
            }

            $reason = $rejectionReason ?: 'Contact your Sahodaya for details.';

            $copy = NotificationTemplate::renderOrDefault(
                'program.fee.rejected',
                [
                    'fee_type_label' => $feeTypeLabel,
                    'context_title'  => $contextTitle,
                    'sahodaya_name'  => $sahodaya->name,
                    'reason'         => $reason,
                ],
                'Payment proof rejected — re-upload required',
                'Your payment proof for {{fee_type_label}} ({{context_title}}) was reviewed by {{sahodaya_name}} and could not be accepted. Reason: {{reason}}. Please re-upload your proof.',
            );

            $viewData = [
                'headerTitle'    => 'Payment proof rejected',
                'headerSubtitle' => $school->name,
                'headerEyebrow'  => $feeTypeLabel,
                'title'          => $copy['title'],
                'body'           => $copy['body'],
                'feeTypeLabel'   => $feeTypeLabel,
                'contextTitle'   => $contextTitle,
                'reason'         => $reason,
                'dashboardUrl'   => EmailBranding::schoolAdminUrl($sahodaya, $school, $adminPath),
                'paymentsUrl'    => EmailBranding::schoolAdminUrl($sahodaya, $school, 'payments'),
            ];

            $emails = $recipients->pluck('email')->filter()->values()->all();
            if ($emails) {
                SahodayaMailer::for($sahodaya->id)->sendViewToMany(
                    $emails,
                    "{$feeTypeLabel} proof rejected — {$contextTitle}",
                    'emails.fee-receipt-rejected',
                    $viewData,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Program fee rejected notification failed', [
                'school_id' => $school->id,
                'context'   => $contextTitle,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
