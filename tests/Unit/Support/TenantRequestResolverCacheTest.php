<?php

namespace Tests\Unit\Support;

use App\Support\TenantRequestResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantRequestResolverCacheTest extends TestCase
{
    public function test_warm_host_resolution_uses_no_database_queries_and_preserves_tenant_attributes(): void
    {
        config(['cache.default' => 'array']);
        Cache::store('array')->put('tenant-host:v1:example.test', 'cached-tenant', 300);
        Cache::store('array')->put('tenant-host-model:v1:cached-tenant', [
            'id' => 'cached-tenant', 'type' => 'sahodaya', 'name' => 'Cached tenant',
            'data' => '{"tenancy_db_name":"tenant_cached"}',
        ], 300);
        DB::shouldReceive('connection')->never();
        $tenant = TenantRequestResolver::tenantForDomain('EXAMPLE.TEST');
        $this->assertSame('cached-tenant', $tenant->id);
        $this->assertSame('Cached tenant', $tenant->name);
        $this->assertTrue($tenant->exists);
    }

    public function test_domain_and_tenant_changes_invalidate_their_central_cache_entries(): void
    {
        config(['cache.default' => 'array']);
        $cache = Cache::store('array');
        $cache->put('tenant-host:v1:example.test', 'cached-tenant', 300);
        $cache->put('tenant-host-model:v1:cached-tenant', ['id' => 'cached-tenant'], 300);
        TenantRequestResolver::forgetDomain('EXAMPLE.TEST');
        TenantRequestResolver::forgetTenant('cached-tenant');
        $this->assertNull($cache->get('tenant-host:v1:example.test'));
        $this->assertNull($cache->get('tenant-host-model:v1:cached-tenant'));
    }
}
