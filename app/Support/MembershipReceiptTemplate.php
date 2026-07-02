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
            $merged['registered_office'] = $profile->address;
        }

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
