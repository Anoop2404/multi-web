<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use App\Models\FestEventStaff;
use App\Support\TenantUserCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSahodayaAdmin
{
    use RedirectsUnauthenticated;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->redirectToLogin($request);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (! $user->hasAnyRole(TenantUserCatalog::sahodayaAdminPanelRoles())) {
            abort(403);
        }

        $tenantId = $request->route('tenantId');
        if ($tenantId && $user->tenant_id !== $tenantId) {
            abort(403);
        }

        $request->attributes->set(
            'isSahodayaStaff',
            ! $user->isSuperAdmin()
            && ! $user->hasRole('sahodaya_admin')
            && $user->hasAnyRole(TenantUserCatalog::sahodayaPermissionRoles()),
        );

        // Event admins get a full sahodaya-admin experience, but locked to the
        // specific events they've been assigned (via FestEventStaff duty=event_admin).
        // Users with a broader role (sahodaya_admin, etc.) bypass this scoping even
        // if they also happen to hold the event_admin role.
        if ($user->hasRole('event_admin') && ! $user->hasRole('sahodaya_admin')) {
            $allowedEventIds = FestEventStaff::query()
                ->where('user_id', $user->id)
                ->where('duty', 'event_admin')
                ->pluck('event_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $requestedEventId = $this->resolveRouteEventId($request);
            if ($requestedEventId !== null && ! in_array($requestedEventId, $allowedEventIds, true)) {
                abort(403, 'You are not assigned to this event.');
            }

            $request->attributes->set('eventAdminEventIds', $allowedEventIds);
        }

        return $next($request);
    }

    private function resolveRouteEventId(Request $request): ?int
    {
        $raw = $request->route('event');

        if ($raw === null) {
            return null;
        }

        if (is_object($raw)) {
            return isset($raw->id) ? (int) $raw->id : null;
        }

        return is_numeric($raw) ? (int) $raw : null;
    }
}
