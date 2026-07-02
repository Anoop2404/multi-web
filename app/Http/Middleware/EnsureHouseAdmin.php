<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHouseAdmin
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

        if (! $user->hasAnyRole(['house_admin', 'school_admin'])) {
            abort(403, 'House admin access required.');
        }

        $tenantId = $request->route('tenantId');
        if ($tenantId && $user->tenant_id !== $tenantId) {
            abort(403);
        }

        if ($user->hasRole('house_admin') && ! $user->school_house_id) {
            abort(403, 'No school house assigned to this account.');
        }

        return $next($request);
    }
}
