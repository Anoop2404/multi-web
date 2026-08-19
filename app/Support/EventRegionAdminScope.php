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
     * @return array{eventIds: list<int>, regionScopes: list<array{event_id: int, region_id: ?int, source_phase_id: ?int}>, phaseScopes: list<array{event_id: int, source_phase_id: int}>}
     */
    public static function resolve(User $user, bool $hasEventAdmin, bool $hasRegionAdmin, bool $hasPhaseAdmin = false): array
    {
        $allowedEventIds = [];
        $allowedRegionScopes = [];
        $allowedPhaseScopes = [];

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
                ->get(['event_id', 'region_id', 'source_phase_id'])
                ->map(fn ($row) => [
                    'event_id'        => (int) $row->event_id,
                    'region_id'       => $row->region_id !== null ? (int) $row->region_id : null,
                    'source_phase_id' => $row->source_phase_id !== null ? (int) $row->source_phase_id : null,
                ])
                ->values()
                ->all();
        }

        if ($hasPhaseAdmin) {
            // Unlike region_admin, a phase_admin scope with no source_phase_id is not
            // "unscoped" — it's an incomplete assignment (FestEventStaffController::store()
            // always requires one). Excluding it here means such a row grants no access at
            // all, rather than being misread as "every phase."
            $allowedPhaseScopes = FestEventStaff::query()
                ->where('user_id', $user->id)
                ->where('duty', 'phase_admin')
                ->whereNotNull('source_phase_id')
                ->get(['event_id', 'source_phase_id'])
                ->map(fn ($row) => [
                    'event_id'        => (int) $row->event_id,
                    'source_phase_id' => (int) $row->source_phase_id,
                ])
                ->values()
                ->all();
        }

        return ['eventIds' => $allowedEventIds, 'regionScopes' => $allowedRegionScopes, 'phaseScopes' => $allowedPhaseScopes];
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
     * Phase check (mirrors FestReportScopeResolver::actorRegionScopes()'s existing,
     * already-enforced logic for reports): a scope's source_phase_id, when set, must
     * match the requested event's own source_phase_id. Every scope assigned with "All
     * phases in region" left selected (the default, and every scope that predates this
     * field) has source_phase_id === null, so this is a no-op for them — it only
     * narrows access for scopes an admin explicitly restricted to one phase. The
     * requested event's own source_phase_id is also allowed to be null (e.g. the hub
     * itself isn't "in" any one phase) so a phase-restricted scope doesn't lose
     * hub-level access it already had.
     *
     * @param  list<array{event_id: int, region_id: ?int, source_phase_id: ?int}>  $allowedRegionScopes
     */
    public static function matchesRegionScope(int $requestedEventId, array $allowedRegionScopes): bool
    {
        $requestedEvent = FestEvent::query()
            ->select(['id', 'parent_event_id', 'region_id', 'source_phase_id'])
            ->find($requestedEventId);

        if (! $requestedEvent) {
            return false;
        }

        $requestedIsHub = $requestedEvent->parent_event_id === null;

        foreach ($allowedRegionScopes as $scope) {
            $scopePhaseId = $scope['source_phase_id'] ?? null;
            $phaseMatches = $scopePhaseId === null
                || $requestedEvent->source_phase_id === null
                || $scopePhaseId === (int) $requestedEvent->source_phase_id;

            if ($scope['event_id'] === (int) $requestedEvent->id) {
                if ($requestedIsHub && $scope['region_id'] === null) {
                    continue;
                }

                if ($phaseMatches) {
                    return true;
                }

                continue;
            }

            if ($requestedEvent->parent_event_id
                && $scope['event_id'] === (int) $requestedEvent->parent_event_id
                && $scope['region_id'] !== null
                && $requestedEvent->region_id !== null
                && $scope['region_id'] === (int) $requestedEvent->region_id
                && $phaseMatches) {
                return true;
            }
        }

        return false;
    }

    /**
     * For a hub/singleton program event (Kalotsav, English Fest, ...) that an event/region/
     * phase-scoped admin cannot directly open, finds the specific child event their scope
     * DOES cover — so "open my program" (the sidebar program link, which always resolves to
     * the hub — see FestEventController::programIndex()) lands a region-only admin on their
     * own region's event instead of redirecting them into the hub and 403ing.
     *
     * Returns null when the admin can already reach the hub directly (matchesRegionScope()/
     * matchesPhaseScope() already handle "assigned on the hub reaches every matching child" —
     * this only covers the reverse: assigned on a child only, landing on the hub), or when no
     * child of this hub matches any of their scopes.
     *
     * @param  list<int>  $allowedEventIds
     * @param  list<array{event_id: int, region_id: ?int, source_phase_id: ?int}>  $regionScopes
     * @param  list<array{event_id: int, source_phase_id: int}>  $phaseScopes
     */
    public static function resolveScopedLandingEvent(FestEvent $hub, array $allowedEventIds, array $regionScopes, array $phaseScopes): ?FestEvent
    {
        if (in_array($hub->id, $allowedEventIds, true)
            || self::matchesRegionScope($hub->id, $regionScopes)
            || self::matchesPhaseScope($hub->id, $phaseScopes)) {
            return null;
        }

        $children = FestEvent::where('parent_event_id', $hub->id)->get();

        foreach ($children as $child) {
            if (in_array($child->id, $allowedEventIds, true)
                || self::matchesRegionScope($child->id, $regionScopes)
                || self::matchesPhaseScope($child->id, $phaseScopes)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Phase-admin counterpart to matchesRegionScope(): true when the requested event is
     * either (a) directly assigned (a FestEventStaff row on this exact event), or (b)
     * reached via a scope granted on its parent hub whose source_phase_id matches this
     * event's own source_phase_id — i.e. a phase admin assigned on the hub can reach
     * every region's leaf event under their phase without a separate row per region.
     *
     * There is no hub-level "all regions, no phase filter" case to guard against here the
     * way matchesRegionScope() has to for gap G1: every phase_admin scope always carries a
     * real source_phase_id (resolve() drops any row where it's null), so a bare hub
     * assignment can never leak access to other phases.
     *
     * @param  list<array{event_id: int, source_phase_id: int}>  $allowedPhaseScopes
     */
    public static function matchesPhaseScope(int $requestedEventId, array $allowedPhaseScopes): bool
    {
        if ($allowedPhaseScopes === []) {
            return false;
        }

        $requestedEvent = FestEvent::query()
            ->select(['id', 'parent_event_id', 'source_phase_id'])
            ->find($requestedEventId);

        if (! $requestedEvent) {
            return false;
        }

        foreach ($allowedPhaseScopes as $scope) {
            if ($scope['event_id'] === (int) $requestedEvent->id) {
                return true;
            }

            if ($requestedEvent->parent_event_id
                && $scope['event_id'] === (int) $requestedEvent->parent_event_id
                && $requestedEvent->source_phase_id !== null
                && $scope['source_phase_id'] === (int) $requestedEvent->source_phase_id) {
                return true;
            }
        }

        return false;
    }
}
