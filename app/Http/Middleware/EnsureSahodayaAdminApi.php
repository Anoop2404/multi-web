<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ResolvesSahodayaAdminScope;
use App\Support\TenantUserCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSahodayaAdminApi
{
    use ResolvesSahodayaAdminScope;

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

        if ($user->hasRole('training_admin') && ! $user->hasRole('sahodaya_admin')
            && ! preg_match('#(?:^|/)training(?:/|$)#', $request->path())) {
            return response()->json(['message' => 'This account is limited to teacher training.'], 403);
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

        // Delegates to ResolvesSahodayaAdminScope (App\Http\Middleware\Concerns) so this
        // stays byte-for-byte in sync with EnsureSahodayaAdmin (web middleware) — see that
        // trait's docblock for the gap-G1 rationale (§10.1 of the remediation plan requires
        // API and web middleware to produce equivalent allow/deny decisions).
        $scope = $this->resolveSahodayaAdminScope($request, $user);

        if ($scope['applies']) {
            if ($scope['denialReason'] === 'not_assigned') {
                return response()->json(['message' => 'You are not assigned to this event.'], 403);
            } elseif ($scope['denialReason'] === 'unsafe_method') {
                return response()->json(['message' => 'Event/region admins can only modify their assigned events.'], 403);
            }

            $request->attributes->set('eventAdminEventIds', $scope['allowedEventIds']);
            $request->attributes->set('regionAdminScopes', $scope['allowedRegionScopes']);
        }

        return $next($request);
    }
}
