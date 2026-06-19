<?php

namespace App\Support\Mail;

use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;

class EmailBranding
{
    /** @return array<string, mixed> */
    public static function forTenant(?Tenant $tenant, ?SahodayaProfile $profile = null): array
    {
        if (! $tenant) {
            return self::defaults();
        }

        $portal = TenantDomainSync::publicUrl($tenant);
        $logo = TenantBranding::logoUrl($tenant);

        return [
            'sahodayaName'  => $tenant->name,
            'portalUrl'     => $portal,
            'logoUrl'       => self::absoluteAssetUrl($logo, $portal),
            'contactEmail'  => $profile?->contact_email,
            'fromName'      => $profile?->mail_from_name ?: $tenant->name,
        ];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'sahodayaName'  => config('app.name'),
            'portalUrl'     => config('app.url'),
            'logoUrl'       => null,
            'contactEmail'  => null,
            'fromName'      => config('app.name'),
        ];
    }

    public static function absoluteAssetUrl(?string $path, ?string $baseUrl = null): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $root = rtrim($baseUrl ?: (string) config('app.url'), '/');

        return $root.'/'.ltrim($path, '/');
    }

    public static function portalUrl(?Tenant $tenant, string $path = ''): string
    {
        $base = $tenant ? TenantDomainSync::publicUrl($tenant) : null;
        $root = rtrim($base ?: (string) config('app.url'), '/');
        $path = ltrim($path, '/');

        return $path !== '' ? "{$root}/{$path}" : $root;
    }

    public static function schoolLoginUrl(?Tenant $sahodaya): string
    {
        return self::portalUrl($sahodaya, 'login');
    }

    public static function schoolAdminUrl(?Tenant $sahodaya, Tenant $school, string $path = ''): string
    {
        $segment = 'school-admin/'.$school->id;
        $fullPath = $path !== '' ? $segment.'/'.ltrim($path, '/') : $segment;

        return self::portalUrl($sahodaya, $fullPath);
    }

    public static function sahodayaAdminUrl(?Tenant $sahodaya, string $path = ''): string
    {
        if (! $sahodaya) {
            return self::portalUrl(null, $path);
        }

        $segment = 'sahodaya-admin/'.$sahodaya->id;

        return self::portalUrl($sahodaya, $path !== '' ? $segment.'/'.ltrim($path, '/') : $segment);
    }
}
