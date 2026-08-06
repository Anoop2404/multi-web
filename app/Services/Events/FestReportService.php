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
use App\Support\ExcelExport;
use App\Support\FestClassGroupScheme;
use App\Support\PdfGenerator;
use App\Support\ReportFilename;
use App\Support\TenantBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FestReportService
{
    private bool $preview = false;

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

    public function schools(): Collection
    {
        $ids = FestRegistration::whereIn('event_id', $this->event->reportableEventIds())->pluck('school_id')->unique();

        return Tenant::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    public function items(): Collection
    {
        return $this->event->items()->with('head:id,name')->orderBy('display_order')->get();
    }

    /** @return array<string, string> */
    public static function classGroups(?FestEvent $event = null): array
    {
        return FestClassGroupScheme::labels(null, $event);
    }

    public function approvedRegistrations(?string $classGroup = null, ?string $schoolId = null)
    {
        return FestRegistration::whereIn('event_id', $this->event->reportableEventIds())
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($classGroup, fn ($q) => $q->whereHas('item', fn ($i) => $i->where('class_group', $classGroup)))
            ->with(['item', 'participants.student', 'participants.teacher', 'school'])
            ->orderBy('school_id')
            ->get();
    }

    public function activeRegistrations(?string $classGroup = null, ?string $schoolId = null)
    {
        return FestRegistration::whereIn('event_id', $this->event->reportableEventIds())
            ->active()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($classGroup, fn ($q) => $q->whereHas('item', fn ($i) => $i->where('class_group', $classGroup)))
            ->with(['item', 'participants.student', 'participants.teacher', 'school'])
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
    ) {
        return FestParticipant::query()
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->whereHas('registration', function ($q) use ($itemId, $classGroup, $schoolId, $approvedOnly) {
                $q->whereIn('event_id', $this->event->reportableEventIds())
                    ->when($approvedOnly, fn ($q2) => $q2->where('status', 'approved'), fn ($q2) => $q2->active())
                    ->when($schoolId, fn ($q2) => $q2->where('school_id', $schoolId))
                    ->when($itemId, fn ($q2) => $q2->whereIn('item_id', $this->event->reportableItemIds([$itemId])))
                    ->when($classGroup, fn ($q2) => $q2->whereHas('item', fn ($i) => $i->where('class_group', $classGroup)));
            })
            ->with(['group', 'registration.event', 'registration.item.head', 'registration.school', 'student.schoolClass.classCategory', 'teacher'])
            ->orderBy('chest_no')
            ->get();
    }

    public function marks(?string $schoolId = null, ?int $itemId = null, ?string $classGroup = null)
    {
        return FestMark::whereIn('event_id', $this->event->reportableEventIds())
            ->when($itemId, fn ($q) => $q->whereIn('item_id', $this->event->reportableItemIds([$itemId])))
            ->when($classGroup, fn ($q) => $q->whereHas('item', fn ($i) => $i->where('class_group', $classGroup)))
            ->when($schoolId, fn ($q) => $q->whereHas('participant.registration', fn ($r) => $r->where('school_id', $schoolId)))
            ->with(['participant.student', 'participant.teacher', 'participant.registration.school', 'participant.registration.item', 'item'])
            ->orderBy('item_id')
            ->orderBy('position')
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public function markEntryStatusRows(?string $schoolId = null): array
    {
        $rows = [];
        foreach ($this->items() as $item) {
            $partCount = FestParticipant::whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $this->event->reportableEventIds())
                ->whereIn('item_id', $this->event->reportableItemIds([$item->id]))
                ->whereNotIn('status', ['rejected', 'withdrawn'])
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId)))->count();

            $scoredQuery = FestMark::whereIn('event_id', $this->event->reportableEventIds())
                ->whereIn('item_id', $this->event->reportableItemIds([$item->id]))
                ->where(function ($q) {
                    $q->whereNotNull('grade')->orWhereNotNull('score')->orWhereNotNull('position');
                });
            if ($schoolId) {
                $scoredQuery->whereHas('participant.registration', fn ($q) => $q->where('school_id', $schoolId));
            }
            $scored = $scoredQuery->distinct('participant_id')->count('participant_id');

            $judges = FestJudgeAssignment::whereIn('event_id', $this->event->reportableEventIds())
                ->whereIn('item_id', $this->event->reportableItemIds([$item->id]))
                ->count();

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
        return \App\Models\FestStage::whereIn('event_id', $this->event->reportableEventIds())
            ->with('venue:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'venue_id']);
    }

    public function schoolRankingRows(): Collection
    {
        $ctx = EventContext::for($this->event);
        $board = collect($ctx->scoreboardBySchool());

        $marks = FestMark::whereIn('event_id', $this->event->reportableEventIds())
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
        $this->preview = $request->boolean('preview');

        EventLifecycleGate::allowReportExport($this->event, $type, $audience);
        EventLifecycleGate::allowResultReport($this->event, $type);

        return match ($type) {
            'registrations' => $this->registrationsXls(),
            'category-wise-students' => $this->categoryWiseStudentsXls($request),
            'item-participants' => $this->itemParticipantsXls($request),
            'student-wise-report' => $this->studentWiseReportXls($request),
            'results' => $this->resultsXls(),
            'fees' => app(FestExportService::class)->fees($this->event),
            'fee-breakdown' => app(FestExportService::class)->feeBreakdown($this->event),
            'student-event-registrations' => app(FestExportService::class)->studentEventRegistrations($this->event),
            'registration-list' => $this->registrationListPdf($request),
            'school-wise' => $this->schoolWisePdf($request),
            'overall-ranking' => $this->overallRankingPdf(),
            'house-wise' => $this->houseWisePdf(),
            'item-list' => $this->itemListPdf(),
            'item-wise' => $this->itemWisePdf($request),
            'cumulative' => $this->cumulativePdf(),
            'day-wise' => $this->dayWisePdf($request),
            'attendance-sheet' => $this->attendanceSheetPdf($request),
            'attendance-sheet-school' => $this->attendanceSheetSchoolPdf($request),
            'judge-sheet' => $this->judgeSheetPdf($request),
            'mark-entry-sheet' => $this->markEntrySheetPdf($request),
            'mark-entered-summary' => $this->markEntryStatusCsv(),
            'mark-entry-status' => $this->markEntryStatusCsv(),
            'item-order-public' => $this->itemOrderPublicPdf($request),
            'green-room-list' => $this->greenRoomListPdf($request),
            'clashes' => $this->clashesCsv($request),
            'clashes-school' => $this->clashesSchoolPdf($request),
            'promotions' => $this->promotionsCsv(),
            'promotions-pdf' => $this->promotionsPdf(),
            'certificate-counts' => $this->certificateCountsCsv(),
            'catering' => $this->cateringCsv(),
            'students' => $this->studentsCsv(),
            'admit-cards' => $this->admitCardsPdf($request),
            'sahodaya-ranking' => $this->sahodayaRankingPdf(),
            'student-participation' => $this->studentParticipationXls($request),
            'discipline-registration' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->exportDisciplineRegistration(),
            'age-group-matrix' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->exportAgeGroupMatrix($request->input('school_id')),
            'fee-pending-schools' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->exportFeePendingSchools(),
            'head-wise-participants' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->exportHeadWiseParticipants(
                $request->integer('head_id') ?: null,
                $request->input('school_id'),
            ),
            'area-wise-participants' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->exportAreaWiseParticipants(
                $request->input('area_id') !== null && $request->input('area_id') !== ''
                    ? ($request->input('area_id') === 'other' ? 0 : $request->integer('area_id'))
                    : null,
                $request->input('school_id'),
            ),
            'team-squad-sheets' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->teamSquadPdf($request->input('school_id')),
            'medal-tally' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->medalTallyPdf(),
            'assignment-completeness' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->exportAssignmentCompleteness($request->input('school_id')),
            'numbering-register' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->exportNumberingRegister($request->input('school_id')),
            'pending-approvals' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->exportPendingApprovals($request->input('school_id')),
            'volunteer-roster' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->exportVolunteerRoster(),
            'catering-by-school' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->exportCateringBySchool($request->input('school_id')),
            'id-cards-by-head' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->idCardsByHeadPdf(
                $request->integer('head_id') ?: null,
                $request->input('school_id'),
                $request->input('template'),
            ),
            'audit-log-extract' => app(FestEventReportAnalyticsService::class, ['event' => $this->event])->exportAuditLogExtract(),
            'item-schedule' => $this->itemScheduleCsv($request),
            'item-schedule-pdf' => $this->itemSchedulePdf($request),
            default => abort(404, 'Unknown export type'),
        };
    }

    private function slug(): string
    {
        return str($this->event->title)->slug()->limit(40)->toString();
    }

    private function renderPdf(
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

        return collect($participants)->map(function (FestParticipant $p) use ($visibility, $audience, $schedules) {
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
                    'dob'         => $p->student?->dob?->format('d M Y'),
                    'class'       => $p->student?->schoolClass?->name,
                ],
            );
        })->all();
    }

    private function registrationsXls(): StreamedResponse
    {
        return app(FestExportService::class)->registrations($this->event);
    }

    private function resultsXls(): StreamedResponse
    {
        return app(FestExportService::class)->results($this->event);
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
                $p->level_registration_number,
            ])
            ->values()
            ->all();

        return ExcelExport::download($this->slug().'-category-wise-students', [
            'Category', 'Class', 'Reg No', 'Admission No', 'Student', 'Gender', 'DOB',
            'School', 'Item', 'Item Head', 'Chest No', 'Fest ID',
        ], $rows);
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
                $p->item_registration_number,
                $p->level_registration_number,
            ])
            ->values()
            ->all();

        return ExcelExport::download($this->slug().'-item-participants', [
            'Item Head', 'Item', 'Class Group', 'School', 'Participant', 'Reg No',
            'Class', 'Chest No', 'Item Reg No', 'Fest ID',
        ], $rows);
    }

    private function studentWiseReportXls(Request $request): StreamedResponse
    {
        $participants = $this->participantsFlat(
            null,
            null,
            $request->input('school_id'),
        )->filter(fn (FestParticipant $p) => $p->student !== null);

        $rows = $participants
            ->groupBy('student_id')
            ->map(function (Collection $entries) {
                /** @var FestParticipant $first */
                $first = $entries->first();
                $items = $entries
                    ->map(fn (FestParticipant $p) => $p->registration?->item?->title)
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    $first->registration?->school?->name,
                    $first->student?->reg_no,
                    $first->student?->admission_number,
                    $first->student?->name,
                    $first->student?->gender,
                    $first->student?->schoolClass?->name,
                    $first->student?->schoolClass?->classCategory?->label,
                    $items->count(),
                    $items->implode(', '),
                ];
            })
            ->sortBy(fn (array $row) => [$row[0] ?? '', $row[5] ?? '', $row[3] ?? ''])
            ->values()
            ->all();

        return ExcelExport::download($this->slug().'-student-wise-report', [
            'School', 'Reg No', 'Admission No', 'Student', 'Gender', 'Class',
            'Category', 'Item Count', 'Items',
        ], $rows);
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
            'class_group'      => $row['class_group'],
            'stage_type'       => $row['stage_type'] ?? null,
            'approved'         => $row['approved'],
            'pending'          => $row['pending'],
            'registered_count' => $row['registration_count'],
            'participants'     => $row['participant_count'],
            'item_reg_assigned'=> $row['item_reg_assigned'],
            'school_count'     => $row['school_count'] ?? null,
            'fee_per_item'     => $row['fee_per_item'],
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

        $marks = FestMark::whereIn('event_id', $this->event->reportableEventIds())
            ->when($itemId, fn ($q) => $q->whereIn('item_id', $this->event->reportableItemIds([$itemId])))
            ->with(['participant.student', 'participant.registration.school', 'item'])
            ->orderBy('position')
            ->orderByDesc('score')
            ->limit($topN)
            ->get();

        $item = FestEventItem::find($itemId);

        return $this->renderPdf('fest.reports.item-wise', [
            'event' => $this->event,
            'item'  => $item,
            'marks' => $marks,
            'topN'  => $topN,
            ...$this->brandingData(),
        ], $this->slug().'-item-wise.pdf');
    }

    private function cumulativePdf(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->renderPdf('fest.reports.cumulative', [
            'event'   => $this->event,
            'schools' => $this->schoolRankingRows(),
            ...$this->brandingData(),
        ], $this->slug().'-cumulative.pdf');
    }

    private function dayWisePdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $date = $request->input('date', today()->toDateString());
        $audience = $this->reportAudience($request);

        $schedules = FestSchedule::whereIn('event_id', $this->event->reportableEventIds())
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
        $participants = $this->participantsFlat(
            $request->integer('item_id') ?: null,
            $request->input('class_group'),
            $request->input('school_id'),
            null,
            null,
            false,
        )
            ->filter(fn ($p) => $p->participant_role !== 'standby' && ($p->student_id || $p->teacher_id))
            ->values();

        $audience = $this->reportAudience($request);
        $isPreview = $this->preview;
        $isDomPdf = empty(env('PDF_CONVERTER_URL'));

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

        // Sort participants: group_id (team), school, then chest_no.
        // This keeps all members of the same team together under one divider.
        $rowsByItem = $rowsByItem->map(fn ($itemRows) => $itemRows->sortBy([
            fn ($a, $b) => ($a['group_id'] ?? 0) <=> ($b['group_id'] ?? 0),
            fn ($a, $b) => ($a['school'] ?? '') <=> ($b['school'] ?? ''),
            fn ($a, $b) => ((int) preg_replace('/[^0-9]/', '', $a['reference'] ?? '0'))
                <=> ((int) preg_replace('/[^0-9]/', '', $b['reference'] ?? '0')),
        ])->values()->all());

        $sahodaya = Tenant::find($this->event->tenant_id);
        $logo = $sahodaya ? \App\Support\TenantBranding::logoEmbedSrc($sahodaya) : null;

        // Single-item filter → header can name the item; combined report → just the event name.
        $singleItemName = $rowsByItem->count() === 1
            ? str_replace('_', ' ', $rowsByItem->keys()->first())
            : null;

        $bladeData = [
            'event'          => $this->event,
            'sahodaya'       => $sahodaya,
            'logo'           => $logo,
            'rowsByItem'     => $rowsByItem,
            'audience'       => $audience,
            'isPreview'      => $isPreview,
            'singleItemName' => $singleItemName,
            // dompdf supports literal {PAGE_NUM}/{PAGE_COUNT} substitution; an external
            // Chromium-based converter (PDF_CONVERTER_URL) does not, so avoid printing
            // unresolved placeholder text on that path.
            'isDomPdf'       => $isDomPdf,
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
        [$headerTemplate, $footerTemplate] = $this->attendanceSheetHeaderFooterTemplates($sahodaya, $logo, $singleItemName);
        $filename = ReportFilename::build(
            'attendance-sheet',
            $sahodaya?->name ?? 'Sahodaya',
            $this->event->event_start,
            [$this->event->title, $singleItemName ?? 'all-items'],
        );

        return $this->renderPdf(
            'fest.reports.attendance-sheet',
            $bladeData,
            $filename,
            false,
            $headerTemplate,
            $footerTemplate,
            ['top' => '102px', 'right' => '38px', 'bottom' => '55px', 'left' => '38px'],
        );
    }

    /**
     * Build Puppeteer header/footer template HTML for the attendance sheet. These are
     * rendered by Chromium in isolation from the main page (no external/page stylesheet
     * access), so every style must be inline. `pageNumber`/`totalPages` are special
     * classes Chromium fills in automatically.
     *
     * @return array{0: string, 1: string}
     */
    private function attendanceSheetHeaderFooterTemplates(?Tenant $sahodaya, ?string $logo, ?string $singleItemName): array
    {
        $orgName = e($sahodaya->name ?? 'SAHODAYA');
        $eventTitle = e($this->event->title);
        $generated = e(now()->format('d M Y, h:i A'));
        // Mirrors the "sep"/item span in the preview's .event-context-bar
        // (see fest.reports.attendance-sheet blade, lines ~274-280) so the
        // event/item line matches what the on-screen preview shows.
        $itemLine = $singleItemName
            ? ' <span style="color:#94a3b8; padding:0 4px;">&bull;</span> <span>'.e($singleItemName).'</span>'
            : '';

        $logoImg = $logo
            ? '<img src="'.e($logo).'" style="width:34px;height:34px;object-fit:contain;margin-right:10px;">'
            : '';

        // Kept in sync by hand with partials/pdf-branding-header.blade.php +
        // the .event-context-bar block in fest.reports.attendance-sheet —
        // Chromium renders this template in isolation from the page's own
        // stylesheet/partials, so it can't simply @include them.
        $header = <<<HTML
            <div style="width:100%; font-family:Arial,sans-serif; padding:0 38px; box-sizing:border-box; border-bottom:2px solid #0f172a; padding-bottom:6px;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        {$logoImg}
                        <div>
                            <div style="font-size:14px; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:0.3px;">{$orgName}</div>
                            <div style="font-size:8px; font-weight:600; color:#475569; margin-top:2px;">CBSE Sahodaya Inter-School Competitions &amp; Events</div>
                        </div>
                    </div>
                    <div style="background:#0f172a; color:#fff; padding:4px 10px; border-radius:4px; font-size:8px; font-weight:bold; letter-spacing:0.4px; white-space:nowrap;">ATTENDANCE SHEET</div>
                </div>
                <div style="font-size:8px; color:#334155; margin-top:3px;">
                    <span style="font-weight:bold; color:#0f172a;">{$eventTitle}</span>{$itemLine}
                </div>
            </div>
            HTML;

        $footer = <<<HTML
            <div style="width:100%; font-family:Arial,sans-serif; font-size:7px; color:#64748b; padding:0 38px; box-sizing:border-box; display:flex; justify-content:space-between; border-top:1px solid #cbd5e1; padding-top:4px;">
                <span>{$orgName} &bull; {$eventTitle} &bull; Generated {$generated}</span>
                <span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>
            </div>
            HTML;

        return [$header, $footer];
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
                'chest_number' => $p->chest_no ?? '—',
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

    private function judgeSheetPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $itemId = $request->integer('item_id') ?: $this->items()->first()?->id;
        $item = FestEventItem::findOrFail($itemId);
        $audience = $this->reportAudience($request);
        $criteria = collect($item->criteria_json ?? [])->map(fn ($c, $i) => (object) [
            'name'      => is_array($c) ? ($c['name'] ?? "Criterion {$i}") : (string) $c,
            'max_marks' => is_array($c) ? ($c['max'] ?? 10) : 10,
        ]);

        $schedule = FestSchedule::whereIn('event_id', $this->event->reportableEventIds())
            ->whereIn('item_id', $this->event->reportableItemIds([$itemId]))
            ->orderBy('scheduled_at')
            ->first();

        $participants = $this->participantsFlat($itemId, null, null, null, null, false);

        return $this->renderPdf('fest.reports.judge-sheet', [
            'event'    => $this->event,
            'item'     => $item,
            'criteria' => $criteria,
            'schedule' => $schedule,
            'rows'     => $this->participantReportRows($participants, $audience),
            'audience' => $audience,
            ...$this->brandingData(),
        ], $this->slug()."-judge-{$itemId}.pdf");
    }

    private function markEntrySheetPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $itemId = $request->integer('item_id') ?: $this->items()->first()?->id;
        $item = FestEventItem::find($itemId);
        $audience = $this->reportAudience($request);
        $participants = $this->participantsFlat($itemId, null, null, null, null, false);

        $sahodaya = Tenant::find($this->event->tenant_id);

        return $this->renderPdf('fest.reports.mark-entry-sheet', [
            'event'    => $this->event,
            'item'     => $item,
            'rows'     => $this->participantReportRows($participants, $audience),
            'audience' => $audience,
            'sahodaya' => $sahodaya,
            'logoSrc'  => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
        ], $this->slug()."-mark-entry-{$itemId}.pdf");
    }

    private function itemOrderPublicPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $itemId = $request->integer('item_id') ?: $this->items()->first()?->id;
        abort_unless($itemId, 422, 'Select an item.');

        $item = FestEventItem::findOrFail($itemId);
        $schedules = FestSchedule::whereIn('event_id', $this->event->reportableEventIds())
            ->whereIn('item_id', $this->event->reportableItemIds([$itemId]))
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
            ->whereIn('event_id', $this->event->reportableEventIds())
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->when($itemId, fn ($q2) => $q2->whereIn('item_id', $this->event->reportableItemIds([$itemId]))))
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
        ], $rows);
    }

    private function clashesCsv(Request $request): StreamedResponse
    {
        $clashes = $this->scheduleClashRows($request->input('school_id'))['participant'];

        $csv = "Student,School,Item 1,Item 2,Clash Time\n";
        foreach ($clashes as $c) {
            $csv .= '"'.$c['student_name'].'","'.$c['school_name'].'","'.$c['event1'].'","'.$c['event2'].'","'.$c['time']."\"\n";
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

        $csv = "Item,Age group,Date,Time,Venue,Stage\n";
        foreach ($rows as $row) {
            $csv .= '"'.str_replace('"', '""', (string) $row['title']).'",';
            $csv .= '"'.strtoupper((string) ($row['age_group'] ?? '')).'",';
            $csv .= '"'.($row['scheduled_date'] ?? '').'",';
            $csv .= '"'.($row['scheduled_time'] ?? '').'",';
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

    private function promotionsCsv(): StreamedResponse
    {
        $quals = FestQualification::whereIn('event_id', $this->event->reportableEventIds())
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
        $quals = FestQualification::whereIn('event_id', $this->event->reportableEventIds())
            ->with(['participant.student', 'participant.registration.school', 'participant.registration.item', 'nextLevelEvent'])
            ->get();

        return $this->renderPdf('fest.reports.promotion-sheet', [
            'event'  => $this->event,
            'quals'  => $quals,
            ...$this->brandingData(),
        ], $this->slug().'-promotions.pdf');
    }

    private function certificateCountsCsv(): StreamedResponse
    {
        $schoolIds = $this->schools()->pluck('id');
        $rows = [];

        foreach ($schoolIds as $schoolId) {
            $name = Tenant::where('id', $schoolId)->value('name');
            $partIds = FestParticipant::whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $this->event->reportableEventIds())
                ->where('school_id', $schoolId))->pluck('id');

            $certs = Certificate::where('entity_type', FestParticipant::class)
                ->whereIn('entity_id', $partIds)
                ->count();

            $marks = FestMark::whereIn('event_id', $this->event->reportableEventIds())
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
        ], $rows);
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
        ], $rows);
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
        ], $rows);
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
        ], $rows);
    }
}
