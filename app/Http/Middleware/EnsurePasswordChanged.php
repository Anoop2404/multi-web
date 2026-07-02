<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    use RedirectsUnauthenticated;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->redirectToLogin($request);
        }

        if ($user->must_change_password && ! $request->routeIs('password.change', 'password.change.store', 'logout')) {
            if ($request->expectsJson()) {
                abort(403, 'You must change your password before continuing.');
            }

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
