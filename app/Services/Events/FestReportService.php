<?php

namespace App\Services\Events;

use App\Models\Certificate;
use App\Models\FestCateringOrder;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestHouse;
use App\Models\FestJudgeAssignment;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestQualification;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestIndividualChampionshipService;
use App\Support\ExcelExport;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use App\Support\FestStudentClassResolver;
use App\Support\PdfGenerator;
use App\Support\ReportFilename;
use App\Support\SchoolEventCoordinator;
use App\Support\TenantBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\Events\Reports\FestReportScope;

class FestReportService
{
    use \App\Http\Controllers\SahodayaAdmin\Concerns\ParsesBulkSheetFilters;


    // Public (was private) so a controller calling renderPdf() directly — bypassing the
    // generic export() dispatcher, which is the only place that used to set this from the
    // request — can still honor ?inline=1/?preview=1 vs ?download=1. See
    // FestSchoolReportController::exportStudentLimitsPdf() for the resolution logic to
    // mirror (export()'s own, line ~413).
    public bool $preview = false;

    /**
     * Chest numbers are Sahodaya-admin-only information — schools don't see them until
     * assigned on fest day. FestSchoolReportController::admitCards() sets this true
     * before calling export('admit-cards', ...); Sahodaya-admin's export leaves the
     * default, since it does need this field.
     */
    public bool $hideChestNo = false;

    public function __construct(public FestEvent $event, private ?FestReportScope $scope = null) {}

    /** @return list<int> */
    private function eventIds(): array
    {
        return $this->scope?->eventIds ?? $this->event->reportableEventIds();
    }

    /** @return list<int> */
    private function itemIdsFor(?int $requestedItemId = null): array
    {
        $ids = $requestedItemId
            ? $this->event->reportableItemIds([$requestedItemId])
            : ($this->scope?->itemIds ?? FestEventItem::whereIn('event_id', $this->eventIds())->pluck('id')->map(fn ($id) => (int) $id)->all());

        if ($this->scope) {
            $ids = array_values(array_intersect($ids, $this->scope->itemIds));
        }

        return $ids;
    }

    /**
     * Resolves the Bulk Sheets picker's ?item_ids=/?phase_id=/?area_id= (see
     * ParsesBulkSheetFilters) to a concrete list of item ids across this service's
     * reportable event scope, for use by attendanceSheetPdf()/timesheetPdf(). Returns null
     * when none of those three params were given at all (existing single item_id/"every
     * item" behavior unaffected); an empty array means a filter WAS given but matched
     * nothing.
     *
     * @return list<int>|null
     */
    private function resolveBulkItemIds(Request $request): ?array
    {
        [$itemIds, $phaseId, $areaId] = $this->parseBulkSheetFilters($request);
        if (! $itemIds && ! $phaseId && ! $areaId) {
            return null;
        }

        $query = FestEventItem::whereIn('event_id', $this->eventIds());
        if ($itemIds) {
            $query->whereIn('id', $itemIds);
        }
        if ($phaseId) {
            $query->where('phase_id', $phaseId);
        }
        if ($areaId) {
            $query->where('area_id', $areaId);
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function scopedSchoolId(?string $requestedSchoolId): ?string
    {
        if (! $this->scope?->isActorRestricted) {
            return $requestedSchoolId;
        }

        if ($requestedSchoolId !== null && ! in_array($requestedSchoolId, $this->scope->schoolIds, true)) {
            abort(403, 'School is outside your report scope.');
        }

        return $requestedSchoolId;
    }

    /**
     * Sahodaya branding (org name + logo data URI) for PDF report headers.
     *
     * Public for the same reason renderPdf() above is: FestSchoolReportController's
     * exportStudentWisePdf() calls this on an externally-constructed instance.
     *
     * @return array{orgName: string, logoSrc: ?string}
     */
    public function brandingData(): array
    {
        $sahodaya = Tenant::find($this->event->tenant_id);

        return [
            'orgName' => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc' => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
        ];
    }

    /**
     * Schools with an active registration in this event, grouped by phase for
     * phased_regional_billing roots. A registration's phase is resolved from
     * registration.event.source_phase_id (the leaf FestEvent it was made against) — never
     * from FestEventItem.phase_id, which has been mistagged in production before (see
     * FestEventReportAnalyticsService::itemWiseReportRows() docblock).
     *
     * @return array{rows: list<array<string, mixed>>, usesPhases: bool, totals: array<string, int>}
     */
    public function schoolParticipationReport(): array
    {
        $regs = $this->activeRegistrations();
        $usesPhases = $this->event->rootEvent()->usesPhasedRegionalBilling();

        $rows = $regs
            ->groupBy(fn ($r) => $r->school_id.'|'.($usesPhases ? ($r->event->source_phase_id ?? 0) : 0))
            ->map(function ($group) use ($usesPhases) {
                $first = $group->first();
                $enabled = $group->filter(fn ($r) => $r->item?->is_enabled ?? true);

                return [
                    'school_id'            => $first->school_id,
                    'school_name'          => $first->school?->name ?? $first->school_id,
                    'phase_id'             => $usesPhases ? $first->event->source_phase_id : null,
                    'phase_name'           => $usesPhases ? ($first->event->sourcePhase?->name ?? 'Unassigned') : null,
                    'active_count'         => $group->sum(fn ($r) => $this->registrationAtomicCount($r)),
                    'item_count'           => $enabled->pluck('item_id')->unique()->count(),
                    'unique_student_count' => $enabled->flatMap(fn ($r) => $r->participants)->pluck('student_id')->filter()->unique()->count(),
                ];
            })
            ->values()
            ->all();

        usort($rows, function ($a, $b) {
            $phaseCmp = strcmp($a['phase_name'] ?? '', $b['phase_name'] ?? '');

            return $phaseCmp !== 0 ? $phaseCmp : $b['active_count'] <=> $a['active_count'];
        });

        return [
            'rows'       => $rows,
            'usesPhases' => $usesPhases,
            'totals'     => [
                'schools'              => $regs->pluck('school_id')->unique()->count(),
                'active_registrations' => $regs->sum(fn ($r) => $this->registrationAtomicCount($r)),
                'unique_students'      => $regs->flatMap(fn ($r) => $r->participants)->pluck('student_id')->filter()->unique()->count(),
            ],
        ];
    }

    /**
     * How many "registrations" one FestRegistration row represents for reporting.
     * A team/group/pair/trio row is one entry no matter its roster size. But for an
     * individual item, FestRegistrationCreateService::createForSchool() can bundle
     * several distinct students from the same school under a single row when
     * max_per_school > 1 — so each of their FestParticipant rows is its own
     * registration, mirroring the atomic-unit convention FestMark::deduplicationKey()
     * already uses for the same reason (a participant, not a registration, is the
     * correct unit for an individual entrant).
     */
    private function registrationAtomicCount(FestRegistration $registration): int
    {
        $participantType = strtolower((string) ($registration->item?->participant_type ?? 'individual'));

        return $participantType === 'individual'
            ? max($registration->participants->count(), 1)
            : 1;
    }

    private function schoolParticipationPdf(): \Symfony\Component\HttpFoundation\Response
    {
        $report = $this->schoolParticipationReport();

        return $this->renderPdf('fest.reports.school-participation', [
            'event'      => $this->event,
            'rows'       => $report['rows'],
            'usesPhases' => $report['usesPhases'],
            'totals'     => $report['totals'],
            ...$this->brandingData(),
        ], $this->slug().'-school-participation.pdf');
    }

    private function schoolParticipationXls(): StreamedResponse
    {
        $report = $this->schoolParticipationReport();

        $header = $report['usesPhases']
            ? ['School', 'Phase', 'Active registrations', 'Items', 'Unique students']
            : ['School', 'Active registrations', 'Items', 'Unique students'];

        $rows = collect($report['rows'])->map(fn ($r) => $report['usesPhases']
            ? [$r['school_name'], $r['phase_name'], $r['active_count'], $r['item_count'], $r['unique_student_count']]
            : [$r['school_name'], $r['active_count'], $r['item_count'], $r['unique_student_count']]);

        return ExcelExport::download($this->slug().'-school-participation', $header, $rows, ExcelExport::generatedOnNote());
    }

    /**
     * Unique participant counts categorized by class group / category range and school-wise,
     * including bulk category counts across all schools and summary statistics.
     *
     * @return array{
     *     categories: list<array{key: string, label: string}>,
     *     rows: list<array{
     *         school_id: string,
     *         school_name: string,
     *         school_code: ?string,
     *         category_counts: array<string, int>,
     *         total_unique_participants: int,
     *         total_registrations: int,
     *     }>,
     *     totals: array{
     *         category_counts: array<string, int>,
     *         total_schools: int,
     *         total_unique_participants: int,
     *         total_registrations: int,
     *     }
     * }
     */
    public function uniqueParticipantCategoryReport(?string $schoolId = null): array
    {
        $rootEvent = $this->event->rootEvent();
        $rawCategoryLabels = FestClassGroupScheme::labels(null, $rootEvent);

        $schoolId = $this->scopedSchoolId($schoolId);
        $participants = $this->participantsFlat(schoolId: $schoolId);

        $categories = [];
        $categoryUniqueMap = [];
        foreach ($rawCategoryLabels as $key => $label) {
            $categories[] = ['key' => (string) $key, 'label' => (string) $label];
            $categoryUniqueMap[$key] = [];
        }

        $schools = [];
        $allUniqueStudentIds = [];

        foreach ($participants as $p) {
            $studentEntityId = $p->student_id ?: ('t:'.$p->teacher_id);
            if (! $studentEntityId) {
                continue;
            }

            $allUniqueStudentIds[$studentEntityId] = true;

            $item = $p->registration?->item;
            $rawCat = $item?->class_group;
            $catKey = null;

            if ($rawCat && $rawCat !== 'open') {
                $catKey = FestClassGroupScheme::resolveItemKey($rawCategoryLabels, $rawCat);
            } elseif ($p->student) {
                $studentCat = FestStudentClassResolver::classGroupForStudent($p->student, $rootEvent);
                if ($studentCat) {
                    $catKey = FestClassGroupScheme::resolveItemKey($rawCategoryLabels, $studentCat);
                }
            }

            $catKey = $catKey ?: 'open';
            if (! isset($rawCategoryLabels[$catKey])) {
                $rawCategoryLabels[$catKey] = ucwords(str_replace(['_', '-'], ' ', $catKey));
                $categories[] = ['key' => (string) $catKey, 'label' => (string) $rawCategoryLabels[$catKey]];
                $categoryUniqueMap[$catKey] = [];
            }

            $categoryUniqueMap[$catKey][$studentEntityId] = true;

            $sId = (string) ($p->registration?->school_id ?? 'unknown');
            if (! isset($schools[$sId])) {
                $schools[$sId] = [
                    'school_id'           => $sId,
                    'school_name'         => $p->registration?->school?->name ?? 'Unknown School',
                    'school_code'         => $p->registration?->school?->schoolCode(),
                    'students_by_cat'     => [],
                    'all_students'        => [],
                    'total_registrations' => 0,
                ];
                foreach ($rawCategoryLabels as $k => $l) {
                    $schools[$sId]['students_by_cat'][$k] = [];
                }
            }

            $schools[$sId]['students_by_cat'][$catKey][$studentEntityId] = true;
            $schools[$sId]['all_students'][$studentEntityId] = true;
            $schools[$sId]['total_registrations']++;
        }

        $rows = [];
        foreach ($schools as $sId => $data) {
            $catCounts = [];
            foreach ($categories as $cat) {
                $k = $cat['key'];
                $catCounts[$k] = count($data['students_by_cat'][$k] ?? []);
            }

            $rows[] = [
                'school_id'                 => $sId,
                'school_name'               => $data['school_name'],
                'school_code'               => $data['school_code'],
                'category_counts'           => $catCounts,
                'total_unique_participants' => count($data['all_students']),
                'total_registrations'       => $data['total_registrations'],
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['school_name'], $b['school_name']));

        $totalCategoryCounts = [];
        foreach ($categories as $cat) {
            $k = $cat['key'];
            $totalCategoryCounts[$k] = count($categoryUniqueMap[$k] ?? []);
        }

        return [
            'categories' => $categories,
            'rows'       => $rows,
            'totals'     => [
                'category_counts'           => $totalCategoryCounts,
                'total_schools'             => count($rows),
                'total_unique_participants' => count($allUniqueStudentIds),
                'total_registrations'       => array_sum(array_column($rows, 'total_registrations')),
            ],
        ];
    }

    public function uniqueParticipantsPdf(?string $schoolId = null): \Symfony\Component\HttpFoundation\Response
    {
        $report = $this->uniqueParticipantCategoryReport($schoolId);

        return $this->renderPdf('fest.reports.unique-participants', [
            'event'      => $this->event,
            'categories' => $report['categories'],
            'rows'       => $report['rows'],
            'totals'     => $report['totals'],
            ...$this->brandingData(),
        ], $this->slug().'-unique-participants.pdf');
    }

    public function uniqueParticipantsXls(?string $schoolId = null): StreamedResponse
    {
        $report = $this->uniqueParticipantCategoryReport($schoolId);

        $headers = ['School Code', 'School Name'];
        foreach ($report['categories'] as $cat) {
            $headers[] = $cat['label'];
        }
        $headers[] = 'Total Unique Students';
        $headers[] = 'Total Registrations';

        $rows = [];
        foreach ($report['rows'] as $r) {
            $row = [$r['school_code'] ?? '—', $r['school_name']];
            foreach ($report['categories'] as $cat) {
                $row[] = $r['category_counts'][$cat['key']] ?? 0;
            }
            $row[] = $r['total_unique_participants'];
            $row[] = $r['total_registrations'];
            $rows[] = $row;
        }

        $summaryRow = ['TOTALS', count($report['rows']).' Schools'];
        foreach ($report['categories'] as $cat) {
            $summaryRow[] = $report['totals']['category_counts'][$cat['key']] ?? 0;
        }
        $summaryRow[] = $report['totals']['total_unique_participants'];
        $summaryRow[] = $report['totals']['total_registrations'];
        $rows[] = $summaryRow;

        return ExcelExport::download(
            $this->slug().'-unique-participants',
            $headers,
            $rows,
            ExcelExport::generatedOnNote()
        );
    }

    /**
     * Whole-fest, always — mirrors FestReportController::studentLimits()'s own docblock:
     * limits are a single fest-wide policy, so $this->event->rootEvent() is used
     * regardless of which region/phase leaf this export was requested against.
     *
     * @return array{rows: list<array<string, mixed>>, summary: array<string, int>}
     */
    private function studentLimitsReport(?string $schoolId, ?string $category = null, ?int $itemId = null, bool $includePhotoDataUri = false): array
    {
        $service = new FestParticipationLimitService($this->event->rootEvent());
        $rows = $service->studentLimitReportRows($schoolId, null, $category, $itemId, $includePhotoDataUri);

        return ['rows' => $rows, 'summary' => $service->summarizeStudentLimitRows($rows)];
    }

    private function studentLimitsPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $report = $this->studentLimitsReport(
            $request->input('school_id') ?: null,
            $request->input('category') ?: null,
            $request->filled('item_id') ? (int) $request->input('item_id') : null,
            includePhotoDataUri: true,
        );

        return $this->renderPdf('fest.reports.student-limits', [
            'event'   => $this->event,
            'rows'    => $report['rows'],
            'summary' => $report['summary'],
            ...$this->brandingData(),
        ], $this->slug().'-student-limits.pdf');
    }

    private function studentLimitsXls(Request $request): StreamedResponse
    {
        $report = $this->studentLimitsReport(
            $request->input('school_id') ?: null,
            $request->input('category') ?: null,
            $request->filled('item_id') ? (int) $request->input('item_id') : null,
        );
        $fmt = fn (array $dim) => $dim['limit'] !== null ? "{$dim['used']}/{$dim['limit']}" : (string) $dim['used'];

        $rows = collect($report['rows'])->map(fn ($r) => [
            $r['name'], $r['reg_no'] ?? '', $r['school_name'] ?? '',
            $fmt($r['on_stage']), $fmt($r['off_stage']), $fmt($r['individual']), $fmt($r['group']), $fmt($r['total']),
            $r['exceeds_any'] ? 'Yes' : 'No',
        ]);

        return ExcelExport::download($this->slug().'-student-limits', [
            'Student', 'Reg No', 'School', 'On-stage', 'Off-stage', 'Individual', 'Group', 'Total', 'Exceeds limit?',
        ], $rows, ExcelExport::generatedOnNote());
    }

    public function schools(): Collection
    {
        $ids = FestRegistration::whereIn('event_id', $this->eventIds())
            ->when($this->scope?->isActorRestricted, fn ($query) => $query->whereIn('school_id', $this->scope->schoolIds))
            ->pluck('school_id')->unique();

        return Tenant::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    public function items(): Collection
    {
        return FestEventItem::whereIn('id', $this->itemIdsFor())->with('head:id,name')->orderBy('display_order')->get();
    }

    /** @return array<string, string> */
    public static function classGroups(?FestEvent $event = null): array
    {
        return FestClassGroupScheme::labels(null, $event?->rootEvent());
    }

    public function approvedRegistrations(?string $classGroup = null, ?string $schoolId = null)
    {
        $schoolId = $this->scopedSchoolId($schoolId);

        return FestRegistration::whereIn('event_id', $this->eventIds())
            ->when($this->scope?->isActorRestricted, fn ($q) => $q->whereIn('school_id', $this->scope->schoolIds))
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($classGroup, fn ($q) => $q->whereHas('item', fn ($i) => $i->where('class_group', $classGroup)))
            ->with(['item', 'participants.student', 'participants.teacher', 'school'])
            ->orderBy('school_id')
            ->get();
    }

    public function activeRegistrations(?string $classGroup = null, ?string $schoolId = null)
    {
        $schoolId = $this->scopedSchoolId($schoolId);

        return FestRegistration::whereIn('event_id', $this->eventIds())
            ->when($this->scope?->isActorRestricted, fn ($q) => $q->whereIn('school_id', $this->scope->schoolIds))
            ->active()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($classGroup, fn ($q) => $q->whereHas('item', fn ($i) => $i->where('class_group', $classGroup)))
            ->with(['item', 'participants.student', 'participants.teacher', 'school', 'event:id,source_phase_id', 'event.sourcePhase:id,name'])
            ->orderBy('school_id')
            ->get();
    }

    public function participantsFlat(
        ?int $itemId = null,
        ?string $classGroup = null,
        ?string $schoolId = null,
        ?int $studentId = null,
        ?int $teacherId = null,
        bool $approvedOnly = false,
        array $explicitItemIds = [],
    ) {
        $schoolId = $this->scopedSchoolId($schoolId);
        // Bulk Sheets picker (item_ids/phase_id/area_id, resolved by the caller via
        // resolveBulkItemIds()) takes precedence over the single $itemId when given.
        $resolvedItemIds = $explicitItemIds !== [] ? $explicitItemIds : ($itemId ? $this->itemIdsFor($itemId) : null);

        return FestParticipant::query()
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->whereHas('registration', function ($q) use ($resolvedItemIds, $classGroup, $schoolId, $approvedOnly) {
                $q->whereIn('event_id', $this->eventIds())
                    ->when($this->scope?->isActorRestricted, fn ($q2) => $q2->whereIn('school_id', $this->scope->schoolIds))
                    ->when($approvedOnly, fn ($q2) => $q2->where('status', 'approved'), fn ($q2) => $q2->active())
                    ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId))
                    ->when($resolvedItemIds, fn ($q2) => $q2->whereIn('item_id', $resolvedItemIds))
                    ->when($classGroup, fn ($q2) => $q2->whereHas('item', fn ($i) => $i->where('class_group', $classGroup)));
            })
            ->with(['group', 'registration.event', 'registration.item.head', 'registration.school', 'student.schoolClass.classCategory', 'teacher'])
            ->orderBy('chest_no')
            ->get();
    }

