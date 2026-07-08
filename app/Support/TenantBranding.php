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

        $stored = TenancyDatabase::whenDatabaseReady(
            $tenant,
            fn () => $tenant->getSetting('logo'),
        );
        if ($stored) {
            if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://') || str_starts_with($stored, '/')) {
                return self::publicPath($stored);
            }

            return TenantStorage::logoUrl($tenant, $stored) ?? self::publicPath('/storage/'.$stored);
        }

        if ($tenant->subdomain && isset(self::DEFAULT_LOGOS[$tenant->subdomain])) {
            return self::publicPath(self::DEFAULT_LOGOS[$tenant->subdomain]);
        }

        return null;
    }

    /** Logo as data URI or absolute URL for PDF / print embeds. */
    public static function logoEmbedSrc(Tenant $tenant): ?string
    {
        $stored = TenancyDatabase::whenDatabaseReady(
            $tenant,
            fn () => $tenant->getSetting('logo'),
        );

        if ($stored) {
            $embedded = TenantStorage::photoDataUri($tenant, $stored);
            if ($embedded) {
                return $embedded;
            }
        }

        if ($tenant->subdomain && isset(self::DEFAULT_LOGOS[$tenant->subdomain])) {
            $path = public_path(ltrim(self::DEFAULT_LOGOS[$tenant->subdomain], '/'));
            if (is_file($path)) {
                $mime = mime_content_type($path) ?: 'image/png';

                return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
            }
        }

        $url = self::logoUrl($tenant);
        if (! $url) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return url($url);
        }

        return $url;
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
        $path = TenantStorage::storeLogo($file, $tenant->id);

        TenancyDatabase::runWhenDatabaseReady(
            $tenant,
            function () use ($tenant, $path) {
                $tenant->setSetting('logo', $path);

                // Keep hero section in sync when logo is uploaded after template apply.
                $tenant->sections()->where('section_type', 'hero')->each(function ($section) use ($path) {
                    $config = $section->config ?? [];
                    $config['logo'] = $path;
                    $section->update(['config' => $config]);
                });
            },
        );

        $stored = TenancyDatabase::whenDatabaseReady(
            $tenant,
            fn () => $tenant->getSetting('logo'),
        );

        $resolved = TenantStorage::logoUrl($tenant, $stored ?? $path);

        return $resolved ?? self::publicPath('/storage/'.$path);
    }
}
