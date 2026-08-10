<?php

namespace App\Http\Middleware;

use App\Models\FestEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Implements docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md §3.6 literally: "A
 * request to a parent report URL is either redirected to the matching regional scope or
 * resolved server-side as that fixed region." Runs after EnsureSahodayaAdmin (which has
 * already populated the 'regionAdminScopes' request attribute and rejected any access
 * the actor isn't entitled to at all — see that middleware's matchesRegionScope()).
 *
 * When the actor is a region-locked admin (never a broader role — EnsureSahodayaAdmin
 * only ever sets 'regionAdminScopes' for a user with duty=region_admin and no broader
 * admin role) and the bound {event} route parameter is a hub/root event, this
 * substitutes the bound event with that actor's own regional child before the
 * controller runs. Every report method downstream then reads $event as that leaf child
 * — reportableEventIds()/reportableItemIds() naturally resolve to just that child's own
 * data. This is the same containment already applied by hand to Registration Register
 * (FestReportController::resolveRegistrationRegisterScope()), but applied once,
 * centrally, to every route in the reports route group (see routes/web.php, the
 * Route::middleware([...])->group() wrapping the /{event}/reports/* routes).
 *
 * This is a scoped fix for Phase 1's exit criteria ("Region A admin receives no Region B
 * sentinel data from any parent, child, preview, or export URL"). It intentionally does
 * NOT replace the deeper per-report-family FestReportScope retrofit (Phase 3) — reports
 * that need explicit Combined vs Region-wise *selection* (not just containment against a
 * single assigned region) still need that work, and this middleware does not touch
 * routes outside the reports group (settings, registration approval, etc.).
 */
class ResolveRegionScopedReportEvent
{
    public function handle(Request $request, Closure $next): Response
    {
        $scopes = $request->attributes->get('regionAdminScopes');

        if (empty($scopes)) {
            return $next($request);
        }

        $bound = $request->route('event');
        $event = $bound instanceof FestEvent ? $bound : ($bound !== null ? FestEvent::find($bound) : null);

        if (! $event || $event->parent_event_id !== null) {
            // Not a hub — either already a leaf (nothing to resolve) or the route has no
            // {event} param at all. Leave EnsureSahodayaAdmin's own check as the source
            // of truth for whether this request is allowed at all.
            return $next($request);
        }

        $allowedRegionIds = collect($scopes)->pluck('region_id')->filter()->unique()->values()->all();

        if (count($allowedRegionIds) !== 1) {
            // Zero regions: EnsureSahodayaAdmin's fail-closed fix already blocked this
            // request before it reached here. More than one assigned region: ambiguous
            // which one the hub should resolve to — leave $event as the hub rather than
            // guess, and require the report's own scope resolution to demand an explicit
            // region_id (as FestReportController::resolveRegistrationRegisterScope()
            // already does).
            return $next($request);
        }

        $child = $event->regionalChild($allowedRegionIds[0]);

        if ($child) {
            $request->route()->setParameter('event', $child);
        }

        return $next($request);
    }
}
