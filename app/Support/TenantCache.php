<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;

class TenantCache
{
    public static function store(): Repository
    {
        return app('cache')->store(config('cache.default'));
    }

    public static function remember(string $tenantId, string $key, mixed $ttl, callable $callback): mixed
    {
        return self::store()->remember(self::key($tenantId, $key), $ttl, $callback);
    }

    public static function forget(string $tenantId, string $key): void
    {
        self::store()->forget(self::key($tenantId, $key));
    }

    public static function flushTenant(string $tenantId): void
    {
        foreach (['setting:theme', 'setting:nav_config', 'setting:footer_config', 'setting:widgets', 'setting:seo', 'setting:locale', 'setting:logo', 'site:layout', 'sitemap'] as $key) {
            self::forget($tenantId, $key);
        }
    }

    private static function key(string $tenantId, string $key): string
    {
        return "tenant:{$tenantId}:{$key}";
    }
}
