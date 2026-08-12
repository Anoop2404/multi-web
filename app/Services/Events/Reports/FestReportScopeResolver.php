<?php

namespace App\Services\Events\Reports;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventStaff;
use App\Models\SchoolRegionAssignment;
use App\Models\User;
use App\Support\AcademicYear;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves a validated FestReportScope for a report request, replacing the pattern of
 * report controllers/services deriving their own event/school/item ids ad hoc (which is
 * gap G4: some report families call reportableEventIds(), others filter on the current
 * event only, so parent reports are inconsistently combined or empty). See
 * docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md §4.1.
 *
 * This resolver is additive: FestEvent::reportableEventIds() is left untouched (it's
 * still used by non-report operational code, and by report code not yet retrofitted —
 * see FestReportController::resolveRegistrationRegisterScope() and the Phase 3
 * checklist). New/retrofitted report code should prefer this resolver instead.
 */
class FestReportScopeResolver
{
    /**
     * @param  array{mode?: ?string, region_id?: int|string|null, competition_phase_id?: int|string|null, school_id?: ?string, item_ids?: ?list<int>}  $params
     */
    public function resolve(FestEvent $requestedEvent, User $actor, array $params = []): FestReportScope
    {
        $root = $requestedEvent->rootEvent();
        $mode = $params['mode'] ?? 'self';
        $regionId = isset($params['region_id']) && $params['region_id'] !== '' && $params['region_id'] !== null
            ? (int) $params['region_id']
            : null;
        $phaseId = isset($params['competition_phase_id']) && $params['competition_phase_id'] !== ''
            ? (int) $params['competition_phase_id']
            : null;

        $regionScopes = $this->actorRegionScopes($actor, $root);

        if ($regionScopes !== null) {
            return $this->resolveForRestrictedActor($requestedEvent, $root, $regionScopes, $regionId, $phaseId);
        }

        return match ($mode) {
            'region' => $this->regionScope($requestedEvent, $root, $regionId, $phaseId, false),
            'finale' => $this->roleScope($requestedEvent, $root, 'finale', $phaseId, false),
            'cluster' => $this->roleScope($requestedEvent, $root, 'cluster', $phaseId, false),
            'combined' => $this->combinedScope($requestedEvent, $root, $phaseId, false),
            default => $this->selfScope($requestedEvent, $root, $phaseId, false),
        };
    }

    /**
     * @param  list<array{event_id: int, region_id: ?int}>  $regionScopes
     */
    private function resolveForRestrictedActor(
        FestEvent $requestedEvent,
        FestEvent $root,
        array $regionScopes,
        ?int $requestedRegionId,
        ?int $phaseId,
    ): FestReportScope {
        $allowedRegionIds = collect($regionScopes)->pluck('region_id')->filter()->unique()->values()->all();

        if ($allowedRegionIds === []) {
            // Assigned duty=region_admin but no region on any scope row — fail closed
            // (plan §3.6, Phase 1 exit criteria), never fall through to an unfiltered read.
            return $this->emptyScope($requestedEvent, $root, 'region', null, $phaseId, true);
        }

        if ($requestedRegionId !== null && ! in_array($requestedRegionId, $allowedRegionIds, true)) {
            throw new HttpException(403, 'You are not assigned to that region.');
        }

        $regionId = $requestedRegionId ?? (count($allowedRegionIds) === 1 ? $allowedRegionIds[0] : null);

        if ($regionId === null) {
            // Multiple assigned regions and the caller didn't say which one — still fail
            // closed rather than silently union/combine them into a de facto Combined view
            // (plan §3.6: "a region_admin assignment ... authorizes the matching regional
            // child, not the parent combined dataset").
            return $this->emptyScope($requestedEvent, $root, 'region', null, $phaseId, true);
        }

        return $this->regionScope($requestedEvent, $root, $regionId, $phaseId, true);
    }

    private function selfScope(FestEvent $requestedEvent, FestEvent $root, ?int $phaseId, bool $restricted): FestReportScope
    {
        return new FestReportScope(
            requestedEvent: $requestedEvent,
            rootEvent: $root,
            mode: 'self',
            regionId: null,
            competitionPhaseId: $phaseId,
            eventIds: [(int) $requestedEvent->id],
            itemIds: $this->itemIds([(int) $requestedEvent->id], $phaseId),
            schoolIds: [],
            includedPartitionRoles: [],
            isActorRestricted: $restricted,
        );
    }

