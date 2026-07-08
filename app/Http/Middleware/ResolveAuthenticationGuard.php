<?php

namespace App\Http\Middleware;

use App\Support\TenantDomainSync;
use App\Support\TenantRequestResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pick the auth guard and initialize the Sahodaya database from the request host.
 */
class ResolveAuthenticationGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            Auth::shouldUse('web');

            return $next($request);
        }

        if (TenantDomainSync::isCentralHost($request->getHost())) {
            Auth::shouldUse('platform');
        } elseif (config('tenancy.database_per_sahodaya', true)) {
            try {
                TenantRequestResolver::initializeFromRequest($request);
            } catch (TenantCouldNotBeIdentifiedOnDomainException) {
                Auth::shouldUse('platform');
            }

            if (tenancy()->initialized) {
                Auth::shouldUse('web');
            }
        }

        return $next($request);
    }
}
