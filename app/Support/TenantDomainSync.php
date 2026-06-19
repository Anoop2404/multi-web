<?php

namespace App\Support;

use App\Models\Tenant;

class TenantDomainSync
{
    /** @return array<int, string> Host entries for the Stancl `domains` table */
    public static function resolveHosts(Tenant $tenant): array
    {
        $hosts = [];

        if ($tenant->domain) {
            $hosts[] = self::normalizeCustomDomain($tenant->domain);
        }

        if ($tenant->subdomain) {
            $hosts[] = strtolower($tenant->subdomain);
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    public static function publicUrl(Tenant $tenant): ?string
    {
        if ($tenant->domain) {
            return 'https://'.self::normalizeCustomDomain($tenant->domain);
        }

        if ($tenant->subdomain) {
            return 'https://'.self::subdomainFqdn($tenant->subdomain);
        }

        return null;
    }

    public static function subdomainFqdn(string $subdomain): string
    {
        return strtolower($subdomain).'.'.config('tenancy.tenant_base_domain');
    }

    public static function normalizeCustomDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');

        return $domain;
    }

    public static function isCentralHost(string $host): bool
    {
        $host = self::normalizeCustomDomain($host);

        return in_array($host, config('tenancy.central_domains', []), true);
    }

    public static function isReservedSubdomain(string $subdomain): bool
    {
        return in_array(strtolower($subdomain), config('tenancy.reserved_subdomains', []), true);
    }

    public static function sync(Tenant $tenant): void
    {
        if (! $tenant->is_active) {
            $tenant->domains()->delete();

            return;
        }

        $hosts = self::resolveHosts($tenant);

        $tenant->domains()->whereNotIn('domain', $hosts)->delete();

        foreach ($hosts as $host) {
            $tenant->domains()->updateOrCreate(['domain' => $host]);
        }
    }

    public static function syncAll(): int
    {
        $count = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$count) {
            self::sync($tenant);
            $count++;
        });

        return $count;
    }
}
