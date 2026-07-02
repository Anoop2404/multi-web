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

        if (! $user->hasAnyRole(['state_admin', 'state_staff'])) {
            abort(403, 'State admin access required.');
        }

        $request->attributes->set('isStateStaff', $user->hasRole('state_staff'));

        if ($user->hasRole('state_staff')
            && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            abort(403, 'View-only access. Contact your state administrator.');
        }

        return $next($request);
    }
}
