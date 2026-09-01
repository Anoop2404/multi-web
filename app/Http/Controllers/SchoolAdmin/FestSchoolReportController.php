<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Support\CsvSafety;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestQualification;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsFestIdCardResponses;
use App\Services\Events\FestParticipationLimitService;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestIdCardService;
use App\Services\Events\FestRegistrationRegisterService;
use App\Services\Events\FestReportService;
use App\Services\Events\FestSchoolReportExportService;
use App\Services\Events\FestSchoolReportAnalyticsService;
use App\Services\Events\FestEventReportAnalyticsService;
use App\Services\Events\FestHeadItemNavigationService;
use App\Services\Events\FestItemHeadService;
use App\Services\School\SchoolDocumentDownloadGateService;
use App\Support\FestReportCatalog;
use App\Support\ExcelExport;
use App\Support\FestClassGroupScheme;
use App\Support\ProgramRouteMap;
use App\Support\SchoolFestProgram;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use App\Support\FestEventMeta;
use App\Support\FestTeamSquadRules;
use Illuminate\Http\Request;

class FestSchoolReportController extends SchoolAdminController
{
    use BuildsFestIdCardResponses;

    public function reportsHub()
    {
        $programs = collect(\App\Support\ProgramRouteMap::FEST_PROGRAMS)
            ->map(fn ($meta) => ['slug' => $meta['slug'], 'label' => $meta['label']])
            ->values()
            ->all();

        return $this->inertia('School/Events/ReportsHub', [
            'school'   => $this->school->only('id', 'name'),
            'programs' => $programs,
        ]);
    }

    public function qualifiers(Request $request, string $tenantId, string $program = 'kalotsav')
    {
        $meta = SchoolFestProgram::meta($program);
        $program = $meta['slug'];
        $eventType = $meta['eventType'];

        $qualifications = \App\Models\FestQualification::query()
            ->whereHas('participant.registration', fn ($q) => $q->where('school_id', $this->school->id))
            ->whereHas('event', fn ($q) => $q
                ->where('tenant_id', $this->school->parent_id)
                ->where('event_type', $eventType))
            ->with([
                'event:id,title,level_round',
                'item:id,title',
                'participant.student:id,name,reg_no',
                'participant.teacher:id,name,reg_no',
                'nextLevelEvent:id,title,level_round,status',
            ])
            ->latest('promoted_at')
            ->get()
            ->map(fn ($q) => [
                'from_event'  => $q->event?->title,
                'from_level'  => $q->event?->level_round,
                'item'        => $q->item?->title,
                'participant' => $q->participant?->student?->name ?? $q->participant?->teacher?->name,
                'reg_no'      => $q->participant?->student?->reg_no ?? $q->participant?->teacher?->reg_no,
                'next_event'  => $q->nextLevelEvent?->title,
                'next_level'  => $q->nextLevelEvent?->level_round,
                'next_status' => $q->nextLevelEvent?->status,
                'promoted_at' => $q->promoted_at?->toIso8601String(),
            ]);

        return $this->inertia('School/Events/Qualifiers', [
            'program'        => $program,
            'programMeta'    => $meta,
            'school'         => $this->school->only('id', 'name'),
            'qualifications' => $qualifications,
        ]);
    }

