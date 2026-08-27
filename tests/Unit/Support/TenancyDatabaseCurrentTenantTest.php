<?php

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Support\TenancyDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TenancyDatabase::currentTenant()'s reverse-lookup-by-connection-name branch matters for
 * exactly one real scenario: a queued job (e.g. SendTrainingCertificateEmailChunkJob) that
 * wraps work in TenancyDatabase::withTenantDatabase() without ever calling Stancl's own
 * tenancy()->initialize() — so tenancy()->tenant is null even though the correct database
 * is active. Tested here as pure config/logic (not a real second physical database):
 * withTenantDatabase()'s connection swap (via usingDatabase()) only ever changes what
 * database.default points at and what database name that connection is configured with —
 * currentTenant() just needs to read that config back and match it against a Tenant's
 * db_name. Actually routing real queries through a swapped SQLite :memory: connection
 * would connect to an entirely separate, empty in-memory database (each :memory: PDO
 * connection is isolated unless using SQLite's shared-cache DSN), so this deliberately
 * doesn't attempt that — it verifies the exact config comparison currentTenant() does.
 */
class TenancyDatabaseCurrentTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_tenant_returns_null_when_no_tenant_context_is_active(): void
    {
        $this->assertFalse(tenancy()->initialized);
        $this->assertNull(TenancyDatabase::currentTenant());
    }

    public function test_current_tenant_resolves_by_matching_the_active_connections_database_name(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Current Tenant Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        $sahodaya->setInternal('db_name', 'fake_tenant_db_for_test');
        $sahodaya->save();

        $this->assertFalse(tenancy()->initialized, 'This test exercises the non-Stancl-initialized branch specifically.');

        $previousDefault = config('database.default');

        try {
            config([
                'database.connections.tenant_runtime' => ['driver' => 'sqlite', 'database' => 'fake_tenant_db_for_test'],
                'database.default' => 'tenant_runtime',
            ]);

            $resolved = TenancyDatabase::currentTenant();
        } finally {
            config(['database.default' => $previousDefault]);
        }

        $this->assertNotNull($resolved);
        $this->assertSame($sahodaya->id, $resolved->id);
    }

    public function test_current_tenant_does_not_match_a_different_tenants_database_name(): void
    {
        $other = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Other Tenant',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        $other->setInternal('db_name', 'some_other_db');
        $other->save();

        $previousDefault = config('database.default');

        try {
            config([
                'database.connections.tenant_runtime' => ['driver' => 'sqlite', 'database' => 'a_db_no_tenant_owns'],
                'database.default' => 'tenant_runtime',
            ]);

            $resolved = TenancyDatabase::currentTenant();
        } finally {
            config(['database.default' => $previousDefault]);
        }

        $this->assertNull($resolved);
    }
}
