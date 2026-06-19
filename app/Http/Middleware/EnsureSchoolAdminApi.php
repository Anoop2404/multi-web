<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolAdminApi
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

        if (! $user->hasAnyRole(['school_admin', 'sahodaya_admin'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $tenantId = $request->route('tenantId');
        if ($tenantId && $user->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->hasRole('school_admin') && ! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email verification required.'], 403);
        }

        if ($tenantId && $user->hasRole('school_admin')) {
            $school = Tenant::find($tenantId);
            if ($school?->membership_status === 'rejected') {
                return response()->json(['message' => 'Your school application was rejected.'], 403);
            }
        }

        return $next($request);
    }
}