    private function regionScope(FestEvent $requestedEvent, FestEvent $root, ?int $regionId, ?int $phaseId, bool $restricted): FestReportScope
    {
        if ($regionId === null) {
            return $this->emptyScope($requestedEvent, $root, 'region', null, $phaseId, $restricted);
        }

        $eventIds = $this->regionEventIdsForRoot($root, $regionId);
        if ($eventIds === []) {
            return $this->emptyScope($requestedEvent, $root, 'region', $regionId, $phaseId, $restricted);
        }

        // REG-06 fix (functional audit, 2026-08-11/12): this used to always
        // resolve "today's" active academic year via AcademicYear::forSahodaya(),
        // regardless of which year $root (the event actually being reported
        // on) ran in. SchoolRegionAssignment is correctly year-keyed (one row
        // per school per academic_year, see 2026_07_21_000001_create_regions_tables.php)
        // specifically so a school's region history is preserved across
        // reassignment — but a query that ignores the record's own year and
        // always asks for "current" defeats that: viewing a region-filtered
        // report for a PAST event silently returned the region's school list
        // as it stands today, not as it stood when that event actually ran.
        // Falls back to the live current year only if the event predates
        // academic_year_id being populated (older historical events).
        $year = $root->academicYear?->label ?? AcademicYear::forSahodaya($root->tenant_id);
        $schoolIds = SchoolRegionAssignment::forTenant($root->tenant_id)
            ->forYear($year)
            ->where('region_id', $regionId)
            ->pluck('school_id')
            ->all();

        return new FestReportScope(
            requestedEvent: $requestedEvent,
            rootEvent: $root,
            mode: 'region',
            regionId: $regionId,
            competitionPhaseId: $phaseId,
            eventIds: $eventIds,
            itemIds: $this->itemIds($eventIds, $phaseId),
            schoolIds: $schoolIds,
            includedPartitionRoles: ['region'],
            isActorRestricted: $restricted,
        );
    }

    private function roleScope(FestEvent $requestedEvent, FestEvent $root, string $role, ?int $phaseId, bool $restricted): FestReportScope
    {
        $children = $root->childrenForRoles([$role]);
        $eventIds = $children->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return new FestReportScope(
            requestedEvent: $requestedEvent,
            rootEvent: $root,
            mode: $role,
            regionId: null,
            competitionPhaseId: $phaseId,
            eventIds: $eventIds,
            itemIds: $this->itemIds($eventIds, $phaseId),
            schoolIds: [],
            includedPartitionRoles: [$role],
            isActorRestricted: $restricted,
        );
    }

    /**
     * "Combined" is a report-family-specific aggregation, not "every descendant" (plan
     * §3.5). This default aggregates operational region children only — preliminary/
     * registration-shaped families' correct default — and deliberately excludes finale/
     * cluster rows unless a caller explicitly asks via roleScope()/a dedicated
     * combined-with-roles variant. Callers whose report family needs a different combined
     * policy (results/ranking combine finale too, per aggregation_config) should not use
     * this method as-is; that per-family policy still needs to be implemented per report
     * family in Phase 3.
     *
     * Sports-aware (Phase 7, §7.3 "Season + Combined: aggregate authorized leaves across
     * all sports and regions"): when $root has sports_discipline children, this walks
     * into each one and prefers its own region leaves once FestSportsRegionPartitionService
     * has synced them, falling back to the sport event itself for any sport not yet
     * split into region leaves — so combined output is never silently incomplete just
     * because region sync hasn't run for one sport yet.
     */
    private function combinedScope(FestEvent $requestedEvent, FestEvent $root, ?int $phaseId, bool $restricted): FestReportScope
    {
        $regionChildren = $root->childrenForRoles(['region']);

        if ($regionChildren->isNotEmpty()) {
            $eventIds = $regionChildren->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $roles = ['region'];
        } else {
            $sportChildren = $root->childrenForRoles(['sports_discipline']);

            if ($sportChildren->isNotEmpty()) {
                $eventIds = [];
                $roles = [];

                foreach ($sportChildren as $sport) {
                    $sportRegionLeaves = $sport->childrenForRoles(['region']);

                    if ($sportRegionLeaves->isNotEmpty()) {
                        $eventIds = array_merge($eventIds, $sportRegionLeaves->pluck('id')->map(fn ($id) => (int) $id)->all());
                        $roles[] = 'region';
                    } else {
                        $eventIds[] = (int) $sport->id;
                        $roles[] = 'sports_discipline';
                    }
                }

                $eventIds = array_values(array_unique($eventIds));
                $roles = array_values(array_unique($roles));
            } else {
                $eventIds = [(int) $root->id];
                $roles = [];
            }
        }

        return new FestReportScope(
            requestedEvent: $requestedEvent,
            rootEvent: $root,
            mode: 'combined',
            regionId: null,
            competitionPhaseId: $phaseId,
            eventIds: $eventIds,
            itemIds: $this->itemIds($eventIds, $phaseId),
            schoolIds: [],
            includedPartitionRoles: $roles,
            isActorRestricted: $restricted,
        );
    }

