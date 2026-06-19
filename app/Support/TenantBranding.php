<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantBranding
{
    /** Default logos keyed by subdomain (demo / seed assets). */
    private const DEFAULT_LOGOS = [
        'malappuram' => '/images/tenants/malappuram-logo.png',
    ];

    public static function resolveTenant(?Request $request = null): ?Tenant
    {
        if (function_exists('tenant')) {
            $current = tenant();
            if ($current) {
                return $current;
            }
        }

        $request ??= request();
        if (! $request) {
            return null;
        }

        $host = strtolower($request->getHost());

        $byDomain = Tenant::where('domain', $host)->first();
        if ($byDomain) {
            return $byDomain;
        }

        $base = config('tenancy.tenant_base_domain');
        if ($base && str_ends_with($host, '.' . $base)) {
            $subdomain = substr($host, 0, -strlen('.' . $base));
            if ($subdomain !== '' && ! in_array($subdomain, config('tenancy.reserved_subdomains', []), true)) {
                return Tenant::where('subdomain', $subdomain)->first();
            }
        }

        return null;
    }

    public static function logoUrl(?Tenant $tenant = null): ?string
    {
        $tenant ??= self::resolveTenant();

        if (! $tenant) {
            return null;
        }

        $stored = $tenant->getSetting('logo');
        if ($stored) {
            return self::publicPath($stored);
        }

        if ($tenant->subdomain && isset(self::DEFAULT_LOGOS[$tenant->subdomain])) {
            return self::publicPath(self::DEFAULT_LOGOS[$tenant->subdomain]);
        }

        return null;
    }

    /** Root-relative URL so logos work on tenant subdomains and dev ports (e.g. :8000). */
    private static function publicPath(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }

    public static function storeUpload(Tenant $tenant, \Illuminate\Http\UploadedFile $file): string
    {
        $path = $file->store('logos/' . $tenant->id, 'public');
        $url  = self::publicPath('storage/' . $path);
        $tenant->setSetting('logo', $url);

        return $url;
    }
}
