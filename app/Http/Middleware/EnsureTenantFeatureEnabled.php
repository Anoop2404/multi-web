<?php

namespace App\Http\Middleware;

use App\Services\Licensing\FeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parameterized module gate — Route::middleware('feature:module.mcq'). Mirrors
 * EnsureWebsiteEnabled's plain abort(404) pattern rather than inventing a new one.
 * Superadmin always bypasses, same convention as every other Ensure* middleware.
 */
class EnsureTenantFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        if ($request->user()?->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = tenancy()->tenant;

        if ($tenant && ! app(FeatureGate::class)->allows($tenant, $featureKey)) {
            abort(404);
        }

        return $next($request);
    }
}
