<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use App\Models\UserRegionAssignment;
use App\Support\AcademicYear;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegionScope
{
    use RedirectsUnauthenticated;

    /**
     * Inject the requesting user's allowed region IDs into the request attributes
     * so downstream controllers can filter queries by region. The middleware is
     * a no-op for superadmins, sahodaya_admins, and users without any region
     * assignments — they see everything.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->redirectToLogin($request);
        }

        // Superadmins and Sahodaya admins are never region-scoped.
        if ($user->isSuperAdmin() || $user->hasRole('sahodaya_admin')) {
            $request->attributes->set('region_ids', null);

            return $next($request);
        }

        $tenantId = $request->route('tenantId');
        if (! $tenantId) {
            $request->attributes->set('region_ids', null);

            return $next($request);
        }

        // User must have region_admin role to be region-scoped.
        if (! $user->hasRole('region_admin')) {
            $request->attributes->set('region_ids', null);

            return $next($request);
        }

        $year = AcademicYear::forSahodaya((string) $tenantId);
        $regionIds = UserRegionAssignment::forTenant((string) $tenantId)
            ->forUser((string) $user->id)
            ->forYear($year)
            ->pluck('region_id')
            ->unique()
            ->values()
            ->all();

        // null = unrestricted (all regions), [] = no accessible regions
        $request->attributes->set('region_ids', $regionIds !== [] ? $regionIds : null);

        return $next($request);
    }
}
