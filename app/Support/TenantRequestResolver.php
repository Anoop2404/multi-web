<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

class TenantRequestResolver
{
    public static function initializeFromHost(string $host): void
    {
        $host = strtolower($host);
        $stateDomain = strtolower((string) config('state.domain', 'state.localhost'));

        if ($stateDomain && $host === $stateDomain) {
            return;
        }

        $tenant = self::tenantForDomain($host);
        if ($tenant) {
            TenancyDatabase::initializeForTenant($tenant);

            return;
        }

        $base = config('tenancy.tenant_base_domain');
        if ($base && str_ends_with($host, '.'.strtolower($base))) {
            $subdomain = substr($host, 0, -strlen('.'.strtolower($base)));
            $tenant = self::tenantForDomain($subdomain);
            if ($tenant) {
                TenancyDatabase::initializeForTenant($tenant);

                return;
            }
        }

        throw new TenantCouldNotBeIdentifiedOnDomainException($host);
    }

    private static function cache()
    {
        // Explicit store bypasses tenant cache tags, including during model events.
        // Database cache would reintroduce the central connection wait on every hit.
        $store = config('cache.default');
        return Cache::store($store === 'database' ? 'file' : $store);
    }

    public static function tenantForDomain(string $host): ?Tenant
    {
        $host = strtolower($host);
        $cache = self::cache();
        $key = 'tenant-host:v1:'.$host;
        $id = $cache->get($key);
        if (! $id) {
            $id = Domain::where('domain', $host)->value('tenant_id');
            if (! $id) {
                return null;
            }
            $cache->put($key, $id, 300);
        }
        $attributes = $cache->remember('tenant-host-model:v1:'.$id, 300,
            fn () => Tenant::find($id)?->getAttributes());
        if (! $attributes) {
            $cache->forget($key);
            return null;
        }
        $tenant = new Tenant;
        $tenant->setRawAttributes($attributes, true);
        $tenant->exists = true;
        return $tenant;
    }

    public static function forgetDomain(string $host): void
    {
        self::cache()->forget('tenant-host:v1:'.strtolower($host));
    }

    public static function forgetTenant(string $id): void
    {
        self::cache()->forget('tenant-host-model:v1:'.$id);
    }

    public static function initializeFromRequest(Request $request): void
    {
        self::initializeFromHost($request->getHost());
    }
}
