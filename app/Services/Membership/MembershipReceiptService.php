<?php

namespace App\Services\Membership;

use App\Models\FeeReceipt;
use App\Models\MembershipPayment;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Support\IndianAmountInWords;
use App\Support\MembershipReceiptTemplate;
use App\Support\SahodayaReceiptNumberAllocator;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class MembershipReceiptService
{
    /**
     * Generate an official membership receipt when payment is verified.
     * Returns the stored HTML path, or null when generation is skipped.
     */
    public function issueForPayment(MembershipPayment $payment): ?string
    {
        if ($payment->status !== 'verified') {
            return null;
        }

        $payment->loadMissing(['school.parent', 'registration', 'feeReceipt']);
        $school = $payment->school;
        $sahodaya = $school?->parent;

        if (! $school || ! $sahodaya) {
            return null;
        }

        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->first();
        $template = MembershipReceiptTemplate::resolve($profile, $sahodaya);

        $receipt = $payment->feeReceipt;
        if (! $receipt) {
            app(FeeReceiptService::class)->syncFromMembershipPayment($payment->fresh());
            $receipt = $payment->fresh()->feeReceipt;
        }

        if (! $receipt) {
            return null;
        }

        if ($receipt->generated_receipt_path) {
            return $receipt->generated_receipt_path;
        }

        return DB::transaction(function () use ($payment, $school, $sahodaya, $profile, $template, $receipt) {
            // Same "PREFIX-0000" formatting Fest ('EF') and Training ('TRN') already use via
            // ProgramFeeReceiptService::formatNumber() — Membership was the one program still
            // casting the raw allocator sequence straight to a string with no prefix at all,
            // so its receipts looked like a bare "74" next to every other program's "EF-0074".
            $receiptNo = app(\App\Services\Fees\ProgramFeeReceiptService::class)->formatNumber(
                'MEM',
                app(SahodayaReceiptNumberAllocator::class)->next($sahodaya->id),
            );

            $html = $this->renderHtml($payment, $school, $sahodaya, $profile, $template, $receiptNo);
            $path = $this->storeHtml($sahodaya, $receiptNo, $html);

            $receipt->update([
                'receipt_number'          => $receiptNo,
                'generated_receipt_path'  => $path,
                'payment_date'            => $payment->verified_at?->toDateString() ?? now()->toDateString(),
            ]);

            return $path;
        });
    }

    /** @return array<string, mixed> */
    public function buildViewData(
        MembershipPayment $payment,
        Tenant $school,
        Tenant $sahodaya,
        ?SahodayaProfile $profile,
        array $template,
        string $receiptNo,
    ): array {
        $registration = $payment->registration;
        $membershipNo = $registration?->reg_no ?? $school->school_prefix ?? '—';
        $amount = (float) $payment->amount;
        $paymentDate = $payment->verified_at ?? $payment->created_at ?? now();

        $vars = [
            '{{school_name}}'     => $school->name,
            '{{membership_no}}'   => $membershipNo,
            '{{academic_year}}'   => $payment->academic_year,
            '{{amount}}'          => number_format($amount, 2, '.', ''),
            '{{amount_words}}'    => IndianAmountInWords::rupees($amount),
            '{{payment_method}}'  => $this->formatPaymentMethod($payment->payment_method),
            '{{transaction_ref}}' => $payment->transaction_ref ?? '—',
            '{{payment_date}}'    => $paymentDate->format('d-m-Y'),
            '{{receipt_no}}'      => $receiptNo,
        ];

        $logoUrl = ($template['show_logo'] ?? true)
            ? TenantBranding::logoEmbedSrc($sahodaya)
            : null;
        $representatives = collect($template['representatives'] ?? [])
            ->map(fn (array $representative) => array_merge($representative, [
                'signature_url' => ! empty($representative['signature_path'])
                    ? TenantStorage::photoDataUri($sahodaya, $representative['signature_path'])
                    : null,
            ]))
            ->values()
            ->all();
        $sealUrl = ! empty($template['seal_path'])
            ? TenantStorage::photoDataUri($sahodaya, $template['seal_path'])
            : null;

        return [
            'template'        => $template,
            'receiptNo'       => $receiptNo,
            'receiptDate'     => $paymentDate->format('d-m-Y'),
            'schoolName'      => $school->name,
            'membershipNo'    => $membershipNo,
            'academicYear'    => $payment->academic_year,
            'amount'          => $amount,
            'amountFormatted' => '₹'.number_format($amount, 2),
            'amountWords'     => IndianAmountInWords::rupees($amount),
            'paymentMethod'   => $this->formatPaymentMethod($payment->payment_method),
            'transactionRef'  => $payment->transaction_ref,
            'purpose'         => MembershipReceiptTemplate::interpolate($template['purpose_template'] ?? '', $vars),
            'logoUrl'         => $logoUrl,
            'representatives' => $representatives,
            'sealUrl'         => $sealUrl,
            'sahodayaName'    => $sahodaya->name,
        ];
    }

    public function renderHtml(
        MembershipPayment $payment,
        Tenant $school,
        Tenant $sahodaya,
        ?SahodayaProfile $profile,
        array $template,
        string $receiptNo,
    ): string {
        return View::make('receipts.membership-official', $this->buildViewData(
            $payment, $school, $sahodaya, $profile, $template, $receiptNo,
        ))->render();
    }

    /** Sample data for admin preview. */
    public function renderPreview(Tenant $sahodaya, ?SahodayaProfile $profile): string
    {
        $template = MembershipReceiptTemplate::resolve($profile, $sahodaya);
        $template = array_merge($template, array_filter([
            'header_title'         => $template['header_title'] ?? strtoupper($sahodaya->name).' (MCS)',
            'registered_office'    => $template['registered_office'] ?? 'Registered office : Anchamile, Pookkottumpadam',
            'society_registration' => $template['society_registration'] ?? 'Reg. Under Societies Registration Act 2025 No. MPM/109/2026',
        ]));

        $samplePayment = new MembershipPayment([
            'academic_year'   => $profile?->resolvedAcademicYear() ?? '2026-27',
            'amount'          => 5000,
            'payment_method'  => 'NEFT',
            'transaction_ref' => 'UTR123456789',
            'verified_at'     => now(),
            'status'          => 'verified',
        ]);

        $school = new Tenant([
            'name'          => 'Sample CBSE School',
            'school_prefix' => 'GHS',
        ]);

        $registration = new \App\Models\Registration([
            'reg_no' => ($profile?->prefix ?? 'MCS').'/26/1',
        ]);
        $samplePayment->setRelation('registration', $registration);

        return $this->renderHtml($samplePayment, $school, $sahodaya, $profile, $template, '92');
    }

    private function storeHtml(Tenant $sahodaya, string $receiptNo, string $html): string
    {
        $relative = 'sahodaya/'.$sahodaya->id.'/membership-receipts/receipt-'.$receiptNo.'.html';
        $disk = TenantStorage::uploadDisk();
        Storage::disk($disk)->put($relative, $html);

        return $relative;
    }

    public function readGeneratedReceipt(FeeReceipt $receipt): ?string
    {
        $path = $receipt->generated_receipt_path;
        if (! $path) {
            return null;
        }

        $disk = TenantStorage::uploadDisk();

        try {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->get($path);
            }
        } catch (\Throwable) {
            $local = TenantStorage::findLocalDisk($path);
            if ($local) {
                return Storage::disk($local)->get($path);
            }
        }

        return null;
    }

    public function readOrGenerateForPayment(MembershipPayment $payment): ?string
    {
        if ($payment->status !== 'verified') {
            return null;
        }

        $payment = $payment->fresh(['school.parent', 'registration', 'feeReceipt']) ?? $payment;

        if (! $payment->feeReceipt?->generated_receipt_path) {
            $this->issueForPayment($payment);
            $payment = $payment->fresh(['school.parent', 'registration', 'feeReceipt']) ?? $payment;
        }

        $receipt = $payment->feeReceipt;
        if (! $receipt) {
            return null;
        }

        $school = $payment->school;
        $sahodaya = $school?->parent;
        if (! $school || ! $sahodaya) {
            return null;
        }

        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->first();
        $template = MembershipReceiptTemplate::resolve($profile, $sahodaya);
        $receiptNo = $receipt->receipt_number ?: app(\App\Services\Fees\ProgramFeeReceiptService::class)->formatNumber(
            'MEM',
            app(SahodayaReceiptNumberAllocator::class)->next($sahodaya->id),
        );
        $html = $this->renderHtml($payment, $school, $sahodaya, $profile, $template, $receiptNo);

        try {
            $receipt->update([
                'receipt_number'         => $receiptNo,
                'generated_receipt_path' => $this->storeHtml($sahodaya, $receiptNo, $html),
                'payment_date'           => $payment->verified_at?->toDateString() ?? $receipt->payment_date ?? now()->toDateString(),
            ]);
        } catch (\Throwable) {
            // If storage is temporarily unavailable, still render the verified receipt.
        }

        return $html;
    }

    private function formatPaymentMethod(?string $method): string
    {
        if (! $method) {
            return 'Cash / NEFT / RTGS';
        }

        return ucwords(str_replace('_', ' ', $method));
    }
}
