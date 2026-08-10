<?php

namespace App\Http\Middleware;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
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
            $allowedEventIds = [];
            $allowedRegionScopes = [];

            if ($hasEventAdmin) {
                $allowedEventIds = FestEventStaff::query()
                    ->where('user_id', $user->id)
                    ->where('duty', 'event_admin')
                    ->pluck('event_id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
            }

            if ($hasRegionAdmin) {
                $allowedRegionScopes = FestEventStaff::query()
                    ->where('user_id', $user->id)
                    ->where('duty', 'region_admin')
                    ->get(['event_id', 'region_id'])
                    ->map(fn ($row) => [
                        'event_id'  => (int) $row->event_id,
                        'region_id' => $row->region_id !== null ? (int) $row->region_id : null,
                    ])
                    ->values()
                    ->all();
            }

            $raw = $request->route('event');
            $requestedEventId = is_object($raw) ? ($raw->id ?? null) : (is_numeric($raw) ? (int) $raw : null);
            $requestedEventId = $requestedEventId !== null ? (int) $requestedEventId : null;

            if ($requestedEventId !== null) {
                $allowed = in_array($requestedEventId, $allowedEventIds, true);

                if (! $allowed && $allowedRegionScopes !== []) {
                    $requestedEvent = FestEvent::query()
                        ->select(['id', 'parent_event_id', 'region_id'])
                        ->find($requestedEventId);

                    $requestedIsHub = $requestedEvent && $requestedEvent->parent_event_id === null;

                    // Mirrors EnsureSahodayaAdmin::matchesRegionScope() (web middleware) — see
                    // that method's docblock for why a hub-level scope with no region_id must
                    // not grant access (gap G1). Kept in sync deliberately: §10.1 of the
                    // remediation plan requires API and web middleware to produce equivalent
                    // results.
                    $allowed = $requestedEvent && collect($allowedRegionScopes)->contains(function (array $scope) use ($requestedEvent, $requestedIsHub) {
                        if ($scope['event_id'] === (int) $requestedEvent->id) {
                            return ! ($requestedIsHub && $scope['region_id'] === null);
                        }

                        return $requestedEvent->parent_event_id
                            && $scope['event_id'] === (int) $requestedEvent->parent_event_id
                            && $scope['region_id'] !== null
                            && $requestedEvent->region_id !== null
                            && $scope['region_id'] === (int) $requestedEvent->region_id;
                    });
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
