<?php

namespace App\Support;

use App\Models\SahodayaProfile;
use App\Models\Tenant;

class MembershipReceiptTemplate
{
    /** @return array<string, mixed> */
    public static function resolve(?SahodayaProfile $profile, ?Tenant $sahodaya = null): array
    {
        $defaults = config('membership_receipt.defaults', []);
        $custom = $profile?->receipt_template_json ?? [];

        $merged = array_merge($defaults, array_filter($custom, fn ($v) => $v !== null && $v !== ''));

        if (empty($merged['header_title'])) {
            $merged['header_title'] = strtoupper((string) ($sahodaya?->name ?? 'SAHODAYA'));
        }

        if (empty($merged['registered_office']) && filled($profile?->address)) {
            $merged['registered_office'] = 'Registered office : '.$profile->address;
        } elseif (filled($merged['registered_office']) && ! str_starts_with(strtolower($merged['registered_office']), 'registered office')) {
            $merged['registered_office'] = 'Registered office : '.$merged['registered_office'];
        }

        $representatives = is_array($merged['representatives'] ?? null)
            ? $merged['representatives']
            : [];

        if ($representatives === []) {
            $representatives = [
                [
                    'enabled' => true,
                    'name' => '',
                    'designation' => $merged['receiver_label'] ?? 'Receiver Signature',
                    'signature_path' => null,
                ],
                [
                    'enabled' => true,
                    'name' => '',
                    'designation' => $merged['counter_label'] ?? 'Counter Signature',
                    'signature_path' => null,
                ],
            ];
        }

        $merged['representatives'] = array_values(array_map(
            fn (array $representative) => [
                'enabled' => array_key_exists('enabled', $representative) ? (bool) $representative['enabled'] : true,
                'name' => (string) ($representative['name'] ?? ''),
                'designation' => (string) ($representative['designation'] ?? 'Authorised Signatory'),
                'signature_path' => $representative['signature_path'] ?? null,
            ],
            array_slice($representatives, 0, 4),
        ));
        $merged['receipt_signatures_enabled'] = array_key_exists('receipt_signatures_enabled', $merged)
            ? (bool) $merged['receipt_signatures_enabled']
            : true;
        $merged['show_seal'] = array_key_exists('show_seal', $merged) ? (bool) $merged['show_seal'] : false;

        return $merged;
    }

    /**
     * @param  array<string, string|null>  $vars
     */
    public static function interpolate(?string $template, array $vars): string
    {
        if (! $template) {
            return '';
        }

        $search = array_keys($vars);
        $replace = array_map(fn ($v) => (string) ($v ?? ''), $vars);

        return str_replace($search, $replace, $template);
    }
}
