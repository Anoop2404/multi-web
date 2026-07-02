<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGroupAdmin
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

        // group_admin, school_admin, or sahodaya_admin may access group portals
        if (! $user->hasAnyRole(['group_admin', 'school_admin', 'sahodaya_admin'])) {
            abort(403, 'Group admin access required.');
        }

        $tenantId = $request->route('tenantId');
        if ($tenantId && $user->tenant_id !== $tenantId) {
            if ($user->hasRole('sahodaya_admin')) {
                $school = \App\Models\Tenant::query()->find($tenantId);
                abort_unless($school && $school->type === 'school' && $school->parent_id === $user->tenant_id, 403);
            } else {
                abort(403);
            }
        }

        return $next($request);
    }
}
