<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * State-level analog of EnsureJudgePortal — no tenant_id scoping since State isn't a tenant;
 * a 'state_judge' role plus state_admin/state_staff (who can act as judges for oversight,
 * same as sahodaya_admin can at the tenant level).
 */
class EnsureStateJudgePortal
{
    use RedirectsUnauthenticated;

    private const ALLOWED_ROLES = ['state_judge', 'state_admin', 'state_staff'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->redirectToLogin($request);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (! $user->hasAnyRole(self::ALLOWED_ROLES)) {
            abort(403, 'State judge access required.');
        }

        return $next($request);
    }
}
