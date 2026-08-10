<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware ensuring state domain routes are executed on the explicit State domain
 * and preventing tenant context initialization on the State host.
 */
class EnsureStateDomainContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $stateDomain = strtolower((string) config('state.domain', 'state.localhost'));
        $requestHost = strtolower($request->getHost());

        if (function_exists('tenancy') && tenancy()->initialized) {
            abort(403, 'Tenant context is forbidden on dedicated State domain.');
        }

        if ($stateDomain && $requestHost !== $stateDomain && ! app()->environment('testing', 'local')) {
            abort(403, 'Request domain does not match configured State application domain.');
        }

        return $next($request);
    }
}
