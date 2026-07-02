<?php

namespace App\Http\Middleware;

use App\Support\TenantUserCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSahodayaAdminApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (! $user->hasAnyRole(TenantUserCatalog::sahodayaAdminPanelRoles())) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $tenantId = $request->route('tenantId');
        if ($tenantId && $user->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $isStaff = ! $user->isSuperAdmin()
            && ! $user->hasRole('sahodaya_admin')
            && $user->hasAnyRole(TenantUserCatalog::sahodayaPermissionRoles());

        $request->attributes->set('isSahodayaStaff', $isStaff);

        if ($isStaff && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $permission = TenantUserCatalog::writePermissionForPath($request->path());
            if ($permission === null || ! $user->can($permission)) {
                return response()->json(['message' => 'View-only access. Contact your Sahodaya administrator.'], 403);
            }
        }

        return $next($request);
    }
}
