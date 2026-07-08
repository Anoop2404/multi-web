<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\MembershipPayment;
use App\Models\SahodayaProfile;
use App\Services\Membership\MembershipReceiptService;
use App\Support\TenantStorage;
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

        $html = $receiptService->readOrGenerateForPayment($payment);
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
            'society_registration'  => 'nullable|string|max:500',
            'purpose_template'      => 'nullable|string|max:500',
            'receiver_label'        => 'nullable|string|max:80',
            'counter_label'         => 'nullable|string|max:80',
            'receipt_signatures_enabled' => 'nullable|boolean',
            'representatives'       => 'nullable|array|max:4',
            'representatives.*.enabled' => 'nullable|boolean',
            'representatives.*.name' => 'nullable|string|max:120',
            'representatives.*.designation' => 'nullable|string|max:120',
            'representatives.*.signature_path' => 'nullable|string|max:500',
            'show_seal'             => 'nullable|boolean',
            'seal_label'            => 'nullable|string|max:120',
            'seal_path'             => 'nullable|string|max:500',
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
            'society_registration' => $data['society_registration'] ?? null,
            'purpose_template'     => $data['purpose_template'] ?? null,
            'receiver_label'       => $data['receiver_label'] ?? null,
            'counter_label'        => $data['counter_label'] ?? null,
            'receipt_signatures_enabled' => array_key_exists('receipt_signatures_enabled', $data) ? (bool) $data['receipt_signatures_enabled'] : null,
            'representatives'      => $this->mergeRepresentativeAssets(
                $data['representatives'] ?? ($existing['representatives'] ?? []),
                $existing['representatives'] ?? [],
            ),
            'show_seal'            => array_key_exists('show_seal', $data) ? (bool) $data['show_seal'] : null,
            'seal_label'           => $data['seal_label'] ?? null,
            'seal_path'            => $data['seal_path'] ?? ($existing['seal_path'] ?? null),
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

    public function uploadAsset(Request $request)
    {
        $profile = SahodayaProfile::firstOrCreate(['tenant_id' => $this->sahodaya->id]);

        $data = $request->validate([
            'asset_type' => 'required|in:seal,signature',
            'signature_index' => 'nullable|required_if:asset_type,signature|integer|min:0|max:3',
            'file' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $path = TenantStorage::storeUploadedFile(
            $request->file('file'),
            "membership-receipts/{$this->sahodaya->id}/assets",
        );

        $template = $profile->receipt_template_json ?? [];

        if ($data['asset_type'] === 'seal') {
            $template['seal_path'] = $path;
            $template['show_seal'] = true;
        } else {
            $representatives = $this->normalizeRepresentatives($template['representatives'] ?? []);
            $index = (int) $data['signature_index'];
            while (count($representatives) <= $index) {
                $representatives[] = [
                    'enabled' => true,
                    'name' => '',
                    'designation' => 'Authorised Signatory',
                    'signature_path' => null,
                ];
            }
            $representatives[$index]['signature_path'] = $path;
            $representatives[$index]['enabled'] = true;
            $template['representatives'] = $representatives;
            $template['receipt_signatures_enabled'] = true;
        }

        $profile->update(['receipt_template_json' => $template]);

        return back()->with('success', 'Receipt image uploaded.');
    }

    /** @return list<array{enabled: bool, name: string, designation: string, signature_path: ?string}> */
    private function normalizeRepresentatives(array $representatives): array
    {
        return array_values(array_map(
            fn (array $representative) => [
                'enabled' => array_key_exists('enabled', $representative) ? (bool) $representative['enabled'] : true,
                'name' => (string) ($representative['name'] ?? ''),
                'designation' => (string) ($representative['designation'] ?? 'Authorised Signatory'),
                'signature_path' => $representative['signature_path'] ?? null,
            ],
            array_slice($representatives, 0, 4),
        ));
    }

    /** @return list<array{enabled: bool, name: string, designation: string, signature_path: ?string}> */
    private function mergeRepresentativeAssets(array $submitted, array $existing): array
    {
        $submitted = $this->normalizeRepresentatives($submitted);
        $existing = $this->normalizeRepresentatives($existing);

        foreach ($submitted as $index => $representative) {
            if (empty($representative['signature_path']) && ! empty($existing[$index]['signature_path'])) {
                $submitted[$index]['signature_path'] = $existing[$index]['signature_path'];
            }
        }

        return $submitted;
    }
}
