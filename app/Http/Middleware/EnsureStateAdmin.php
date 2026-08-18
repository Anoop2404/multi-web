<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStateAdmin
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

        $isStateUser = $user->hasAnyRole(['state_admin', 'state_staff'])
            || (method_exists($user, 'isStateUser') && $user->isStateUser());

        if (! $isStateUser) {
            abort(403, 'State admin access required.');
        }

        $isStaff = $user->hasRole('state_staff')
            || (method_exists($user, 'hasStateStaffRole') && $user->hasStateStaffRole());

        $request->attributes->set('isStateStaff', $isStaff);

        if ($isStaff && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            abort(403, 'View-only access. Contact your state administrator.');
        }

        // Data-isolation fix (FRD-13 gap analysis, Finding A): controllers read this
        // to scope their queries. A state user with no state assigned yet gets null,
        // which every scoped query below treats as "see nothing" — fail closed, not
        // open, until an admin assigns them via State Users.
        $request->attributes->set('stateId', $user->state_id);

        return $next($request);
    }
}
