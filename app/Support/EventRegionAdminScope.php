<?php

namespace App\Support;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * PERM-04 fix (functional audit, 2026-08-11/12): EnsureSahodayaAdmin (web) and
 * EnsureSahodayaAdminApi (API) each hand-maintained an independent copy of this exact
 * event/region-admin scoping logic. That was the audit's flagged residual risk — a
 * future edit to one without the other would silently reintroduce drift, which had
 * already happened once historically (see matchesRegionScope()'s doc on gap G1: a
 * hub-level region scope with no region_id used to leak unscoped access, and the fix
 * had to be hand-applied to both files separately). Both middleware now delegate here
 * instead of keeping their own copies.
 */
class EventRegionAdminScope
{
    /**
     * @return array{eventIds: list<int>, regionScopes: list<array{event_id: int, region_id: ?int}>}
     */
    public static function resolve(User $user, bool $hasEventAdmin, bool $hasRegionAdmin): array
    {
        $allowedEventIds = [];
        $allowedRegionScopes = [];

        if ($hasEventAdmin) {
            $allowedEventIds = FestEventStaff::query()
                ->where('user_id', $user->id)
                ->where('duty', 'event_admin')
                ->pluck('event_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        if ($hasRegionAdmin) {
            $allowedRegionScopes = FestEventStaff::query()
                ->where('user_id', $user->id)
                ->where('duty', 'region_admin')
                ->get(['event_id', 'region_id'])
                ->map(fn ($row) => [
                    'event_id'  => (int) $row->event_id,
                    'region_id' => $row->region_id !== null ? (int) $row->region_id : null,
                ])
                ->values()
                ->all();
        }

        return ['eventIds' => $allowedEventIds, 'regionScopes' => $allowedRegionScopes];
    }

    /**
     * Reads the {event} route parameter (Fest routes), whether it arrived as a bound
     * model or a raw id, and resolves it to the FestEvent id that scoped region/event
     * admins are checked against.
     *
     * Also handles {exam} (every MCQ route binds this instead of {event} — see
     * routes/web.php's "mcq-exams" group). McqExam has no event_id/FK back to
     * FestEvent: it is a Sahodaya-wide dataset with its own separate authorization
     * mechanism (McqExamStaff), entirely independent of FestEventStaff's
     * event_admin/region_admin scoping. Before this fix, {exam} routes always
     * resolved to null here, and EnsureSahodayaAdmin lets GET requests through
     * unchecked when no event id resolves (correct for genuinely event-less routes
     * like the dashboard) — which silently gave event/region admins, who are only
     * meant to be scoped to their assigned FestEvent(s), unrestricted read access to
     * the entire MCQ dataset on any {exam} route.
     *
     * Since there is no real FestEvent for an {exam} route to resolve to, we return
     * the sentinel -1: it can never appear in an admin's allowedEventIds/regionScopes
     * (real event ids are positive) and FestEvent::query()->find(-1) always resolves
     * to null, so matchesRegionScope() also returns false. That makes the containment
     * check in EnsureSahodayaAdmin fail closed for every {exam} route, denying
     * event/region-scoped admins outright. Full admins (sahodaya_admin, etc.) never
     * reach that containment check at all — they bypass this branch entirely — so
     * this is purely additive and does not touch the existing {event} resolution.
     */
    public static function resolveRouteEventId(Request $request): ?int
    {
        $raw = $request->route('event');

        if ($raw !== null) {
            if (is_object($raw)) {
                return isset($raw->id) ? (int) $raw->id : null;
            }

            return is_numeric($raw) ? (int) $raw : null;
        }

        if ($request->route('exam') !== null) {
            return -1;
        }

        return null;
    }

    /**
     * True when the requested event is either (a) a region-partition child directly
     * matching one of the admin's (event_id, region_id) scopes, or (b) that same child
     * reached via a scope granted on its parent hub — i.e. a region admin assigned on
     * the hub can reach any of the hub's children for their own region without needing
     * a separate FestEventStaff row per child event.
     *
     * A scope directly on a hub/root event (no parent_event_id of its own) must carry a
     * region_id. Without this check, a FestEventStaff row with duty=region_admin,
     * event_id=<hub>, region_id=null would satisfy the very first comparison below
     * regardless of region — granting full, unscoped access to every child/region under
     * that hub. That is gap G1 in docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md:
     * a region admin assigned on a parent hub could open the hub itself, and parent
     * reports then aggregate every child. A scope on a genuine leaf/child event has
     * nothing further under it to leak, so it's allowed on its own.
     *
     * @param  list<array{event_id: int, region_id: ?int}>  $allowedRegionScopes
     */
    public static function matchesRegionScope(int $requestedEventId, array $allowedRegionScopes): bool
    {
        $requestedEvent = FestEvent::query()
            ->select(['id', 'parent_event_id', 'region_id'])
            ->find($requestedEventId);

        if (! $requestedEvent) {
            return false;
        }

        $requestedIsHub = $requestedEvent->parent_event_id === null;

        foreach ($allowedRegionScopes as $scope) {
            if ($scope['event_id'] === (int) $requestedEvent->id) {
                if ($requestedIsHub && $scope['region_id'] === null) {
                    continue;
                }

                return true;
            }

            if ($requestedEvent->parent_event_id
                && $scope['event_id'] === (int) $requestedEvent->parent_event_id
                && $scope['region_id'] !== null
                && $requestedEvent->region_id !== null
                && $scope['region_id'] === (int) $requestedEvent->region_id) {
                return true;
            }
        }

        return false;
    }
}
