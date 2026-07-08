<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use App\Support\TenantUserCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolAdmin
{
    use RedirectsUnauthenticated;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->redirectToLogin($request);
        }

        // Superadmin can access any school panel
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Must have a school panel role
        if (! $user->hasAnyRole(TenantUserCatalog::schoolPanelRoles()) && ! $user->hasRole('sahodaya_admin')) {
            abort(403);
        }

        // Tenant must match
        $tenantId = $request->route('tenantId');
        if ($tenantId && $user->tenant_id !== $tenantId) {
            abort(403);
        }

        if ($user->hasAnyRole(['school_admin', 'school_principal', 'school_vice_principal', 'school_event_coordinator', 'school_sports_coordinator', 'school_kalotsavam_coordinator', 'school_mcq_coordinator', 'school_training_coordinator', 'school_finance_coordinator', 'school_staff']) && ! $user->hasVerifiedEmail()) {
            return \App\Support\InertiaAuth::redirectTo($request, route('verification.notice'));
        }

        $tenantId = $request->route('tenantId');
        if ($tenantId && $user->hasAnyRole(['school_admin', 'school_principal', 'school_vice_principal', 'school_event_coordinator', 'school_sports_coordinator', 'school_kalotsavam_coordinator', 'school_mcq_coordinator', 'school_training_coordinator', 'school_finance_coordinator', 'school_staff'])) {
            $school = \App\Models\Tenant::find($tenantId);
            if ($school && ! $school->is_active) {
                abort(403, 'This school account is inactive.');
            }
            if ($school?->membership_status === 'rejected') {
                abort(403, 'Your school application was rejected.');
            }
        }

        $request->attributes->set('isSchoolStaff',
            ! $user->isSuperAdmin()
            && ! $user->hasAnyRole(TenantUserCatalog::schoolManagementRoles())
            && $user->hasAnyRole(TenantUserCatalog::schoolWriteGatedRoles())
        );

        $request->attributes->set('isEventCoordinator',
            ! $user->isSuperAdmin()
            && ! $user->hasAnyRole(TenantUserCatalog::schoolManagementRoles())
            && $user->hasRole('school_event_coordinator')
        );

        return $next($request);
    }
}
