<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExamPortal
{
    use RedirectsUnauthenticated;

    private const ALLOWED = [
        'exam_controller', 'exam_staff', 'sahodaya_admin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->redirectToLogin($request);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (! $user->hasAnyRole(self::ALLOWED)) {
            abort(403);
        }

        $tenantId = $request->route('tenantId');
        if ($tenantId && $user->tenant_id !== $tenantId && ! $user->isSuperAdmin()) {
            abort(403);
        }

        return $next($request);
    }
}
