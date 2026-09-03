<?php

namespace App\Services\Events;

use App\Support\CsvSafety;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestCompetitionArea;
use App\Models\FestItemHead;
use App\Models\FestJudgeAssignment;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestSchoolEventFee;
use App\Models\FestVolunteer;
use App\Models\FestCateringOrder;
use App\Models\FestAttendance;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ExcelExport;
use App\Support\FestClassGroupScheme;
use App\Support\FestIdCardTemplates;
use App\Support\FestItemCategoryLabel;
use App\Services\Events\FestIdCardService;
use App\Support\FestTeamSquadRules;
use App\Support\TenantBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\Events\Reports\FestReportScope;

class FestEventReportAnalyticsService
{
    public function __construct(public FestEvent $event, private ?FestReportScope $scope = null) {}

    /** @return list<int> */
    private function eventIds(): array
    {
        return $this->scope?->eventIds ?? $this->event->reportableEventIds();
    }

    /**
     * Batched equivalent of calling reportableItemIds() once per item — computing each
     * item's reportable family (itself plus any partition-region copies sharing
     * its root/item_code) used to cost ~4 queries per item via reportableItemIds().
     * This prefetches every item in the event's topology once and resolves every
     * family from that in memory.
     *
     * @param \Illuminate\Support\Collection<int, FestEventItem> $items
     * @return array{0: list<int>, 1: array<int, int>} [allReportableItemIds, itemId => canonicalItemId]
     */
    private function itemFamiliesFor($items): array
    {
        // Same root/item_code matching rule reportableItemIds() uses — shared on the
        // model (see FestEvent::itemFamilyGroups()) so the two can't drift apart.
        [$byRoot, $byCode] = $this->event->itemFamilyGroups();

        $allReportableItemIds = [];
        $itemFamilyMap = [];

        foreach ($items as $item) {
            $rootId = (int) ($item->inherited_from_item_id ?: $item->id);
            $familyIds = $byRoot->get($rootId, collect())->pluck('id');

            if ($item->item_code) {
                $familyIds = $familyIds->merge($byCode->get($item->item_code, collect())->pluck('id'));
            }

            $familyIds = $familyIds->map(fn ($id) => (int) $id)->unique();

            if ($this->scope) {
                $familyIds = $familyIds->filter(fn ($id) => in_array($id, $this->scope->itemIds, true));
            }

            foreach ($familyIds as $fid) {
                $allReportableItemIds[] = $fid;
                $itemFamilyMap[$fid] = $item->id;
            }
        }

        return [array_values(array_unique($allReportableItemIds)), $itemFamilyMap];
    }

    /**
     * Sahodaya branding (org name + logo data URI) for PDF report headers.
     *
     * @return array{orgName: string, logoSrc: ?string}
     */
    private function brandingData(): array
    {
        $sahodaya = Tenant::find($this->event->tenant_id);

        return [
            'orgName' => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc' => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function disciplineRegistrationRows(?string $schoolId = null): array
    {
        $taxonomy = app(FestTaxonomyRegistry::class)->forTenant($this->event->tenant_id);
        $labels = $taxonomy->labels('sport_discipline');

        $eventIds = $this->eventIds();
        $items = FestEventItem::whereIn('event_id', $eventIds)->get(['id', 'sport_discipline']);
        $byDiscipline = $items->groupBy(fn ($i) => $i->sport_discipline ?: 'unspecified');

        $rows = [];
        foreach ($byDiscipline as $discipline => $group) {
            $itemIds = $group->pluck('id');
            $regQuery = fn (string $status) => FestRegistration::whereIn('event_id', $eventIds)
                ->whereIn('item_id', $itemIds)
                ->where('status', $status)
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

            $rows[] = [
                'discipline'       => $discipline,
                'discipline_label' => $labels[$discipline] ?? ($discipline === 'unspecified' ? 'Unspecified' : $discipline),
                'item_count'       => $itemIds->count(),
                'approved'         => $regQuery('approved')->count(),
                'pending'          => $regQuery('submitted')->count(),
            ];
        }

        usort($rows, fn ($a, $b) => $a['discipline_label'] <=> $b['discipline_label']);

        return $rows;
    }

    /** @return array{schools: list<string>, age_groups: list<string>, matrix: array<string, array<string, int>>, totals: array<string, int>} */
    public function ageGroupMatrix(?string $schoolId = null): array
    {
        $taxonomy = app(FestTaxonomyRegistry::class)->forTenant($this->event->tenant_id);
        $ageLabels = $taxonomy->allLabels()['age_group'] ?? [];

        $query = FestRegistration::where('fest_registrations.event_id', $this->event->id)
            ->whereIn('fest_registrations.status', ['submitted', 'approved'])
            ->join('fest_event_items', 'fest_registrations.item_id', '=', 'fest_event_items.id')
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId));

        $pairs = $query->selectRaw('fest_registrations.school_id, fest_event_items.age_group, count(*) as cnt')
            ->groupBy('fest_registrations.school_id', 'fest_event_items.age_group')
            ->get();

        $schoolIds = $pairs->pluck('school_id')->unique()->values();
        $schools = Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');

        $ageGroups = $pairs->pluck('age_group')->filter()->unique()->sort()->values()->all();
        $matrix = [];
        $totals = array_fill_keys($ageGroups, 0);

        foreach ($pairs as $row) {
            $age = $row->age_group ?: 'open';
            if (! in_array($age, $ageGroups, true)) {
                $ageGroups[] = $age;
            }
            $matrix[$row->school_id][$age] = (int) $row->cnt;
            $totals[$age] = ($totals[$age] ?? 0) + (int) $row->cnt;
        }

        sort($ageGroups);

        return [
            'schools'     => $schoolIds->map(fn ($id) => ['id' => $id, 'name' => $schools[$id] ?? $id])->values()->all(),
            'age_groups'  => collect($ageGroups)->map(fn ($k) => ['key' => $k, 'label' => $ageLabels[$k] ?? strtoupper($k)])->values()->all(),
            'matrix'      => $matrix,
            'totals'      => $totals,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function feeCollectionRows(): array
    {
        $schoolIds = $this->feeCollectionQuery()
            ->pluck('school_id')
            ->unique();
        $schools = Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');

        return $this->feeCollectionQuery()
            ->with(['feeReceipt', 'registrationBatch'])
            ->orderBy('school_id')
            ->get()
            ->map(fn (FestSchoolEventFee $fee) => [
                'school_id'        => $fee->school_id,
                'school_name'      => $schools[$fee->school_id] ?? $fee->school_id,
                'total_due'        => (float) $fee->total_due,
                'paid'             => (float) ($fee->feeReceipt?->amount ?? 0),
                'status'           => $fee->status,
                'registration_batch_id' => $fee->registration_batch_id,
                'registration_batch' => $fee->registrationBatch?->name,
                'receipt_no'       => $fee->feeReceipt?->receipt_number,
                // See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §14.
                'available_credit' => $fee->outstandingCredit(),
            ])
            ->all();
    }

    /**
     * FestSchoolEventFee rows for phased-regional-billing (batch) billing are always
     * written against the root event's id, keyed by registration_batch_id, never
     * against the region/phase leaf a 'region' scope resolves $this->event to (see
     * FestRegistrationBatchFeeService::recalculateAll()) — so filtering by
     * $this->event->id here returned nothing for a region-scoped phased event even
     * though the school's fee record genuinely exists. Reads through $this->scope
     * (already correctly resolved by FestReportScopeResolver, including the
     * FestSchoolPhaseRegionSelection-based school list) instead of re-deriving it.
     */
    private function feeCollectionQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $feeService = app(FestSchoolEventFeeService::class);
        $feeOwnerEvent = $feeService->feeOwnerEvent($this->event);

        if ($this->scope && $this->scope->mode === 'region' && $this->event->usesPhasedRegionalBilling()) {
            $query = FestSchoolEventFee::where('event_id', $this->scope->rootEvent->id)->forAmountAggregation();

            if ($this->scope->schoolIds !== []) {
                $query->whereIn('school_id', $this->scope->schoolIds);
            }

            $batchId = $this->scope->registrationBatchId
                ?? ($this->scope->competitionPhaseId ? \App\Models\FestEventPhase::find($this->scope->competitionPhaseId)?->registration_batch_id : null);

            if ($batchId) {
                $query->where('registration_batch_id', $batchId);
            }

            return $query;
        }

        $regionSchoolIds = null;
        if ($this->event->parent_event_id !== null || ($this->scope && $this->scope->mode === 'region')) {
            $reportableEventIds = $this->event->reportableEventIds();
            $regionSchoolIds = FestRegistration::whereIn('event_id', $reportableEventIds)
                ->distinct()
                ->pluck('school_id')
                ->all();
        }

        return FestSchoolEventFee::where('event_id', $feeOwnerEvent->id)
            ->when($regionSchoolIds !== null, fn ($q) => $q->whereIn('school_id', $regionSchoolIds))
            ->forAmountAggregation();
    }

    /** @return list<array<string, mixed>> */
    public function feeCollectionByHeadRows(): array
    {
        $feeService = app(FestSchoolEventFeeService::class);
        if (! $feeService->feeRequired($this->event)) {
            return [];
        }

        $schedule = $feeService->resolveSchedule($this->event);
        $itemResolver = app(FestItemFeeResolver::class);
        $usesPerHeadBilling = $feeService->usesPerHeadBilling($this->event);

        $heads = FestItemHead::forTenant($this->event->tenant_id)
            ->forEvent($this->event->id)
            ->orderBy('sort_order')
            ->get();

        return $heads->map(function (FestItemHead $head) use ($feeService, $itemResolver, $schedule, $usesPerHeadBilling) {
            $items = FestEventItem::where('event_id', $this->event->id)
                ->where('head_id', $head->id)
                ->get();

            $regCount = FestRegistration::where('event_id', $this->event->id)
                ->whereIn('item_id', $items->pluck('id'))
                ->whereIn('status', ['submitted', 'approved'])
                ->count();

            $estimated = $items->sum(fn (FestEventItem $item) => $itemResolver->amountForItem($item, $schedule, $this->event));

            $row = [
                'head_id'        => $head->id,
                'head_name'      => $head->name,
                'item_count'     => $items->count(),
                'registrations'  => $regCount,
                'default_fee'    => $head->default_item_fee !== null ? (float) $head->default_item_fee : null,
                'extra_fee'      => $head->extra_item_fee !== null ? (float) $head->extra_item_fee : null,
                'catalog_total'  => round($estimated, 2),
            ];

            // When this event actually bills per-head, surface the real collected/pending
            // amounts from the real FestSchoolEventFee rows for this head, rather than only
            // the what-if catalog estimate above (which is still shown for non-billing context).
            if ($usesPerHeadBilling) {
                $feeOwnerEvent = $feeService->feeOwnerEvent($this->event);
                $regionSchoolIds = null;
                if ($this->event->parent_event_id !== null || ($this->scope && $this->scope->mode === 'region')) {
                    $reportableEventIds = $this->event->reportableEventIds();
                    $regionSchoolIds = FestRegistration::whereIn('event_id', $reportableEventIds)
                        ->distinct()
                        ->pluck('school_id')
                        ->all();
                }

                $headFees = FestSchoolEventFee::where('event_id', $feeOwnerEvent->id)
                    ->where('head_id', $head->id)
                    ->when($regionSchoolIds !== null, fn ($q) => $q->whereIn('school_id', $regionSchoolIds))
                    ->get();
                $row['due_total'] = round((float) $headFees->sum('total_due'), 2);
                $row['collected_total'] = round((float) $headFees->where('status', 'approved')->sum('total_due'), 2);
                $row['pending_total'] = round((float) $headFees->whereNotIn('status', ['approved', 'waived'])->sum('total_due'), 2);
                $row['schools_billed'] = $headFees->count();
                $row['schools_paid'] = $headFees->filter(fn (FestSchoolEventFee $f) => $f->isFullyPaid())->count();
            }

            return $row;
        })->all();
    }

    /** @return list<array<string, mixed>> */
    public function headWiseSummary(?string $schoolId = null): array
    {
        // Sports (Head = Event): summarise per sport event instead of per head row.
        if ($this->event->event_type === 'sports') {
            return $this->sportsWiseSummary($schoolId);
        }

        $heads = FestItemHead::forTenant($this->event->tenant_id)
            ->forEvent($this->event->id)
            ->orderBy('sort_order')
            ->get();

        if ($heads->isEmpty()) {
            return [];
        }

        $headIds = $heads->pluck('id');

        // Per-head due/collected totals only mean something when this event actually
        // bills per-head; otherwise every real FestSchoolEventFee row has head_id = null
        // and filtering by a specific head's id always returns zero, silently showing
        // "₹0 due" per head for a school that in fact owes a real single-record total.
        $usesPerHeadBilling = app(FestSchoolEventFeeService::class)->usesPerHeadBilling($this->event);

        // Batched aggregates instead of ~6-7 queries per head — see
        // docs/SCHOOL_REPORTS_PERFORMANCE_PLAN.md §2/§7 Phase 3. Each map below costs
        // exactly one query, regardless of head count.
        $itemCountByHead = FestEventItem::where('event_id', $this->event->id)
            ->whereIn('head_id', $headIds)
            ->selectRaw('head_id, count(*) as cnt')
            ->groupBy('head_id')
            ->pluck('cnt', 'head_id');

        $statusRows = FestRegistration::query()
            ->join('fest_event_items', 'fest_event_items.id', '=', 'fest_registrations.item_id')
            ->where('fest_registrations.event_id', $this->event->id)
            ->whereIn('fest_event_items.head_id', $headIds)
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId))
            ->selectRaw('fest_event_items.head_id as head_id, fest_registrations.status as status, count(*) as cnt')
            ->groupBy('fest_event_items.head_id', 'fest_registrations.status')
            ->get();

