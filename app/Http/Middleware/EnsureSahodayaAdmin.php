<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use App\Http\Middleware\Concerns\ResolvesSahodayaAdminScope;
use App\Support\TenantSubscriptionGate;
use App\Support\TenantUserCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSahodayaAdmin
{
    use RedirectsUnauthenticated;
    use ResolvesSahodayaAdminScope;

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

        if ($tenantId) {
            $sahodaya = \App\Models\Tenant::find($tenantId);
            if ($sahodaya && ! $sahodaya->is_active) {
                abort(403, 'This organization is inactive.');
            }
            if ($sahodaya) {
                $subscriptionBlock = TenantSubscriptionGate::check($sahodaya, $request);
                if ($subscriptionBlock === 'suspended') {
                    abort(403, "This organization's subscription has been suspended. Contact the platform administrator.");
                }
                if ($subscriptionBlock === 'readonly') {
                    abort(403, "This organization's subscription is read-only. Contact the platform administrator to restore full access.");
                }
            }
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
        // See docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.3. The actual scope
        // resolution below is shared with EnsureSahodayaAdminApi via
        // ResolvesSahodayaAdminScope — see that trait's docblock.
        $scope = $this->resolveSahodayaAdminScope($request, $user);

        if ($scope['applies']) {
            if ($scope['denialReason'] === 'not_assigned') {
                abort(403, 'You are not assigned to this event.');
            } elseif ($scope['denialReason'] === 'unsafe_method') {
                abort(403, 'Event/region admins can only modify their assigned events.');
            }

            $request->attributes->set('eventAdminEventIds', $scope['allowedEventIds']);
            $request->attributes->set('regionAdminScopes', $scope['allowedRegionScopes']);
            $request->attributes->set('phaseAdminScopes', $scope['allowedPhaseScopes']);
        }

        return $next($request);
    }
}
