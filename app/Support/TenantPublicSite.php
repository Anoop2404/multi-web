<?php

namespace App\Support;

use App\Models\Tenant;

class TenantPublicSite
{
    public const SETTING_KEY = 'public_website_enabled';

    /**
     * Whether the tenant's full public marketing website should be shown; when
     * false, visitors see the registration/login portal landing instead.
     */
    public static function isEnabled(?Tenant $tenant = null): bool
    {
        $tenant ??= tenancy()->tenant;

        if (! $tenant) {
            return false;
        }

        $setting = $tenant->getSetting(self::SETTING_KEY);

        if ($setting === null) {
            return true;
        }

        if (is_array($setting)) {
            return (bool) ($setting['enabled'] ?? true);
        }

        return (bool) $setting;
    }

    public static function setEnabled(Tenant $tenant, bool $enabled): void
    {
        $tenant->setSetting(self::SETTING_KEY, ['enabled' => $enabled]);
    }
}
