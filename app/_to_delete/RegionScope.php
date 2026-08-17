<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use App\Models\UserRegionAssignment;
use App\Support\AcademicYear;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated Retired 06 Aug 2026 (docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.3, Phase 2).
 * This was never registered on any route or middleware alias — confirmed dead code before this
 * plan. Region-admin scoping is now handled by EnsureSahodayaAdmin's region_admin branch, keyed
 * off FestEventStaff.region_id (per event, per region) rather than UserRegionAssignment
 * (tenant-wide, never populated by any UI). Not deleted outright because files in the tracked
 * workspace can't be removed by the tooling that made this change — safe to `git rm` this file
 * and app/Models/UserRegionAssignment.php, and drop the user_region_assignments table via the
 * migration in database/migrations/tenant/2026_09_14_000002_drop_user_region_assignments_table.php.
 */
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
