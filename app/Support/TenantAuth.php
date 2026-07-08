<?php

namespace App\Support;

use App\Models\Tenant;

class TenantAuth
{
    /**
     * Run a callback with the Sahodaya tenant database active for portal user reads/writes.
     */
    public static function withTenantUsers(Tenant $tenant, callable $callback): mixed
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            return $callback();
        }

        return TenancyDatabase::withTenantDatabase(TenancyDatabase::owner($tenant), $callback);
    }
}
