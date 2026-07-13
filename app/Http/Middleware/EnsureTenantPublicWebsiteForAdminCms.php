<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantPublicSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides full CMS admin routes when a tenant has disabled their public website.
 * Portal landing settings remain available so admins can re-enable the site.
 */
class EnsureTenantPublicWebsiteForAdminCms
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenancy()->tenant ?? $this->resolveRouteTenant($request);

        if (! $tenant || TenantPublicSite::isEnabled($tenant)) {
            return $next($request);
        }

        $message = 'Public website is disabled. Open Portal landing to edit the registration portal or re-enable the full CMS.';

        if ($tenant->type === 'sahodaya') {
            return redirect("/sahodaya-admin/{$tenant->id}/public-content")
                ->with('info', $message);
        }

        return redirect("/school-admin/{$tenant->id}/settings")
            ->with('info', $message);
    }

    private function resolveRouteTenant(Request $request): ?Tenant
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