    public function marks(?string $schoolId = null, ?int $itemId = null, ?string $classGroup = null)
    {
        $schoolId = $this->scopedSchoolId($schoolId);

        return FestMark::whereIn('event_id', $this->eventIds())
            ->when($this->scope?->isActorRestricted, fn ($q) => $q->whereHas(
                'participant.registration',
                fn ($registration) => $registration->whereIn('school_id', $this->scope->schoolIds)
            ))
            ->when($itemId, fn ($q) => $q->whereIn('item_id', $this->itemIdsFor($itemId)))
            ->when($classGroup, fn ($q) => $q->whereHas('item', fn ($i) => $i->where('class_group', $classGroup)))
            ->when($schoolId, fn ($q) => $q->whereHas('participant.registration', fn ($r) => $r->where('school_id', $schoolId)))
            ->with(['participant.student', 'participant.teacher', 'participant.registration.school', 'participant.registration.item', 'item'])
            ->orderBy('item_id')
            ->orderBy('position')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     *
     * Batched rewrite (2026-08-13): the original version called
     * FestEvent::reportableItemIds() — itself 2-3 queries — three separate times
     * (participants/marks/judges) per item, i.e. roughly 3+ query groups per item, for
     * every item in the event (~450+ queries on a 150-item Kalotsavam). This version
     * fetches the whole reportable item family, participant/mark/judge rows once each,
     * then computes each item's own "reportable group" (partition siblings sharing a
     * root id or item_code, matching reportableItemIds()'s own logic exactly) and counts
     * in memory. Output shape and per-row values are unchanged — see
     * markEntryStatusCsv()/markEntryStatusSummary() which consume this unmodified.
     */
    public function markEntryStatusRows(?string $schoolId = null): array
    {
        $schoolId = $this->scopedSchoolId($schoolId);
        $items = $this->items();
        if ($items->isEmpty()) {
            return [];
        }

        $eventIds = $this->eventIds();

        // Every item across the reportable event family (partition children/season-hub
        // children included), fetched once so each row's expanded id group can be
        // computed in memory instead of via a fresh reportableItemIds() query per item.
        $familyItems = FestEventItem::whereIn('event_id', $eventIds)
            ->get(['id', 'item_code', 'inherited_from_item_id']);

        $rootIdOf = fn (FestEventItem $i) => (int) ($i->inherited_from_item_id ?: $i->id);

        // Same expansion FestEvent::reportableItemIds([$item->id]) performs: every
        // family item sharing this item's root id, or sharing its item_code.
        $groupIdsByItemId = [];
        foreach ($items as $item) {
            $itemRootId = $rootIdOf($item);
            $itemCode = $item->item_code;

            $groupIdsByItemId[$item->id] = $familyItems
                ->filter(fn (FestEventItem $fi) => $rootIdOf($fi) === $itemRootId
                    || ($itemCode !== null && $fi->item_code === $itemCode))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $allGroupItemIds = collect($groupIdsByItemId)->flatten()->unique()->values();

        // Raw participant rows (not pre-aggregated counts) across every item's expanded
        // group in one query, counted per row's own group below — this preserves the
        // "distinct participant across the whole group" semantics the original per-item
        // query had, without risking a double-count from summing per-sub-item counts.
        $participantsByItemId = FestParticipant::query()
            ->join('fest_registrations', 'fest_registrations.id', '=', 'fest_participants.registration_id')
            ->whereIn('fest_registrations.event_id', $eventIds)
            ->whereIn('fest_registrations.item_id', $allGroupItemIds)
            ->whereNotIn('fest_registrations.status', ['rejected', 'withdrawn'])
            ->when($this->scope?->isActorRestricted, fn ($q) => $q->whereIn('fest_registrations.school_id', $this->scope->schoolIds))
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId))
            ->selectRaw('fest_participants.id as participant_id, fest_registrations.item_id as item_id')
            ->get()
            ->groupBy('item_id');

        $scoredQuery = FestMark::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('item_id', $allGroupItemIds)
            ->where(fn ($q) => $q->whereNotNull('grade')->orWhereNotNull('score')->orWhereNotNull('position'));
        if ($schoolId) {
            $scoredQuery->whereHas('participant.registration', fn ($q) => $q->where('school_id', $schoolId));
        }
        $scoredByItemId = $scoredQuery->get(['item_id', 'participant_id'])->groupBy('item_id');

        $judgesByItemId = FestJudgeAssignment::whereIn('event_id', $eventIds)
            ->whereIn('item_id', $allGroupItemIds)
            ->get(['item_id'])
            ->groupBy('item_id');

        $rows = [];
        foreach ($items as $item) {
            $groupIds = $groupIdsByItemId[$item->id];

            $partCount = collect($groupIds)
                ->flatMap(fn ($id) => $participantsByItemId->get($id, collect()))
                ->pluck('participant_id')
                ->unique()
                ->count();

            $scored = collect($groupIds)
                ->flatMap(fn ($id) => $scoredByItemId->get($id, collect()))
                ->pluck('participant_id')
                ->unique()
                ->count();

            $judges = collect($groupIds)->sum(fn ($id) => $judgesByItemId->get($id, collect())->count());

            $rows[] = [
                'item_id'      => $item->id,
                'title'        => $item->title,
                'class_group'  => $item->class_group,
                'head_id'      => $item->head_id,
                'head_name'    => $item->head?->name,
                'judges'       => $judges,
                'participants' => $partCount,
                'marked'       => $scored,
                'pending'      => max(0, $partCount - $scored),
                'complete'     => $partCount > 0 && $scored >= $partCount,
                'competition_start' => $item->competition_start,
                'competition_end'   => $item->competition_end,
                'competition_time'  => $item->competition_time,
            ];
        }

        return $rows;
    }

