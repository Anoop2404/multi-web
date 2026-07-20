<?php

namespace App\Services\Events;

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
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Support\ExcelExport;
use App\Support\FestIdCardTemplates;
use App\Services\Events\FestIdCardService;
use App\Support\TenantBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FestEventReportAnalyticsService
{
    public function __construct(public FestEvent $event) {}

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

        $items = FestEventItem::where('event_id', $this->event->id)->get(['id', 'sport_discipline']);
        $byDiscipline = $items->groupBy(fn ($i) => $i->sport_discipline ?: 'unspecified');

        $rows = [];
        foreach ($byDiscipline as $discipline => $group) {
            $itemIds = $group->pluck('id');
            $regQuery = fn (string $status) => FestRegistration::where('event_id', $this->event->id)
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
        $schoolIds = FestSchoolEventFee::where('event_id', $this->event->id)
            ->forAmountAggregation()
            ->pluck('school_id')
            ->unique();
        $schools = Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');

        return FestSchoolEventFee::where('event_id', $this->event->id)
            ->forAmountAggregation()
            ->with('feeReceipt')
            ->orderBy('school_id')
            ->get()
            ->map(fn (FestSchoolEventFee $fee) => [
                'school_id'   => $fee->school_id,
                'school_name' => $schools[$fee->school_id] ?? $fee->school_id,
                'total_due'   => (float) $fee->total_due,
                'paid'        => (float) ($fee->feeReceipt?->amount ?? 0),
                'status'      => $fee->status,
                'receipt_no'  => $fee->feeReceipt?->receipt_number,
            ])
            ->all();
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

        return $heads->map(function (FestItemHead $head) use ($itemResolver, $schedule, $usesPerHeadBilling) {
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
                $headFees = FestSchoolEventFee::where('event_id', $this->event->id)->where('head_id', $head->id)->get();
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

        return $heads->map(function (FestItemHead $head) use ($schoolId) {
            $itemIds = FestEventItem::where('event_id', $this->event->id)
                ->where('head_id', $head->id)
                ->pluck('id');

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

            $verifiedParticipants = FestParticipant::query()
                ->whereHas('registration', fn ($q) => $q
                    ->where('event_id', $this->event->id)
                    ->whereIn('item_id', $itemIds)
                    ->active()
                    ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
                ->whereHas('student', fn ($q) => $q->whereNotNull('verified_at'))
                ->count();

            $perItemCounts = FestRegistration::query()
                ->where('event_id', $this->event->id)
                ->whereIn('item_id', $itemIds)
                ->active()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->selectRaw('item_id, count(*) as cnt')
                ->groupBy('item_id')
                ->pluck('cnt', 'item_id');

            $maxItemReg = $perItemCounts->max() ?? 0;

            $quota = max(0, (int) ($head->included_items_per_student ?? 0));
            $headFees = FestSchoolEventFee::where('event_id', $this->event->id)
                ->where('head_id', $head->id)
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->get();

            return [
                'head_id'             => $head->id,
                'head_name'           => $head->name,
                'item_count'          => $itemIds->count(),
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
                'due_total'           => round((float) $headFees->sum('total_due'), 2),
                'collected_total'     => round((float) $headFees->where('status', 'approved')->sum('total_due'), 2),
                'pending_fee_total'   => round((float) $headFees->whereNotIn('status', ['approved', 'waived'])->sum('total_due'), 2),
                'default_item_fee'    => $head->default_item_fee !== null ? (float) $head->default_item_fee : null,
                'extra_item_fee'      => $head->extra_item_fee !== null ? (float) $head->extra_item_fee : null,
            ];
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

        return $sports->map(function (FestEvent $sport) use ($schoolId) {
            $itemIds = FestEventItem::where('event_id', $sport->id)->pluck('id');

            $regBase = FestRegistration::query()
                ->where('event_id', $sport->id)
                ->whereIn('item_id', $itemIds)
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

            $approved = (clone $regBase)->where('status', 'approved')->count();
            $pending = (clone $regBase)->whereIn('status', ['submitted', 'pending_approval'])->count();
            $waitlisted = (clone $regBase)->where('status', 'waitlisted')->count();

            $participantBase = FestParticipant::query()
                ->whereHas('registration', fn ($q) => $q
                    ->where('event_id', $sport->id)
                    ->whereIn('item_id', $itemIds)
                    ->active()
                    ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)));

            $participantCount = (clone $participantBase)->count();
            $verifiedParticipants = (clone $participantBase)
                ->whereHas('student', fn ($q) => $q->whereNotNull('verified_at'))
                ->count();

            $maxItemReg = FestRegistration::query()
                ->where('event_id', $sport->id)
                ->whereIn('item_id', $itemIds)
                ->active()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->selectRaw('item_id, count(*) as cnt')
                ->groupBy('item_id')
                ->pluck('cnt', 'item_id')
                ->max() ?? 0;

            $fees = FestSchoolEventFee::where('event_id', $sport->id)
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->get();

            return [
                'head_id'             => $sport->id,
                'head_name'           => $sport->title,
                'item_count'          => $itemIds->count(),
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
    public function itemRegistrationRows(?string $schoolId = null): array
    {
        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($this->event);
        $feeRequired = $feeService->feeRequired($this->event);
        $feeResolver = app(FestItemFeeResolver::class);

        $items = FestEventItem::query()
            ->where('event_id', $this->event->id)
            ->where('is_enabled', true)
            ->with('head:id,name,default_item_fee,extra_item_fee')
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $regBase = FestRegistration::query()
                ->where('event_id', $this->event->id)
                ->where('item_id', $item->id)
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

            $approved = (clone $regBase)->where('status', 'approved')->count();
            $pending = (clone $regBase)->where('status', 'submitted')->count();
            $totalRegs = $approved + $pending;

            $participantQuery = FestParticipant::query()
                ->whereHas('registration', fn ($q) => $q
                    ->where('event_id', $this->event->id)
                    ->where('item_id', $item->id)
                    ->active()
                    ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)));

            $participants = (clone $participantQuery)->count();
            $itemRegAssigned = (clone $participantQuery)
                ->whereNotNull('item_registration_number')
                ->count();

            $schoolCount = $schoolId
                ? ($totalRegs > 0 ? 1 : 0)
                : (int) FestRegistration::query()
                    ->where('event_id', $this->event->id)
                    ->where('item_id', $item->id)
                    ->active()
                    ->distinct()
                    ->count('school_id');

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
            'items'         => count($rows),
            'approved'      => array_sum(array_column($rows, 'approved')),
            'pending'       => array_sum(array_column($rows, 'pending')),
            'registrations' => array_sum(array_column($rows, 'registration_count')),
            'participants'  => array_sum(array_column($rows, 'participant_count')),
            'estimated_fee' => round(collect($rows)->sum(fn ($r) => (float) ($r['line_fee'] ?? 0)), 2),
        ];
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
        $itemScheduleIds = FestSchedule::query()
            ->where('event_id', $this->event->id)
            ->whereNull('participant_id')
            ->pluck('id', 'item_id');

        $items = FestEventItem::query()
            ->where('event_id', $this->event->id)
            ->where('is_enabled', true)
            ->with('head:id,name')
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $regBase = FestRegistration::query()
                ->where('event_id', $this->event->id)
                ->where('item_id', $item->id)
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

            $approved = (clone $regBase)->where('status', 'approved')->count();
            $pending = (clone $regBase)->where('status', 'submitted')->count();

            $performerQuery = FestParticipant::query()
                ->whereNull('disqualified_at')
                ->where(function ($q) {
                    $q->whereNull('participant_role')->orWhere('participant_role', '!=', 'standby');
                })
                ->whereHas('registration', fn ($q) => $q
                    ->where('event_id', $this->event->id)
                    ->where('item_id', $item->id)
                    ->where('status', 'approved')
                    ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)));

            $performers = (clone $performerQuery)->count();
            $chestAssigned = (clone $performerQuery)
                ->where(function ($q) {
                    $q->whereNotNull('chest_no')
                      ->orWhereHas('group', fn ($g) => $g->whereNotNull('chest_no'));
                })
                ->count();
            $itemRegAssigned = (clone $performerQuery)->whereNotNull('item_registration_number')->count();

            $scheduledParticipants = FestSchedule::query()
                ->where('event_id', $this->event->id)
                ->where('item_id', $item->id)
                ->whereNotNull('participant_id')
                ->when($schoolId, fn ($q) => $q->whereHas('participant.registration', fn ($r) => $r->where('school_id', $schoolId)))
                ->distinct('participant_id')
                ->count('participant_id');

            $marksEntered = FestMark::query()
                ->where('event_id', $this->event->id)
                ->where('item_id', $item->id)
                ->where(fn ($q) => $q->whereNotNull('grade')->orWhereNotNull('score')->orWhereNotNull('position'))
                ->when($schoolId, fn ($q) => $q->whereHas('participant.registration', fn ($r) => $r->where('school_id', $schoolId)))
                ->distinct('participant_id')
                ->count('participant_id');

            $judges = FestJudgeAssignment::where('event_id', $this->event->id)
                ->where('item_id', $item->id)
                ->count();

            $rows[] = [
                'item_id'                => $item->id,
                'head_id'                => $item->head_id,
                'head_name'              => $item->head?->name,
                'title'                  => $item->title,
                'age_group'              => $item->age_group,
                'class_group'            => $item->class_group,
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
        return FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $this->event->id)
                ->active()
                ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
            ->with([
                'student:id,name,reg_no',
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
                'reg_no'         => $p->student?->reg_no ?? $p->teacher?->reg_no,
                'reg_status'     => $p->registration?->status,
                'role'           => $p->participant_role ?? 'performer',
                'fest_id'        => $p->level_registration_number,
                'item_reg'       => $p->item_registration_number,
                'chest_no'       => $p->chest_no,
                'disqualified'   => $p->disqualified_at !== null,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function pendingApprovalRows(?string $schoolId = null): array
    {
        return FestRegistration::query()
            ->where('event_id', $this->event->id)
            ->where('status', 'submitted')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->with(['school:id,name', 'item:id,title,head_id', 'item.head:id,name', 'participants.student:id,name', 'participants.teacher:id,name'])
            ->orderBy('school_id')
            ->orderBy('item_id')
            ->get()
            ->map(fn (FestRegistration $reg) => [
                'registration_id' => $reg->id,
                'school_id'       => $reg->school_id,
                'school'          => $reg->school?->name,
                'head_name'       => $reg->item?->head?->name,
                'item_id'         => $reg->item_id,
                'item'            => $reg->item?->title,
                'participant_count' => $reg->participants->count(),
                'participants'    => $reg->participants->map(fn ($p) => $p->student?->name ?? $p->teacher?->name)->filter()->values()->all(),
                'submitted_at'    => $reg->updated_at?->toIso8601String(),
            ])
            ->all();
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
    public function headWiseParticipantRows(?int $headId = null, ?string $schoolId = null): array
    {
        $heads = FestItemHead::forTenant($this->event->tenant_id)
            ->forEvent($this->event->id)
            ->when($headId, fn ($q) => $q->where('id', $headId))
            ->orderBy('sort_order')
            ->get();

        $rows = [];
        foreach ($heads as $head) {
            $itemIds = FestEventItem::where('event_id', $this->event->id)
                ->where('head_id', $head->id)
                ->pluck('id');

            $participants = FestParticipant::query()
                ->whereHas('registration', fn ($q) => $q
                    ->where('event_id', $this->event->id)
                    ->whereIn('item_id', $itemIds)
                    ->active()
                    ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
                ->with([
                    'student:id,name,reg_no,photo,tenant_id',
                    'student.schoolClass:id,name',
                    'teacher:id,name,reg_no',
                    'registration.school:id,name',
                    'registration.item:id,title,head_id,competition_start,competition_end,competition_time',
                ])
                ->get();

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
                    'photo_url'  => $p->student?->photoUrl(),
                    'item'       => $p->registration?->item?->title,
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
        $teamItems = FestEventItem::where('event_id', $this->event->id)
            ->whereIn('participant_type', ['team', 'group'])
            ->orderBy('title')
            ->get();

        $rows = [];
        foreach ($teamItems as $item) {
            $regs = FestRegistration::where('event_id', $this->event->id)
                ->where('item_id', $item->id)
                ->active()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->with(['school:id,name', 'participants.student:id,name,reg_no', 'participants.teacher:id,name'])
                ->get();

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
        $service = new FestReportService($this->event);
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

        $rows = [];
        foreach ($areas as $area) {
            $itemIds = FestEventItem::where('event_id', $this->event->id)
                ->where('area_id', $area->id)
                ->pluck('id');

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
                    'registration.item:id,title,area_id',
                ])
                ->get();

            foreach ($participants as $p) {
                $rows[] = [
                    'area_id' => $area->id,
                    'area_name' => $area->name,
                    'item_id' => $p->registration?->item_id,
                    'school' => $p->registration?->school?->name,
                    'student' => $p->student?->name ?? $p->teacher?->name,
                    'reg_no' => $p->student?->reg_no ?? $p->teacher?->reg_no,
                    'item' => $p->registration?->item?->title,
                    'item_reg' => $p->item_registration_number,
                    'chest_no' => $p->chest_no,
                    'fest_id' => $p->level_registration_number,
                    'status' => $p->registration?->status,
                ];
            }
        }

        if ($areaId === null || $areaId === 0) {
            $itemIds = FestEventItem::where('event_id', $this->event->id)->whereNull('area_id')->pluck('id');
            if ($itemIds->isNotEmpty() && ($areaId === null || $areaId === 0)) {
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
                        'registration.item:id,title,area_id',
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
            fputcsv($out, ['Timestamp', 'User', 'Action', 'Description', 'Page', 'Category']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
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
    public function studentWiseBrowserRows(?string $schoolId = null, ?string $search = null): array
    {
        $eventIds = [$this->event->id];
        if ($this->event->isSportsSeasonEvent()) {
            $eventIds = array_merge($eventIds, FestEvent::where('parent_event_id', $this->event->id)->pluck('id')->all());
        }

        $participants = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $eventIds)
                ->active()
                ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId)))
            ->whereNotNull('student_id')
            ->with([
                'student:id,name,reg_no,gender',
                'registration.school:id,name',
                'registration.item:id,title,head_id,event_id',
                'registration.item.head:id,name',
                'registration.item.event:id,title',
            ])
            ->get();

        $marksByParticipant = FestMark::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('participant_id', $participants->pluck('id'))
            ->get()
            ->keyBy('participant_id');

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

            $items = $entries->map(function (FestParticipant $p) use ($marksByParticipant) {
                $mark = $marksByParticipant->get($p->id);

                return [
                    'item_id'           => $p->registration?->item_id,
                    'item_title'        => $p->registration?->item?->title,
                    'head_name'         => $p->registration?->item?->head?->name,
                    'status'            => $p->registration?->status,
                    'fest_id'           => $p->level_registration_number,
                    'item_reg'          => $p->item_registration_number,
                    'chest_no'          => $p->chest_no,
                    'grade'             => $mark?->grade,
                    'position'          => $mark?->position,
                    'score'             => $mark?->score,
                    'mark_value'        => $mark?->measurement_value,
                    'mark_unit'         => $mark?->measurement_unit,
                    'sport_event_id'    => $p->registration?->event_id,
                    'sport_event_title' => $p->registration?->item?->event?->title,
                ];
            })->values()->all();

            $rows[] = [
                'student_id'  => (int) $studentId,
                'school_id'   => $first->registration?->school_id,
                'school_name' => $first->registration?->school?->name,
                'name'        => $name,
                'reg_no'      => $regNo,
                'gender'      => $student->gender,
                'item_count'  => count($items),
                'total_score' => collect($items)->sum(fn ($i) => (float) ($i['score'] ?? 0)),
                'items'       => $items,
            ];
        }

        usort($rows, fn ($a, $b) => [$a['school_name'] ?? '', $a['name']] <=> [$b['school_name'] ?? '', $b['name']]);

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function itemWiseBrowserRows(int $itemId): array
    {
        return FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $this->event->id)
                ->where('item_id', $itemId)
                ->active())
            ->with(['student', 'teacher', 'registration.school', 'mark'])
            ->orderBy('chest_no')
            ->get()
            ->map(fn (FestParticipant $p) => [
                'id'          => $p->id,
                'participant' => $p->student?->name ?? $p->teacher?->name,
                'reg_no'      => $p->student?->reg_no ?? $p->teacher?->reg_no,
                'school'      => $p->registration?->school?->name,
                'fest_id'     => $p->level_registration_number,
                'item_reg'    => $p->item_registration_number,
                'chest_no'    => $p->chest_no,
                'grade'       => $p->mark?->grade,
                'position'    => $p->mark?->position,
                'score'       => $p->mark?->score,
                'status'      => $p->registration?->status,
            ])
            ->all();
    }
}