    /**
     * Resolves the event ids representing one region under $root, whether $root
     * partitions region children directly (plain region-wise events) or nests them
     * under sports_discipline children (Sports season, Phase 7 §7.1) — in the Sports
     * case a single "region" scope is the union of that region's leaf under every sport,
     * e.g. Athletics-RegionA + Chess-RegionA, not one single event id.
     *
     * @return list<int>
     */
    private function regionEventIdsForRoot(FestEvent $root, int $regionId): array
    {
        $direct = $root->regionalChild($regionId);
        if ($direct) {
            return [(int) $direct->id];
        }

        $sportChildren = $root->childrenForRoles(['sports_discipline']);
        if ($sportChildren->isEmpty()) {
            return [];
        }

        $ids = [];
        foreach ($sportChildren as $sport) {
            $regionLeaf = $sport->regionalChild($regionId);
            if ($regionLeaf) {
                $ids[] = (int) $regionLeaf->id;
            }
        }

        return $ids;
    }

    private function emptyScope(FestEvent $requestedEvent, FestEvent $root, string $mode, ?int $regionId, ?int $phaseId, bool $restricted): FestReportScope
    {
        return new FestReportScope(
            requestedEvent: $requestedEvent,
            rootEvent: $root,
            mode: $mode,
            regionId: $regionId,
            competitionPhaseId: $phaseId,
            eventIds: [],
            itemIds: [],
            schoolIds: [],
            includedPartitionRoles: [],
            isActorRestricted: $restricted,
        );
    }

    /** @return list<int> */
    private function itemIds(array $eventIds, ?int $phaseId): array
    {
        if ($eventIds === []) {
            return [];
        }

        return FestEventItem::query()
            ->whereIn('event_id', $eventIds)
            ->when($phaseId, fn ($q) => $q->where('phase_id', $phaseId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Null when the actor is not a region-locked admin for this event's topology (a
     * sahodaya_admin, event_admin, or super admin, or someone with no region_admin
     * assignment reaching this root at all — the latter should already have been
     * rejected earlier by EnsureSahodayaAdmin and never reach here).
     *
     * @return null|list<array{event_id: int, region_id: ?int}>
     */
    private function actorRegionScopes(User $actor, FestEvent $root): ?array
    {
        if ($actor->isSuperAdmin() || $actor->hasRole('sahodaya_admin')) {
            return null;
        }

        if (! $actor->hasRole('region_admin')) {
            return null;
        }

        $candidateEventIds = array_merge(
            [(int) $root->id],
            $root->childrenForRoles(['region'])->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        // Sports nested topology (Phase 7): a region_admin might be assigned directly on
        // a sport-region leaf, or (less commonly) on the sport itself — both need to be
        // in the candidate set for a Sports season root. Note this only recognizes scopes
        // assigned at these two levels; it does not (yet) let a scope assigned only on
        // the season root resolve down through Sports the way ResolveRegionScopedReportEvent
        // does for the single-level case — that middleware's matchesRegionScope() would
        // need the same nested-topology awareness to fully close this for Sports.
        foreach ($root->childrenForRoles(['sports_discipline']) as $sport) {
            $candidateEventIds[] = (int) $sport->id;
            foreach ($sport->childrenForRoles(['region']) as $regionLeaf) {
                $candidateEventIds[] = (int) $regionLeaf->id;
            }
        }

        $scopes = FestEventStaff::query()
            ->where('user_id', $actor->id)
            ->where('duty', 'region_admin')
            ->whereIn('event_id', array_values(array_unique($candidateEventIds)))
            ->get(['event_id', 'region_id'])
            ->map(fn ($row) => ['event_id' => (int) $row->event_id, 'region_id' => $row->region_id !== null ? (int) $row->region_id : null])
            ->values()
            ->all();

        return $scopes === [] ? null : $scopes;
    }
}
