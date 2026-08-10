<?php

namespace App\Services\Events\Reports;

use App\Models\FestEvent;

/**
 * Immutable result of FestReportScopeResolver::resolve() — every report
 * controller/service should read scope through this object rather than deriving its
 * own event/school/item ids. See docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md §4.1.
 */
final class FestReportScope
{
    /**
     * @param  string  $mode  self|combined|region|finale|cluster
     * @param  list<int>  $eventIds  Authorized operational/config event ids for this dataset.
     * @param  list<int>  $itemIds
     * @param  list<string>  $schoolIds  Concrete authorized school ids. Empty array means
     *                                   "no school-level restriction" (only valid when
     *                                   isActorRestricted is false) — a restricted actor
     *                                   always gets a concrete, non-empty-unless-genuinely-
     *                                   zero-schools list.
     * @param  list<string>  $includedPartitionRoles
     */
    public function __construct(
        public readonly FestEvent $requestedEvent,
        public readonly FestEvent $rootEvent,
        public readonly string $mode,
        public readonly ?int $regionId,
        public readonly ?int $competitionPhaseId,
        public readonly array $eventIds,
        public readonly array $itemIds,
        public readonly array $schoolIds,
        public readonly array $includedPartitionRoles,
        public readonly bool $isActorRestricted,
    ) {}

    public function isEmpty(): bool
    {
        return $this->eventIds === [];
    }

    /** Suffix for export filenames/headings — plan §3.4/§4.4 ("every filename identifies the scope"). */
    public function label(): string
    {
        return match ($this->mode) {
            'region' => 'region-'.($this->regionId ?? 'unknown'),
            'finale' => 'finale',
            'cluster' => 'cluster',
            'combined' => 'combined',
            default => 'self',
        };
    }
}
