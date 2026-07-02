<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\MembershipPayment;
use App\Models\SahodayaProfile;
use App\Services\Membership\MembershipReceiptService;
use Illuminate\Http\Request;

class MembershipReceiptController extends SahodayaAdminController
{
    public function preview(MembershipReceiptService $receiptService)
    {
        $profile = SahodayaProfile::where('tenant_id', $this->sahodaya->id)->first();
        $html = $receiptService->renderPreview($this->sahodaya, $profile);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function show(string $tenantId, MembershipPayment $payment, MembershipReceiptService $receiptService)
    {
        abort_if($payment->school?->parent_id !== $this->sahodaya->id, 403);
        abort_unless($payment->status === 'verified', 404);

        $payment->loadMissing('feeReceipt');
        $receipt = $payment->feeReceipt;

        if (! $receipt?->generated_receipt_path) {
            $receiptService->issueForPayment($payment->fresh());
            $receipt = $payment->fresh()->feeReceipt;
        }

        $html = $receipt ? $receiptService->readGeneratedReceipt($receipt) : null;
        abort_if(! $html, 404, 'Receipt not generated yet.');

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function updateTemplate(Request $request)
    {
        $profile = SahodayaProfile::firstOrCreate(['tenant_id' => $this->sahodaya->id]);

        $data = $request->validate([
            'header_title'          => 'nullable|string|max:255',
            'header_subtitle'       => 'nullable|string|max:500',
            'registered_office'     => 'nullable|string|max:500',
            'purpose_template'      => 'nullable|string|max:500',
            'receiver_label'        => 'nullable|string|max:80',
            'counter_label'         => 'nullable|string|max:80',
            'accent_color'          => 'nullable|string|max:20',
            'show_logo'             => 'nullable|boolean',
            'auto_email_on_verify'  => 'nullable|boolean',
            'receipt_next_number'   => 'nullable|integer|min:1',
        ]);

        $existing = $profile->receipt_template_json ?? [];
        $template = array_merge($existing, array_filter([
            'header_title'         => $data['header_title'] ?? null,
            'header_subtitle'      => $data['header_subtitle'] ?? null,
            'registered_office'    => $data['registered_office'] ?? null,
            'purpose_template'     => $data['purpose_template'] ?? null,
            'receiver_label'       => $data['receiver_label'] ?? null,
            'counter_label'        => $data['counter_label'] ?? null,
            'accent_color'         => $data['accent_color'] ?? null,
            'show_logo'            => array_key_exists('show_logo', $data) ? (bool) $data['show_logo'] : null,
            'auto_email_on_verify' => array_key_exists('auto_email_on_verify', $data) ? (bool) $data['auto_email_on_verify'] : null,
        ], fn ($v) => $v !== null));

        $profile->update([
            'receipt_template_json' => $template,
            'receipt_next_number' => $data['receipt_next_number'] ?? $profile->receipt_next_number,
        ]);

        return back()->with('success', 'Receipt template saved.');
    }
}
