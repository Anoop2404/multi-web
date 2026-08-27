<?php

namespace Tests\Unit\Support;

use App\Support\TenancyDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for a bug found while live-testing the certificate tenant-resolution
 * fix: TenancyDatabase::usingDatabase()'s RUNTIME_CONNECTION is a single global config
 * slot, not one per call. A nested call for the same database (e.g.
 * PublicCertificateController::print() wraps Training rendering in
 * withTenantDatabase(), and TrainingCertificateService::renderContext() calls
 * TenantBranding::logoUrl() -> TenancyDatabase::whenDatabaseReady() ->
 * SahodayaDatabaseProvisioner::schemaIsReady(), which calls
 * TenancyDatabase::usingDatabase() again directly) used to null out
 * database.connections.tenant_runtime in its own `finally` cleanup the moment it
 * returned — breaking any query the OUTER call still needed to make afterward
 * ("Database connection [tenant_runtime] not configured", reproduced live on
 * certificates.print for a real Training certificate before this fix).
 */
class TenancyDatabaseNestedCallTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_call_for_the_same_database_does_not_break_the_outer_connection(): void
    {
        $previousDefault = config('database.default');

        try {
            TenancyDatabase::usingDatabase('same_db_for_test', function () {
                // Nested call, same database — must be a safe no-op re-entry, not a
                // second swap-and-tear-down.
                $inner = TenancyDatabase::usingDatabase('same_db_for_test', fn () => 'inner-ran');
                $this->assertSame('inner-ran', $inner);

                // The connection the outer call set up must still be intact afterward —
                // this is exactly the state a subsequent query (like print()'s second
                // resolveFieldValues() call) depends on.
                $this->assertSame('tenant_runtime', config('database.default'));
                $this->assertNotNull(config('database.connections.tenant_runtime'));
                $this->assertSame('same_db_for_test', config('database.connections.tenant_runtime.database'));
            });
        } finally {
            config(['database.default' => $previousDefault]);
        }
    }

    /**
     * A nested call for a genuinely different database is NOT the scenario this fix
     * addresses (and isn't one this codebase's real call patterns produce — nesting only
     * ever happens for the tenant the outer call is already in) — it must not be
     * mistaken for the same-database case above and silently no-op on the wrong
     * database. What happens to the OUTER call's connection after such a mismatched
     * nested call returns is a pre-existing limitation of RUNTIME_CONNECTION being a
     * single global slot, not something this fix changes either way.
     */
    public function test_a_call_for_a_different_database_still_swaps_to_its_own_database(): void
    {
        $previousDefault = config('database.default');

        try {
            TenancyDatabase::usingDatabase('outer_db_for_test', function () {
                $this->assertSame('outer_db_for_test', config('database.connections.tenant_runtime.database'));

                TenancyDatabase::usingDatabase('different_db_for_test', function () {
                    $this->assertSame('different_db_for_test', config('database.connections.tenant_runtime.database'));
                });
            });
        } finally {
            config(['database.default' => $previousDefault]);
        }
    }
}
