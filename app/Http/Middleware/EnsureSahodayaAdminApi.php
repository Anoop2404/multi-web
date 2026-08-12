<?php

namespace App\Http\Middleware;

use App\Support\EventRegionAdminScope;
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

        $hasEventAdmin = $user->hasRole('event_admin') && ! $user->hasRole('sahodaya_admin');
        $hasRegionAdmin = $user->hasRole('region_admin') && ! $user->hasRole('sahodaya_admin');

        if ($hasEventAdmin || $hasRegionAdmin) {
            // Delegates to EventRegionAdminScope (App\Support) so this stays byte-for-byte
            // in sync with EnsureSahodayaAdmin (web middleware) — see that class's docblock
            // for the gap-G1 rationale (§10.1 of the remediation plan requires API and web
            // middleware to produce equivalent allow/deny decisions).
            $scopes = EventRegionAdminScope::resolve($user, $hasEventAdmin, $hasRegionAdmin);
            $allowedEventIds = $scopes['eventIds'];
            $allowedRegionScopes = $scopes['regionScopes'];

            $requestedEventId = EventRegionAdminScope::resolveRouteEventId($request);

            if ($requestedEventId !== null) {
                $allowed = in_array($requestedEventId, $allowedEventIds, true);

                if (! $allowed && $allowedRegionScopes !== []) {
                    $allowed = EventRegionAdminScope::matchesRegionScope($requestedEventId, $allowedRegionScopes);
                }

                if (! $allowed) {
                    return response()->json(['message' => 'You are not assigned to this event.'], 403);
                }
            } elseif (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                return response()->json(['message' => 'Event/region admins can only modify their assigned events.'], 403);
            }

            $request->attributes->set('eventAdminEventIds', $allowedEventIds);
            $request->attributes->set('regionAdminScopes', $allowedRegionScopes);
        }

        return $next($request);
    }
}
