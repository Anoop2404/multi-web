<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use App\Models\FestEventStaff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFestEventOps
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

        $tenantId = $request->route('tenantId');

        if ($user->hasRole('sahodaya_admin') && $user->tenant_id === $tenantId) {
            return $next($request);
        }

        if ($user->hasRole('fest_ops') && $user->tenant_id === $tenantId) {
            return $next($request);
        }

        if ($tenantId && FestEventStaff::where('user_id', $user->id)->exists()) {
            return $next($request);
        }

        abort(403, 'Event operations access required.');
    }
}
