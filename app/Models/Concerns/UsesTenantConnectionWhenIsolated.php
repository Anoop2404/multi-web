<?php

namespace App\Models\Concerns;

/**
 * Use the Sahodaya tenant database when TENANCY_DATABASE_PER_SAHODAYA=true.
 * Fall back to the central connection for single-database local/test setups.
 */
trait UsesTenantConnectionWhenIsolated
{
    public function getConnectionName(): ?string
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            return (string) config('tenancy.database.central_connection', 'central');
        }

        return null;
    }
}
