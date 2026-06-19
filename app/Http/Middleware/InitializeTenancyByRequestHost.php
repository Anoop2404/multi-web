<?php

namespace App\Http\Middleware;

use App\Support\TenantRequestResolver;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve tenant by full custom domain first, then platform subdomain.
 *
 * Avoids Stancl's default check where hosts like malappuramsahodaya.test are
 * mistaken for subdomains of sahodaya.test because they share that suffix.
 */
class InitializeTenancyByRequestHost
{
    public function handle(Request $request, Closure $next): Response
    {
        TenantRequestResolver::initializeFromRequest($request);

        return $next($request);
    }
}