        $statusMap = [];
        foreach ($statusRows as $row) {
            $statusMap[$row->head_id][$row->status] = (int) $row->cnt;
        }

        $participantRows = FestParticipant::query()
            ->join('fest_registrations', 'fest_registrations.id', '=', 'fest_participants.registration_id')
            ->join('fest_event_items', 'fest_event_items.id', '=', 'fest_registrations.item_id')
            ->leftJoin('students', 'students.id', '=', 'fest_participants.student_id')
            ->where('fest_registrations.event_id', $this->event->id)
            ->whereIn('fest_event_items.head_id', $headIds)
            ->whereIn('fest_registrations.status', FestRegistration::ACTIVE_STATUSES)
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId))
            ->selectRaw('
                fest_event_items.head_id as head_id,
                count(*) as participant_count,
                sum(case when students.verified_at is not null then 1 else 0 end) as verified_count
            ')
            ->groupBy('fest_event_items.head_id')
            ->get();

        $participantMap = [];
        foreach ($participantRows as $row) {
            $participantMap[$row->head_id] = [
                'participant_count' => (int) $row->participant_count,
                'verified_count'    => (int) $row->verified_count,
            ];
        }

        // max_item_reg_count needs per-item counts first, then the max within each
        // head's items — one query for per-item counts, grouped by head in PHP.
        $perItemRows = FestRegistration::query()
            ->join('fest_event_items', 'fest_event_items.id', '=', 'fest_registrations.item_id')
            ->where('fest_registrations.event_id', $this->event->id)
            ->whereIn('fest_event_items.head_id', $headIds)
            ->whereIn('fest_registrations.status', FestRegistration::ACTIVE_STATUSES)
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId))
            ->selectRaw('fest_event_items.head_id as head_id, fest_registrations.item_id as item_id, count(*) as cnt')
            ->groupBy('fest_event_items.head_id', 'fest_registrations.item_id')
            ->get();

        $maxItemRegByHead = [];
        foreach ($perItemRows as $row) {
            $maxItemRegByHead[$row->head_id] = max($maxItemRegByHead[$row->head_id] ?? 0, (int) $row->cnt);
        }

        $headFeesByHead = [];
        if ($usesPerHeadBilling) {
            $feeOwnerEvent = app(FestSchoolEventFeeService::class)->feeOwnerEvent($this->event);
            $regionSchoolIds = null;
            if ($this->event->parent_event_id !== null || ($this->scope && $this->scope->mode === 'region')) {
                $reportableEventIds = $this->event->reportableEventIds();
                $regionSchoolIds = FestRegistration::whereIn('event_id', $reportableEventIds)
                    ->distinct()
                    ->pluck('school_id')
                    ->all();
            }

            $allHeadFees = FestSchoolEventFee::where('event_id', $feeOwnerEvent->id)
                ->whereIn('head_id', $headIds)
                ->when($regionSchoolIds !== null, fn ($q) => $q->whereIn('school_id', $regionSchoolIds))
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->get();
            $headFeesByHead = $allHeadFees->groupBy('head_id');
        }

        return $heads->map(function (FestItemHead $head) use ($schoolId, $usesPerHeadBilling, $itemCountByHead, $statusMap, $participantMap, $maxItemRegByHead, $headFeesByHead) {
            $approved = $statusMap[$head->id]['approved'] ?? 0;
            $pending = ($statusMap[$head->id]['submitted'] ?? 0) + ($statusMap[$head->id]['pending_approval'] ?? 0);
            $waitlisted = $statusMap[$head->id]['waitlisted'] ?? 0;

            $participantCount = $participantMap[$head->id]['participant_count'] ?? 0;
            $verifiedParticipants = $participantMap[$head->id]['verified_count'] ?? 0;

            $maxItemReg = $maxItemRegByHead[$head->id] ?? 0;

            $quota = max(0, (int) ($head->included_items_per_student ?? 0));

            $row = [
                'head_id'             => $head->id,
                'head_name'           => $head->name,
                'item_count'          => (int) ($itemCountByHead[$head->id] ?? 0),
                'registration_count' => $approved + $pending,
                'approved_count'      => $approved,
                'pending_count'       => $pending,
                'waitlisted_count'    => $waitlisted,
                'participant_count'   => $participantCount,
                'verified_count'      => $verifiedParticipants,
                'unverified_count'    => max(0, $participantCount - $verifiedParticipants),
                'max_item_reg_count'  => (int) $maxItemReg,
                'included_quota'      => $quota,
                'verification_policy' => $head->verification_policy ?? 'all_students',
                'approval_policy'     => $head->approval_policy ?? 'auto',
                'default_item_fee'    => $head->default_item_fee !== null ? (float) $head->default_item_fee : null,
                'extra_item_fee'      => $head->extra_item_fee !== null ? (float) $head->extra_item_fee : null,
            ];

            if ($usesPerHeadBilling) {
                $headFees = $headFeesByHead->get($head->id, collect());

                $row['due_total'] = round((float) $headFees->sum('total_due'), 2);
                $row['collected_total'] = round((float) $headFees->where('status', 'approved')->sum('total_due'), 2);
                $row['pending_fee_total'] = round((float) $headFees->whereNotIn('status', ['approved', 'waived'])->sum('total_due'), 2);
            }

            return $row;
        })->all();
    }

    /**
     * Sports summary: one row per sport event (children when run on the season hub,
     * itself when run on a single sport event). head_id carries the sport event id.
     *
     * @return list<array<string, mixed>>
     */
    private function sportsWiseSummary(?string $schoolId = null): array
    {
        $sports = $this->event->isSportsSeasonEvent()
            ? FestEvent::where('parent_event_id', $this->event->id)
                ->ofType('sports')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
            : collect([$this->event]);

        if ($sports->isEmpty()) {
            return [];
        }

        $sportIds = $sports->pluck('id');

        // Batched aggregates instead of ~6 queries per sport — see
        // docs/SCHOOL_REPORTS_PERFORMANCE_PLAN.md §2/§7 Phase 3. Each map below costs
        // exactly one query, regardless of sport count. Grouping is by event_id alone
        // (dropping the old whereIn('item_id', $itemIds) filter) — a registration's
        // event_id already fully determines which sport it belongs to, so the item_id
        // constraint was always redundant with it, not an independent condition.
        $itemCountBySport = FestEventItem::whereIn('event_id', $sportIds)
            ->selectRaw('event_id, count(*) as cnt')
            ->groupBy('event_id')
            ->pluck('cnt', 'event_id');

        $statusRows = FestRegistration::query()
            ->whereIn('event_id', $sportIds)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw('event_id, status, count(*) as cnt')
            ->groupBy('event_id', 'status')
            ->get();

        $statusMap = [];
        foreach ($statusRows as $row) {
            $statusMap[$row->event_id][$row->status] = (int) $row->cnt;
        }

        $participantRows = FestParticipant::query()
            ->join('fest_registrations', 'fest_registrations.id', '=', 'fest_participants.registration_id')
            ->leftJoin('students', 'students.id', '=', 'fest_participants.student_id')
            ->whereIn('fest_registrations.event_id', $sportIds)
            ->whereIn('fest_registrations.status', FestRegistration::ACTIVE_STATUSES)
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId))
            ->selectRaw('
                fest_registrations.event_id as event_id,
                count(*) as participant_count,
                sum(case when students.verified_at is not null then 1 else 0 end) as verified_count
            ')
            ->groupBy('fest_registrations.event_id')
            ->get();

        $participantMap = [];
        foreach ($participantRows as $row) {
            $participantMap[$row->event_id] = [
                'participant_count' => (int) $row->participant_count,
                'verified_count'    => (int) $row->verified_count,
            ];
        }

        $perItemRows = FestRegistration::query()
            ->whereIn('event_id', $sportIds)
            ->whereIn('status', FestRegistration::ACTIVE_STATUSES)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw('event_id, item_id, count(*) as cnt')
            ->groupBy('event_id', 'item_id')
            ->get();

        $maxItemRegBySport = [];
        foreach ($perItemRows as $row) {
            $maxItemRegBySport[$row->event_id] = max($maxItemRegBySport[$row->event_id] ?? 0, (int) $row->cnt);
        }

        $feesBySport = FestSchoolEventFee::whereIn('event_id', $sportIds)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->get()
            ->groupBy('event_id');

        return $sports->map(function (FestEvent $sport) use ($itemCountBySport, $statusMap, $participantMap, $maxItemRegBySport, $feesBySport) {
            $approved = $statusMap[$sport->id]['approved'] ?? 0;
            $pending = ($statusMap[$sport->id]['submitted'] ?? 0) + ($statusMap[$sport->id]['pending_approval'] ?? 0);
            $waitlisted = $statusMap[$sport->id]['waitlisted'] ?? 0;

            $participantCount = $participantMap[$sport->id]['participant_count'] ?? 0;
            $verifiedParticipants = $participantMap[$sport->id]['verified_count'] ?? 0;

            $maxItemReg = $maxItemRegBySport[$sport->id] ?? 0;

            $fees = $feesBySport->get($sport->id, collect());

            return [
                'head_id'             => $sport->id,
                'head_name'           => $sport->title,
                'item_count'          => (int) ($itemCountBySport[$sport->id] ?? 0),
                'registration_count'  => $approved + $pending,
                'approved_count'      => $approved,
                'pending_count'       => $pending,
                'waitlisted_count'    => $waitlisted,
                'participant_count'   => $participantCount,
                'verified_count'      => $verifiedParticipants,
                'unverified_count'    => max(0, $participantCount - $verifiedParticipants),
                'max_item_reg_count'  => (int) $maxItemReg,
                'included_quota'      => max(0, (int) ($sport->included_items_per_student ?? 0)),
                'verification_policy' => $sport->verification_policy ?? 'all_students',
                'approval_policy'     => $sport->approval_policy ?? 'auto',
                'due_total'           => round((float) $fees->sum('total_due'), 2),
                'collected_total'     => round((float) $fees->where('status', 'approved')->sum('total_due'), 2),
                'pending_fee_total'   => round((float) $fees->whereNotIn('status', ['approved', 'waived'])->sum('total_due'), 2),
                'default_item_fee'    => $sport->default_item_fee !== null ? (float) $sport->default_item_fee : null,
                'extra_item_fee'      => $sport->extra_item_fee !== null ? (float) $sport->extra_item_fee : null,
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    protected function scopedEventIds(): array
    {
        if ($this->event->parent_event_id !== null) {
            return [(int) $this->event->id];
        }

        return $this->eventIds();
    }

    public function itemRegistrationRows(?string $schoolId = null): array
    {
        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($this->event);
        $feeRequired = $feeService->feeRequired($this->event);
        $feeResolver = app(FestItemFeeResolver::class);
        $eventIds = $this->scopedEventIds();

        $targetEventId = FestEventItem::where('event_id', $this->event->id)->where('is_enabled', true)->exists()
            ? $this->event->id
            : ($this->event->parent_event_id ? $this->event->rootEvent()->id : $this->event->id);

        $items = FestEventItem::query()
            ->where('event_id', $targetEventId)
            ->where('is_enabled', true)
            ->with(['head:id,name,default_item_fee,extra_item_fee', 'phase:id,source_phase_id'])
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();

        $items = \App\Services\Events\FestHeadItemNavigationService::filterToOwnPhase($items, $this->event);

        if ($items->isEmpty()) {
            return [];
        }

        [$allReportableItemIds, $itemFamilyMap] = $this->itemFamiliesFor($items);

        $statusRows = FestRegistration::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('item_id', $allReportableItemIds)
            ->whereIn('status', ['approved', 'submitted'])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw('item_id, status, count(*) as cnt')
            ->groupBy('item_id', 'status')
            ->get();

        $statusMap = [];
        foreach ($statusRows as $row) {
            $canonicalId = $itemFamilyMap[$row->item_id] ?? $row->item_id;
            $statusMap[$canonicalId][$row->status] = ($statusMap[$canonicalId][$row->status] ?? 0) + (int) $row->cnt;
        }

        $participantRows = FestParticipant::query()
            ->join('fest_registrations', 'fest_registrations.id', '=', 'fest_participants.registration_id')
            ->whereIn('fest_registrations.event_id', $eventIds)
            ->whereIn('fest_registrations.item_id', $allReportableItemIds)
            ->whereIn('fest_registrations.status', FestRegistration::ACTIVE_STATUSES)
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId))
            ->selectRaw('fest_registrations.item_id as item_id, count(*) as participant_count, sum(case when fest_participants.item_registration_number is not null then 1 else 0 end) as assigned_count')
            ->groupBy('fest_registrations.item_id')
            ->get();

        $participantMap = [];
        foreach ($participantRows as $row) {
            $canonicalId = $itemFamilyMap[$row->item_id] ?? $row->item_id;
            $prevPart = $participantMap[$canonicalId]['participants'] ?? 0;
            $prevAssigned = $participantMap[$canonicalId]['assigned'] ?? 0;
            $participantMap[$canonicalId] = [
                'participants' => $prevPart + (int) $row->participant_count,
                'assigned'     => $prevAssigned + (int) $row->assigned_count,
            ];
        }

        $schoolCountMap = [];
        if (! $schoolId) {
            $schoolRows = FestRegistration::query()
                ->whereIn('event_id', $eventIds)
                ->whereIn('item_id', $allReportableItemIds)
                ->active()
                ->selectRaw('item_id, school_id')
                ->distinct()
                ->get();
            $schoolsByCanonical = [];
            foreach ($schoolRows as $sr) {
                $cId = $itemFamilyMap[$sr->item_id] ?? $sr->item_id;
                $schoolsByCanonical[$cId][$sr->school_id] = true;
            }
            foreach ($schoolsByCanonical as $cId => $schools) {
                $schoolCountMap[$cId] = count($schools);
            }
        }

        $classGroupLabels = FestClassGroupScheme::labels(null, $this->event->rootEvent());
        $artsCategoryLabels = app(FestTaxonomyRegistry::class)->forTenant($this->event->tenant_id)->labels('arts_category');

        $rows = [];
        foreach ($items as $item) {
            $approved = $statusMap[$item->id]['approved'] ?? 0;
            $pending = $statusMap[$item->id]['submitted'] ?? 0;
            $totalRegs = $approved + $pending;

            $participants = $participantMap[$item->id]['participants'] ?? 0;
            $itemRegAssigned = $participantMap[$item->id]['assigned'] ?? 0;

            $schoolCount = $schoolId
                ? ($totalRegs > 0 ? 1 : 0)
                : (int) ($schoolCountMap[$item->id] ?? 0);

            $feePerItem = $feeRequired ? $feeResolver->amountForItem($item, $schedule, $this->event) : null;
            $lineFee = $feePerItem !== null ? round($feePerItem * $totalRegs, 2) : null;

            $rows[] = [
                'item_id'            => $item->id,
                'head_id'            => $item->head_id,
                'head_name'          => $item->head?->name,
                'title'              => $item->title,
                'item_code'          => $item->item_code,
                'class_group'        => $item->class_group,
                'age_group'          => $item->age_group,
                'category_label'     => FestItemCategoryLabel::resolve($item, $classGroupLabels, $artsCategoryLabels),
                'stage_type'         => $item->stage_type,
                'participant_type'   => $item->participant_type,
                'approved'           => $approved,
                'pending'            => $pending,
                'registration_count' => $totalRegs,
                'participant_count'  => $participants,
                'item_reg_assigned'  => $itemRegAssigned,
                'school_count'       => $schoolCount,
                'max_per_school'     => $item->max_per_school,
                'fee_per_item'       => $feePerItem,
                'line_fee'           => $lineFee,
                'reg_start'          => $item->reg_start,
                'reg_end'            => $item->reg_end,
                'competition_start'  => $item->competition_start,
                'competition_end'    => $item->competition_end,
                'competition_time'   => $item->competition_time,
            ];
        }

        return $rows;
    }

    /** @param list<array<string, mixed>> $rows */
    public function itemRegistrationTotals(array $rows): array
    {
        return [
            'items'           => count($rows),
            'approved'        => array_sum(array_column($rows, 'approved')),
            'pending'         => array_sum(array_column($rows, 'pending')),
            'registrations'   => array_sum(array_column($rows, 'registration_count')),
            'participants'    => array_sum(array_column($rows, 'participant_count')),
            'unique_students' => $this->uniqueStudentCount(),
            'estimated_fee'   => round(collect($rows)->sum(fn ($r) => (float) ($r['line_fee'] ?? 0)), 2),
        ];
    }

    /**
     * Distinct student headcount across the report's scope — 'participants' above sums
     * participant_count per item, so a student registered for 5 items counts 5 times
     * there; this counts them once. Same active-registration/enabled-item filter as
     * itemRegistrationRows() so the two numbers stay comparable.
     */
    public function uniqueStudentCount(?string $schoolId = null): int
    {
        $targetEventId = $this->scope
            ? $this->scope->requestedEvent->id
            : ($this->event->parent_event_id ? $this->event->rootEvent()->id : $this->event->id);

        $items = FestEventItem::query()
            ->where('event_id', $targetEventId)
            ->where('is_enabled', true)
            ->get();

        $items = \App\Services\Events\FestHeadItemNavigationService::filterToOwnPhase($items, $this->event);
        if ($items->isEmpty()) {
            return 0;
        }

        [$allReportableItemIds] = $this->itemFamiliesFor($items);

        return FestParticipant::query()
            ->join('fest_registrations', 'fest_registrations.id', '=', 'fest_participants.registration_id')
            ->join('fest_event_items', 'fest_event_items.id', '=', 'fest_registrations.item_id')
            ->whereIn('fest_registrations.event_id', $this->eventIds())
            ->whereIn('fest_registrations.item_id', $allReportableItemIds)
            ->whereIn('fest_registrations.status', FestRegistration::ACTIVE_STATUSES)
            ->where('fest_event_items.is_enabled', true)
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId))
            ->whereNotNull('fest_participants.student_id')
            ->distinct('fest_participants.student_id')
            ->count('fest_participants.student_id');
    }

    /** @return list<array<string, mixed>> */
    public function headRegistrationSummary(?string $schoolId = null): array
    {
        $rows = $this->itemRegistrationRows($schoolId);
        $byHead = collect($rows)->groupBy(fn ($r) => $r['head_id'] ?? 0);

        return collect($this->headWiseSummary($schoolId))->map(function (array $head) use ($byHead) {
            $headRows = $byHead->get($head['head_id'], collect());
            $maxRow = $headRows->sortByDesc('registration_count')->first();

            return array_merge($head, [
                'estimated_fee'     => round($headRows->sum(fn ($r) => (float) ($r['line_fee'] ?? 0)), 2),
                'max_item_title'    => $maxRow['title'] ?? null,
                'busiest_item_regs' => (int) ($maxRow['registration_count'] ?? 0),
            ]);
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function assignmentCompletenessRows(?string $schoolId = null): array
    {
        $eventIds = $this->eventIds();

        $itemScheduleIds = FestSchedule::query()
            ->whereIn('event_id', $eventIds)
            ->whereNull('participant_id')
            ->pluck('id', 'item_id');

        $items = FestEventItem::query()
            ->whereIn('event_id', $eventIds)
            ->where('is_enabled', true)
            ->with('head:id,name')
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        $itemIds = $items->pluck('id');

        // Batched aggregates instead of ~8 queries per item — see
        // docs/SCHOOL_REPORTS_PERFORMANCE_PLAN.md §2/§7 Phase 2. Each map below costs
        // exactly one query, regardless of item count.
        $statusRows = FestRegistration::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('item_id', $itemIds)
            ->whereIn('status', ['approved', 'submitted'])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw('item_id, status, count(*) as cnt')
            ->groupBy('item_id', 'status')
            ->get();

        $statusMap = [];
        foreach ($statusRows as $row) {
            $statusMap[$row->item_id][$row->status] = (int) $row->cnt;
        }

        // Performers = active (not disqualified, not standby) participants on an
        // approved registration. Chest-assigned also counts a participant whose GROUP
        // (not the participant row itself) has a chest number — team items assign
        // chest numbers at the group level, hence the left join + OR below, matching
        // the original per-item query this replaces exactly.
        $performerRows = FestParticipant::query()
            ->join('fest_registrations', 'fest_registrations.id', '=', 'fest_participants.registration_id')
            ->leftJoin('fest_groups', 'fest_groups.id', '=', 'fest_participants.group_id')
            ->whereNull('fest_participants.disqualified_at')
            ->where(function ($q) {
                $q->whereNull('fest_participants.participant_role')
                    ->orWhere('fest_participants.participant_role', '!=', 'standby');
            })
            ->whereIn('fest_registrations.event_id', $eventIds)
            ->whereIn('fest_registrations.item_id', $itemIds)
            ->where('fest_registrations.status', 'approved')
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId))
            // A participant marked absent for this specific item isn't expected to
            // perform — excluded from the denominator the same way disqualified/standby
            // already are above. Without this, an item with any absent participant can
            // never reach 100% marks-entered (there's nothing to score for someone who
            // didn't show up), permanently blocking that item's publish.
            ->whereNotExists(function ($query) {
                $query->select('id')
                    ->from('fest_attendance')
                    ->whereColumn('fest_attendance.item_id', 'fest_registrations.item_id')
                    ->whereColumn('fest_attendance.participant_id', 'fest_participants.id')
                    ->where('fest_attendance.status', 'absent');
            })
            ->selectRaw('
                fest_registrations.item_id as item_id,
                count(*) as performers,
                sum(case when fest_participants.chest_no is not null or fest_groups.chest_no is not null then 1 else 0 end) as chest_assigned,
                sum(case when fest_participants.item_registration_number is not null then 1 else 0 end) as item_reg_assigned
            ')
            ->groupBy('fest_registrations.item_id')
            ->get();

        $performerMap = [];
        foreach ($performerRows as $row) {
            $performerMap[$row->item_id] = [
                'performers'        => (int) $row->performers,
                'chest_assigned'    => (int) $row->chest_assigned,
                'item_reg_assigned' => (int) $row->item_reg_assigned,
            ];
        }

        $scheduledQuery = FestSchedule::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('item_id', $itemIds)
            ->whereNotNull('participant_id');
        if ($schoolId) {
            $scheduledQuery->whereHas('participant.registration', fn ($r) => $r->where('school_id', $schoolId));
        }
        $scheduledMap = $scheduledQuery
            ->selectRaw('item_id, count(distinct participant_id) as cnt')
            ->groupBy('item_id')
            ->pluck('cnt', 'item_id');

        $marksQuery = FestMark::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('item_id', $itemIds)
            ->where(fn ($q) => $q->whereNotNull('grade')->orWhereNotNull('score')->orWhereNotNull('position'));
        if ($schoolId) {
            $marksQuery->whereHas('participant.registration', fn ($r) => $r->where('school_id', $schoolId));
        }
        $marksMap = $marksQuery
            ->selectRaw('item_id, count(distinct participant_id) as cnt')
            ->groupBy('item_id')
            ->pluck('cnt', 'item_id');

        $judgesMap = FestJudgeAssignment::whereIn('event_id', $eventIds)
            ->whereIn('item_id', $itemIds)
            ->selectRaw('item_id, count(*) as cnt')
            ->groupBy('item_id')
            ->pluck('cnt', 'item_id');

        $classGroupLabels = FestClassGroupScheme::labels(null, $this->event->rootEvent());
        $artsCategoryLabels = app(FestTaxonomyRegistry::class)->forTenant($this->event->tenant_id)->labels('arts_category');

        $rows = [];
        foreach ($items as $item) {
            $approved = $statusMap[$item->id]['approved'] ?? 0;
            $pending = $statusMap[$item->id]['submitted'] ?? 0;

            $performers = $performerMap[$item->id]['performers'] ?? 0;
            $chestAssigned = $performerMap[$item->id]['chest_assigned'] ?? 0;
            $itemRegAssigned = $performerMap[$item->id]['item_reg_assigned'] ?? 0;

            $scheduledParticipants = (int) ($scheduledMap[$item->id] ?? 0);
            $marksEntered = (int) ($marksMap[$item->id] ?? 0);
            $judges = (int) ($judgesMap[$item->id] ?? 0);

            $rows[] = [
                'item_id'                => $item->id,
                'head_id'                => $item->head_id,
                'head_name'              => $item->head?->name,
                'title'                  => $item->title,
                'age_group'              => $item->age_group,
                'class_group'            => $item->class_group,
                'category_label'         => FestItemCategoryLabel::resolve($item, $classGroupLabels, $artsCategoryLabels),
                'approved'               => $approved,
                'pending'                => $pending,
                'registration_count'   => $approved + $pending,
                'performers'             => $performers,
                'chest_assigned'         => $chestAssigned,
                'chest_missing'          => max(0, $performers - $chestAssigned),
                'item_reg_assigned'      => $itemRegAssigned,
                'item_reg_missing'       => max(0, $performers - $itemRegAssigned),
                'item_scheduled'         => $itemScheduleIds->has($item->id),
                'participants_scheduled' => $scheduledParticipants,
                'marks_entered'          => $marksEntered,
                'marks_pending'          => max(0, $performers - $marksEntered),
                'judges_assigned'        => $judges,
                'ready_for_event'      => $performers > 0
                    && $chestAssigned >= $performers
                    && $itemRegAssigned >= $performers
                    && $marksEntered >= $performers,
            ];
        }

        return $rows;
    }

    /** @param list<array<string, mixed>> $rows */
    public function assignmentCompletenessTotals(array $rows): array
    {
        return [
            'items'            => count($rows),
            'performers'       => array_sum(array_column($rows, 'performers')),
            'chest_missing'    => array_sum(array_column($rows, 'chest_missing')),
            'item_reg_missing' => array_sum(array_column($rows, 'item_reg_missing')),
            'marks_pending'    => array_sum(array_column($rows, 'marks_pending')),
            'pending_regs'     => array_sum(array_column($rows, 'pending')),
            'items_scheduled'  => count(array_filter($rows, fn ($r) => $r['item_scheduled'])),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function numberingRegisterRows(?string $schoolId = null): array
    {
        return $this->numberingRegisterRowsSorted($schoolId)->all();
    }

    /**
     * Paginated variant of numberingRegisterRows() for the on-screen table — the
     * underlying query is already a single, cheap query, but for a large school this
     * could be 5,000+ rows rendered in one unvirtualized table. The sort is a 4-key
     * composite closure (head name, item title, chest no, participant name) that
     * doesn't map cleanly onto a single SQL ORDER BY without extra joins, so this
     * slices the same fully-sorted collection rather than re-deriving the sort in
     * SQL — the "Option B" manual-paginator technique already documented in
     * docs/SCALE_AND_PAGINATION_PLAN.md §3 for an equivalent PHP-sorted merge.
     * Exports (exportNumberingRegister()) keep calling the unbounded
     * numberingRegisterRows() above — a printed/exported register inherently needs
     * every row. See docs/SCHOOL_REPORTS_PERFORMANCE_PLAN.md §3/§7 Phase 4.
     */
    public function numberingRegisterPaginated(?string $schoolId, int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        $sorted = $this->numberingRegisterRowsSorted($schoolId);
        $page = max(1, $page);

        return new LengthAwarePaginator(
            $sorted->slice(($page - 1) * $perPage, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page'],
        );
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function numberingRegisterRowsSorted(?string $schoolId): \Illuminate\Support\Collection
    {
        return FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $this->eventIds())
                ->active()
                ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
            ->with([
                'student:id,name,admission_number',
                'teacher:id,name,reg_no',
                'registration:id,event_id,item_id,school_id,status',
                'registration.school:id,name',
                'registration.item:id,title,head_id',
                'registration.item.head:id,name',
            ])
            ->get()
            ->sortBy(fn (FestParticipant $p) => [
                $p->registration?->item?->head?->name ?? '',
                $p->registration?->item?->title ?? '',
                $p->chest_no ?? 99999,
                $p->student?->name ?? $p->teacher?->name ?? '',
            ])
            ->values()
            ->map(fn (FestParticipant $p) => [
                'participant_id' => $p->id,
                'head_name'      => $p->registration?->item?->head?->name,
                'item_id'        => $p->registration?->item_id,
                'item'           => $p->registration?->item?->title,
                'school'         => $p->registration?->school?->name,
                'school_id'      => $p->registration?->school_id,
                'name'           => $p->student?->name ?? $p->teacher?->name,
                'reg_no'         => $p->student?->admission_number ?? $p->teacher?->reg_no,
                'reg_status'     => $p->registration?->status,
                'role'           => $p->participant_role ?? 'performer',
                'fest_id'        => $p->level_registration_number,
                'item_reg'       => $p->item_registration_number,
                'chest_no'       => $p->chest_no,
                'disqualified'   => $p->disqualified_at !== null,
            ]);
    }

    /** @return list<array<string, mixed>> */
    public function pendingApprovalRows(?string $schoolId = null): array
    {
        return $this->pendingApprovalQuery($schoolId)->get()
            ->map(fn (FestRegistration $reg) => $this->mapPendingApprovalRow($reg))
            ->all();
    }

    /**
     * Paginated variant for the on-screen table — see docs/SCHOOL_REPORTS_PERFORMANCE_PLAN.md
     * §3/§7 Phase 4. Unlike numberingRegisterPaginated(), this one's sort is already a
     * plain SQL ORDER BY, so it's a genuine query-level ->paginate(), not a slice of an
     * in-memory collection. Exports (exportPendingApprovals()) keep calling the
     * unbounded pendingApprovalRows() above.
     */
    public function pendingApprovalPaginated(?string $schoolId, int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        return $this->pendingApprovalQuery($schoolId)
            ->paginate($perPage, ['*'], 'page', max(1, $page))
            ->through(fn (FestRegistration $reg) => $this->mapPendingApprovalRow($reg));
    }

    private function pendingApprovalQuery(?string $schoolId)
    {
        return FestRegistration::query()
            ->whereIn('event_id', $this->eventIds())
            ->where('status', 'submitted')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->with(['school:id,name', 'item:id,title,head_id', 'item.head:id,name', 'participants.student:id,name', 'participants.teacher:id,name'])
            ->orderBy('school_id')
            ->orderBy('item_id');
    }

    private function mapPendingApprovalRow(FestRegistration $reg): array
    {
        return [
            'registration_id' => $reg->id,
            'school_id'       => $reg->school_id,
            'school'          => $reg->school?->name,
            'head_name'       => $reg->item?->head?->name,
            'item_id'         => $reg->item_id,
            'item'            => $reg->item?->title,
            'participant_count' => $reg->participants->count(),
            'participants'    => $reg->participants->map(fn ($p) => $p->student?->name ?? $p->teacher?->name)->filter()->values()->all(),
            'submitted_at'    => $reg->updated_at?->toIso8601String(),
        ];
    }

    public function exportAssignmentCompleteness(?string $schoolId = null): StreamedResponse
    {
        $rows = collect($this->assignmentCompletenessRows($schoolId))->map(fn ($r) => [
            $r['head_name'] ?? '—',
            $r['title'],
            $r['approved'],
            $r['pending'],
            $r['performers'],
            $r['chest_assigned'],
            $r['chest_missing'],
            $r['item_reg_assigned'],
            $r['item_reg_missing'],
            $r['item_scheduled'] ? 'Y' : 'N',
            $r['participants_scheduled'],
            $r['marks_entered'],
            $r['marks_pending'],
            $r['judges_assigned'],
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-assignment-completeness',
            ['Head', 'Item', 'Approved', 'Pending', 'Performers', 'Chest OK', 'Chest missing', 'Item reg OK', 'Item reg missing', 'Item scheduled', 'Participants scheduled', 'Marks entered', 'Marks pending', 'Judges'],
            $rows,
        );
    }

    public function exportNumberingRegister(?string $schoolId = null): StreamedResponse
    {
        $rows = collect($this->numberingRegisterRows($schoolId))->map(fn ($r) => [
            $r['head_name'] ?? '—',
            $r['item'] ?? '',
            $r['school'] ?? '',
            $r['name'] ?? '',
            $r['reg_no'] ?? '',
            $r['reg_status'] ?? '',
            $r['role'] ?? '',
            $r['fest_id'] ?? '',
            $r['item_reg'] ?? '',
            $r['chest_no'] ?? '',
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-numbering-register',
            ['Head', 'Item', 'School', 'Participant', 'Reg no', 'Reg status', 'Role', 'Fest ID', 'Item reg', 'Chest'],
            $rows,
        );
    }

    public function exportPendingApprovals(?string $schoolId = null): StreamedResponse
    {
        $rows = collect($this->pendingApprovalRows($schoolId))->map(fn ($r) => [
            $r['school'] ?? '',
            $r['head_name'] ?? '—',
            $r['item'] ?? '',
            $r['participant_count'],
            implode(', ', $r['participants'] ?? []),
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-pending-approvals',
            ['School', 'Head', 'Item', 'Participants', 'Names'],
            $rows,
        );
    }

    /** @return list<array<string, mixed>> */
    public function headWiseParticipantRows(?int $headId = null, ?string $schoolId = null, bool $photoForSchoolAdmin = false): array
    {
        // Sports (Head = Event): FestItemHead rows are never created for sports events —
        // "head" means a sport/discipline, i.e. a child FestEvent. See sportsWiseSummary().
        if ($this->event->event_type === 'sports') {
            return $this->sportsWiseParticipantRows($headId, $schoolId, $photoForSchoolAdmin);
        }

        $heads = FestItemHead::forTenant($this->event->tenant_id)
            ->forEvent($this->event->id)
            ->when($headId, fn ($q) => $q->where('id', $headId))
            ->orderBy('sort_order')
            ->get();

        $classGroupLabels = FestClassGroupScheme::labels(null, $this->event->rootEvent());
        $artsCategoryLabels = app(FestTaxonomyRegistry::class)->forTenant($this->event->tenant_id)->labels('arts_category');

        $itemHeadMap = FestEventItem::where('event_id', $this->event->id)
            ->whereIn('head_id', $heads->pluck('id'))
            ->pluck('head_id', 'id');

        $participantsByHead = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $this->event->id)
                ->whereIn('item_id', $itemHeadMap->keys())
                ->active()
                ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
            ->with([
                'student:id,name,reg_no,photo,tenant_id',
                'student.schoolClass:id,name',
                'teacher:id,name,reg_no',
                'registration.school:id,name',
                'registration.item:id,title,head_id,class_group,category,competition_start,competition_end,competition_time',
            ])
            ->get()
            ->groupBy(fn ($p) => $itemHeadMap->get($p->registration?->item_id));

        $rows = [];
        foreach ($heads as $head) {
            $participants = $participantsByHead->get($head->id, collect());

            foreach ($participants as $p) {
                $rows[] = [
                    'head_id'    => $head->id,
                    'head_name'  => $head->name,
                    'item_id'    => $p->registration?->item_id,
                    'school'     => $p->registration?->school?->name,
                    'student_id' => $p->student_id,
                    'student'    => $p->student?->name ?? $p->teacher?->name,
                    'reg_no'     => $p->student?->reg_no ?? $p->teacher?->reg_no,
                    'class'      => $p->student?->schoolClass?->name,
                    'photo_url'  => $photoForSchoolAdmin ? $p->student?->photoUrl() : $p->student?->sahodayaPhotoUrl($this->event->tenant_id),
                    'item'       => $p->registration?->item?->title,
                    'category_label' => FestItemCategoryLabel::resolve($p->registration?->item, $classGroupLabels, $artsCategoryLabels),
                    'item_reg'   => $p->item_registration_number,
                    'chest_no'   => $p->chest_no,
                    'fest_id'    => $p->level_registration_number,
                    'status'     => $p->registration?->status,
                    'role'       => $p->participant_role,
                    'team_name'  => $p->registration?->team_name,
                    'competition_start' => $p->registration?->item?->competition_start,
                    'competition_end'   => $p->registration?->item?->competition_end,
                    'competition_time'  => $p->registration?->item?->competition_time,
                ];
            }
        }

        return $rows;
    }

    /**
     * Sports twin of the FestItemHead loop above: one "head" bucket per sport/discipline
     * event (children when run on the season hub, itself when run on a single sport
     * event) — mirrors sportsWiseSummary()'s row shape, where head_id carries the sport
     * event id. $headId here is therefore a child-event id, not a FestItemHead id.
     *
     * @return list<array<string, mixed>>
     */
    private function sportsWiseParticipantRows(?int $headId = null, ?string $schoolId = null, bool $photoForSchoolAdmin = false): array
    {
        $sports = $this->event->isSportsSeasonEvent()
            ? FestEvent::where('parent_event_id', $this->event->id)
                ->ofType('sports')
                ->when($headId, fn ($q) => $q->where('id', $headId))
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
            : collect([$this->event])->when($headId, fn ($c) => $c->where('id', $headId));

        $classGroupLabels = FestClassGroupScheme::labels(null, $this->event->rootEvent());
        $artsCategoryLabels = app(FestTaxonomyRegistry::class)->forTenant($this->event->tenant_id)->labels('arts_category');

        $sportIds = $sports->pluck('id');

        $participantsBySport = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $sportIds)
                ->active()
                ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
            ->with([
                'student:id,name,reg_no,photo,tenant_id',
                'student.schoolClass:id,name',
                'teacher:id,name,reg_no',
                'registration.school:id,name',
                'registration.item:id,title,head_id,class_group,category,competition_start,competition_end,competition_time',
            ])
            ->get()
            ->groupBy(fn ($p) => $p->registration?->event_id);

        $rows = [];
        foreach ($sports as $sport) {
            $participants = $participantsBySport->get($sport->id, collect());

            foreach ($participants as $p) {
                $rows[] = [
                    'head_id'    => $sport->id,
                    'head_name'  => $sport->title,
                    'item_id'    => $p->registration?->item_id,
                    'school'     => $p->registration?->school?->name,
                    'student_id' => $p->student_id,
                    'student'    => $p->student?->name ?? $p->teacher?->name,
                    'reg_no'     => $p->student?->reg_no ?? $p->teacher?->reg_no,
                    'class'      => $p->student?->schoolClass?->name,
                    'photo_url'  => $photoForSchoolAdmin ? $p->student?->photoUrl() : $p->student?->sahodayaPhotoUrl($this->event->tenant_id),
                    'item'       => $p->registration?->item?->title,
                    'category_label' => FestItemCategoryLabel::resolve($p->registration?->item, $classGroupLabels, $artsCategoryLabels),
                    'item_reg'   => $p->item_registration_number,
                    'chest_no'   => $p->chest_no,
                    'fest_id'    => $p->level_registration_number,
                    'status'     => $p->registration?->status,
                    'role'       => $p->participant_role,
                    'team_name'  => $p->registration?->team_name,
                    'competition_start' => $p->registration?->item?->competition_start,
                    'competition_end'   => $p->registration?->item?->competition_end,
                    'competition_time'  => $p->registration?->item?->competition_time,
                ];
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function teamSquadRows(?string $schoolId = null): array
    {
        $teamItems = FestEventItem::whereIn('event_id', $this->eventIds())
            ->whereIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES)
            ->orderBy('title')
            ->get();

        $regsByItem = FestRegistration::whereIn('item_id', $teamItems->pluck('id'))
            ->active()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->with(['school:id,name', 'participants.student:id,name,reg_no', 'participants.teacher:id,name'])
            ->get()
            ->groupBy('item_id');

        $rows = [];
        foreach ($teamItems as $item) {
            $regs = $regsByItem->get($item->id, collect());

            foreach ($regs as $reg) {
                $members = $reg->participants->map(fn ($p) => [
                    'name'   => $p->student?->name ?? $p->teacher?->name,
                    'reg_no' => $p->student?->reg_no ?? $p->teacher?->reg_no,
                    'role'   => $p->participant_role ?? 'performer',
                ])->values()->all();

                $rows[] = [
                    'item_id'      => $item->id,
                    'item_title'   => $item->title,
                    'school_name'  => $reg->school?->name,
                    'member_count' => count($members),
                    'members'      => $members,
                ];
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function medalTallyBySchool(): array
    {
        $service = new FestReportService($this->event, $this->scope);
        $marks = $service->marks();

        $bySchool = [];
        foreach ($marks as $m) {
            if (! $m->position || $m->position > 3) {
                continue;
            }
            $schoolId = $m->participant?->registration?->school_id;
            if (! $schoolId) {
                continue;
            }
            if (! isset($bySchool[$schoolId])) {
                $bySchool[$schoolId] = ['gold' => 0, 'silver' => 0, 'bronze' => 0, 'total_points' => 0];
            }
            match ((int) $m->position) {
                1 => $bySchool[$schoolId]['gold']++,
                2 => $bySchool[$schoolId]['silver']++,
                3 => $bySchool[$schoolId]['bronze']++,
                default => null,
            };
        }

        $schoolNames = Tenant::whereIn('id', array_keys($bySchool))->pluck('name', 'id');
        $rows = [];
        foreach ($bySchool as $sid => $counts) {
            $rows[] = array_merge([
                'school_id'   => $sid,
                'school_name' => $schoolNames[$sid] ?? $sid,
            ], $counts);
        }

        usort($rows, fn ($a, $b) => ($b['gold'] <=> $a['gold']) ?: ($b['silver'] <=> $a['silver']));

        return $rows;
    }

    public function exportDisciplineRegistration(): StreamedResponse
    {
        $rows = collect($this->disciplineRegistrationRows())->map(fn ($r) => [
            $r['discipline_label'], $r['item_count'], $r['approved'], $r['pending'],
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-discipline-registration',
            ['Discipline', 'Items', 'Approved regs', 'Pending regs'],
            $rows,
        );
    }

    public function exportAgeGroupMatrix(?string $schoolId = null): StreamedResponse
    {
        $data = $this->ageGroupMatrix($schoolId);
        $headers = array_merge(['School'], array_column($data['age_groups'], 'label'));
        $rows = [];

        foreach ($data['schools'] as $school) {
            $row = [$school['name']];
            foreach ($data['age_groups'] as $ag) {
                $row[] = $data['matrix'][$school['id']][$ag['key']] ?? 0;
            }
            $rows[] = $row;
        }

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-age-group-matrix',
            $headers,
            collect($rows),
        );
    }

    public function exportFeePendingSchools(): StreamedResponse
    {
        $rows = collect($this->feeCollectionRows())
            ->filter(fn ($r) => ! in_array($r['status'], ['approved'], true))
            ->map(fn ($r) => [$r['school_name'], $r['total_due'], $r['status'], $r['receipt_no'] ?? '']);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-fee-pending',
            ['School', 'Due', 'Status', 'Receipt'],
            $rows,
        );
    }

    public function exportHeadWiseParticipants(?int $headId = null, ?string $schoolId = null): StreamedResponse
    {
        $rows = collect($this->headWiseParticipantRows($headId, $schoolId))->map(fn ($r) => [
            $r['head_name'], $r['school'], $r['student'], $r['reg_no'], $r['item'], $r['fest_id'], $r['item_reg'], $r['chest_no'],
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-head-wise-participants',
            ['Head', 'School', 'Participant', 'School reg', 'Item', 'Fest ID', 'Item reg', 'Chest'],
            $rows,
        );
    }

    /** @return list<array<string, mixed>> */
    public function areaWiseSummary(?string $schoolId = null): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('fest_competition_areas')) {
            return [];
        }

        $areas = FestCompetitionArea::where('event_id', $this->event->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $unassignedItems = FestEventItem::where('event_id', $this->event->id)->whereNull('area_id')->pluck('id');

        $rows = $areas->map(function (FestCompetitionArea $area) use ($schoolId) {
            return $this->summarizeAreaBucket(
                $area->id,
                $area->name,
                FestEventItem::where('event_id', $this->event->id)->where('area_id', $area->id)->pluck('id'),
                $schoolId,
                $area->default_item_fee !== null ? (float) $area->default_item_fee : null,
            );
        })->values()->all();

        if ($unassignedItems->isNotEmpty()) {
            $rows[] = $this->summarizeAreaBucket(0, 'Unassigned items', $unassignedItems, $schoolId, null);
        }

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $itemIds
     * @return array<string, mixed>
     */
    private function summarizeAreaBucket(int $areaId, string $areaName, $itemIds, ?string $schoolId, ?float $defaultFee): array
    {
        $itemIds = collect($itemIds);
        $regBase = FestRegistration::query()
            ->where('event_id', $this->event->id)
            ->whereIn('item_id', $itemIds)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

        $approved = (clone $regBase)->where('status', 'approved')->count();
        $pending = (clone $regBase)->whereIn('status', ['submitted', 'pending_approval'])->count();
        $waitlisted = (clone $regBase)->where('status', 'waitlisted')->count();

        $participantCount = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $this->event->id)
                ->whereIn('item_id', $itemIds)
                ->active()
                ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
            ->count();

        return [
            'area_id' => $areaId,
            'area_name' => $areaName,
            'item_count' => $itemIds->count(),
            'registration_count' => $approved + $pending,
            'approved_count' => $approved,
            'pending_count' => $pending,
            'waitlisted_count' => $waitlisted,
            'participant_count' => $participantCount,
            'default_item_fee' => $defaultFee,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function areaWiseParticipantRows(?int $areaId = null, ?string $schoolId = null): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('fest_competition_areas')) {
            return [];
        }

        $areas = FestCompetitionArea::where('event_id', $this->event->id)
            ->when($areaId !== null && $areaId > 0, fn ($q) => $q->where('id', $areaId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $classGroupLabels = FestClassGroupScheme::labels(null, $this->event->rootEvent());
        $artsCategoryLabels = app(FestTaxonomyRegistry::class)->forTenant($this->event->tenant_id)->labels('arts_category');

        $itemAreaMap = FestEventItem::where('event_id', $this->event->id)
            ->whereIn('area_id', $areas->pluck('id'))
            ->pluck('area_id', 'id');

        $participantsByArea = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $this->event->id)
                ->whereIn('item_id', $itemAreaMap->keys())
                ->active()
                ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
            ->with([
                'student:id,name,reg_no,tenant_id',
                'teacher:id,name,reg_no',
                'registration.school:id,name',
                'registration.item:id,title,area_id,class_group,category',
            ])
            ->get()
            ->groupBy(fn ($p) => $itemAreaMap->get($p->registration?->item_id));

        $rows = [];
        foreach ($areas as $area) {
            $participants = $participantsByArea->get($area->id, collect());

            foreach ($participants as $p) {
                $rows[] = [
                    'area_id' => $area->id,
                    'area_name' => $area->name,
                    'item_id' => $p->registration?->item_id,
                    'school' => $p->registration?->school?->name,
                    'student' => $p->student?->name ?? $p->teacher?->name,
                    'reg_no' => $p->student?->reg_no ?? $p->teacher?->reg_no,
                    'item' => $p->registration?->item?->title,
                    'category_label' => FestItemCategoryLabel::resolve($p->registration?->item, $classGroupLabels, $artsCategoryLabels),
                    'item_reg' => $p->item_registration_number,
                    'chest_no' => $p->chest_no,
                    'fest_id' => $p->level_registration_number,
                    'status' => $p->registration?->status,
                ];
            }
        }

        if ($areaId === null || $areaId === 0) {
            $itemIds = FestEventItem::where('event_id', $this->event->id)->whereNull('area_id')->pluck('id');
            if ($itemIds->isNotEmpty()) {
                $participants = FestParticipant::query()
                    ->whereHas('registration', fn ($q) => $q
                        ->where('event_id', $this->event->id)
                        ->whereIn('item_id', $itemIds)
                        ->active()
                        ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
                    ->with([
                        'student:id,name,reg_no,tenant_id',
                        'teacher:id,name,reg_no',
                        'registration.school:id,name',
                        'registration.item:id,title,area_id,class_group,category',
                    ])
                    ->get();

                foreach ($participants as $p) {
                    $rows[] = [
                        'area_id' => 0,
                        'area_name' => 'Unassigned items',
                        'item_id' => $p->registration?->item_id,
                        'school' => $p->registration?->school?->name,
                        'student' => $p->student?->name ?? $p->teacher?->name,
                        'reg_no' => $p->student?->reg_no ?? $p->teacher?->reg_no,
                        'item' => $p->registration?->item?->title,
                        'category_label' => FestItemCategoryLabel::resolve($p->registration?->item, $classGroupLabels, $artsCategoryLabels),
                        'item_reg' => $p->item_registration_number,
                        'chest_no' => $p->chest_no,
                        'fest_id' => $p->level_registration_number,
                        'status' => $p->registration?->status,
                    ];
                }
            }
        }

        return $rows;
    }

    public function exportAreaWiseParticipants(?int $areaId = null, ?string $schoolId = null): StreamedResponse
    {
        $rows = collect($this->areaWiseParticipantRows($areaId, $schoolId))->map(fn ($r) => [
            $r['area_name'], $r['school'], $r['student'], $r['reg_no'], $r['item'], $r['fest_id'], $r['item_reg'], $r['chest_no'],
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-area-wise-participants',
            ['Area', 'School', 'Participant', 'School reg', 'Item', 'Fest ID', 'Item reg', 'Chest'],
            $rows,
        );
    }

    public function teamSquadPdf(?string $schoolId = null): \Illuminate\Http\Response
    {
        return Pdf::loadView('fest.reports.team-squads', [
            'event' => $this->event,
            'rows'  => $this->teamSquadRows($schoolId),
            ...$this->brandingData(),
        ])->download(str($this->event->title)->slug()->limit(40).'-team-squads.pdf');
    }

    public function medalTallyPdf(): \Illuminate\Http\Response
    {
        return Pdf::loadView('fest.reports.medal-tally', [
            'event' => $this->event,
            'rows'  => $this->medalTallyBySchool(),
            ...$this->brandingData(),
        ])->download(str($this->event->title)->slug()->limit(40).'-medal-tally.pdf');
    }

    /** @return list<array<string, mixed>> */
    public function volunteerRosterRows(): array
    {
        return FestVolunteer::where('event_id', $this->event->id)
            ->orderBy('duty')
            ->orderBy('name')
            ->get()
            ->map(fn (FestVolunteer $v) => [
                'name'  => $v->name,
                'phone' => $v->phone,
                'duty'  => $v->duty,
                'notes' => $v->notes,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function cateringBySchoolRows(?string $schoolId = null): array
    {
        $orders = FestCateringOrder::where('event_id', $this->event->id)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->get();

        $schoolIds = $orders->pluck('school_id')->unique();
        $schoolNames = Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');

        $rows = [];
        foreach ($schoolIds as $sid) {
            $schoolOrders = $orders->where('school_id', $sid);
            $rows[] = [
                'school_id'   => $sid,
                'school_name' => $schoolNames[$sid] ?? $sid,
                'order_count' => $schoolOrders->count(),
                'total_heads' => $schoolOrders->sum('head_count'),
                'confirmed'   => $schoolOrders->where('status', 'confirmed')->count(),
                'pending'     => $schoolOrders->whereIn('status', ['pending', 'submitted'])->count(),
                'breakfast'   => $schoolOrders->where('meal_type', 'breakfast')->sum('head_count'),
                'lunch'       => $schoolOrders->where('meal_type', 'lunch')->sum('head_count'),
                'dinner'      => $schoolOrders->where('meal_type', 'dinner')->sum('head_count'),
                'snacks'      => $schoolOrders->where('meal_type', 'snacks')->sum('head_count'),
            ];
        }

        usort($rows, fn ($a, $b) => $a['school_name'] <=> $b['school_name']);

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function auditLogRows(): array
    {
        $morph = (new FestEvent)->getMorphClass();
        $eventId = (string) $this->event->id;

        return AuditLog::query()
            ->with('user:id,name,email')
            ->where(function ($q) use ($morph, $eventId) {
                $q->where(function ($q2) use ($morph, $eventId) {
                    $q2->where('subject_type', $morph)->where('subject_id', $eventId);
                })->orWhere('properties->event_id', $this->event->id);
            })
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get()
            ->map(fn (AuditLog $log) => [
                'created_at'  => $log->created_at?->toDateTimeString(),
                'user'        => $log->user?->name ?? $log->user?->email ?? 'System',
                'action'      => $log->action,
                'description' => $log->description,
                'page'        => $log->properties['page'] ?? '',
                'category'    => $log->category ?? '',
            ])
            ->all();
    }

    public function exportVolunteerRoster(): StreamedResponse
    {
        $rows = collect($this->volunteerRosterRows())->map(fn ($r) => [
            $r['name'], $r['phone'] ?? '', $r['duty'] ?? '', $r['notes'] ?? '',
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-volunteer-roster',
            ['Name', 'Phone', 'Duty', 'Notes'],
            $rows,
        );
    }

    public function exportCateringBySchool(?string $schoolId = null): StreamedResponse
    {
        $rows = collect($this->cateringBySchoolRows($schoolId))->map(fn ($r) => [
            $r['school_name'],
            $r['order_count'],
            $r['total_heads'],
            $r['confirmed'],
            $r['pending'],
            $r['breakfast'],
            $r['lunch'],
            $r['dinner'],
            $r['snacks'],
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-catering-by-school',
            ['School', 'Orders', 'Total heads', 'Confirmed orders', 'Pending orders', 'Breakfast heads', 'Lunch heads', 'Dinner heads', 'Snacks heads'],
            $rows,
        );
    }

    public function exportAuditLogExtract(): StreamedResponse
    {
        $rows = collect($this->auditLogRows())->map(fn ($r) => [
            $r['created_at'],
            $r['user'],
            $r['action'],
            $r['description'],
            $r['page'],
            $r['category'],
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['Timestamp', 'User', 'Action', 'Description', 'Page', 'Category']);
            foreach ($rows as $row) {
                CsvSafety::fputcsv($out, $row);
            }
            fclose($out);
        }, str($this->event->title)->slug()->limit(40).'-audit-log.csv', ['Content-Type' => 'text/csv']);
    }

    public function idCardsByHeadPdf(?int $headId = null, ?string $schoolId = null, ?string $template = null): \Illuminate\Http\Response
    {
        $service = app(FestIdCardService::class);
        $filters = array_filter([
            'school_id' => $schoolId,
        ]);

        $sections = collect($service->cardsGroupedByHead($this->event, $filters))
            ->when($headId, fn ($c) => $c->where('head_id', $headId))
            ->map(fn ($section) => [
                'item_title' => $section['head_title'],
                'cards'      => $section['cards'],
            ])
            ->values()
            ->all();

        abort_if($sections === [], 422, 'No ID cards found for the selected head / school filters.');

        $cluster = Tenant::find($this->event->tenant_id);
        $view = FestIdCardTemplates::sheetView(FestIdCardTemplates::PREMIUM);
        $slug = str($this->event->title)->slug()->limit(40);
        $headSuffix = $headId ? "-head-{$headId}" : '-all-heads';

        return Pdf::loadView($view, [
            'cards'          => [],
            'sections'       => $sections,
            'clusterName'    => $cluster?->name ?? 'Sahodaya',
            'clusterLogoSrc' => $cluster ? TenantBranding::logoEmbedSrc($cluster) : null,
            'eventTitle'     => $this->event->title,
            'audience'       => 'student',
            'showTitle'      => true,
        ])->download("{$slug}{$headSuffix}-id-cards.pdf");
    }

    /** @return list<array<string, mixed>> */
    public function studentWiseBrowserRows(?string $schoolId = null, ?string $search = null, bool $includePhotoDataUri = false, bool $photoForSchoolAdmin = false): array
    {
        $eventIds = $this->eventIds();

        $participants = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $eventIds)
                ->active()
                ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
            ->whereNotNull('student_id')
            ->with([
                'student:id,tenant_id,name,reg_no,gender,photo,school_class_id',
                'student.schoolClass:id,name',
                'registration.school:id,name,school_prefix',
                'registration.item:id,title,head_id,event_id,class_group,age_group,category,stage_type,participant_type,results_published_at',
                'registration.item.head:id,name',
                'registration.item.event:id,title,results_published',
            ])
            ->get();

        $marksByParticipant = FestMark::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('participant_id', $participants->pluck('id'))
            ->get()
            ->keyBy('participant_id');

        $classGroupLabels = FestClassGroupScheme::labels(null, $this->event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

        $rows = [];
        foreach ($participants->groupBy('student_id') as $studentId => $entries) {
            /** @var FestParticipant $first */
            $first = $entries->first();
            $student = $first->student;
            if (! $student) {
                continue;
            }

            $name = (string) ($student->name ?? '');
            $regNo = (string) ($student->reg_no ?? '');
            if ($search) {
                $q = strtolower($search);
                if (! str_contains(strtolower($name), $q) && ! str_contains(strtolower($regNo), $q)) {
                    continue;
                }
            }

            $items = $entries->map(function (FestParticipant $p) use ($marksByParticipant, $classGroupLabels, $artsCategoryLabels) {
                $mark = $marksByParticipant->get($p->id);
                $item = $p->registration?->item;
                $itemEvent = $item?->event;
                // Rank/mark/grade must stay hidden until this item's results are actually
                // published — same gate FestItemResultsService::isItemVisible() already
                // applies on the dedicated results-entry/publish pages — so this report
                // (which schools may receive as a PDF/Excel) never leaks a draft result.
                $resultsPublished = $item && $itemEvent
                    && app(FestItemResultsService::class)->isItemVisible($item, $itemEvent);

                return [
                    'item_id'           => $p->registration?->item_id,
                    'item_title'        => $item?->title,
                    'head_name'         => $item?->head?->name,
                    'category_label'    => FestItemCategoryLabel::shortLabel($item, $classGroupLabels, $artsCategoryLabels),
                    'stage_type'        => $item?->stage_type,
                    'participant_type'  => $item?->participant_type,
                    'status'            => $p->registration?->status,
                    'fest_id'           => $p->level_registration_number,
                    'item_reg'          => $p->item_registration_number,
                    'chest_no'          => $p->chest_no,
                    'results_published' => $resultsPublished,
                    'grade'             => $resultsPublished ? $mark?->grade : null,
                    'position'          => $resultsPublished ? $mark?->position : null,
                    'score'             => $resultsPublished ? $mark?->score : null,
                    'mark_value'        => $resultsPublished ? $mark?->measurement_value : null,
                    'mark_unit'         => $resultsPublished ? $mark?->measurement_unit : null,
                    'sport_event_id'    => $p->registration?->event_id,
                    'sport_event_title' => $item?->event?->title,
                ];
            })->values()->all();

            $rows[] = [
                'student_id'     => (int) $studentId,
                'school_id'      => $first->registration?->school_id,
                'school_name'    => $first->registration?->school?->name,
                'school_code'    => $first->registration?->school?->school_prefix,
                'name'           => $name,
                'reg_no'         => $regNo,
                'gender'         => $student->gender,
                'class_name'     => $student->schoolClass?->name,
                'photo_url'      => $photoForSchoolAdmin ? $student->photoUrl() : $student->sahodayaPhotoUrl($this->event->tenant_id),
                'photo_data_uri' => $includePhotoDataUri ? $student->photoDataUri() : null,
                'item_count'     => count($items),
                'total_score'    => collect($items)->sum(fn ($i) => (float) ($i['score'] ?? 0)),
                'items'          => $items,
            ];
        }

        usort($rows, fn ($a, $b) => [$a['school_name'] ?? '', $a['name']] <=> [$b['school_name'] ?? '', $b['name']]);

        return $rows;
    }

    /**
     * Every item across the resolved scope, in one flat table — one row per registered
     * participant, carrying its item's category/name, the school, and (for a
     * phased_regional_billing root) which phase/region the registration actually landed
     * in. Built to replace picking one item at a time (the older itemWiseBrowserRows()
     * flow): a Sahodaya wants "every item, every registration, filterable by phase and
     * region" as a single report, not 140 separate item pages.
     *
     * Phase/region labels are read from registration.event (the leaf the write actually
     * landed on), never from the item's own phase_id — an item's phase_id can be
     * mistagged relative to the leaf it's catalogued under (see the Wayanad Sahodaya
     * "Light Music-Malayalam" incident, 2026-08-25: item lived on the Phase 1 leaf's item
     * table but phase_id resolved to Phase 2, so trusting it for display would repeat the
     * exact confusion that bug caused). The event a registration is actually attached to
     * is authoritative for "which phase/region this happened in."
     *
     * @param  ?string  $schoolId  Narrow to one school (school-admin callers always pass
     *                             their own id; Sahodaya-admin callers pass null and rely
     *                             on the constructor's FestReportScope for authorization).
     * @return list<array<string, mixed>>
     */
    public function itemWiseReportRows(?string $schoolId = null): array
    {
        // "Category" here means class category (e.g. "Category 1 — Classes 3 & 4"), not the
        // item's arts genre — matches how the item catalog and Fees listing both group/label
        // by class category. The scheme itself is configured on fee_settings.
        // class_group_scheme on the ROOT event — FestClassGroupScheme::resolve() reads that
        // straight off whatever event it's given with no parent walk, so passing a phase
        // leaf here (this->event, e.g. a "PHASE 2" operational child) misses the Sahodaya's
        // actual configured scheme entirely and falls back to the platform default.
        $classGroupLabels = \App\Support\FestClassGroupScheme::labels(null, $this->event->rootEvent());
        $usesPhasedRegionalBilling = $this->event->rootEvent()->usesPhasedRegionalBilling();

        return FestParticipant::query()
            ->whereHas('registration', function ($q) use ($schoolId) {
                $q->whereIn('event_id', $this->eventIds())->active();
                if ($schoolId) {
                    $q->where('school_id', $schoolId);
                }
            })
            ->with([
                'student:id,name,reg_no',
                'teacher:id,name,reg_no',
                'registration:id,event_id,item_id,school_id,status',
                'registration.school:id,name',
                'registration.item:id,title,item_code,category,stage_type,participant_type,class_group',
                'registration.event:id,source_phase_id,region_id',
                'registration.event.sourcePhase:id,name',
                'registration.event.region:id,name,code',
                'mark:id,participant_id,grade,position,score',
            ])
            ->get()
            ->filter(fn (FestParticipant $p) => $p->registration && $p->registration->item)
            ->map(function (FestParticipant $p) use ($classGroupLabels, $usesPhasedRegionalBilling) {
                $registration = $p->registration;
                $item = $registration->item;
                $event = $registration->event;

                return [
                    'id'              => $p->id,
                    'item_id'         => $item->id,
                    'item_title'      => $item->title,
                    'item_code'       => $item->item_code,
                    'category'        => \App\Support\FestClassGroupScheme::resolveItemKey($classGroupLabels, $item->class_group),
                    'category_label'  => \App\Support\FestClassGroupScheme::resolveItemLabel($classGroupLabels, $item->class_group),
                    'stage_type'      => $item->stage_type,
                    'participant_type' => $item->participant_type,
                    'phase_name'      => $usesPhasedRegionalBilling ? ($event?->sourcePhase?->name) : null,
                    'region_name'     => $event?->region?->name,
                    'region_code'     => $event?->region?->code,
                    'school_id'       => $registration->school_id,
                    'school_name'     => $registration->school?->name,
                    'participant'     => $p->student?->name ?? $p->teacher?->name,
                    'reg_no'          => $p->student?->reg_no ?? $p->teacher?->reg_no,
                    'fest_id'         => $p->level_registration_number,
                    'item_reg'        => $p->item_registration_number,
                    'chest_no'        => $p->chest_no,
                    'status'          => $registration->status,
                    'grade'           => $p->mark?->grade,
                    'position'        => $p->mark?->position,
                    'score'           => $p->mark?->score,
                ];
            })
            ->sortBy(['item_title', 'participant'])
            ->values()
            ->all();
    }

    /**
     * Participant-level list of everyone marked absent (fest_attendance.status =
     * 'absent') — no existing report exposed this; attendance only had a write-side
     * marking UI. Mirrors itemWiseReportRows()'s phase/region resolution (off the
     * registration's own operational event, never item.phase_id — see that method's
     * docblock) so the two reports stay consistent.
     *
     * @return list<array<string, mixed>>
     */
    public function absentReportRows(?string $schoolId = null): array
    {
        $absentByKey = FestAttendance::whereIn('event_id', $this->eventIds())
            ->where('status', 'absent')
            ->get(['item_id', 'participant_id', 'marked_by', 'marked_at'])
            ->keyBy(fn (FestAttendance $a) => $a->item_id.'-'.$a->participant_id);

        if ($absentByKey->isEmpty()) {
            return [];
        }

        $markedByNames = User::whereIn('id', $absentByKey->pluck('marked_by')->filter()->unique())
            ->pluck('name', 'id');

        $taxonomy = app(FestTaxonomyRegistry::class)->forTenant($this->event->tenant_id)->labels('arts_category');
        $usesPhasedRegionalBilling = $this->event->rootEvent()->usesPhasedRegionalBilling();

        return FestParticipant::query()
            ->whereHas('registration', function ($q) use ($schoolId) {
                $q->whereIn('event_id', $this->eventIds())->active();
                if ($schoolId) {
                    $q->where('school_id', $schoolId);
                }
            })
            ->with([
                'student:id,name,reg_no',
                'teacher:id,name,reg_no',
                'group:id,chest_no',
                'registration:id,event_id,item_id,school_id,status',
                'registration.school:id,name',
                'registration.item:id,title,item_code,category',
                'registration.event:id,source_phase_id,region_id',
                'registration.event.sourcePhase:id,name',
                'registration.event.region:id,name,code',
            ])
            ->get()
            ->filter(fn (FestParticipant $p) => $p->registration && $p->registration->item)
            ->map(fn (FestParticipant $p) => [$p, $absentByKey->get($p->registration->item_id.'-'.$p->id)])
            ->filter(fn (array $tuple) => $tuple[1] !== null)
            ->map(function (array $tuple) use ($taxonomy, $usesPhasedRegionalBilling, $markedByNames) {
                [$p, $attendance] = $tuple;
                $registration = $p->registration;
                $item = $registration->item;
                $event = $registration->event;

                return [
                    'item_id'        => $item->id,
                    'item_title'     => $item->title,
                    'item_code'      => $item->item_code,
                    'category_label' => $taxonomy[$item->category] ?? ucfirst((string) $item->category),
                    'phase_name'     => $usesPhasedRegionalBilling ? ($event?->sourcePhase?->name) : null,
                    'region_name'    => $event?->region?->name,
                    'region_code'    => $event?->region?->code,
                    'school_id'      => $registration->school_id,
                    'school_name'    => $registration->school?->name,
                    'participant'    => $p->student?->name ?? $p->teacher?->name,
                    'reg_no'         => $p->student?->reg_no ?? $p->teacher?->reg_no,
                    'chest_no'       => $p->group?->chest_no ?? $p->chest_no,
                    'marked_by'      => $markedByNames->get($attendance->marked_by),
                    'marked_at'      => $attendance->marked_at?->format('Y-m-d H:i'),
                ];
            })
            ->sortBy(['item_title', 'participant'])
            ->values()
            ->all();
    }

    public function exportAbsentReport(?string $schoolId = null): StreamedResponse
    {
        $rows = collect($this->absentReportRows($schoolId))->map(fn ($r) => [
            $r['item_title'], $r['item_code'], $r['category_label'], $r['phase_name'], $r['region_name'],
            $r['school_name'], $r['participant'], $r['reg_no'], $r['chest_no'], $r['marked_by'], $r['marked_at'],
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-absent-report',
            ['Item', 'Item Code', 'Category', 'Phase', 'Region', 'School', 'Participant', 'Reg No', 'Chest No', 'Marked By', 'Marked At'],
            $rows,
        );
    }

    /**
     * Enabled items grouped by category (class_group for non-sports events, age_group
     * for sports) — same column PublicFestScoreboardService::categories() groups by, so
     * this report's category tabs line up with what the public scoreboard shows.
     *
     * @return array<string, list<array<string, mixed>>> category key => items
     */
    public function categoryWiseItemRows(): array
    {
        $root = $this->event->rootEvent();
        $column = $root->event_type === 'sports' ? 'age_group' : 'class_group';

        $items = FestEventItem::whereIn('event_id', $this->eventIds())
            ->where('is_enabled', true)
            ->orderBy('display_order')
            ->orderBy('title')
            ->get(['id', 'title', 'item_code', 'participant_type', $column]);

        return $items
            ->groupBy(fn (FestEventItem $item) => $item->{$column} ?: 'open')
            ->map(fn ($group) => $group->map(fn (FestEventItem $item) => [
                'id'               => $item->id,
                'title'            => $item->title,
                'item_code'        => $item->item_code,
                'participant_type' => $item->participant_type,
            ])->values()->all())
            ->all();
    }

    /**
     * School × item pivot with category header bands and a per-school category subtotal
     * plus overall grand total — the consolidated report matching the printed
     * "OVERALL RESULT" sheet schools already produce by hand (school rows, item columns
     * grouped under CAT1-4 headers, category subtotal columns, OVERALL column). Reuses
     * categoryWiseItemRows() for the item/category grouping and the same points-per-mark
     * + dedup pattern already proven in EventContext::scoreboardByCategory()/
     * recalculateSchoolPoints() — cell values always agree with the championship
     * leaderboard because they're the exact same computation.
     *
     * @return array{categories: list<array<string, mixed>>, schools: list<array<string, mixed>>}
     */
    public function schoolItemPointsMatrix(): array
    {
        $itemsByCategory = $this->categoryWiseItemRows();
        $gradePointService = app(FestGradePointService::class);
        $scoreboards = app(PublicFestScoreboardService::class);

        // Same dedup as EventContext::scoreboardByCategory() — pair/group items save one
        // FestMark per teammate, all sharing deduplicationKey(), so a team's points must
        // only be counted once, not once per member.
        $marks = FestMark::whereIn('event_id', $this->eventIds())
            ->with(['participant.registration.school', 'item'])
            ->get()
            ->unique(fn (FestMark $m) => $m->deduplicationKey());

        $cellPoints = [];
        $schoolNames = [];

        foreach ($marks as $mark) {
            $participant = $mark->participant;
            if (! $participant || $participant->disqualified_at) {
                continue;
            }

            $school = $participant->registration?->school;
            if (! $school || ! $mark->item_id) {
                continue;
            }

            $schoolNames[$school->id] = $school->name;
            $cellPoints[$school->id][$mark->item_id] =
                ($cellPoints[$school->id][$mark->item_id] ?? 0) + $gradePointService->pointsForMark($this->event, $mark);
        }

        $categories = collect($itemsByCategory)
            ->map(fn (array $items, string $key) => [
                'key'   => $key,
                'label' => $key === 'open' ? 'Open' : $scoreboards->categoryLabel($this->event, $key),
                'items' => $items,
            ])
            ->sortBy('label')
            ->values();

        $schools = collect($schoolNames)
            ->map(function (string $name, string $schoolId) use ($categories, $cellPoints) {
                $points = $cellPoints[$schoolId] ?? [];
                $categoryTotals = [];
                $overall = 0;

                foreach ($categories as $category) {
                    $subtotal = 0;
                    foreach ($category['items'] as $item) {
                        $subtotal += $points[$item['id']] ?? 0;
                    }
                    $categoryTotals[$category['key']] = $subtotal;
                    $overall += $subtotal;
                }

                return [
                    'school_id'       => $schoolId,
                    'school_name'     => $name,
                    'points_by_item'  => $points,
                    'category_totals' => $categoryTotals,
                    'overall'         => $overall,
                ];
            })
            ->sortByDesc('overall')
            ->values()
            ->all();

        return [
            'categories' => $categories->all(),
            'schools'    => $schools,
        ];
    }

    /**
     * Per-participant rank/grade points breakdown for one item — powers the
     * Category-wise Points report's eye-icon detail view.
     *
     * @return list<array<string, mixed>>
     */
    public function itemPointsBreakdownRows(int $itemId): array
    {
        $gradePoints = app(FestGradePointService::class);

        return FestMark::whereIn('event_id', $this->eventIds())
            ->where('item_id', $itemId)
            ->with(['participant.student', 'participant.teacher', 'participant.registration.school', 'item'])
            ->orderBy('position')
            ->orderByDesc('score')
            ->get()
            ->unique(fn (FestMark $m) => $m->deduplicationKey())
            ->map(function (FestMark $mark) use ($gradePoints) {
                $breakdown = $gradePoints->pointsBreakdown($this->event, $mark);
                $participant = $mark->participant;
                $person = $participant?->student ?? $participant?->teacher;

                return [
                    'participant'  => $person?->name,
                    'school'       => $participant?->registration?->school?->name,
                    'position'     => $mark->position,
                    'grade'        => $mark->grade,
                    'rank_points'  => $breakdown['rank_points'],
                    'grade_points' => $breakdown['grade_points'],
                    'total'        => $breakdown['total'],
                ];
            })
            ->values()
            ->all();
    }
}