    /** @return array{summary: array<string, int>, rows: list<array<string, mixed>>} */
    public function markEntryStatusSummary(?string $schoolId = null): array
    {
        $rows = $this->markEntryStatusRows($schoolId);

        return [
            'summary' => [
                'items'        => count($rows),
                'participants' => array_sum(array_column($rows, 'participants')),
                'marked'       => array_sum(array_column($rows, 'marked')),
                'pending'      => array_sum(array_column($rows, 'pending')),
                'complete'     => count(array_filter($rows, fn ($r) => $r['complete'])),
            ],
            'rows' => $rows,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function itemRegistrationCountRows(): array
    {
        return app(FestEventReportAnalyticsService::class, ['event' => $this->event])
            ->itemRegistrationRows();
    }

    /**
     * Items where every performer already has marks entered but results haven't been
     * published yet — the same "marks ready, not published" state the Overview page's
     * marked_unpublished_items tile counts (FestEventController::show()), surfaced here
     * as an actual listing so an admin can see which items still need the Publish click
     * instead of just a number. Reuses FestItemResultsService::itemSummaries() rather
     * than reimplementing its marks_ready/results_published logic.
     *
     * @return list<array<string, mixed>>
     */
    public function resultsPendingRows(): array
    {
        $summaries = app(FestItemResultsService::class)->itemSummaries($this->event);

        if ($this->scope) {
            $allowedItemIds = array_flip($this->scope->itemIds);
            $summaries = array_values(array_filter($summaries, fn (array $r) => isset($allowedItemIds[$r['item_id']])));
        }

        return array_values(array_filter(
            $summaries,
            fn (array $r) => ($r['marks_ready'] ?? false) && ! ($r['results_published'] ?? false),
        ));
    }

    /** @return array{participant: list<array<string, mixed>>, stage: list<array<string, mixed>>} */
    public function scheduleClashRows(?string $schoolId = null): array
    {
        $service = new FestScheduleConflictService($this->event);

        return [
            'participant' => $service->detectAll($schoolId),
            'stage'       => $service->detectStageConflicts(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function itemScheduleRows(?string $date = null, ?int $stageId = null): array
    {
        return app(FestItemScheduleService::class)->reportRows($this->event, $date, $stageId);
    }

    /** @return array{total: int, scheduled: int, unscheduled: int} */
    public function itemScheduleSummary(): array
    {
        return app(FestItemScheduleService::class)->summary($this->event);
    }

    public function scheduleStages(): Collection
    {
        return \App\Models\FestStage::whereIn('event_id', $this->eventIds())
            ->with('venue:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'venue_id']);
    }

    public function schoolRankingRows(): Collection
    {
        $ctx = EventContext::for($this->event);
        $board = collect($ctx->scoreboardBySchool());

        $marks = FestMark::whereIn('event_id', $this->eventIds())
            ->whereNotNull('position')
            ->with('participant.registration')
            ->get();

        return $board->map(function ($row) use ($marks) {
            $schoolId = $row['school_id'];
            $schoolMarks = $marks->filter(fn ($m) => $m->participant?->registration?->school_id === $schoolId);

            return (object) [
                'id'           => $schoolId,
                'name'         => $row['school_name'],
                'gold'         => $schoolMarks->where('position', 1)->count(),
                'silver'       => $schoolMarks->where('position', 2)->count(),
                'bronze'       => $schoolMarks->where('position', 3)->count(),
                'total_points' => $row['total_points'],
                'rank'         => $row['rank'],
            ];
        });
    }

    public function export(string $type, Request $request): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $audience = $request->input('audience', 'staff') === 'public' ? 'public' : 'staff';
        $this->preview = (! $request->boolean('download')) && ($request->boolean('inline') || $request->boolean('preview') || ! $request->has('download'));

        EventLifecycleGate::allowReportExport($this->event, $type, $audience);
        EventLifecycleGate::allowResultReport($this->event, $type);

        $analytics = fn () => new FestEventReportAnalyticsService($this->event, $this->scope);

        return match ($type) {
            'registrations' => $this->registrationsXls($request->input('school_id')),
            'category-wise-students' => $this->categoryWiseStudentsXls($request),
            'item-participants' => $this->itemParticipantsXls($request),
            'student-wise-report' => $this->studentWiseReportXls($request),
            'student-wise-pdf' => $this->studentWisePdf($request),
            'results' => $this->resultsXls($request->input('school_id')),
            'fees' => app(FestExportService::class)->fees($this->event, $request->input('school_id'), $this->scope),
            'fee-breakdown' => app(FestExportService::class)->feeBreakdown($this->event, $request->input('school_id'), $this->scope),
            'student-event-registrations' => app(FestExportService::class)->studentEventRegistrations($this->event, $request->input('school_id'), $this->scope),
            'registration-list' => $this->registrationListPdf($request),
            'school-wise' => $this->schoolWisePdf($request),
            'overall-ranking' => $this->overallRankingPdf(),
            'category-item-matrix-xls' => $this->categoryItemMatrixXls($analytics()),
            'category-item-matrix-pdf' => $this->categoryItemMatrixPdf($analytics()),
            'category-totals-xls' => $this->categoryTotalsXls($analytics()),
            'category-totals-pdf' => $this->categoryTotalsPdf($analytics()),
            'individual-championship-xls' => $this->individualChampionshipXls(),
            'individual-championship-pdf' => $this->individualChampionshipPdf(),
            'house-wise' => $this->houseWisePdf(),
            'item-list' => $this->itemListPdf(),
            'item-wise' => $this->itemWisePdf($request),
            'cumulative' => $this->cumulativePdf(),
            'day-wise' => $this->dayWisePdf($request),
            'attendance-sheet' => $this->attendanceSheetPdf($request),
            'attendance-sheet-school' => $this->attendanceSheetSchoolPdf($request),
            'timesheet' => $this->timesheetPdf($request),
            'mark-entry-status' => $this->markEntryStatusCsv(),
            'results-pending' => $this->resultsPendingCsv(),
            'absent-report' => $analytics()->exportAbsentReport($request->input('school_id')),
            'item-order-public' => $this->itemOrderPublicPdf($request),
            'green-room-list' => $this->greenRoomListPdf($request),
            'clashes' => $this->clashesCsv($request),
            'clashes-school' => $this->clashesSchoolPdf($request),
            'schedule-clashes-pdf' => $this->scheduleClashesPdf($request),
            'promotions' => $this->promotionsCsv(),
            'promotions-pdf' => $this->promotionsPdf(),
            'certificate-counts' => $this->certificateCountsCsv($request->input('school_id')),
            'catering' => $this->cateringCsv(),
            'students' => $this->studentsCsv(),
            'admit-cards' => $this->admitCardsPdf($request),
            'sahodaya-ranking' => $this->sahodayaRankingPdf(),
            'student-participation' => $this->studentParticipationXls($request),
            'discipline-registration' => $analytics()->exportDisciplineRegistration(),
            'age-group-matrix' => $analytics()->exportAgeGroupMatrix($request->input('school_id')),
            'fee-pending-schools' => $analytics()->exportFeePendingSchools(),
            'head-wise-participants' => $analytics()->exportHeadWiseParticipants(
                $request->integer('head_id') ?: null,
                $request->input('school_id'),
            ),
            'area-wise-participants' => $analytics()->exportAreaWiseParticipants(
                $request->input('area_id') !== null && $request->input('area_id') !== ''
                    ? ($request->input('area_id') === 'other' ? 0 : $request->integer('area_id'))
                    : null,
                $request->input('school_id'),
            ),
            'team-squad-sheets' => $analytics()->teamSquadPdf($request->input('school_id')),
            'medal-tally' => $analytics()->medalTallyPdf(),
            'assignment-completeness' => $analytics()->exportAssignmentCompleteness($request->input('school_id')),
            'numbering-register' => $analytics()->exportNumberingRegister($request->input('school_id')),
            'pending-approvals' => $analytics()->exportPendingApprovals($request->input('school_id')),
            'volunteer-roster' => $analytics()->exportVolunteerRoster(),
            'catering-by-school' => $analytics()->exportCateringBySchool($request->input('school_id')),
            'id-cards-by-head' => $analytics()->idCardsByHeadPdf(
                $request->integer('head_id') ?: null,
                $request->input('school_id'),
                $request->input('template'),
            ),
            'audit-log-extract' => $analytics()->exportAuditLogExtract(),
            'item-schedule' => $this->itemScheduleCsv($request),
            'item-schedule-pdf' => $this->itemSchedulePdf($request),
            'school-participation-pdf' => $this->schoolParticipationPdf(),
            'school-participation-xls' => $this->schoolParticipationXls(),
            'unique-participants-pdf' => $this->uniqueParticipantsPdf($request->input('school_id')),
            'unique-participants-xls' => $this->uniqueParticipantsXls($request->input('school_id')),
            'student-limits-pdf' => $this->studentLimitsPdf($request),
            'student-limits-xls' => $this->studentLimitsXls($request),
            'team-managers' => $this->teamManagersXls($request),
            'team-managers-pdf' => $this->teamManagersPdf($request),
            'team-managers-registration-sheet' => $this->teamManagersRegistrationSheetPdf($request),
            default => abort(404, "Report type '{$type}' not supported."),
        };
    }

    private function slug(): string
    {
        return $this->slugFor($this->event);
    }

    private function slugFor(FestEvent $event): string
    {
        return str($event->title)->slug()->limit(40)->toString();
    }

    /**
     * Public: FestSchoolReportController::exportStudentWisePdf() calls this on an
     * externally-constructed FestReportService instance (every other call site is
     * internal, $this->renderPdf(...)) — this was 'private' and that call would fatal
     * with "Call to private method from scope FestSchoolReportController" for every
     * school trying to download the student-wise PDF, pre-existing and unrelated to
     * chest-number handling, found while adding test coverage for that fix.
     */
    public function renderPdf(
        string $view,
        array $data,
        string $filename,
        bool $landscape = false,
        ?string $headerTemplate = null,
        ?string $footerTemplate = null,
        ?array $margin = null,
    ): \Symfony\Component\HttpFoundation\Response {
        $html = view($view, $data)->render();

        return PdfGenerator::download($html, $filename, $this->preview, $landscape, $headerTemplate, $footerTemplate, $margin);
    }

    private function reportAudience(Request $request): string
    {
        return $request->input('audience', 'staff') === 'public' ? 'public' : 'staff';
    }

    /** @return list<array<string, mixed>> */
    private function participantReportRows($participants, string $audience): array
    {
        $visibility = app(FestPublicVisibilityService::class);

        $participantIds = collect($participants)->pluck('id')->all();
        $schedules = FestSchedule::whereIn('participant_id', $participantIds)
            ->get()
            ->keyBy('participant_id');

        // Resolved once, not per-row — FestClassGroupScheme::labels() walks up to the
        // root event, which would otherwise re-run for every single participant.
        $classGroupLabels = \App\Support\FestClassGroupScheme::labels(null, $this->event->rootEvent());

        return collect($participants)->map(function (FestParticipant $p) use ($visibility, $audience, $schedules, $classGroupLabels) {
            $schedule = $schedules->get($p->id);

            $item = $p->registration?->item;
            $ageGroup = trim((string) ($item?->age_group ?? ''));
            $classGroup = trim((string) ($item?->class_group ?? ''));

            return array_merge(
                $visibility->formatReportRow($this->event, $p, $audience, $schedule),
                [
                    '_student_id' => $p->student_id,
                    '_uses_age'   => $ageGroup !== '',
                    '_uses_class' => $ageGroup === '' && $classGroup !== '' && $classGroup !== 'open',
                    'fest_id'     => $p->level_registration_number,
                    'dob'         => $p->student?->dob?->format('d M Y'),
                    'class'       => $p->student?->schoolClass?->name,
                    // Item's own Category/Type/Gender — read by the attendance sheet's
                    // single-item header meta line (item_category/item_type/item_gender
                    // keys were previously read but never actually set anywhere, so that
                    // header line was always blank).
                    'item_category' => ($classGroup !== '' && $classGroup !== 'open')
                        ? \App\Support\FestClassGroupScheme::resolveItemLabel($classGroupLabels, $classGroup)
                        : null,
                    'item_type'   => $item ? (\App\Support\FestTeamSquadRules::isMultiPerson($item->participant_type) ? 'Group' : 'Individual') : null,
                    'item_gender' => $item ? (\App\Support\FestSportsAgeGroup::genderLabel($item->gender) ?? 'Open') : null,
                ],
            );
        })->all();
    }

    private function registrationsXls(?string $schoolId = null): StreamedResponse
    {
        return app(FestExportService::class)->registrations($this->event, $schoolId);
    }

    private function resultsXls(?string $schoolId = null): StreamedResponse
    {
        return app(FestExportService::class)->results($this->event, $schoolId);
    }

    private function categoryWiseStudentsXls(Request $request): StreamedResponse
    {
        $rows = $this->participantsFlat(
            null,
            null,
            $request->input('school_id'),
        )
            ->filter(fn (FestParticipant $p) => $p->student !== null)
            ->sortBy(fn (FestParticipant $p) => [
                $p->student?->schoolClass?->classCategory?->label ?? '',
                $p->student?->schoolClass?->name ?? '',
                $p->student?->name ?? '',
                $p->registration?->item?->title ?? '',
            ])
            ->map(fn (FestParticipant $p) => [
                $p->student?->schoolClass?->classCategory?->label,
                $p->student?->schoolClass?->name,
                $p->student?->reg_no,
                $p->student?->admission_number,
                $p->student?->name,
                $p->student?->gender,
                $p->student?->dob?->format('Y-m-d'),
                $p->registration?->school?->name,
                $p->registration?->item?->title,
                $p->registration?->item?->head?->name,
                $p->chest_no,
            ])
            ->values()
            ->all();

        return ExcelExport::download($this->slug().'-category-wise-students', [
            'Category', 'Class', 'Reg No', 'Admission No', 'Student', 'Gender', 'DOB',
            'School', 'Item', 'Item Head', 'Chest No',
        ], $rows, ExcelExport::generatedOnNote());
    }

    private function itemParticipantsXls(Request $request): StreamedResponse
    {
        $rows = $this->participantsFlat(
            $request->integer('item_id') ?: null,
            null,
            $request->input('school_id'),
        )
            ->sortBy(fn (FestParticipant $p) => [
                $p->registration?->item?->head?->name ?? '',
                $p->registration?->item?->title ?? '',
                $p->chest_no ?? 999999,
                $p->student?->name ?? $p->teacher?->name ?? '',
            ])
            ->map(fn (FestParticipant $p) => [
                $p->registration?->item?->head?->name,
                $p->registration?->item?->title,
                $p->registration?->item?->class_group,
                $p->registration?->school?->name,
                $p->student?->name ?? $p->teacher?->name,
                $p->student?->reg_no,
                $p->student?->schoolClass?->name,
                $p->chest_no,
            ])
            ->values()
            ->all();

        return ExcelExport::download($this->slug().'-item-participants', [
            'Item Head', 'Item', 'Class Group', 'School', 'Participant', 'Reg No',
            'Class', 'Chest No',
        ], $rows, ExcelExport::generatedOnNote());
    }

    private function studentWisePdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $schoolId = $request->input('school_id');
        $search = $request->input('search');
        $rank = $request->integer('rank') ?: null;
        $analytics = app(FestEventReportAnalyticsService::class, ['event' => $this->event]);
        $rows = $analytics->studentWiseBrowserRows($schoolId, $search, includePhotoDataUri: true, rank: $rank);

        return $this->renderPdf('fest.reports.student-wise', [
            'event' => $this->event,
            'students' => $rows,
            'showChestNo' => false,
            'bySchool' => $request->boolean('by_school'),
            ...$this->brandingData(),
        ], $this->slug().($request->boolean('by_school') ? '-student-wise-by-school.pdf' : '-student-wise-report.pdf'));
    }

    private function studentWiseReportXls(Request $request): StreamedResponse
    {
        $analytics = app(FestEventReportAnalyticsService::class, ['event' => $this->event]);
        $students = $analytics->studentWiseBrowserRows($request->input('school_id'), $request->input('search'), rank: $request->integer('rank') ?: null);

        // One row per student × item (not one row per student) so rank/mark/grade — which
        // are per-item — have somewhere to go; the old one-row-per-student shape only had
        // room for a concatenated item-title list.
        $rows = [];
        foreach ($students as $student) {
            foreach ($student['items'] as $item) {
                $rows[] = [
                    $student['school_name'],
                    $student['school_code'],
                    $student['reg_no'],
                    $student['name'],
                    $student['gender'],
                    $item['item_title'],
                    $item['category_label'],
                    $item['results_published'] ? $item['position'] : null,
                    $item['results_published'] ? $item['score'] : null,
                    $item['results_published'] ? $item['grade'] : ($item['results_published'] === false ? 'Pending' : null),
                ];
            }
        }

        return ExcelExport::download($this->slug().'-student-wise-report', [
            'School', 'School Code', 'Reg No', 'Student', 'Gender', 'Item', 'Category',
            'Rank', 'Mark', 'Grade',
        ], $rows, ExcelExport::generatedOnNote());
    }

    private function registrationListPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $regs = $this->activeRegistrations(
            $request->input('class_group'),
            $request->input('school_id'),
        );

        return $this->renderPdf('fest.reports.registration-list', [
            'event' => $this->event,
            'rows'  => $regs,
            ...$this->brandingData(),
        ], $this->slug().'-registration-list.pdf');
    }

    private function schoolWisePdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $marks = $this->marks(
            $request->input('school_id'),
            null,
            $request->input('class_group'),
        );

        return $this->renderPdf('fest.reports.school-wise', [
            'event'   => $this->event,
            'marks'   => $marks,
            ...$this->brandingData(),
        ], $this->slug().'-school-wise.pdf');
    }

    private function overallRankingPdf(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->renderPdf('fest.reports.overall-ranking', [
            'event'   => $this->event,
            'schools' => $this->schoolRankingRows(),
            ...$this->brandingData(),
        ], $this->slug().'-overall-ranking.pdf');
    }

    /**
     * Category totals only -- one column per category (its Sub total) plus OVERALL, no
     * per-item breakdown at all. schoolItemPointsMatrix() already computes every number
     * this needs (category_totals, overall); this just re-shapes the same data into a
     * narrower table that fits comfortably in portrait instead of the item-level
     * matrix's wide landscape sheet. Excluded-from-overall categories are left out
     * entirely, same reasoning/consistency as categoryItemMatrixXls()/Pdf() above.
     *
     * @return array{categories: list<array<string, mixed>>, rows: list<list<mixed>>}
     */
    private function categoryTotalsData(FestEventReportAnalyticsService $analytics): array
    {
        $matrix = $analytics->schoolItemPointsMatrix();
        $categories = collect($matrix['categories'])->reject(fn (array $c) => $c['excluded_from_overall'])->values()->all();

        // Rank last, not first -- this Sahodaya wants School leading the row with Rank
        // trailing after OVERALL, not the item-matrix's Rank-first layout.
        $rows = collect($matrix['schools'])->map(function (array $school) use ($categories) {
            $row = [strtoupper($school['school_name'])];
            foreach ($categories as $category) {
                $row[] = $school['category_totals'][$category['key']] ?? 0;
            }
            $row[] = $school['overall'];
            $row[] = $school['rank'];

            return $row;
        })->all();

        return ['categories' => $categories, 'rows' => $rows];
    }

    private function categoryTotalsXls(FestEventReportAnalyticsService $analytics): StreamedResponse
    {
        ['categories' => $categories, 'rows' => $rows] = $this->categoryTotalsData($analytics);

        $headers = ['School'];
        $columnStyles = [];
        foreach ($categories as $i => $category) {
            $columnStyles[count($headers)] = ExcelExport::CATEGORY_BAND_STYLES[$i % count(ExcelExport::CATEGORY_BAND_STYLES)];
            $headers[] = $category['label'];
        }
        $columnStyles[count($headers)] = 'overall';
        $headers[] = 'OVERALL';
        $headers[] = 'Rank';

        return ExcelExport::download($this->slug().'-category-totals', $headers, $rows, ExcelExport::generatedOnNote(), [], $columnStyles);
    }

    private function categoryTotalsPdf(FestEventReportAnalyticsService $analytics): \Symfony\Component\HttpFoundation\Response
    {
        ['categories' => $categories, 'rows' => $rows] = $this->categoryTotalsData($analytics);

        return $this->renderPdf('fest.reports.category-totals', [
            'event'      => $this->event,
            'categories' => $categories,
            'rows'       => $rows,
            ...$this->brandingData(),
        ], $this->slug().'-category-totals.pdf');
    }

    private function categoryItemMatrixXls(FestEventReportAnalyticsService $analytics): StreamedResponse
    {
        $matrix = $analytics->schoolItemPointsMatrix();
        // Categories the admin excluded from OVERALL (aggregation_config.
        // excluded_overall_categories) are left out of the downloaded file entirely --
        // unlike the interactive web page, which still shows them (with a † marker) for
        // full visibility. A printed/exported sheet is meant to represent the school's
        // official standing, and this Sahodaya doesn't want that category's numbers in
        // the file it hands out at all, not just excluded from the OVERALL total.
        $categories = collect($matrix['categories'])->reject(fn (array $c) => $c['excluded_from_overall'])->values()->all();

        // Flat single-row header ("CAT 1 › Head: Item Name") — a true multi-tier merged
        // header needs an ExcelExport extension this simple headers+rows API doesn't
        // have. Item-name headers are rotated (see $verticalHeaderIndices below), same
        // as the web page/PDF, so a wide combined report doesn't need one
        // impossibly-wide column per item just to fit its label horizontally.
        $headers = ['Rank', 'School'];
        $verticalHeaderIndices = [];
        // A category's items (and its own Sub column) share one alternating band colour
        // (ExcelExport::CATEGORY_BAND_STYLES) -- the same "tell categories apart at a
        // glance" cue the web page's per-category header shading gives, needed here
        // because this flat single-row header can't reproduce the web/PDF's merged
        // multi-tier category band. Sub and OVERALL get their own bold highlight
        // regardless of category, matching the PDF's .subtotal-col/.overall-col.
        $columnStyles = [];
        foreach ($categories as $catIndex => $category) {
            $bandStyle = ExcelExport::CATEGORY_BAND_STYLES[$catIndex % count(ExcelExport::CATEGORY_BAND_STYLES)];
            foreach ($category['heads'] as $head) {
                foreach ($head['items'] as $item) {
                    $gender = (! ($item['gender'] ?? null) || $item['gender'] === 'open') ? 'Mixed' : (['male' => 'Boys', 'female' => 'Girls'][$item['gender']] ?? ucfirst($item['gender']));
                    $type = in_array($item['participant_type'] ?? null, ['team', 'group', 'pair', 'trio'], true) ? 'Group' : 'Individual';
                    $columnStyles[count($headers)] = $bandStyle;
                    $verticalHeaderIndices[] = count($headers);
                    $headers[] = ($item['item_code'] ? $item['item_code'].' — '.$item['title'] : $item['title'])." · {$gender} · {$type}";
                }
            }
            // "Subtotal" alone (relying on its position right after that category's
            // items, plus the shared $bandStyle colour, for which-category context)
            // rather than the full "Category 1 — Classes 3 & 4 — Subtotal" -- an
            // unrotated header column is sized to fit its own text, so prefixing the
            // full category name here forced one enormous blank-looking column that
            // threw off the whole sheet's alignment next to the narrow rotated item
            // columns either side of it. "Subtotal" alone is short enough to stay narrow.
            $columnStyles[count($headers)] = 'sub';
            $headers[] = 'Subtotal';
        }
        $columnStyles[count($headers)] = 'overall';
        $headers[] = 'OVERALL';

        $rows = collect($matrix['schools'])->map(function (array $school) use ($categories, $analytics) {
            $row = [$school['rank'], strtoupper($school['school_name'])];
            foreach ($categories as $category) {
                foreach ($category['heads'] as $head) {
                    foreach ($head['items'] as $item) {
                        $row[] = $analytics->formatMatrixCell($school, $item['id']);
                    }
                }
                $row[] = $school['category_totals'][$category['key']] ?? 0;
            }
            $row[] = $school['overall'];

            return $row;
        });

        return ExcelExport::download($this->slug().'-category-item-matrix', $headers, $rows, ExcelExport::generatedOnNote(), $verticalHeaderIndices, $columnStyles);
    }

    private function categoryItemMatrixPdf(FestEventReportAnalyticsService $analytics): \Symfony\Component\HttpFoundation\Response
    {
        $matrix = $analytics->schoolItemPointsMatrix();
        // Same exclusion-from-the-download as categoryItemMatrixXls() above — see its
        // comment for why this deliberately differs from the interactive web page.
        $categories = collect($matrix['categories'])->reject(fn (array $c) => $c['excluded_from_overall'])->values()->all();

        // A combined (multi-phase/region) event can easily run to 100+ item columns —
        // far too wide for one printed page. paginateMatrixColumns() splits them into
        // page-sized chunks with a "(cont'd)" continuation marker; a single-phase
        // event with few enough items still comes back as one page, unchanged.
        $pages = FestEventReportAnalyticsService::paginateMatrixColumns($categories);

        return $this->renderPdf('fest.reports.category-item-matrix', [
            'event'      => $this->event,
            'pages'      => $pages,
            'schools'    => $matrix['schools'],
            'analytics'  => $analytics,
            ...$this->brandingData(),
        ], $this->slug().'-category-item-matrix.pdf', true);
    }

    /**
     * Same source and ranking as the admin Championship page and the public Results
     * "Championship" tab (FestIndividualChampionshipService) — combined across every
     * phase when this hub uses phases, since a printed/exported report is meant to be
     * the definitive final standing, not one phase's isolated slice. Unlike the public
     * tab, this is a staff report: no per-leaf visibility gating — an admin can already
     * see every phase's numbers regardless of publish state on the Championship page
     * itself, so the export matches that.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, combined: bool, displayEvent: FestEvent}
     */
    private function individualChampionshipData(): array
    {
        $service = app(FestIndividualChampionshipService::class);
        $root = $this->event->rootEvent();
        $combined = $root->usesPhasedRegionalBilling();

        return [
            'rows' => $combined ? $service->crossPhaseStanding($root) : $service->leaderboardForEvent($this->event),
            'combined' => $combined,
            // A combined report is the hub's definitive standing, not one phase's own —
            // its title should say so (the hub's name), not name whichever single leaf
            // the export happened to be requested from.
            'displayEvent' => $combined ? $root : $this->event,
        ];
    }

    private function individualChampionshipXls(): StreamedResponse
    {
        $data = $this->individualChampionshipData();
        $categoryLabels = FestClassGroupScheme::labels(null, $this->event->rootEvent());
        $genderLabels = ['male' => 'Boys', 'female' => 'Girls'];

        $headers = ['Rank', 'Student', 'School', 'Category', 'Gender', 'Points'];
        $rows = $data['rows']->map(fn (array $row) => [
            $row['rank'],
            strtoupper((string) $row['student']['name']),
            strtoupper((string) $row['school']),
            $categoryLabels[$row['category']] ?? $row['category'],
            $genderLabels[$row['gender']] ?? $row['gender'],
            $row['points'],
        ]);

        return ExcelExport::download(
            $this->slugFor($data['displayEvent']).'-individual-championship',
            $headers,
            $rows,
            ExcelExport::generatedOnNote()
        );
    }

    private function individualChampionshipPdf(): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->individualChampionshipData();
        $categoryLabels = FestClassGroupScheme::labels(null, $this->event->rootEvent());
        $genderLabels = ['male' => 'Boys', 'female' => 'Girls'];

        $groups = $data['rows']
            ->groupBy(fn (array $row) => $row['category'].'|'.$row['gender'])
            ->map(fn ($rows, string $key) => [
                'label' => ($categoryLabels[$rows->first()['category']] ?? $rows->first()['category']).' · '.($genderLabels[$rows->first()['gender']] ?? $rows->first()['gender']),
                'rows'  => $rows,
            ])
            ->values();

        return $this->renderPdf('fest.reports.individual-championship', [
            'event'    => $data['displayEvent'],
            'combined' => $data['combined'],
            'groups'   => $groups,
            ...$this->brandingData(),
        ], $this->slugFor($data['displayEvent']).'-individual-championship.pdf');
    }

    private function houseWisePdf(): \Symfony\Component\HttpFoundation\Response
    {
        $houses = FestHouse::where('event_id', $this->event->id)->with('schoolAssignments')->get();
        $board = EventContext::for($this->event)->scoreboardByHouse();

        return $this->renderPdf('fest.reports.house-wise', [
            'event'  => $this->event,
            'houses' => $houses,
            'board'  => $board,
            ...$this->brandingData(),
        ], $this->slug().'-house-wise.pdf');
    }

    private function itemListPdf(): \Symfony\Component\HttpFoundation\Response
    {
        $items = collect($this->itemRegistrationCountRows())->map(fn ($row) => (object) [
            'title'            => $row['title'],
            'head_name'        => $row['head_name'] ?? null,
            'category_label'   => $row['category_label'] ?? '—',
            'participant_type' => $row['participant_type'] ?? 'individual',
            'approved'         => $row['approved'],
            'pending'          => $row['pending'],
            'registered_count' => $row['registration_count'],
            'participants'     => $row['participant_count'],
            'school_count'     => $row['school_count'] ?? 0,
        ]);

        return $this->renderPdf('fest.reports.item-list', [
            'event' => $this->event,
            'items' => $items,
            ...$this->brandingData(),
        ], $this->slug().'-item-list.pdf');
    }

    private function itemWisePdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $itemId = $request->integer('item_id') ?: $this->items()->first()?->id;
        $topN = min(50, max(1, $request->integer('top_n') ?: 10));

        $marks = FestMark::whereIn('event_id', $this->eventIds())
            ->when($itemId, fn ($q) => $q->whereIn('item_id', $this->itemIdsFor($itemId)))
            ->with(['participant.student', 'participant.registration.school', 'item'])
            ->orderBy('position')
            ->orderByDesc('score')
            ->limit($topN)
            ->get();

        $item = FestEventItem::find($itemId);
        $categoryLabel = FestItemCategoryLabel::resolve($item, FestClassGroupScheme::labels(null, $this->event->rootEvent()));

        return $this->renderPdf('fest.reports.item-wise', [
            'event'         => $this->event,
            'item'          => $item,
            'categoryLabel' => $categoryLabel,
            'marks'         => $marks,
            'topN'          => $topN,
            ...$this->brandingData(),
        ], $this->slug().'-item-wise.pdf');
    }