    public function index(Request $request, string $tenantId, string $program = 'kalotsav')
    {
        $meta = SchoolFestProgram::meta($program);
        $program = $meta['slug'];
        $eventType = $meta['eventType'];

        $events = FestEvent::where('tenant_id', $this->school->parent_id)
            ->ofType($eventType)
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'status', 'event_start', 'event_end', 'venue', 'results_published', 'schedule_published'])
            ->map(fn (FestEvent $e) => array_merge(
                $e->only(['id', 'title', 'status', 'event_start', 'event_end', 'venue', 'results_published', 'schedule_published']),
                ['event_dates_label' => FestEventMeta::dateRangeLabel($e->event_start, $e->event_end)],
            ));

        return $this->inertia('School/Events/Reports', [
            'program'     => $program,
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'events'      => $events,
        ]);
    }

    protected function schoolItemHeadReportContext(FestEvent $event, string $program): array
    {
        $nav = app(FestHeadItemNavigationService::class)->navigationForEvent($event, $this->school->id);
        $base = $this->schoolReportsBase($program, $event);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);

        return array_merge($nav, [
            'headSummary'        => $analytics->headRegistrationSummary(),
            'headWiseReportBase' => "{$base}/head-wise",
            'headWiseExportUrl'  => "{$base}/export/head-wise-participants",
        ]);
    }

    public function eventHub(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        $this->assertProgramMatchesEvent($event, $program);

        $meta = SchoolFestProgram::meta($program);

        return $this->inertia('School/Events/ReportEventHub', array_merge(
            $this->schoolItemHeadReportContext($event, $program),
            [
                'program'     => $meta['slug'],
                'programMeta' => $meta,
                'school'      => $this->school->only('id', 'name'),
                'event'       => $event->only('id', 'title', 'status', 'event_start', 'event_end', 'venue', 'results_published', 'schedule_published'),
                'eventMeta'   => FestEventMeta::reportSnapshot($event),
            ],
        ));
    }

    protected function schoolReportsBase(string $program, FestEvent $event): string
    {
        $prefix = ProgramRouteMap::prefixFromSlug($program);

        return ProgramRouteMap::schoolBase($this->school->id, $prefix)."/reports/{$event->id}";
    }

    /**
     * EVENT_REPORTS_FIX_TODO_2026_08_14.md Milestone 2.1 / audit P0 "School report URLs do
     * not bind the program to the event type" — eventHub() and export() previously
     * verified only that the event belongs to the school's own Sahodaya, never that the
     * route's {program} segment (kalotsav/sports-meet/kids-fest/...) actually matches the
     * event's own event_type. A Kalotsav event's id could be requested through the Sports
     * Meet program URL and still resolve, mixing report layouts/exports meant for one
     * event type onto data from another. Scoped to these two entry points for now
     * (matches the audit's specific P0 evidence); rolling this out to every other method
     * in this controller is the rest of Milestone 2.1, not part of the P0 fix.
     */
    private function assertProgramMatchesEvent(FestEvent $event, string $program): void
    {
        $expectedEventType = SchoolFestProgram::meta($program)['eventType'];

        abort_unless($event->event_type === $expectedEventType, 404, 'This report is not available for that event type.');
    }

    public function participation(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $service = new FestParticipationLimitService($event);
        $usage = $service->usageForSchool($this->school->id);

        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportParticipation', [
            'program' => $program,
            'school'  => $this->school->only('id', 'name'),
            'event'   => $event->only('id', 'title'),
            'used'    => $usage['used'],
            'limits'  => $usage['limits'],
            'pdfUrl'  => "{$base}/participation/pdf",
            'csvUrl'  => "{$base}/participation/export",
        ]);
    }

    /**
     * Limits are per-student for the whole fest, not per-phase — a student's on-stage /
     * off-stage / individual / group counts must be tallied across every phase and
     * region under the root event, regardless of which phase's report page the school
     * opened. FestParticipationLimitService's own scopeEventIds() only widens to the
     * full tree for the root event itself (or a 'partitioned' child), so construct the
     * service against event->rootEvent() here rather than the route-resolved $event —
     * reportableEventIds() on the root always returns [root, ...children, ...grandchildren].
     */
    public function studentLimits(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $service = new FestParticipationLimitService($event->rootEvent());
        $category = $request->input('category') ?: null;
        $itemId = $request->filled('item_id') ? (int) $request->input('item_id') : null;
        $rows = $service->studentLimitReportRows($this->school->id, $request->input('search'), $category, $itemId, photoForSchoolAdmin: true);
        $summary = $service->summarizeStudentLimitRows($rows);

        $base = $this->schoolReportsBase($program, $event);
        $exportParams = http_build_query(array_filter(['category' => $category, 'item_id' => $itemId]));

        return $this->inertia('School/Events/ReportStudentLimits', [
            'program' => $program,
            'school'  => $this->school->only('id', 'name'),
            'event'   => $event->only('id', 'title'),
            'rows'    => $rows,
            'summary' => $summary,
            'categories'     => \App\Support\FestClassGroupScheme::labels(null, $event->rootEvent()),
            'items'          => $service->itemFilterOptions(),
            'filterCategory' => $category,
            'filterItemId'   => $itemId,
            'csvUrl'  => "{$base}/student-limits/export".($exportParams ? "?{$exportParams}" : ''),
            'pdfUrl'  => "{$base}/student-limits/pdf".($exportParams ? "?{$exportParams}" : ''),
        ]);
    }

    /** Same whole-fest scoping as studentLimits() above — see its docblock. */
    public function exportStudentLimitsPdf(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $service = new FestParticipationLimitService($event->rootEvent());
        $rows = $service->studentLimitReportRows(
            $this->school->id,
            $request->input('search'),
            $request->input('category') ?: null,
            $request->filled('item_id') ? (int) $request->input('item_id') : null,
            includePhotoDataUri: true,
            photoForSchoolAdmin: true,
        );
        $summary = $service->summarizeStudentLimitRows($rows);

        $reportService = app(FestReportService::class, ['event' => $event]);
        $reportService->preview = $request->boolean('inline') || $request->boolean('preview')
            || (! $request->boolean('download') && ! $request->has('download'));

        return $reportService->renderPdf('fest.reports.student-limits', [
            'event'   => $event,
            'rows'    => $rows,
            'summary' => $summary,
            ...$reportService->brandingData(),
        ], \Illuminate\Support\Str::slug($event->title).'-student-limits-report.pdf');
    }

    /** Same whole-fest scoping as studentLimits() above — see its docblock. */
    public function exportStudentLimits(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $service = new FestParticipationLimitService($event->rootEvent());
        $rows = $service->studentLimitReportRows(
            $this->school->id,
            $request->input('search'),
            $request->input('category') ?: null,
            $request->filled('item_id') ? (int) $request->input('item_id') : null,
        );

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, [
                'Reg No', 'Name',
                'On-stage used', 'On-stage limit',
                'Off-stage used', 'Off-stage limit',
                'Individual used', 'Individual limit',
                'Group used', 'Group limit',
                'Total used', 'Total limit',
                'Exceeds limit', 'Items',
            ]);
            foreach ($rows as $row) {
                $itemTitles = collect($row['items'])->pluck('item_title')->filter()->implode('; ');
                CsvSafety::fputcsv($out, [
                    $row['reg_no'] ?? '',
                    $row['name'] ?? '',
                    $row['on_stage']['used'], $row['on_stage']['limit'] ?? '',
                    $row['off_stage']['used'], $row['off_stage']['limit'] ?? '',
                    $row['individual']['used'], $row['individual']['limit'] ?? '',
                    $row['group']['used'], $row['group']['limit'] ?? '',
                    $row['total']['used'], $row['total']['limit'] ?? '',
                    $row['exceeds_any'] ? 'Yes' : 'No',
                    $itemTitles,
                ]);
            }
            fclose($out);
        }, "{$event->id}-student-limits.csv", ['Content-Type' => 'text/csv']);
    }

    public function studentWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $analytics = app(\App\Services\Events\FestEventReportAnalyticsService::class, ['event' => $event]);
        $rows = $this->stripChestNumbers($analytics->studentWiseBrowserRows($this->school->id, $request->input('search')));

        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportStudentWise', [
            'program'  => $program,
            'school'   => $this->school->only('id', 'name'),
            'event'    => $event->only('id', 'title'),
            'rows'     => $rows,
            'pdfUrl'   => "{$base}/student-wise/pdf",
            'xlsUrl'   => "{$base}/student-wise/export",
            'csvUrl'   => "{$base}/student-wise/export",
        ]);
    }

    /**
     * Batch-fetch every registration + mark for the event/school once, instead of the
     * previous per-student pattern of 3 queries each (partIds, registrations, marks) —
     * a 3000-student school triggered 9000+ queries rendering a single report. Reused
     * by both studentWise() (screen) and exportStudentWise() (CSV).
     * See docs/SCALE_AND_PAGINATION_PLAN.md §7.
     *
     * @return array{0: \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, string|null>>, 1: \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, FestMark>>}
     */
    private function studentWiseLookups(FestEvent $event): array
    {
        $eventIds = $event->reportableEventIds();

        $registrations = FestRegistration::whereIn('event_id', $eventIds)
            ->where('school_id', $this->school->id)
            ->active()
            ->with(['item:id,title', 'participants:id,registration_id,student_id'])
            ->get();

        $itemsByStudent = collect();
        $participantIdsByStudent = collect();

        foreach ($registrations as $reg) {
            foreach ($reg->participants as $participant) {
                if (! $participant->student_id) {
                    continue;
                }
                $itemsByStudent->put(
                    $participant->student_id,
                    ($itemsByStudent->get($participant->student_id) ?? collect())->push($reg->item?->title),
                );
                $participantIdsByStudent->put(
                    $participant->student_id,
                    ($participantIdsByStudent->get($participant->student_id) ?? collect())->push($participant->id),
                );
            }
        }

        $allParticipantIds = $participantIdsByStudent->flatten()->unique()->values();

        $marksByParticipant = FestMark::whereIn('event_id', $eventIds)
            ->whereIn('participant_id', $allParticipantIds)
            ->with('item:id,title')
            ->get()
            ->groupBy('participant_id');

        $marksByStudent = $participantIdsByStudent->map(
            fn ($participantIds) => $participantIds->flatMap(fn ($pid) => $marksByParticipant->get($pid) ?? collect()),
        );

        return [$itemsByStudent, $marksByStudent];
    }

    public function itemWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);

        // All items, one flat table (2026-08-25 rework) — replaces the old "pick one
        // item to see its roster" flow. No FestReportScope needed here (unlike the
        // Sahodaya-admin version): a school is always confined to its own school_id
        // regardless of phase/region, so there's no authorization boundary to enforce —
        // phase/region are pure display/filter dimensions on the client below.
        $analytics = new FestEventReportAnalyticsService($event);
        $rows = $analytics->itemWiseReportRows($this->school->id);

        $root = $event->rootEvent();
        // Filter options are derived straight from the rows' own category/category_label
        // (class category — see FestEventReportAnalyticsService::itemWiseReportRows())
        // instead of a separate taxonomy lookup, so the filter list can never drift from
        // what's actually shown in the CATEGORY column.
        $categories = collect($rows)->unique('category')->sortBy('category_label')->values()
            ->map(fn ($row) => ['key' => $row['category'], 'label' => $row['category_label']])
            ->all();

        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportItemWise', array_merge(
            $this->schoolItemHeadReportContext($event, $program),
            [
                'program'      => $meta['slug'],
                'programMeta'  => $meta,
                'school'       => $this->school->only('id', 'name'),
                'event'        => $event->only('id', 'title'),
                'rows'         => $rows,
                'categories'   => $categories,
                'usesPhases'   => $root->usesPhasedRegionalBilling(),
                'csvUrl'       => "{$base}/item-wise/export",
                'pdfUrl'       => "{$base}/item-wise/marks-pdf",
            ],
        ));
    }

    public function admitCards(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        app(SchoolDocumentDownloadGateService::class)->assertFestEventFeeForDownloads(
            $event, $this->school, $request->integer('head_id') ?: null,
        );

        $request->merge(['school_id' => $this->school->id]);

        return (new FestReportService($event))->export('admit-cards', $request);
    }

    public function teacherWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($event->event_type !== 'teacher_fest', 404);

        $teachers = \App\Models\Teacher::where('tenant_id', $this->school->id)->active()->orderBy('name')->get(['id', 'name', 'reg_no', 'designation']);

        [$itemsByTeacher, $marksByTeacher] = $this->teacherWiseLookups($event);

        $rows = $teachers->map(function ($teacher) use ($itemsByTeacher, $marksByTeacher) {
            $marks = $marksByTeacher->get($teacher->id) ?? collect();

            return [
                'teacher'       => $teacher->only(['id', 'name', 'reg_no', 'designation']),
                'registrations' => ($itemsByTeacher->get($teacher->id) ?? collect())->values(),
                'total_score'   => $marks->sum('score'),
                'results'       => $marks->map(fn ($m) => [
                    'item'     => $m->item?->title,
                    'position' => $m->position,
                    'grade'    => $m->grade,
                    'score'    => $m->score,
                ])->values(),
            ];
        });

        return $this->inertia('School/Events/ReportTeacherWise', [
            'program' => $program,
            'school'  => $this->school->only('id', 'name'),
            'event'   => $event->only('id', 'title'),
            'rows'    => $rows,
        ]);
    }

    /**
     * Teacher-side twin of studentWiseLookups() — same batch-fetch fix, same reasoning.
     * Reused by both teacherWise() (screen) and exportTeacherWise() (CSV).
     */
    private function teacherWiseLookups(FestEvent $event): array
    {
        $registrations = FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $this->school->id)
            ->active()
            ->with(['item:id,title', 'participants:id,registration_id,teacher_id'])
            ->get();

        $itemsByTeacher = collect();
        $participantIdsByTeacher = collect();

        foreach ($registrations as $reg) {
            foreach ($reg->participants as $participant) {
                if (! $participant->teacher_id) {
                    continue;
                }
                $itemsByTeacher->put(
                    $participant->teacher_id,
                    ($itemsByTeacher->get($participant->teacher_id) ?? collect())->push($reg->item?->title),
                );
                $participantIdsByTeacher->put(
                    $participant->teacher_id,
                    ($participantIdsByTeacher->get($participant->teacher_id) ?? collect())->push($participant->id),
                );
            }
        }

        $allParticipantIds = $participantIdsByTeacher->flatten()->unique()->values();

        $marksByParticipant = FestMark::whereIn('event_id', $event->reportableEventIds())
            ->whereIn('participant_id', $allParticipantIds)
            ->with('item:id,title')
            ->get()
            ->groupBy('participant_id');

        $marksByTeacher = $participantIdsByTeacher->map(
            fn ($participantIds) => $participantIds->flatMap(fn ($pid) => $marksByParticipant->get($pid) ?? collect()),
        );

        return [$itemsByTeacher, $marksByTeacher];
    }

    /**
     * Chest numbers are Sahodaya-admin-only information — schools don't see them until
     * assigned on fest day (see festDay() below, a separate, non-report page).
     * studentWiseBrowserRows() is shared with Sahodaya-admin (which does need this
     * field), so the strip happens here rather than in that shared service.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function stripChestNumbers(array $rows): array
    {
        return array_map(function (array $row) {
            if (isset($row['items']) && is_array($row['items'])) {
                $row['items'] = array_map(
                    fn (array $item) => \Illuminate\Support\Arr::except($item, ['chest_no']),
                    $row['items'],
                );
            }

            return \Illuminate\Support\Arr::except($row, ['chest_no']);
        }, $rows);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function rowsWithAdmissionNumbers(array $rows): array
    {
        $studentIds = collect($rows)
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values();

        if ($studentIds->isEmpty()) {
            return $rows;
        }

        $admissionNumbers = Student::where('tenant_id', $this->school->id)
            ->whereIn('id', $studentIds)
            ->pluck('admission_number', 'id');

        return array_map(function (array $row) use ($admissionNumbers) {
            if (isset($row['student_id']) && array_key_exists($row['student_id'], $admissionNumbers->all())) {
                $row['reg_no'] = $admissionNumbers[$row['student_id']] ?: null;
            }

            return $row;
        }, $rows);
    }

    public function exportStudentWisePdf(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $analytics = app(\App\Services\Events\FestEventReportAnalyticsService::class, ['event' => $event]);
        $rows = $this->stripChestNumbers($analytics->studentWiseBrowserRows($this->school->id, $request->input('search')));

        $reportService = app(\App\Services\Events\FestReportService::class, ['event' => $event]);

        return $reportService->renderPdf('fest.reports.student-wise', [
            'event'        => $event,
            'students'     => $rows,
            'showChestNo'  => false,
            ...$reportService->brandingData(),
        ], \Illuminate\Support\Str::slug($event->title).'-student-wise-report.pdf');
    }

    public function exportStudentWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $analytics = app(\App\Services\Events\FestEventReportAnalyticsService::class, ['event' => $event]);
        $rows = $analytics->studentWiseBrowserRows($this->school->id, $request->input('search'));

        return response()->streamDownload(function () use ($event, $rows) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['Reg / Adm No', 'Name', 'Gender', 'Item Count', 'Registered Items', 'Total Score']);
            foreach ($rows as $row) {
                $itemTitles = collect($row['items'])->pluck('item_title')->filter()->implode('; ');
                CsvSafety::fputcsv($out, [
                    $row['reg_no'] ?? '',
                    $row['name'] ?? '',
                    $row['gender'] ?? '',
                    $row['item_count'] ?? 0,
                    $itemTitles,
                    $row['total_score'] ?? 0,
                ]);
            }
            fclose($out);
        }, "{$event->id}-student-wise.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportTeacherWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($event->event_type !== 'teacher_fest', 404);

        [$itemsByTeacher, $marksByTeacher] = $this->teacherWiseLookups($event);

        return response()->streamDownload(function () use ($event, $itemsByTeacher, $marksByTeacher) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['Reg No', 'Name', 'Designation', 'Items', 'Total Score', 'Results']);
            $teachers = \App\Models\Teacher::where('tenant_id', $this->school->id)->active()->orderBy('name')->get(['id', 'name', 'reg_no', 'designation']);
            foreach ($teachers as $teacher) {
                $items = ($itemsByTeacher->get($teacher->id) ?? collect())->filter()->implode('; ');
                $marks = $marksByTeacher->get($teacher->id) ?? collect();
                $results = $marks->map(fn ($m) => ($m->item?->title ?? '').':'.($m->grade ?? $m->position ?? $m->score))->implode('; ');
                CsvSafety::fputcsv($out, [$teacher->reg_no, $teacher->name, $teacher->designation, $items, $marks->sum('score'), $results]);
            }
            fclose($out);
        }, "{$event->id}-teacher-wise.csv", ['Content-Type' => 'text/csv']);
    }

    /** All-items CSV — companion export to itemWise() above (2026-08-25 rework, replaces the old single-item export). */
    public function exportItemWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $rows = (new FestEventReportAnalyticsService($event))->itemWiseReportRows($this->school->id);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['Category', 'Item', 'Item Code', 'Phase', 'Region', 'School', 'Participant', 'Reg No', 'Fest ID', 'Item Reg', 'Chest', 'Status', 'Grade', 'Rank', 'Score']);
            foreach ($rows as $row) {
                CsvSafety::fputcsv($out, [
                    $row['category_label'], $row['item_title'], $row['item_code'],
                    $row['phase_name'], $row['region_name'], $row['school_name'],
                    $row['participant'], $row['reg_no'], $row['fest_id'], $row['item_reg'], $row['chest_no'],
                    $row['status'], $row['grade'], $row['position'], $row['score'],
                ]);
            }
            fclose($out);
        }, "{$event->id}-item-wise-report.csv", ['Content-Type' => 'text/csv']);
    }

    /** Mark entry report as PDF — chest no / grade / rank / score for the school's own registrations, stamped with who generated it and when (and an optional ?for_whom= recipient note). */
    public function itemWisePdf(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $rows = (new FestEventReportAnalyticsService($event))->itemWiseReportRows($this->school->id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('fest.reports.item-wise-marks', [
            'sahodaya'    => Tenant::find($event->tenant_id),
            'event'       => $event,
            'rows'        => $rows,
            'showPhase'   => collect($rows)->contains(fn ($r) => $r['phase_name']),
            'showRegion'  => collect($rows)->contains(fn ($r) => $r['region_name']),
            'generatedBy' => $request->user()->name ?? 'Unknown',
            'generatedAt' => now()->format('d M Y, h:i A'),
            'forWhom'     => trim((string) $request->input('for_whom', '')) ?: null,
            'logoSrc'     => \App\Support\TenantBranding::logoEmbedSrc($this->school),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("{$event->id}-mark-entry-report.pdf");
    }

    public function exportItemWisePdf(
        Request $request,
        string $tenantId,
        FestEvent $event,
        string $program,
        FestSchoolReportExportService $exports,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $itemId = $request->integer('item_id') ?: abort(422, 'Select an item.');
        $eventIds = $event->reportableEventIds();
        $item = \App\Models\FestEventItem::whereIn('event_id', $eventIds)->findOrFail($itemId);

        $rows = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $eventIds)
            ->where('school_id', $this->school->id)
            ->where('item_id', $itemId)
            ->active())
            ->with(['student.schoolClass', 'teacher', 'mark'])
            ->get()
            ->map(fn (FestParticipant $p) => [
                'participant' => $p->student?->name ?? $p->teacher?->name,
                'reg_no'      => $p->student?->admission_number ?? $p->teacher?->reg_no ?? '',
                'class'       => $p->student?->schoolClass?->name,
                'fest_id'     => $p->level_registration_number,
                'item_reg'    => $p->item_registration_number,
                'grade'       => $p->mark?->grade,
                'position'    => $p->mark?->position,
                'score'       => $p->mark?->score,
            ])
            ->all();

        return $exports->itemWiseParticipantsPdf($event, $this->school, $item, $rows);
    }

    public function exportParticipation(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $service = new FestParticipationLimitService($event);
        $usage = $service->usageForSchool($this->school->id);

        return response()->streamDownload(function () use ($event, $usage) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['Event', 'Limit type', 'Used', 'Limit']);
            foreach ($usage['used'] as $type => $count) {
                CsvSafety::fputcsv($out, [
                    $event->title,
                    $type,
                    $count,
                    $usage['limits'][$type] ?? '',
                ]);
            }
            fclose($out);
        }, "{$event->id}-participation.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportQualifiers(Request $request, string $tenantId, string $program)
    {
        $meta = SchoolFestProgram::meta($program);
        $program = $meta['slug'];
        $eventType = $meta['eventType'];

        $rows = FestQualification::query()
            ->whereHas('participant.registration', fn ($q) => $q->where('school_id', $this->school->id))
            ->whereHas('event', fn ($q) => $q
                ->where('tenant_id', $this->school->parent_id)
                ->where('event_type', $eventType))
            ->with(['event', 'item', 'participant.student', 'participant.teacher', 'nextLevelEvent'])
            ->latest('promoted_at')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['Participant', 'Reg No', 'Item', 'From event', 'From level', 'Next event', 'Next level', 'Promoted at']);
            foreach ($rows as $q) {
                CsvSafety::fputcsv($out, [
                    $q->participant?->student?->name ?? $q->participant?->teacher?->name,
                    $q->participant?->student?->reg_no ?? $q->participant?->teacher?->reg_no,
                    $q->item?->title,
                    $q->event?->title,
                    $q->event?->level_round,
                    $q->nextLevelEvent?->title,
                    $q->nextLevelEvent?->level_round,
                    $q->promoted_at?->toDateString(),
                ]);
            }
            fclose($out);
        }, "{$program}-qualifiers.csv", ['Content-Type' => 'text/csv']);
    }

    public function registrationRegister(Request $request, string $tenantId, FestEvent $event, string $program, FestRegistrationRegisterService $register)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $data = $register->build(
            $event,
            $this->school->id,
            $request->integer('page', 1),
            50,
            $request->string('head_id')->toString() ?: null,
            $request->string('item_id')->toString() ?: null,
        );

        // Chest numbers are Sahodaya-admin-only information — schools don't see them until
        // assigned on fest day. build() is shared with Sahodaya-admin (which does need
        // this field), so the strip happens here rather than in that shared service.
        $rows = $data['rows']->through(fn (array $row) => \Illuminate\Support\Arr::except($row, ['chest_no']));

        return $this->inertia('School/Events/ReportRegistrationRegister', array_merge(
            $this->schoolItemHeadReportContext($event, $program),
            [
                'program'         => $meta['slug'],
                'programMeta'     => $meta,
                'school'          => $this->school->only('id', 'name'),
                'event'           => $event->only('id', 'title', 'status', 'level_round'),
                'rows'            => $rows,
                'schoolSummary'   => $data['school_summaries'][0] ?? null,
                'totals'          => $data['totals'],
                'paymentsUrl'     => "/school-admin/{$this->school->id}/payments",
                'pdfUrl'          => $this->schoolReportsBase($program, $event).'/registration-register/pdf',
                'csvUrl'          => $this->schoolReportsBase($program, $event).'/registration-register/export',
            ],
        ));
    }

    public function exportRegistrationRegister(Request $request, string $tenantId, FestEvent $event, string $program, FestRegistrationRegisterService $register)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        return $register->exportCsv($event, $this->school->id, includeChestNo: false);
    }

    public function exportRegistrationRegisterPdf(
        Request $request,
        string $tenantId,
        FestEvent $event,
        string $program,
        FestRegistrationRegisterService $register,
        FestSchoolReportExportService $exports,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        return $exports->registrationRegisterPdf($event, $this->school, $register);
    }

    public function idCards(Request $request, string $tenantId, FestEvent $event, string $program, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        $service->hideChestNo = true;

        $meta = SchoolFestProgram::meta($program);
        $event->load(['items' => fn ($q) => $q->where('is_enabled', true)->orderBy('title')]);

        $itemCounts = $service->itemParticipantCounts($event, $this->school->id);
        $registrationCounts = $service->itemRegistrationCounts($event, $this->school->id);
        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $ageGroupLabels = config('fest_item_taxonomy.age_group', []);

        $cluster = Tenant::find($this->school->parent_id);
        $downloadGate = app(SchoolDocumentDownloadGateService::class)->payload($this->school, $event);

        return $this->inertia('School/Events/ReportIdCards', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'clusterName' => $cluster?->name ?? 'Sahodaya',
            'clusterLogoUrl' => $cluster ? TenantBranding::logoUrl($cluster) : null,
            'event'       => $event->only('id', 'title', 'status', 'event_type'),
            'items'       => $event->items->map(fn (\App\Models\FestEventItem $item) => [
                'id'                 => $item->id,
                'title'              => $item->title,
                'participant_type'   => $item->participant_type,
                'count'              => $itemCounts[$item->id] ?? 0,
                'registration_count' => $registrationCounts[$item->id] ?? 0,
                'category_label'     => $this->itemCategoryLabel($item, $classGroupLabels, $ageGroupLabels),
            ]),
            'heads'       => $service->headOptions($event, $this->school->id),
            'meta'        => $service->indexMeta($event, $this->school->id),
            'downloadGate' => $downloadGate,
        ]);
    }

    /** Which named Phase (if any) an ID-card item filter belongs to, for phase-scoped fee gating. */
    private function resolvePhaseIdForItem(?int $itemId): ?int
    {
        if (! $itemId) {
            return null;
        }

        return FestEventItem::where('id', $itemId)->value('phase_id');
    }

    public function idCardsJson(Request $request, string $tenantId, FestEvent $event, string $program, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        $service->hideChestNo = true;

        $filters = array_merge($this->idCardFilters($request), [
            'school_id'        => $this->school->id,
            'school_downloads' => true,
        ]);

        // Scoped to the item actually selected — for per-phase Kalotsavam billing, a
        // school that's fully paid Phase 1 must see Phase 1 cards even while Phase 2 is
        // still unpaid (phases are independently payable). "All items" bundles every
        // phase, so it resolves no single phase and correctly falls back to the
        // whole-event aggregate check below.
        $headId = $filters['head_id'] ?? null;
        $phaseId = $this->resolvePhaseIdForItem($filters['item_id'] ?? null);

        $downloadGate = app(SchoolDocumentDownloadGateService::class)
            ->payload($this->school, $event, null, $headId, $phaseId);

        if ($downloadGate['blocked']) {
            return response()->json(['cards' => [], 'downloadGate' => $downloadGate]);
        }

        if (($filters['scope'] ?? 'item') === 'item' && $request->input('item_id') === 'all') {
            $sections = $service->cardsGroupedByItem($event, $filters);
            $cards = collect($sections)->flatMap(fn ($section) => $section['cards'])->values()->all();

            return response()->json(['cards' => $cards, 'downloadGate' => $downloadGate]);
        }

        if (($filters['scope'] ?? 'item') === 'item' && empty($filters['item_id'])) {
            return response()->json(['cards' => [], 'message' => 'Select an item to preview cards.', 'downloadGate' => $downloadGate]);
        }

        if (($filters['scope'] ?? 'item') === 'head' && empty($filters['head_id'])) {
            return response()->json(['cards' => [], 'message' => 'Select an item head to preview cards.', 'downloadGate' => $downloadGate]);
        }

        return response()->json([
            'cards' => $service->cards($event, 'student', $filters),
            'downloadGate' => $downloadGate,
        ]);
    }

    public function idCardsPreview(Request $request, string $tenantId, FestEvent $event, string $program, FestIdCardService $service)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        $service->hideChestNo = true;

        $filters = array_merge($this->idCardFilters($request), [
            'school_id'        => $this->school->id,
            'school_downloads' => true,
        ]);

        app(SchoolDocumentDownloadGateService::class)->assertFestEventFeeForDownloads(
            $event, $this->school, $filters['head_id'] ?? null, $this->resolvePhaseIdForItem($filters['item_id'] ?? null),
        );

        $cluster = Tenant::findOrFail($this->school->parent_id);

        $service->requireStudentItem('student', $filters);

        $cards = $service->cards($event, 'student', $filters);
        $customTemplate = $this->resolveCustomIdCardTemplate($event, $filters['item_id'] ?? null, 'student');

        return view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $event,
            $cluster,
            $cards,
            'student',
            true,
            null,
            $customTemplate,
        ));
    }

    public function idCardsPdf(Request $request, string $tenantId, FestEvent $event, string $program, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        $service->hideChestNo = true;

        $filters = array_merge($this->idCardFilters($request), [
            'school_id'        => $this->school->id,
            'school_downloads' => true,
            'include_data_uris' => true,
        ]);

        app(SchoolDocumentDownloadGateService::class)->assertFestEventFeeForDownloads(
            $event, $this->school, $filters['head_id'] ?? null, $this->resolvePhaseIdForItem($filters['item_id'] ?? null),
        );

        $cluster = Tenant::findOrFail($this->school->parent_id);

        $service->requireStudentItem('student', $filters);

        $cards = $service->cards($event, 'student', $filters);
        $slug = str($event->title)->slug('-');
        $scopeSuffix = match ($filters['scope'] ?? 'item') {
            'event' => 'event-pass',
            'head'  => 'head-pass',
            default => 'student',
        };
        $customTemplate = $this->resolveCustomIdCardTemplate($event, $filters['item_id'] ?? null, 'student');

        $isDomPdf = empty(env('PDF_CONVERTER_URL'));
        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $event,
            $cluster,
            $cards,
            'student',
            false,
            null,
            $customTemplate,
            $isDomPdf,
        ))->render();

        return \App\Support\PdfGenerator::download($html, "{$slug}-{$scopeSuffix}-id-cards.pdf");
    }

    public function idCardsPdfAllHeads(Request $request, string $tenantId, FestEvent $event, string $program, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        $service->hideChestNo = true;

        app(SchoolDocumentDownloadGateService::class)->assertFestEventFeeForDownloads($event, $this->school);

        $cluster = Tenant::findOrFail($this->school->parent_id);
        $filters = [
            'school_id'        => $this->school->id,
            'school_downloads' => true,
            'include_data_uris' => true,
        ];
        $sections = collect($service->cardsGroupedByHead($event, $filters))
            ->map(fn ($section) => [
                'item_title' => $section['head_title'],
                'cards'      => $section['cards'],
            ])
            ->values()
            ->all();

        abort_if($sections === [], 422, 'No participants found for any item head.');

        $slug = str($event->title)->slug('-');
        $customTemplate = $this->resolveCustomIdCardTemplate($event, null, 'student');

        $isDomPdf = empty(env('PDF_CONVERTER_URL'));
        $cards = collect($sections)->flatMap(fn($section) => $section['cards'])->values()->all();

        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $event,
            $cluster,
            $cards,
            'student',
            false,
            null,
            $customTemplate,
            $isDomPdf,
        ))->render();

        return \App\Support\PdfGenerator::download($html, "{$slug}-all-heads-id-cards.pdf");
    }

    public function idCardsPdfAllItems(Request $request, string $tenantId, FestEvent $event, string $program, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        $service->hideChestNo = true;

        app(SchoolDocumentDownloadGateService::class)->assertFestEventFeeForDownloads($event, $this->school);

        $cluster = Tenant::findOrFail($this->school->parent_id);
        $filters = [
            'school_id'        => $this->school->id,
            'school_downloads' => true,
            'include_data_uris' => true,
        ];
        $sections = $service->cardsGroupedByItem($event, $filters);

        abort_if($sections === [], 422, 'No approved participants found for any item.');

        $slug = str($event->title)->slug('-');
        $customTemplate = $this->resolveCustomIdCardTemplate($event, null, 'student');

        $isDomPdf = empty(env('PDF_CONVERTER_URL'));
        $cards = collect($sections)->flatMap(fn($section) => $section['cards'])->values()->all();

        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $event,
            $cluster,
            $cards,
            'student',
            false,
            null,
            $customTemplate,
            $isDomPdf,
        ))->render();

        return \App\Support\PdfGenerator::download($html, "{$slug}-all-items-id-cards.pdf");
    }

    public function feeSummary(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);

        return $this->inertia('School/Events/ReportFeeSummary', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'event'       => $event->only('id', 'title', 'status'),
            'fee'         => $analytics->feeSummary(),
            'paymentsUrl' => "/school-admin/{$this->school->id}/payments",
            'xlsUrl'      => $this->schoolReportsBase($program, $event).'/export/fee-breakdown',
        ]);
    }

    public function disciplineParticipation(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);
        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportDisciplineParticipation', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'event'       => $event->only('id', 'title'),
            'rows'        => $analytics->disciplineParticipationRows(),
            'pdfUrl'      => "{$base}/discipline-participation/pdf",
            'xlsUrl'      => "{$base}/export/discipline-registration",
        ]);
    }

    public function headWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        if ($event->event_type === 'sports') {
            app(FestItemHeadService::class)->syncEventHeads($event);
        }

        $meta = SchoolFestProgram::meta($program);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);
        $headContext = $this->schoolItemHeadReportContext($event, $program);
        $headId = $request->integer('head_id') ?: null;

        $base = $this->schoolReportsBase($program, $event);
        $exportQuery = http_build_query(array_filter([
            'head_id' => $request->input('head_id'),
            'item_id' => $request->integer('item_id') ?: null,
        ]));

        return $this->inertia('School/Events/ReportHeadWise', array_merge(
            $headContext,
            [
                'program'      => $meta['slug'],
                'programMeta'  => $meta,
                'school'       => $this->school->only('id', 'name'),
                'event'        => $event->only('id', 'title', 'status', 'event_start', 'event_end', 'venue', 'results_published', 'schedule_published'),
                'eventMeta'    => FestEventMeta::reportSnapshot($event),
                'summary'      => $analytics->headRegistrationSummary(),
                'rows'         => $this->rowsWithAdmissionNumbers($analytics->headWiseParticipantRows($headId)),
                'filterHeadId' => $headId,
                'filterItemId' => $request->integer('item_id') ?: null,
                'pdfUrl'       => "{$base}/head-wise/pdf".($exportQuery ? "?{$exportQuery}" : ''),
                'xlsUrl'       => "{$base}/export/head-wise-participants".($exportQuery ? "?{$exportQuery}" : ''),
            ],
        ));
    }

    public function exportHeadWisePdf(
        Request $request,
        string $tenantId,
        FestEvent $event,
        string $program,
        FestSchoolReportExportService $exports,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        return $exports->headWisePdf($event, $this->school, $request);
    }

    public function itemCounts(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        if ($event->event_type === 'sports') {
            app(FestItemHeadService::class)->syncEventHeads($event);
        }

        $meta = SchoolFestProgram::meta($program);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);
        $rows = $analytics->schoolItemRegistrationRows();
        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportItemCounts', array_merge(
            $this->schoolItemHeadReportContext($event, $program),
            [
                'program'     => $meta['slug'],
                'programMeta' => $meta,
                'school'      => $this->school->only('id', 'name'),
                'event'       => $event->only('id', 'title', 'status', 'event_start', 'event_end', 'venue', 'results_published', 'schedule_published'),
                'eventMeta'   => FestEventMeta::reportSnapshot($event),
                'rows'        => $rows,
                'headSummary' => $analytics->headRegistrationSummary(),
                'totals'      => $analytics->itemRegistrationTotals($rows),
                'pdfUrl'      => "{$base}/item-counts/pdf",
                'xlsUrl'      => "{$base}/item-counts/export",
            ],
        ));
    }

    public function itemParticipantsJson(
        Request $request,
        string $tenantId,
        FestEvent $event,
        int $item,
        string $program,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $data = (new FestSchoolReportAnalyticsService($event, $this->school->id))
            ->itemParticipantDetails($item);

        $base = $this->schoolReportsBase($program, $event);

        return response()->json(array_merge($data, [
            'pdf_url'    => "{$base}/items/{$item}/participants/pdf",
            'export_url' => "{$base}/items/{$item}/participants/export",
        ]));
    }

    public function exportItemParticipantsPdf(
        string $tenantId,
        FestEvent $event,
        int $item,
        string $program,
        FestSchoolReportExportService $exports,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $itemModel = $event->items()->findOrFail($item);
        $rows = (new FestSchoolReportAnalyticsService($event, $this->school->id))
            ->itemParticipantDetails($item)['participants'];

        return $exports->itemWiseParticipantsPdf($event, $this->school, $itemModel, $rows);
    }

    public function exportItemParticipantsExcel(
        string $tenantId,
        FestEvent $event,
        int $item,
        string $program,
        FestSchoolReportExportService $exports,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $rows = (new FestSchoolReportAnalyticsService($event, $this->school->id))
            ->itemParticipantDetails($item)['participants'];

        return $exports->itemParticipantsExcel($event, $item, $rows);
    }

    public function exportItemCountsPdf(
        Request $request,
        string $tenantId,
        FestEvent $event,
        string $program,
        FestSchoolReportExportService $exports,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);
        $rows = $analytics->schoolItemRegistrationRows();
        $headId = $request->integer('head_id') ?: null;
        $itemId = $request->integer('item_id') ?: null;
        if ($headId || $itemId) {
            $rows = array_values(array_filter($rows, function (array $row) use ($headId, $itemId) {
                if ($headId && (int) ($row['head_id'] ?? 0) !== $headId) {
                    return false;
                }
                if ($itemId && (int) ($row['item_id'] ?? 0) !== $itemId) {
                    return false;
                }

                return true;
            }));
        }

        return $exports->itemCountsPdf(
            $event,
            $this->school,
            $analytics->headRegistrationSummary(),
            $rows,
            $analytics->itemRegistrationTotals($rows),
        );
    }

    public function exportItemCountsExcel(
        Request $request,
        string $tenantId,
        FestEvent $event,
        string $program,
        FestSchoolReportExportService $exports,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);
        $rows = $analytics->schoolItemRegistrationRows();
        $headId = $request->integer('head_id') ?: null;
        $itemId = $request->integer('item_id') ?: null;
        if ($headId || $itemId) {
            $rows = array_values(array_filter($rows, function (array $row) use ($headId, $itemId) {
                if ($headId && (int) ($row['head_id'] ?? 0) !== $headId) {
                    return false;
                }
                if ($itemId && (int) ($row['item_id'] ?? 0) !== $itemId) {
                    return false;
                }

                return true;
            }));
        }

        return $exports->itemCountsExcel($event, $rows);
    }

    public function assignmentCompleteness(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);
        $rows = $analytics->assignmentCompletenessRows();
        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportAssignmentCompleteness', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'event'       => $event->only('id', 'title'),
            'rows'        => $rows,
            'totals'      => $analytics->assignmentCompletenessTotals($rows),
            'xlsUrl'      => "{$base}/assignment-completeness/export",
        ]);
    }

    public function exportAssignmentCompleteness(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        return (new FestEventReportAnalyticsService($event))->exportAssignmentCompleteness($this->school->id);
    }

    public function numberingRegister(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);
        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportNumberingRegister', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'event'       => $event->only('id', 'title'),
            'rows'        => $analytics->numberingRegisterPaginated($request->integer('page', 1)),
            'xlsUrl'      => "{$base}/numbering-register/export",
        ]);
    }

    public function exportNumberingRegister(Request $request, string $tenantId, FestEvent $event, string $program, FestSchoolReportExportService $exports)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $rows = (new FestSchoolReportAnalyticsService($event, $this->school->id))->numberingRegisterRows();

        return $exports->numberingRegisterExcel($event, $rows);
    }

    public function pendingApprovals(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);
        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportPendingApprovals', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'event'       => $event->only('id', 'title'),
            'rows'        => $analytics->pendingApprovalPaginated($request->integer('page', 1)),
            'xlsUrl'      => "{$base}/pending-approvals/export",
        ]);
    }

    public function exportPendingApprovals(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        return (new FestEventReportAnalyticsService($event))->exportPendingApprovals($this->school->id);
    }

    public function scheduleClashes(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);
        $clashes = $analytics->scheduleClashes();

        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportScheduleClashes', array_merge(
            $this->schoolItemHeadReportContext($event, $program),
            [
                'program'     => $meta['slug'],
                'programMeta' => $meta,
                'school'      => $this->school->only('id', 'name'),
                'event'       => $event->only('id', 'title'),
                'participant' => $clashes['participant'],
                'stage'       => $clashes['stage'],
                'pdfUrl'      => "{$base}/export/clashes-school",
                'csvUrl'      => "{$base}/export/clashes",
            ],
        ));
    }

    public function itemSchedule(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $service = new FestReportService($event);
        $date = $request->input('date');
        $stageId = $request->integer('stage_id') ?: null;
        $rows = $service->itemScheduleRows($date, $stageId);
        $summary = $service->itemScheduleSummary();
        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportItemSchedule', array_merge(
            $this->schoolItemHeadReportContext($event, $program),
            [
                'program'     => $meta['slug'],
                'programMeta' => $meta,
                'school'      => $this->school->only('id', 'name'),
                'event'       => $event->only('id', 'title', 'schedule_published'),
                'rows'        => $rows,
                'summary'     => $summary,
                'stages'      => $service->scheduleStages(),
                'filters'     => ['date' => $date, 'stage_id' => $stageId],
                'pdfUrl'      => "{$base}/export/item-schedule-pdf?".http_build_query(array_filter(['date' => $date, 'stage_id' => $stageId])),
                'csvUrl'      => "{$base}/export/item-schedule?".http_build_query(array_filter(['date' => $date, 'stage_id' => $stageId])),
            ],
        ));
    }

    public function markEntryStatus(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $data = (new FestSchoolReportAnalyticsService($event, $this->school->id))->markEntryStatus();
        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportMarkEntryStatus', array_merge(
            $this->schoolItemHeadReportContext($event, $program),
            [
                'program'     => $meta['slug'],
                'programMeta' => $meta,
                'school'      => $this->school->only('id', 'name'),
                'event'       => $event->only('id', 'title'),
                'summary'     => $data['summary'],
                'rows'        => $data['rows'],
                'pdfUrl'      => "{$base}/mark-entry-status/pdf",
                'csvUrl'      => "{$base}/mark-entry-status/export",
            ],
        ));
    }

    public function exportParticipationPdf(
        Request $request,
        string $tenantId,
        FestEvent $event,
        string $program,
        FestSchoolReportExportService $exports,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $usage = (new FestParticipationLimitService($event))->usageForSchool($this->school->id);

        return $exports->participationPdf($event, $this->school, $usage['used'], $usage['limits']);
    }

    public function exportDisciplinePdf(
        Request $request,
        string $tenantId,
        FestEvent $event,
        string $program,
        FestSchoolReportExportService $exports,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $rows = (new FestSchoolReportAnalyticsService($event, $this->school->id))->disciplineParticipationRows();

        return $exports->disciplinePdf($event, $this->school, $rows);
    }

    public function exportMarkEntryStatusPdf(
        Request $request,
        string $tenantId,
        FestEvent $event,
        string $program,
        FestSchoolReportExportService $exports,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $data = (new FestSchoolReportAnalyticsService($event, $this->school->id))->markEntryStatus();

        return $exports->markEntryStatusPdf($event, $this->school, $data['rows'], $data['summary']);
    }

    public function resultsSummary(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $results = (new FestSchoolReportAnalyticsService($event, $this->school->id))->resultsSummary();

        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportResultsSummary', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'event'       => $event->only('id', 'title'),
            'results'     => $results,
            'pdfUrl'      => $event->results_published ? "{$base}/export/school-wise" : null,
        ]);
    }

    public function groupRoster(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $request->merge(['school_id' => $this->school->id]);

        return (new FestReportService($event))->export('team-squad-sheets', $request);
    }

    public function attendanceSheet(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $request->merge(['school_id' => $this->school->id]);

        return (new FestReportService($event))->export('attendance-sheet-school', $request);
    }

    public function attendance(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        if ($event->event_type === 'sports') {
            app(FestItemHeadService::class)->syncEventHeads($event);
        }

        $meta = SchoolFestProgram::meta($program);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);
        $headId = $request->integer('head_id') ?: null;
        $itemId = $request->integer('item_id') ?: null;
        $base = $this->schoolReportsBase($program, $event);
        $exportQuery = http_build_query(array_filter([
            'head_id' => $request->input('head_id'),
            'item_id' => $itemId,
        ]));

        return $this->inertia('School/Events/ReportAttendance', array_merge(
            $this->schoolItemHeadReportContext($event, $program),
            [
                'program'      => $meta['slug'],
                'programMeta'  => $meta,
                'school'       => $this->school->only('id', 'name'),
                'event'        => $event->only('id', 'title', 'status', 'event_start', 'event_end', 'venue', 'results_published', 'schedule_published'),
                'eventMeta'    => FestEventMeta::reportSnapshot($event),
                'rows'         => $this->rowsWithAdmissionNumbers($analytics->attendanceRows($headId, $itemId)),
                'filterHeadId' => $headId,
                'filterItemId' => $itemId,
                'pdfUrl'       => "{$base}/attendance-sheet".($exportQuery ? "?{$exportQuery}" : ''),
            ],
        ));
    }

    public function publishedResults(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        if ($event->event_type === 'sports') {
            app(FestItemHeadService::class)->syncEventHeads($event);
        }

        $meta = SchoolFestProgram::meta($program);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);
        $headId = $request->integer('head_id') ?: null;
        $itemId = $request->integer('item_id') ?: null;
        $base = $this->schoolReportsBase($program, $event);
        $exportQuery = http_build_query(array_filter([
            'head_id' => $request->input('head_id'),
            'item_id' => $itemId,
        ]));

        return $this->inertia('School/Events/ReportPublishedResults', array_merge(
            $this->schoolItemHeadReportContext($event, $program),
            [
                'program'      => $meta['slug'],
                'programMeta'  => $meta,
                'school'       => $this->school->only('id', 'name'),
                'event'        => $event->only('id', 'title', 'status', 'results_published', 'schedule_published'),
                'eventMeta'    => FestEventMeta::reportSnapshot($event),
                'results'      => $this->rowsWithAdmissionNumbers($analytics->publishedResultsRows($headId, $itemId)),
                'filterHeadId' => $headId,
                'filterItemId' => $itemId,
                'pdfUrl'       => "{$base}/export/school-wise".($exportQuery ? "?{$exportQuery}" : ''),
            ],
        ));
    }

    public function resultsPublishStatus(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        if ($event->event_type === 'sports') {
            app(FestItemHeadService::class)->syncEventHeads($event);
        }

        $meta = SchoolFestProgram::meta($program);
        $data = (new FestSchoolReportAnalyticsService($event, $this->school->id))->resultsPublishStatus();
        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportResultsPublishStatus', array_merge(
            $this->schoolItemHeadReportContext($event, $program),
            [
                'program'     => $meta['slug'],
                'programMeta' => $meta,
                'school'      => $this->school->only('id', 'name'),
                'event'       => $event->only('id', 'title', 'status', 'results_published'),
                'summary'     => $data['summary'],
                'rows'        => $data['rows'],
            ],
        ));
    }

    public function export(Request $request, string $tenantId, FestEvent $event, string $exportType, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        $this->assertProgramMatchesEvent($event, $program);

        $catalog = collect(FestReportCatalog::exports($this->school->parent_id, $event->id))->firstWhere('id', $exportType);
        abort_unless(is_array($catalog), 404, "Unknown report export: {$exportType}.");

        // EVENT_REPORTS_FIX_TODO_2026_08_14.md Milestone 1.1 (P0 "School users can request
        // cross-school fest exports") — exports() above is the same Sahodaya-wide catalog
        // used by the Sahodaya admin report controller; its 'audience' field means "staff
        // vs public", never "safe to show one school every other school's data". A known,
        // cataloged export id used to be enough to pass this method. Now it must ALSO be
        // on the explicit school-safe allowlist — fail closed (403), not a guess.
        abort_unless(
            FestReportCatalog::isSchoolSafe($exportType),
            403,
            "This report is not available to schools: {$exportType}."
        );

        EventLifecycleGate::allowReportLifecyclePhase(
            $event,
            $catalog['phase'] ?? 'before',
            (bool) $request->user()?->can('fest.reports.lifecycle_override'),
        );

        EventLifecycleGate::allowResultReport($event, $exportType);

        // Force the authenticated school's own id — request-supplied school_id (if any)
        // is discarded by this merge(), never trusted (Milestone 1.1: "Prevent request
        // parameters from overriding the authenticated school scope").
        $request->merge(['school_id' => $this->school->id]);

        return (new FestReportService($event))->export($exportType, $request);
    }

    public function exportMarkEntryStatus(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $data = (new FestSchoolReportAnalyticsService($event, $this->school->id))->markEntryStatus();
        $rows = array_map(fn ($r) => [
            $r['title'],
            $r['participants'],
            $r['marked'],
            $r['pending'],
            ($r['complete'] ?? false) ? 'Complete' : 'Pending',
        ], $data['rows']);

        return ExcelExport::download(
            str($event->title)->slug('-').'-mark-entry-status',
            ['Item', 'Participants', 'Marked', 'Pending', 'Status'],
            $rows,
        );
    }

    public function gamesEntryForm(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        if ($event->event_type !== 'sports') {
            return redirect()->to("/school-admin/{$this->school->id}/{$program}/events/{$event->id}/registration")
                ->with('error', 'The Games Entry Form is only available for Sports Events.');
        }

        $sahodaya = Tenant::find($this->school->parent_id);
        $sahodayaLogoUrl = $sahodaya ? TenantBranding::logoUrl($sahodaya) : null;
        $sahodayaLogoEmbed = $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null;

        // Dynamically resolve Region Name for ANY Sahodaya tenant & event structure:
        $rawRegionName = null;

        if ($event->region_id) {
            $event->loadMissing('region');
            $rawRegionName = $event->region?->name;
        }

        if (!$rawRegionName && !empty($event->cluster_label)) {
            $rawRegionName = $event->cluster_label;
        }

        if (!$rawRegionName && preg_match('/(?:REGION|ZONE|CLUSTER)\s*[\d\w\s()]+/i', $event->title, $m)) {
            $rawRegionName = trim($m[0]);
        }

        if (!$rawRegionName) {
            $assignment = \App\Models\SchoolRegionAssignment::where('school_id', $this->school->id)
                ->with('region')
                ->first();
            $rawRegionName = $assignment?->region?->name;
        }

        if ($rawRegionName) {
            if (preg_match('/\(([^)]+)\)/', $rawRegionName, $m)) {
                $clean = trim($m[1]);
            } else {
                $clean = preg_replace('/^(REGION|ZONE|CLUSTER)\s*\d*\s*[-—:]*\s*/i', '', $rawRegionName);
                $clean = trim($clean);
            }

            if (!empty($clean) && !str_contains(strtolower($clean), 'region') && !str_contains(strtolower($clean), 'zone')) {
                $regionName = $clean . ' Region';
            } else {
                $regionName = !empty($clean) ? $clean : $rawRegionName;
            }
        } else {
            $regionName = 'District';
        }

        $reportableEventIds = $event->reportableEventIds();

        // Get all registered sports items for this school in this event
        $registrations = FestRegistration::whereIn('event_id', $reportableEventIds)
            ->where('school_id', $this->school->id)
            ->active()
            ->whereHas('item', function ($q) {
                $q->where(function ($query) {
                    $query->whereNotNull('sport_discipline')
                          ->orWhere('category', 'sports')
                          ->orWhereIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES);
                });
            })
            ->with([
                'item:id,title,category,gender,age_group,sport_discipline,participant_type',
                'participants.student.schoolClass',
            ])
            ->get();

        // Group items for selection dropdown & menu navigation
        $registeredItems = $registrations->map(function ($reg) {
            $rawTitle = $reg->item?->title ?? 'Item #'.$reg->item_id;
            $count = $reg->participants ? $reg->participants->filter(fn($p) => !empty($p->student_id))->count() : 0;
            return [
                'id' => $reg->item_id,
                'title' => trim(str_replace('_', ' ', $rawTitle)),
                'category' => $reg->item?->age_group ?: ($reg->item?->category ?: 'General'),
                'gender' => strtolower($reg->item?->gender ?: 'open'),
                'discipline' => $reg->item?->sport_discipline,
                'registered_count' => $count,
            ];
        })->filter(fn ($item) => !empty($item['id']))->unique('id')->values();

        $selectedItemId = $request->integer('item_id') ?: ($registeredItems->first()['id'] ?? null);

        // Filter registrations by selected item if specified
        $filteredRegs = $selectedItemId 
            ? $registrations->where('item_id', $selectedItemId)
            : $registrations;

        $selectedItem = $selectedItemId ? \App\Models\FestEventItem::find($selectedItemId) : null;

        $studentsList = collect();
        foreach ($filteredRegs as $reg) {
            foreach ($reg->participants as $participant) {
                if (!$participant->student) continue;
                $std = $participant->student;
                $studentsList->push([
                    'id' => $std->id,
                    'name' => mb_strtoupper($std->name ?? '', 'UTF-8'),
                    'class' => $std->schoolClass?->name ?? '',
                    'udise_pen' => $std->admission_number ?: ($std->reg_no ?: ''),
                    'dob' => $std->dob ? $std->dob->format('d/m/Y') : '',
                    'father_name' => mb_strtoupper($std->parent_name ?? '', 'UTF-8'),
                    'mother_name' => '',
                    'photo_url' => $std->photo ? \App\Support\TenantStorage::assetUrl($this->school, $std->photo) : null,
                    'photo_path' => $std->photo,
                ]);
            }
        }

        $uniqueStudents = $studentsList->unique('id')->values()->all();

        $academicYear = '2026-27';
        if ($event->title && preg_match('/\d{4}-\d{2,4}/', $event->title, $m)) {
            $academicYear = $m[0];
        }

        $rawGameTitle = $selectedItem?->title ?: ($event->title ?: 'Sports Meet');
        $cleanGameName = trim(str_replace('_', ' ', $rawGameTitle));

        $formData = [
            'sahodayaName' => $sahodaya?->name ?? 'MALAPPURAM CENTRAL SAHODAYA',
            'sahodayaLogoUrl' => $sahodayaLogoUrl,
            'academicYear' => $academicYear,
            'schoolName' => $this->school->name . ($this->school->address ? ', ' . $this->school->address : ''),
            'teamManager' => '',
            'gameName' => $cleanGameName,
            'category' => $selectedItem?->age_group ?: ($selectedItem?->category ?: ''),
            'gender' => strtolower($selectedItem?->gender ?? 'open'),
            'regionName' => $regionName,
        ];

        // Direct PDF Download / Stream Response
        if ($request->boolean('download') || $request->query('export') === 'pdf' || $request->boolean('preview')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.sports-games-entry-form', array_merge($formData, [
                'sahodayaLogoUrl' => $sahodayaLogoEmbed ?: $sahodayaLogoUrl,
                'students' => $this->embedPhotosForPdf($uniqueStudents),
            ]))->setPaper('a4', 'portrait');

            $filename = \Illuminate\Support\Str::slug("Games Entry Form {$cleanGameName} {$this->school->name}") . '.pdf';

            if ($request->boolean('preview')) {
                return $pdf->stream($filename);
            }
            return $pdf->download($filename);
        }

        if ($request->boolean('raw_html')) {
            return view('reports.sports-games-entry-form', array_merge($formData, [
                'sahodayaLogoUrl' => $sahodayaLogoEmbed ?: $sahodayaLogoUrl,
                'students' => $this->embedPhotosForPdf($uniqueStudents),
            ]));
        }

        return $this->inertia('School/Events/SportsEntryForm', [
            'school' => $this->school,
            'event' => $event,
            'form' => $formData,
            'initialStudents' => array_map(fn ($s) => \Illuminate\Support\Arr::except($s, ['photo_path']), $uniqueStudents),
            'registeredItems' => $registeredItems,
            'selectedItemId' => $selectedItemId,
        ]);
    }

    /**
     * Swap each student's remote/asset `photo_url` for a base64 data URI before handing
     * the list to a DomPDF view. DomPDF's `enable_remote` is off by default (security:
     * it would otherwise let a PDF template fetch arbitrary attacker-controlled URLs at
     * render time), so an <img src="https://..."> in the games-entry-form blade view
     * silently renders as a blank/broken image — same pattern already solved for ID
     * card PDFs via TenantStorage::photoBase64DataUri() (see FestIdCardService::portraitDataUri()).
     * Falls back to the original photo_url (rather than a broken image) if the file
     * can't be found/embedded, so a lookup failure never regresses below current behavior.
     *
     * @param  array<int, array<string, mixed>>  $students
     * @return array<int, array<string, mixed>>
     */
    private function embedPhotosForPdf(array $students): array
    {
        return array_map(function (array $std) {
            if (! empty($std['photo_path'])) {
                $std['photo_url'] = \App\Support\TenantStorage::photoBase64DataUri($this->school, $std['photo_path'])
                    ?: $std['photo_url'];
            }
            unset($std['photo_path']);

            return $std;
        }, $students);
    }

    /**
     * Human-readable class/age-bracket or arts-genre label for an item, for display
     * next to the item's title in pickers. Sports events use age_group; everything
     * else uses class_group, falling back to the arts category. Null when nothing
     * more specific than the generic 'open'/'general' buckets applies.
     *
     * @param  array<string, string>  $classGroupLabels
     * @param  array<string, string>  $ageGroupLabels
     */
    private function itemCategoryLabel(\App\Models\FestEventItem $item, array $classGroupLabels, array $ageGroupLabels): ?string
    {
        if ($item->age_group && $item->age_group !== 'open') {
            return $ageGroupLabels[$item->age_group] ?? strtoupper($item->age_group);
        }

        if ($item->class_group && $item->class_group !== 'open') {
            return \App\Support\FestClassGroupScheme::resolveItemLabel($classGroupLabels, $item->class_group);
        }

        if ($item->category && $item->category !== 'general') {
            return config("fest_item_taxonomy.arts_category.{$item->category}")
                ?? ucwords(str_replace(['_', '-'], ' ', $item->category));
        }

        return null;
    }
}
