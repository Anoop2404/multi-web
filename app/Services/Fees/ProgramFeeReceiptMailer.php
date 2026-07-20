<?php

namespace App\Services\Fees;

use App\Models\FeeReceipt;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\SahodayaMailer;
use App\Support\Mail\EmailBranding;
use Illuminate\Support\Facades\Log;

class ProgramFeeReceiptMailer
{
    public function __construct(
        private FeeReceiptEmailTracker $tracker,
    ) {}

    public function sendApproved(
        Tenant $school,
        FeeReceipt $receipt,
        string $feeTypeLabel,
        string $contextTitle,
        ?string $receiptHtml = null,
        string $adminPath = 'payments',
    ): bool {
        $sahodaya = $school->parent_id ? Tenant::find($school->parent_id) : null;
        if (! $sahodaya) {
            return false;
        }

        $receiptHtml ??= app(ProgramFeeReceiptService::class)->readOrGenerate($receipt);
        if (! $receiptHtml) {
            $this->tracker->markSkipped($receipt, 'Receipt HTML not available');
            Log::warning('Program fee receipt email skipped: HTML not available', [
                'receipt_id' => $receipt->id,
                'school_id'  => $school->id,
            ]);

            return false;
        }

        $recipients = $this->schoolRecipientEmails($school);
        if ($recipients === []) {
            $this->tracker->markSkipped($receipt, 'No school recipient emails');

            return false;
        }

        $this->tracker->markQueued($receipt);

        $receiptNo = $receipt->receipt_number ?? '—';
        $subject = "Fee receipt {$receiptNo} — {$contextTitle}";

        $copy = NotificationTemplate::renderOrDefault(
            'email.fees.receipt_approved',
            ['context_title' => $contextTitle, 'sahodaya_name' => $sahodaya->name, 'receipt_no' => $receiptNo],
            'Your fee payment has been approved',
            'Payment for {{context_title}} has been verified by {{sahodaya_name}}. Your official receipt (No. {{receipt_no}}) is attached to this email.',
        );

        $viewData = [
            'headerTitle'    => 'Payment approved',
            'headerSubtitle' => $school->name,
            'headerEyebrow'  => $feeTypeLabel,
            'title'          => $copy['title'],
            'body'           => $copy['body'],
            'feeTypeLabel'   => $feeTypeLabel,
            'contextTitle'   => $contextTitle,
            'receiptNo'      => $receiptNo,
            'amountFormatted'=> '₹'.number_format((float) $receipt->amount, 2),
            'paymentDate'    => $receipt->payment_date?->format('d M Y'),
            'transactionRef' => $receipt->transaction_ref,
            'dashboardUrl'   => EmailBranding::schoolAdminUrl($sahodaya, $school, $adminPath),
            'paymentsUrl'    => EmailBranding::schoolAdminUrl($sahodaya, $school, 'payments'),
        ];

        $attachments = [[
            'content' => $receiptHtml,
            'name'    => 'fee-receipt-'.$receiptNo.'.html',
            'mime'    => 'text/html',
        ]];

        try {
            SahodayaMailer::for($sahodaya->id)->sendViewToManyWithAttachments(
                $recipients,
                $subject,
                'emails.fee-receipt-approved',
                $viewData,
                $attachments,
            );
            $this->tracker->markSent($receipt->fresh());

            return true;
        } catch (\Throwable $e) {
            $this->tracker->markFailed($receipt->fresh(), $e->getMessage());
            Log::warning('Program fee receipt email failed', [
                'receipt_id' => $receipt->id,
                'school_id'  => $school->id,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** @return list<string> */
    private function schoolRecipientEmails(Tenant $school): array
    {
        $recipients = User::query()
            ->where('tenant_id', $school->id)
            ->whereNotNull('email')
            ->pluck('email')
            ->all();

        $contact = $school->application_payload['school_email']
            ?? $school->application_payload['contact_email']
            ?? null;

        if ($contact && ! in_array($contact, $recipients, true)) {
            $recipients[] = $contact;
        }

        return array_values(array_unique(array_filter($recipients)));
    }
}