    private function cumulativePdf(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->overallRankingPdf();
    }

    private function dayWisePdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $date = $request->input('date', today()->toDateString());
        $audience = $this->reportAudience($request);

        $schedules = FestSchedule::whereIn('event_id', $this->eventIds())
            ->whereDate('scheduled_at', $date)
            ->with(['item', 'participant.student', 'participant.teacher', 'participant.registration.school', 'participant.registration.item', 'participant.registration.event'])
            ->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->get();

        $rows = $schedules->map(function (FestSchedule $s) use ($audience) {
            if (! $s->participant) {
                return [
                    'time'      => $s->scheduled_at?->format('H:i'),
                    'item'      => $s->item?->title,
                    'stage'     => $s->stage,
                    'order'     => $s->sort_order,
                    'reference' => '—',
                    'name'      => null,
                    'school'    => null,
                ];
            }

            $formatted = app(FestPublicVisibilityService::class)
                ->formatReportRow($this->event, $s->participant, $audience, $s);

            return [
                'time'      => $s->scheduled_at?->format('H:i'),
                'item'      => $s->item?->title,
                'stage'     => $s->stage,
                'order'     => $s->sort_order,
                'reference' => $formatted['reference'],
                'name'      => $formatted['name'],
                'school'    => $formatted['school'],
            ];
        });

