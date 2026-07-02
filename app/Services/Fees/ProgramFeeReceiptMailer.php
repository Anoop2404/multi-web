<?php

namespace App\Services\Fees;

use App\Models\FeeReceipt;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\SahodayaMailer;
use App\Support\Mail\EmailBranding;
use Illuminate\Support\Facades\Log;

class ProgramFeeReceiptMailer
{
    public function sendApproved(
        Tenant $school,
        FeeReceipt $receipt,
        string $feeTypeLabel,
        string $contextTitle,
        ?string $receiptHtml = null,
        string $adminPath = 'payments',
    ): void {
        $sahodaya = $school->parent_id ? Tenant::find($school->parent_id) : null;
        if (! $sahodaya) {
            return;
        }

        $receiptHtml ??= app(ProgramFeeReceiptService::class)->readOrGenerate($receipt);
        if (! $receiptHtml) {
            Log::warning('Program fee receipt email skipped: HTML not available', [
                'receipt_id' => $receipt->id,
                'school_id'  => $school->id,
            ]);

            return;
        }

        $recipients = $this->schoolRecipientEmails($school);
        if ($recipients === []) {
            return;
        }

        $receiptNo = $receipt->receipt_number ?? '—';
        $subject = "Fee receipt {$receiptNo} — {$contextTitle}";

        $viewData = [
            'headerTitle'    => 'Payment approved',
            'headerSubtitle' => $school->name,
            'headerEyebrow'  => $feeTypeLabel,
            'title'          => 'Your fee payment has been approved',
            'body'           => "Payment for {$contextTitle} has been verified by {$sahodaya->name}. Your official receipt (No. {$receiptNo}) is attached to this email.",
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
        } catch (\Throwable $e) {
            Log::warning('Program fee receipt email failed', [
                'receipt_id' => $receipt->id,
                'school_id'  => $school->id,
                'error'      => $e->getMessage(),
            ]);
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
