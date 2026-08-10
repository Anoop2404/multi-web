<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use App\Models\FestEvent;
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

        if ($user->hasRole('training_admin') && ! $user->hasRole('sahodaya_admin')) {
            $trainingPrefix = "sahodaya-admin/{$tenantId}/training";
            $path = trim($request->path(), '/');
            abort_unless(
                $path === $trainingPrefix || str_starts_with($path, $trainingPrefix.'/'),
                403,
                'This account is limited to teacher training.',
            );
        }

        $request->attributes->set(
            'isSahodayaStaff',
            ! $user->isSuperAdmin()
            && ! $user->hasRole('sahodaya_admin')
            && $user->hasAnyRole(TenantUserCatalog::sahodayaPermissionRoles()),
        );

        // Event admins get a full sahodaya-admin experience, but locked to the
        // specific events they've been assigned (via FestEventStaff duty=event_admin).
        // Region admins get the same style of scoping, but locked to a single region
        // within a single event (via FestEventStaff duty=region_admin + region_id) —
        // either assigned directly on a region-partition child event, or on the hub,
        // in which case they may reach any of that hub's child events matching their
        // region_id. Users with a broader role (sahodaya_admin, etc.) bypass all of
        // this even if they also happen to hold event_admin/region_admin.
        //
        // See docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.3.
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

            $requestedEventId = $this->resolveRouteEventId($request);

            if ($requestedEventId !== null) {
                $allowed = in_array($requestedEventId, $allowedEventIds, true);

                if (! $allowed && $allowedRegionScopes !== []) {
                    $allowed = $this->matchesRegionScope($requestedEventId, $allowedRegionScopes);
                }

                if (! $allowed) {
                    abort(403, 'You are not assigned to this event.');
                }
            } elseif (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                abort(403, 'Event/region admins can only modify their assigned events.');
            }

            $request->attributes->set('eventAdminEventIds', $allowedEventIds);
            $request->attributes->set('regionAdminScopes', $allowedRegionScopes);
        }

        return $next($request);
    }

    /**
     * True when the requested event is either (a) a region-partition child directly
     * matching one of the admin's (event_id, region_id) scopes, or (b) that same child
     * reached via a scope granted on its parent hub — i.e. a region admin assigned on
     * the hub can reach any of the hub's children for their own region without needing
     * a separate FestEventStaff row per child event.
     *
     * A scope directly on a hub/root event (no parent_event_id of its own) must carry a
     * region_id. Without this check, a FestEventStaff row with duty=region_admin,
     * event_id=<hub>, region_id=null would satisfy the very first comparison below
     * regardless of region — granting full, unscoped access to every child/region under
     * that hub. That is gap G1 in docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md:
     * a region admin assigned on a parent hub could open the hub itself, and parent
     * reports then aggregate every child. A scope on a genuine leaf/child event has
     * nothing further under it to leak, so it's allowed on its own.
     *
     * @param  list<array{event_id: int, region_id: ?int}>  $allowedRegionScopes
     */
    private function matchesRegionScope(int $requestedEventId, array $allowedRegionScopes): bool
    {
        $requestedEvent = FestEvent::query()
            ->select(['id', 'parent_event_id', 'region_id'])
            ->find($requestedEventId);

        if (! $requestedEvent) {
            return false;
        }

        $requestedIsHub = $requestedEvent->parent_event_id === null;

        foreach ($allowedRegionScopes as $scope) {
            if ($scope['event_id'] === (int) $requestedEvent->id) {
                if ($requestedIsHub && $scope['region_id'] === null) {
                    continue;
                }

                return true;
            }

            if ($requestedEvent->parent_event_id
                && $scope['event_id'] === (int) $requestedEvent->parent_event_id
                && $scope['region_id'] !== null
                && $requestedEvent->region_id !== null
                && $scope['region_id'] === (int) $requestedEvent->region_id) {
                return true;
            }
        }

        return false;
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