        return $this->renderPdf('fest.reports.day-wise', [
            'event'    => $this->event,
            'date'     => $date,
            'rows'     => $rows,
            'audience' => $audience,
            ...$this->brandingData(),
        ], $this->slug()."-day-{$date}.pdf");
    }

    private function attendanceSheetPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        // Off by default (chest no was dropped from this sheet entirely per organizer
        // feedback — it's just a hand-marking checklist), but some organizers still want
        // it shown, e.g. to double-check attendance against a printed chest-number list.
        $showChest = $request->boolean('show_chest');

        $bulkItemIds = $this->resolveBulkItemIds($request);
        abort_if($bulkItemIds === [], 404, 'No competition items found.');

        $participants = $this->participantsFlat(
            $request->integer('item_id') ?: null,
            $request->input('class_group'),
            $request->input('school_id'),
            null,
            null,
            false,
            $bulkItemIds ?? [],
        )
            ->filter(fn ($p) => $p->participant_role !== 'standby' && ($p->student_id || $p->teacher_id))
            ->values();

        $audience = $this->reportAudience($request);
        // HTML preview only where there is no external converter (local dev). With the
        // converter configured, preview is the converter's own PDF shown inline -- exactly
        // what the download produces -- not a separate browser-rendered approximation.
        $isPreview = $this->preview && empty(config('services.pdf_converter.url'));
        $isDomPdf = empty(config('services.pdf_converter.url'));

        // Build photo map. Embed every photo as a base64 data URI — for the PDF this
        // avoids handing the renderer an auth-gated URL it has no session cookies to
        // fetch (see photoBase64DataUri's docblock); for the on-screen preview it
        // avoids the browser firing ~200 separate authenticated <img> requests, each
        // re-running disk-existence probes (a network round trip for S3), which is
        // exactly what was making photos load slowly and a handful randomly fail —
        // classic PHP-FPM-worker/connection contention under concurrent load.
        //
        // The trade-off: embedding means this request itself does up to ~200
        // sequential S3 reads before it can respond at all. Caching each student's
        // resized thumbnail (keyed on their own updated_at) means only the very
        // first view of a given student's photo, across ANY report, pays that cost —
        // an edited photo naturally busts its own cache key since updated_at changes.
        $photoMap = [];
        foreach ($participants as $p) {
            $sid = $p->student_id;
            $student = $p->student;
            if (! $sid || isset($photoMap[$sid]) || ! $student) {
                continue;
            }

            if (! $student->photo) {
                $photoMap[$sid] = null;

                continue;
            }

            $cacheKey = 'student-photo-thumb:'.$sid.':'.($student->updated_at?->timestamp ?? 0);
            $photoMap[$sid] = \Illuminate\Support\Facades\Cache::remember(
                $cacheKey,
                now()->addDays(30),
                fn () => \App\Support\TenantStorage::photoBase64DataUri($student->tenant, $student->photo),
            );
        }

        // Build rows using participantReportRows (individual students with team metadata).
        $rows = $this->participantReportRows($participants, $audience);

        // Enrich with photo_url, dob
        $rows = array_map(function ($row) use ($photoMap) {
            $sid = $row['_student_id'] ?? null;
            $row['photo_url'] = $sid ? ($photoMap[$sid] ?? null) : null;
            return $row;
        }, $rows);

        // Group by item
        $rowsByItem = collect($rows)->groupBy(fn ($r) => $r['item'] ?? 'Item')->sortKeys();

        // Sort participants primarily by chest number (reference) numerically.
        $rowsByItem = $rowsByItem->map(fn ($itemRows) => $itemRows->sortBy([
            fn ($a, $b) => ((int) preg_replace('/[^0-9]/', '', (string) ($a['reference'] ?? '999999')))
                <=> ((int) preg_replace('/[^0-9]/', '', (string) ($b['reference'] ?? '999999'))),
            fn ($a, $b) => ($a['group_id'] ?? 0) <=> ($b['group_id'] ?? 0),
            fn ($a, $b) => ($a['school'] ?? '') <=> ($b['school'] ?? ''),
        ])->values()->all());

        $sahodaya = Tenant::find($this->event->tenant_id);
        $logo = $sahodaya ? \App\Support\TenantBranding::logoEmbedSrc($sahodaya) : null;

        // Single-item filter → header can name the item with Category, Type, Gender, and
        // participant count -- same field order/separator as
        // partials/pdf-report-heading.blade.php, so this report's header matches every
        // other Bulk Sheets report's header exactly.
        $singleItemName = null;
        $singleItemMetaStr = null;
        if ($rowsByItem->count() === 1) {
            $singleItemName = str_replace('_', ' ', (string) $rowsByItem->keys()->first());
            $firstItemRow = $rowsByItem->first()[0] ?? null;
            if ($firstItemRow) {
                $catLabel = $firstItemRow['item_category'] ?? null;
                $typeLabel = $firstItemRow['item_type'] ?? null;
                $genderLabel = $firstItemRow['item_gender'] ?? null;
                $participantCountLabel = count($rowsByItem->first()).' participant'.(count($rowsByItem->first()) === 1 ? '' : 's');
                $singleItemMetaStr = implode(' · ', array_filter([$singleItemName, $catLabel, $typeLabel, $genderLabel, $participantCountLabel]));
            }
        }

        $bladeData = [
            'event'             => $this->event,
            'sahodaya'          => $sahodaya,
            'logo'              => $logo,
            'rowsByItem'        => $rowsByItem,
            'audience'          => $audience,
            'isPreview'         => $isPreview,
            'singleItemName'    => $singleItemName,
            'singleItemMetaStr' => $singleItemMetaStr,
            // dompdf supports literal {PAGE_NUM}/{PAGE_COUNT} substitution; an external
            // Chromium-based converter (PDF_CONVERTER_URL) does not, so avoid printing
            // unresolved placeholder text on that path.
            'isDomPdf'          => $isDomPdf,
            'showChest'         => $showChest,
        ];

        // Preview mode: return raw HTML (browser handles S3 images, proper page layout)
        if ($isPreview) {
            return response(view('fest.reports.attendance-sheet', $bladeData)->render())
                ->header('Content-Type', 'text/html');
        }

        // The real PDF renderer is an external Puppeteer/Chromium service (chrome-print-
        // server.js) — pass its native repeating headerTemplate/footerTemplate instead of
        // relying on any CSS trick. Ignored by the dompdf fallback (only used locally),
        // which gets its own branding baked into the page content — see the blade file.
        // Item name/category included here as plain text (not a dark bar/badge) when this
        // is a single-item selection -- the blade's own thead-based item-heading-bar only
        // needs to fire for the multi-item case, since this header already repeats on
        // every physical page on its own.
        [$headerTemplate, $footerTemplate] = \App\Support\PdfChromeHeaderFooter::build([
            'orgName'    => $sahodaya->name ?? 'SAHODAYA',
            'logoSrc'    => $logo,
            'docTitle'   => 'ATTENDANCE SHEET',
            'eventTitle' => $this->event->title,
            'itemLine'   => $singleItemMetaStr ?: $singleItemName,
        ]);
        $itemsLabel = $singleItemName ?? ($bulkItemIds !== null ? count($bulkItemIds).'-items' : 'all-items');
        $filename = ReportFilename::build(
            'attendance-sheet',
            $sahodaya?->name ?? 'Sahodaya',
            $this->event->event_start,
            [$this->event->title, $itemsLabel],
        );

        return $this->renderPdf(
            'fest.reports.attendance-sheet',
            $bladeData,
            $filename,
            false,
            $headerTemplate,
            $footerTemplate,
            ['top' => '46mm', 'right' => '10mm', 'bottom' => '15mm', 'left' => '10mm'],
        );
    }

    /**
     * Blank hand-fill sheet for on-ground timing officials — Sl.No / Chest / Fest ID / Name
     * / Starting / Finishing / Signature, one row per participant. Distinct from
     * attendanceSheetPdf() (which records who showed up) — this records each participant's
     * actual clock time for a timed item, to be transcribed into Mark Entry's Time/Distance
     * field afterward. Deliberately no photo/DOB/class/school columns (not requested, and
     * this sheet is meant to print compactly for a stopwatch table, not a check-in desk).
     */
    private function timesheetPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $bulkItemIds = $this->resolveBulkItemIds($request);
        abort_if($bulkItemIds === [], 404, 'No competition items found.');

        $participants = $this->participantsFlat(
            $request->integer('item_id') ?: null,
            $request->input('class_group'),
            $request->input('school_id'),
            null,
            null,
            false,
            $bulkItemIds ?? [],
        )
            ->filter(fn ($p) => $p->participant_role !== 'standby' && ($p->student_id || $p->teacher_id))
            ->values();

        $audience = $this->reportAudience($request);
        // HTML preview only where there is no external converter (local dev). With the
        // converter configured, preview is the converter's own PDF shown inline -- exactly
        // what the download produces -- not a separate browser-rendered approximation.
        $isPreview = $this->preview && empty(config('services.pdf_converter.url'));
        $isDomPdf = empty(config('services.pdf_converter.url'));

        $rows = $this->participantReportRows($participants, $audience);

        $rowsByItem = collect($rows)->groupBy(fn ($r) => $r['item'] ?? 'Item')->sortKeys();

        // Same chest-number-first ordering as attendanceSheetPdf(), so the two sheets line
        // participants up identically for a ground team using both together.
        $rowsByItem = $rowsByItem->map(fn ($itemRows) => $itemRows->sortBy([
            fn ($a, $b) => ((int) preg_replace('/[^0-9]/', '', (string) ($a['reference'] ?? '999999')))
                <=> ((int) preg_replace('/[^0-9]/', '', (string) ($b['reference'] ?? '999999'))),
            fn ($a, $b) => ($a['group_id'] ?? 0) <=> ($b['group_id'] ?? 0),
            fn ($a, $b) => ($a['school'] ?? '') <=> ($b['school'] ?? ''),
        ])->values()->all());

        $sahodaya = Tenant::find($this->event->tenant_id);
        $logo = $sahodaya ? \App\Support\TenantBranding::logoEmbedSrc($sahodaya) : null;

        // Same field order/separator as partials/pdf-report-heading.blade.php -- see
        // attendanceSheetPdf()'s matching block for the full reasoning.
        $singleItemName = null;
        $singleItemMetaStr = null;
        if ($rowsByItem->count() === 1) {
            $singleItemName = str_replace('_', ' ', (string) $rowsByItem->keys()->first());
            $firstItemRow = $rowsByItem->first()[0] ?? null;
            if ($firstItemRow) {
                $catLabel = $firstItemRow['item_category'] ?? null;
                $typeLabel = $firstItemRow['item_type'] ?? null;
                $genderLabel = $firstItemRow['item_gender'] ?? null;
                $participantCountLabel = count($rowsByItem->first()).' participant'.(count($rowsByItem->first()) === 1 ? '' : 's');
                $singleItemMetaStr = implode(' · ', array_filter([$singleItemName, $catLabel, $typeLabel, $genderLabel, $participantCountLabel]));
            }
        }

        $bladeData = [
            'event'             => $this->event,
            'sahodaya'          => $sahodaya,
            'logo'              => $logo,
            'rowsByItem'        => $rowsByItem,
            'audience'          => $audience,
            'isPreview'         => $isPreview,
            'singleItemName'    => $singleItemName,
            'singleItemMetaStr' => $singleItemMetaStr,
            'isDomPdf'          => $isDomPdf,
        ];

        if ($isPreview) {
            return response(view('fest.reports.timesheet', $bladeData)->render())
                ->header('Content-Type', 'text/html');
        }

        // Item name/category included here as plain text -- see the matching comment in
        // attendanceSheetPdf().
        [$headerTemplate, $footerTemplate] = \App\Support\PdfChromeHeaderFooter::build([
            'orgName'    => $sahodaya->name ?? 'SAHODAYA',
            'logoSrc'    => $logo,
            'docTitle'   => 'TIMESHEET',
            'eventTitle' => $this->event->title,
            'itemLine'   => $singleItemMetaStr ?: $singleItemName,
        ]);
        $itemsLabel = $singleItemName ?? ($bulkItemIds !== null ? count($bulkItemIds).'-items' : 'all-items');
        $filename = ReportFilename::build(
            'timesheet',
            $sahodaya?->name ?? 'Sahodaya',
            $this->event->event_start,
            [$this->event->title, $itemsLabel],
        );

        return $this->renderPdf(
            'fest.reports.timesheet',
            $bladeData,
            $filename,
            false,
            $headerTemplate,
            $footerTemplate,
            ['top' => '46mm', 'right' => '10mm', 'bottom' => '15mm', 'left' => '10mm'],
        );
    }

    private function attendanceSheetSchoolPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        // Safety net for large schools: photo embedding below decodes every student's
        // image via GD (see TenantStorage::shrinkImageForEmbed). That was blowing the
        // default 128M limit — imagecreatefromstring on a handful of full-resolution
        // uploads is enough on its own, and this route had neither the memory bump nor
        // the result caching that the sibling attendanceSheetPdf() (admin combined
        // report) already uses. Mirrors the bump idCardsPreview() applies for the same
        // reason.
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $request->validate(['school_id' => 'required|string']);
        $school = Tenant::findOrFail($request->input('school_id'));

        $participants = $this->participantsFlat(null, null, $school->id, null, null, false);
        $studentRows = [];

        foreach ($participants as $p) {
            if (! $p->student) {
                continue;
            }
            $id = $p->student_id;
            $studentRows[$id] ??= ['student' => $p->student, 'events' => []];
            $studentRows[$id]['events'][] = [
                'event_name'   => $p->registration?->item?->title ?? '',
                'chest_number' => $p->group?->chest_no ?? $p->chest_no ?? '—',
                'fest_id'      => $p->level_registration_number ?? '—',
            ];
        }

        $items = $participants->pluck('registration.item')->filter();
        $showDob = $items->contains(fn ($item) => filled($item->age_group));
        $showClass = ! $showDob && $items->contains(
            fn ($item) => filled($item->class_group) && $item->class_group !== 'open',
        );

        // Cache each student's resized thumbnail (keyed on their own updated_at), same
        // pattern as attendanceSheetPdf() — otherwise every school download/preview
        // re-decodes every photo from scratch via GD, which is what was exhausting
        // memory. An edited photo busts its own cache key since updated_at changes.
        foreach ($studentRows as $id => $row) {
            $student = $row['student'];

            if (! $student->photo) {
                $studentRows[$id]['photo_url'] = null;
                $studentRows[$id]['dob'] = $student->dob?->format('d M Y');
                $studentRows[$id]['class'] = $student->schoolClass?->name;

                continue;
            }

            $cacheKey = 'student-photo-thumb:'.$id.':'.($student->updated_at?->timestamp ?? 0);
            $studentRows[$id]['photo_url'] = \Illuminate\Support\Facades\Cache::remember(
                $cacheKey,
                now()->addDays(30),
                fn () => \App\Support\TenantStorage::photoBase64DataUri($student->tenant, $student->photo),
            );
            $studentRows[$id]['dob'] = $student->dob?->format('d M Y');
            $studentRows[$id]['class'] = $student->schoolClass?->name;
        }

        return $this->renderPdf('fest.reports.attendance-sheet-school', [
            'event'       => $this->event,
            'school'      => $school,
            'studentRows' => $studentRows,
            'showDob'      => $showDob,
            'showClass'    => $showClass,
            ...$this->brandingData(),
        ], $this->slug()."-attendance-{$school->id}.pdf", true);
    }

    private function itemOrderPublicPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $itemId = $request->integer('item_id') ?: $this->items()->first()?->id;
        abort_unless($itemId, 422, 'Select an item.');

        $item = FestEventItem::findOrFail($itemId);
        $schedules = FestSchedule::whereIn('event_id', $this->eventIds())
            ->whereIn('item_id', $this->itemIdsFor($itemId))
            ->with(['participant.student', 'participant.teacher', 'participant.registration.school', 'participant.registration.item', 'participant.registration.event'])
            ->orderBy('sort_order')
            ->orderBy('scheduled_at')
            ->get();

        $rows = $schedules->map(function (FestSchedule $s) {
            if (! $s->participant) {
                return ['order' => $s->sort_order, 'time' => $s->scheduled_at?->format('H:i'), 'reference' => '—', 'stage' => $s->stage];
            }

            $formatted = app(FestPublicVisibilityService::class)
                ->formatReportRow($this->event, $s->participant, 'public', $s);

            return [
                'order'     => $s->sort_order ?? $formatted['order'],
                'time'      => $s->scheduled_at?->format('H:i'),
                'reference' => $formatted['reference'],
                'stage'     => $s->stage,
            ];
        });

        return $this->renderPdf('fest.reports.item-order-public', [
            'event' => $this->event,
            'item'  => $item,
            'rows'  => $rows,
            ...$this->brandingData(),
        ], $this->slug()."-item-order-{$itemId}.pdf");
    }

    private function greenRoomListPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $itemId = $request->integer('item_id') ?: null;

        $query = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $this->eventIds())
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->when($itemId, fn ($q2) => $q2->whereIn('item_id', $this->itemIdsFor($itemId))))
            ->with(['registration.item', 'registration.school', 'student', 'teacher'])
            ->orderBy('chest_no');

        $participants = $query->get();
        $rows = $participants->map(fn (FestParticipant $p) => [
            'reference' => app(FestChestNumberService::class)->participantLabel($p),
            'name'      => $p->student?->name ?? $p->teacher?->name,
            'school'    => $p->registration?->school?->name ?? Tenant::find($p->registration?->school_id)?->name,
            'item'      => $p->registration?->item?->title,
            'level_reg' => $p->level_registration_number,
            'revealed'  => (bool) $p->chest_revealed_at,
        ]);

        return $this->renderPdf('fest.reports.green-room-list', [
            'event' => $this->event,
            'rows'  => $rows,
            ...$this->brandingData(),
        ], $this->slug().'-green-room.pdf');
    }

    private function markEntryStatusCsv(): StreamedResponse
    {
        $data = $this->markEntryStatusRows();
        $rows = array_map(fn ($r) => [
            $r['title'],
            $r['class_group'] ?? '',
            $r['judges'],
            $r['participants'],
            $r['marked'],
            $r['pending'],
        ], $data);

        return ExcelExport::download($this->slug().'-mark-entry-status', [
            'Item', 'Class', 'Judges Assigned', 'Participants', 'Marked', 'Pending',
        ], $rows, ExcelExport::generatedOnNote());
    }

    private function resultsPendingCsv(): StreamedResponse
    {
        $rows = array_map(fn (array $r) => [
            $r['title'], $r['item_code'] ?? '', $r['category_label'] ?? '', $r['head_name'] ?? '',
            $r['performers'], $r['marks_entered'], $r['judges_assigned'],
        ], $this->resultsPendingRows());

        return ExcelExport::download($this->slug().'-results-pending', [
            'Item', 'Item Code', 'Category', 'Head', 'Performers', 'Marks Entered', 'Judges Assigned',
        ], $rows, ExcelExport::generatedOnNote());
    }

    private function clashesCsv(Request $request): StreamedResponse
    {
        $clashes = $this->scheduleClashRows($request->input('school_id'))['participant'];

        $esc = fn ($v) => str_replace('"', '""', (string) ($v ?? ''));

        $csv = "Student,School,Item 1,Item 1 Category,Item 1 Gender,Item 1 Type,Item 1 Stage,Item 1 Time,Item 2,Item 2 Category,Item 2 Gender,Item 2 Type,Item 2 Stage,Item 2 Time\n";
        foreach ($clashes as $c) {
            $csv .= '"'.$esc($c['student_name']).'","'.$esc($c['school_name']).'",';
            $csv .= '"'.$esc($c['event1']).'","'.$esc($c['item1_category']).'","'.$esc($c['item1_gender']).'","'.$esc($c['item1_type']).'","'.$esc($c['item1_stage']).'","'.$esc($c['item1_time']).'",';
            $csv .= '"'.$esc($c['event2']).'","'.$esc($c['item2_category']).'","'.$esc($c['item2_gender']).'","'.$esc($c['item2_type']).'","'.$esc($c['item2_stage']).'","'.$esc($c['item2_time'])."\"\n";
        }

        return response()->streamDownload(
            fn () => print($csv),
            $this->slug().'-clashes.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    private function itemScheduleCsv(Request $request): StreamedResponse
    {
        $date = $request->input('date');
        $stageId = $request->integer('stage_id') ?: null;
        $rows = $this->itemScheduleRows($date, $stageId);

        $csv = "Sl No,Item,Category,Gender,Date,Time,Venue,Stage\n";
        foreach ($rows as $i => $row) {
            $csv .= '"'.($i + 1).'",';
            $csv .= '"'.str_replace('"', '""', (string) $row['title']).'",';
            $csv .= '"'.str_replace('"', '""', (string) ($row['category_label'] ?? '')).'",';
            $csv .= '"'.str_replace('"', '""', (string) ($row['gender_label'] ?? '')).'",';
            $csv .= '"'.($row['scheduled_date'] ?? '').'",';
            $csv .= '"'.($row['scheduled_time_12h'] ?? '').'",';
            $csv .= '"'.str_replace('"', '""', (string) ($row['venue'] ?? '')).'",';
            $csv .= '"'.str_replace('"', '""', (string) ($row['stage'] ?? ''))."\"\n";
        }

        return response()->streamDownload(
            fn () => print($csv),
            $this->slug().'-item-schedule.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    private function itemSchedulePdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $date = $request->input('date');
        $stageId = $request->integer('stage_id') ?: null;

        return $this->renderPdf('fest.reports.item-schedule', [
            'event'   => $this->event,
            'date'    => $date,
            'rows'    => $this->itemScheduleRows($date, $stageId),
            'summary' => $this->itemScheduleSummary(),
            ...$this->brandingData(),
        ], $this->slug().'-item-schedule.pdf');
    }

    private function clashesSchoolPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $request->validate(['school_id' => 'required|string']);
        $school = Tenant::findOrFail($request->input('school_id'));
        $conflicts = (new FestScheduleConflictService($this->event))->detectAll($school->id);

        return $this->renderPdf('fest.reports.clash-school', [
            'event'     => $this->event,
            'school'    => $school,
            'conflicts' => $conflicts,
            ...$this->brandingData(),
        ], $this->slug()."-clash-{$school->id}.pdf");
    }

    // Event-wide clashes PDF for the Sahodaya-admin schedule-clashes report — both tables
    // (participant + stage), optionally narrowed to one school, unlike clashesSchoolPdf()
    // above which is the school-admin single-school/participant-only variant.
    private function scheduleClashesPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $schoolId = $request->input('school_id');
        $rows = $this->scheduleClashRows($schoolId);

        return $this->renderPdf('fest.reports.schedule-clashes', [
            'event'      => $this->event,
            'school'     => $schoolId ? Tenant::find($schoolId) : null,
            'participant' => $rows['participant'],
            'stage'      => $rows['stage'],
            ...$this->brandingData(),
        ], $this->slug().'-schedule-clashes.pdf', true);
    }

    private function promotionsCsv(): StreamedResponse
    {
        $quals = FestQualification::whereIn('event_id', $this->eventIds())
            ->with(['participant.student', 'participant.registration.school', 'participant.registration.item', 'nextLevelEvent'])
            ->get();

        $csv = "Item,Student,School,Promoted To,Date\n";
        foreach ($quals as $q) {
            $csv .= '"'.($q->participant?->registration?->item?->title ?? '').'","'
                .($q->participant?->student?->name ?? '').'","'
                .($q->participant?->registration?->school?->name ?? '').'","'
                .($q->nextLevelEvent?->title ?? '').'","'
                .$q->promoted_at?->format('Y-m-d')."\"\n";
        }

        return response()->streamDownload(
            fn () => print($csv),
            $this->slug().'-promotions.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    private function promotionsPdf(): \Symfony\Component\HttpFoundation\Response
    {
        $quals = FestQualification::whereIn('event_id', $this->eventIds())
            ->with(['participant.student', 'participant.registration.school', 'participant.registration.item', 'nextLevelEvent'])
            ->get();

        return $this->renderPdf('fest.reports.promotion-sheet', [
            'event'  => $this->event,
            'quals'  => $quals,
            ...$this->brandingData(),
        ], $this->slug().'-promotions.pdf');
    }

    private function certificateCountsCsv(?string $onlySchoolId = null): StreamedResponse
    {
        $schoolIds = $onlySchoolId ? collect([$onlySchoolId]) : $this->schools()->pluck('id');
        $rows = [];

        foreach ($schoolIds as $schoolId) {
            $name = Tenant::where('id', $schoolId)->value('name');
            $partIds = FestParticipant::whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $this->eventIds())
                ->where('school_id', $schoolId))->pluck('id');

            $certs = Certificate::where('entity_type', FestParticipant::class)
                ->whereIn('entity_id', $partIds)
                ->count();

            $marks = FestMark::whereIn('event_id', $this->eventIds())
                ->whereHas('participant.registration', fn ($q) => $q->where('school_id', $schoolId))
                ->get();

            $rows[] = [
                $name,
                $marks->whereIn('grade', ['A', 'A+'])->count(),
                $marks->where('grade', 'B')->count(),
                $certs,
            ];
        }

        return ExcelExport::download($this->slug().'-certificate-counts', [
            'School', 'A/A+ Results', 'B Results', 'Certificates Issued',
        ], $rows, ExcelExport::generatedOnNote());
    }

    private function cateringCsv(): StreamedResponse
    {
        $orders = FestCateringOrder::where('event_id', $this->event->id)
            ->orderBy('meal_date')
            ->get();

        $schoolNames = Tenant::whereIn('id', $orders->pluck('school_id'))->pluck('name', 'id');

        $rows = $orders->map(fn ($o) => [
            $schoolNames[$o->school_id] ?? $o->school_id,
            $o->meal_date?->format('Y-m-d') ?? '',
            $o->meal_type ?? '',
            $o->head_count,
            $o->status,
            $o->notes ?? '',
        ]);

        return ExcelExport::download($this->slug().'-catering', [
            'School', 'Date', 'Meal', 'Heads', 'Status', 'Notes',
        ], $rows, ExcelExport::generatedOnNote());
    }

    private function studentsCsv(): StreamedResponse
    {
        $schoolIds = Tenant::where('parent_id', $this->event->tenant_id)
            ->where('type', 'school')
            ->pluck('id');

        $students = Student::whereIn('tenant_id', $schoolIds)->active()->orderBy('name')->get();
        $schoolNames = Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');

        $rows = $students->map(fn (Student $s) => [
            $s->reg_no ?? $s->admission_number ?? '',
            $s->name,
            $s->gender ?? '',
            $s->dob?->format('Y-m-d') ?? '',
            $s->class_label,
            $schoolNames[$s->tenant_id] ?? '',
            $s->status,
        ]);

        return ExcelExport::download($this->slug().'-students', [
            'Reg No', 'Name', 'Gender', 'DOB', 'Class', 'School', 'Status',
        ], $rows, ExcelExport::generatedOnNote());
    }

    public function downloadAdmitCards(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return $this->admitCardsPdf($request);
    }

    private function admitCardsPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $participants = $this->participantsFlat(
            null,
            $request->input('class_group'),
            $request->input('school_id'),
            $request->integer('student_id') ?: null,
            $request->integer('teacher_id') ?: null,
            true,
        );

        return $this->renderPdf('fest.reports.admit-cards', [
            'event'        => $this->event,
            'participants' => $participants,
            'showChestNo'  => ! $this->hideChestNo,
            ...$this->brandingData(),
        ], $this->slug().'-admit-cards.pdf');
    }

    private function sahodayaRankingPdf(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->renderPdf('fest.reports.overall-ranking', [
            'event'   => $this->event,
            'schools' => $this->schoolRankingRows(),
            'title'   => 'Sahodaya School Ranking',
            ...$this->brandingData(),
        ], $this->slug().'-sahodaya-ranking.pdf');
    }

    private function studentParticipationXls(Request $request): StreamedResponse
    {
        $participants = $this->participantsFlat(
            null,
            $request->input('class_group'),
            $request->input('school_id'),
        );

        $rows = $participants->map(function (FestParticipant $p) {
            return [
                $p->student?->reg_no ?? $p->student?->admission_number ?? '',
                $p->student?->name ?? $p->teacher?->name ?? '',
                $p->registration?->school?->name ?? '',
                $p->registration?->item?->title ?? '',
                $p->registration?->item?->class_group ?? '',
                $p->chest_no ?? '',
                $p->level_registration_number ?? '',
            ];
        });

        return ExcelExport::download($this->slug().'-student-participation', [
            'Reg No', 'Name', 'School', 'Item', 'Class Group', 'Chest No', 'Level Reg No',
        ], $rows, ExcelExport::generatedOnNote());
    }

    public function teamManagersData(?string $schoolId = null): Collection
    {
        $schoolId = $this->scopedSchoolId($schoolId);

        $participatingSchoolIds = FestRegistration::whereIn('event_id', $this->eventIds())
            ->when($this->scope?->isActorRestricted, fn ($q) => $q->whereIn('school_id', $this->scope->schoolIds))
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->active()
            ->pluck('school_id')
            ->unique();

        $schools = Tenant::whereIn('id', $participatingSchoolIds)
            ->orderBy('name')
            ->get(['id', 'name', 'school_prefix', 'application_payload']);

        $managers = \App\Models\FestSchoolTeamManager::whereIn('event_id', $this->eventIds())
            ->get()
            ->keyBy('school_id');

        // Deliberately narrower than uniqueParticipantCategoryReport()'s own active() scope
        // (which also counts proof_uploaded/pending_proof/partial registrations) — a team
        // manager's headcount should reflect registrations that are either fully approved
        // or at least submitted, not ones still stuck earlier in the fee/proof workflow.
        $uniqueCounts = $this->approvedOrSubmittedUniqueStudentCountsBySchool($schoolId);

        return $schools->map(function ($school) use ($managers, $uniqueCounts) {
            $m = $managers->get($school->id);

            $managerName1  = $m?->manager_name_1;
            $managerPhone1 = $m?->manager_phone_1;
            $managerEmail1 = $m?->manager_email_1;
            $managerRole1  = $m?->manager_role_1;

            // Most schools have not filled in a dedicated team manager yet — rather than
            // leaving the row blank, fall back to the Events Coordinator already on file
            // from the school's own membership application, so there is still someone to
            // call. Only kicks in when NEITHER a name nor a phone was entered, so a manager
            // row that's mid-filled-in is never overwritten.
            if (blank($managerName1) && blank($managerPhone1)) {
                if ($coordinator = SchoolEventCoordinator::forSchool($school)) {
                    $managerName1  = $coordinator['name'];
                    $managerPhone1 = $coordinator['phone'];
                    $managerEmail1 = $coordinator['email'];
                    $managerRole1  = 'Events Coordinator (on file)';
                }
            }

            return (object) [
                'school_id'            => $school->id,
                'school_name'          => $school->name,
                'school_prefix'        => $school->school_prefix ?? '',
                'unique_student_count' => $uniqueCounts[$school->id] ?? 0,
                'manager_name_1'       => $managerName1 ?? '',
                'manager_phone_1'      => $managerPhone1 ?? '',
                'manager_email_1'      => $managerEmail1 ?? '',
                'manager_role_1'       => $managerRole1 ?? '',
                'manager_name_2'       => $m?->manager_name_2 ?? '',
                'manager_phone_2'      => $m?->manager_phone_2 ?? '',
                'manager_email_2'      => $m?->manager_email_2 ?? '',
                'manager_role_2'       => $m?->manager_role_2 ?? '',
                'notes'                => $m?->notes ?? '',
            ];
        });
    }

    /**
     * Per-school unique student counts for the Students column next to each Team Manager —
     * a student is counted once even if registered for several items, and only a
     * registration that is 'approved' or 'submitted' (the school has actually put it in)
     * counts, not one still stuck earlier in the fee/proof workflow (proof_uploaded,
     * pending_proof, partial) — narrower than FestRegistration::active(), which counts
     * those too.
     *
     * @return array<string, int> school_id => count
     */
    private function approvedOrSubmittedUniqueStudentCountsBySchool(?string $schoolId): array
    {
        $participants = FestParticipant::query()
            ->whereHas('registration', function ($q) use ($schoolId) {
                $q->whereIn('event_id', $this->eventIds())
                    ->whereIn('status', ['approved', 'submitted'])
                    ->when($this->scope?->isActorRestricted, fn ($q2) => $q2->whereIn('school_id', $this->scope->schoolIds))
                    ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId));
            })
            ->with('registration:id,school_id')
            ->get(['id', 'registration_id', 'student_id', 'teacher_id']);

        $bySchool = [];
        foreach ($participants as $p) {
            $studentEntityId = $p->student_id ?: ($p->teacher_id ? 't:'.$p->teacher_id : null);
            if (! $studentEntityId) {
                continue;
            }

            $sId = (string) ($p->registration?->school_id ?? 'unknown');
            $bySchool[$sId][$studentEntityId] = true;
        }

        return array_map('count', $bySchool);
    }

    private function teamManagersXls(Request $request): StreamedResponse
    {
        $data = $this->teamManagersData($request->input('school_id'));

        $rows = $data->map(fn ($r) => [
            mb_strtoupper($r->school_name),
            $r->unique_student_count,
            $r->manager_name_1,
            $r->manager_phone_1,
            $r->manager_email_1,
            $r->manager_role_1,
            $r->manager_name_2,
            $r->manager_phone_2,
            $r->manager_email_2,
            $r->manager_role_2,
            $r->notes,
        ]);

        return ExcelExport::download($this->slug().'-team-managers', [
            'School Name', 'Unique Students',
            'Manager 1 Name', 'Manager 1 Phone', 'Manager 1 Email', 'Manager 1 Role',
            'Manager 2 Name', 'Manager 2 Phone', 'Manager 2 Email', 'Manager 2 Role',
            'Notes',
        ], $rows, ExcelExport::generatedOnNote());
    }

    private function teamManagersPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->teamManagersData($request->input('school_id'));
        $isDomPdf = empty(config('services.pdf_converter.url'));

        $bladeData = [
            'event'    => $this->event,
            'schools'  => $data,
            'isDomPdf' => $isDomPdf,
            // The preview render never goes through PdfChromeHeaderFooter (it returns
            // the Blade view's own HTML directly, below) even when the Chromium
            // converter is otherwise configured -- so the view's in-page title/branding
            // must still show for a preview, not just for the dompdf fallback.
            'preview'  => $this->preview && $isDomPdf,
            ...$this->brandingData(),
        ];

        // Same fast-path as attendanceSheetPdf()/timesheetPdf() — skip the external
        // Puppeteer round-trip for an on-screen preview, render the Blade view directly.
        if ($this->preview && $isDomPdf) {
            return response(view('fest.reports.team-managers', $bladeData)->render())
                ->header('Content-Type', 'text/html');
        }

        // Branding + page numbering repeating on every page (this report regularly runs
        // to 5+ pages for a large Sahodaya) needs Chromium's own header/footer iframe —
        // a plain in-page header only ever renders once, at the top of page 1 (dompdf's
        // position:fixed trick, used for the "Generated on" timestamp below, doesn't
        // extend to arbitrary content like a logo/title block). Ignored by the dompdf
        // fallback, which keeps the in-page header from the Blade view itself instead —
        // see its own $isDomPdf check.
        [$headerTemplate, $footerTemplate] = \App\Support\PdfChromeHeaderFooter::build([
            'orgName'    => $bladeData['orgName'],
            'logoSrc'    => $bladeData['logoSrc'],
            'docTitle'   => 'School Team Managers',
            'eventTitle' => $this->event->title,
        ]);

        return $this->renderPdf(
            'fest.reports.team-managers',
            $bladeData,
            $this->slug().'-team-managers.pdf',
            false,
            $headerTemplate,
            $footerTemplate,
        );
    }

    /**
     * A physical sign-in sheet for the registration desk -- same school/manager rows
     * as teamManagersPdf(), but the student count is left blank for the desk to fill in
     * by hand (rather than trusting the system count, which may be stale by the time the
     * team physically arrives) and a signature column is added for the team manager to
     * sign against.
     */
    private function teamManagersRegistrationSheetPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->teamManagersData($request->input('school_id'));
        $isDomPdf = empty(config('services.pdf_converter.url'));

        $bladeData = [
            'event'    => $this->event,
            'schools'  => $data,
            'isDomPdf' => $isDomPdf,
            'preview'  => $this->preview && $isDomPdf,
            ...$this->brandingData(),
        ];

        if ($this->preview && $isDomPdf) {
            return response(view('fest.reports.team-managers-registration-sheet', $bladeData)->render())
                ->header('Content-Type', 'text/html');
        }

        [$headerTemplate, $footerTemplate] = \App\Support\PdfChromeHeaderFooter::build([
            'orgName'    => $bladeData['orgName'],
            'logoSrc'    => $bladeData['logoSrc'],
            'docTitle'   => 'Team Manager Registration Sheet',
            'eventTitle' => $this->event->title,
        ]);

        return $this->renderPdf(
            'fest.reports.team-managers-registration-sheet',
            $bladeData,
            $this->slug().'-team-managers-registration-sheet.pdf',
            true,
            $headerTemplate,
            $footerTemplate,
        );
    }
}
