<?php

namespace App\Http\Middleware;

use App\Support\TenantPublicSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks public CMS pages (news, events, etc.) when a tenant has disabled
 * their public website. Home and portal routes remain available.
 */
class EnsureTenantPublicWebsiteEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! TenantPublicSite::isEnabled(tenancy()->tenant)) {
            return redirect('/');
        }

        return $next($request);
    }
}
