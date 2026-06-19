<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
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

        // Must have school_admin or sahodaya_admin role
        if (!$user->hasAnyRole(['school_admin', 'sahodaya_admin'])) {
            abort(403);
        }

        // Tenant must match
        $tenantId = $request->route('tenantId');
        if ($tenantId && $user->tenant_id !== $tenantId) {
            abort(403);
        }

        if ($user->hasRole('school_admin') && ! $user->hasVerifiedEmail()) {
            return \App\Support\InertiaAuth::redirectTo($request, route('verification.notice'));
        }

        $tenantId = $request->route('tenantId');
        if ($tenantId && $user->hasRole('school_admin')) {
            $school = \App\Models\Tenant::find($tenantId);
            if ($school?->membership_status === 'rejected') {
                abort(403, 'Your school application was rejected.');
            }
        }

        return $next($request);
    }
}
