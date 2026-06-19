<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenancyDatabase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initialize the Sahodaya database for admin/API routes that pass {tenantId} on the central domain.
 */
class InitializeTenancyByRouteTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! TenancyDatabase::enabled()) {
            return $next($request);
        }

        $tenant = $this->resolveTenant($request);

        if ($tenant) {
            TenancyDatabase::initializeForTenant($tenant);
        }

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $param = $request->route('tenantId')
            ?? $request->route('tenant')
            ?? $request->route('school');

        if ($param instanceof Tenant) {
            return $param;
        }

        if (is_string($param) && $param !== '') {
            return Tenant::query()->find($param);
        }

        return null;
    }
}
