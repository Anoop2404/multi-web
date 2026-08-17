<?php

namespace App\Http\Middleware\Concerns;

use App\Models\User;
use App\Support\EventRegionAdminScope;
use Illuminate\Http\Request;

/**
 * PERM-04 (functional audit, 2026-08-11/12) already pulled the underlying event/region
 * scope math (resolve(), resolveRouteEventId(), matchesRegionScope()) into
 * App\Support\EventRegionAdminScope so EnsureSahodayaAdmin (web) and
 * EnsureSahodayaAdminApi (API) wouldn't hand-maintain separate copies of that. Both
 * middleware still separately re-implemented the surrounding orchestration around it
 * though — computing hasEventAdmin/hasRegionAdmin, calling resolve(), reading the
 * {event} route param, deciding allow/deny, and deciding what to stash on the request.
 * This trait centralizes that remaining duplication so the two middleware can't drift
 * from each other the way §10.1 of the remediation plan warns about.
 *
 * Deliberately NOT centralized here: what happens on denial. Web aborts with a redirect/
 * 403 page, API returns JSON — that response-format difference is genuinely
 * web/API-specific, so it stays in each middleware, driven off the 'denialReason' this
 * method returns. Behavior is unchanged from the two previous hand-copies.
 */
trait ResolvesSahodayaAdminScope
{
    /**
     * @return array{
     *     applies: bool,
     *     allowedEventIds: list<int>,
     *     allowedRegionScopes: list<array{event_id: int, region_id: ?int}>,
     *     denialReason: ?string,
     * }
     */
    protected function resolveSahodayaAdminScope(Request $request, User $user): array
    {
        $hasEventAdmin = $user->hasRole('event_admin') && ! $user->hasRole('sahodaya_admin');
        $hasRegionAdmin = $user->hasRole('region_admin') && ! $user->hasRole('sahodaya_admin');

        if (! $hasEventAdmin && ! $hasRegionAdmin) {
            return [
                'applies'             => false,
                'allowedEventIds'     => [],
                'allowedRegionScopes' => [],
                'denialReason'        => null,
            ];
        }

        $scopes = EventRegionAdminScope::resolve($user, $hasEventAdmin, $hasRegionAdmin);
        $allowedEventIds = $scopes['eventIds'];
        $allowedRegionScopes = $scopes['regionScopes'];

        $requestedEventId = EventRegionAdminScope::resolveRouteEventId($request);

        $denialReason = null;

        if ($requestedEventId !== null) {
            $allowed = in_array($requestedEventId, $allowedEventIds, true);

            if (! $allowed && $allowedRegionScopes !== []) {
                $allowed = EventRegionAdminScope::matchesRegionScope($requestedEventId, $allowedRegionScopes);
            }

            if (! $allowed) {
                $denialReason = 'not_assigned';
            }
        } elseif (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $denialReason = 'unsafe_method';
        }

        return [
            'applies'             => true,
            'allowedEventIds'     => $allowedEventIds,
            'allowedRegionScopes' => $allowedRegionScopes,
            'denialReason'        => $denialReason,
        ];
    }
}
