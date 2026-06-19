<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSahodayaAdminApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (! $user->hasRole('sahodaya_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $tenantId = $request->route('tenantId');
        if ($tenantId && $user->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
