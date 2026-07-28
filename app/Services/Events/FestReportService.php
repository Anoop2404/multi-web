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
        $ids = FestRegistration::where('event_id', $this->event->id)->pluck('school_id')->unique();

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
        return FestRegistration::where('event_id', $this->event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($classGroup, fn ($q) => $q->whereHas('item', fn ($i) => $i->where('class_group', $classGroup)))
            ->with(['item', 'participants.student', 'participants.teacher', 'school'])
            ->orderBy('school_id')
            ->get();
    }

    public function activeRegistrations(?string $classGroup = null, ?string $schoolId = null)
    {
        return FestRegistration::where('event_id', $this->event->id)
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
                    ->when($itemId, fn ($q2) => $q2->where('item_id', $itemId))
                    ->when($classGroup, fn ($q2) => $q2->whereHas('item', fn ($i) => $i->where('class_group', $classGroup)));
            })
            ->with(['group', 'registration.event', 'registration.item.head', 'registration.school', 'student.schoolClass.classCategory', 'teacher'])
            ->orderBy('chest_no')
            ->get();
    }

    public function marks(?string $schoolId = null, ?int $itemId = null, ?string $classGroup = null)
    {
        return FestMark::where('event_id', $this->event->id)
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
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
                ->where('event_id', $this->event->id)
                ->where('item_id', $item->id)
                ->whereNotIn('status', ['rejected', 'withdrawn'])
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId)))->count();

            $scoredQuery = FestMark::where('event_id', $this->event->id)
                ->where('item_id', $item->id)
                ->where(function ($q) {
                    $q->whereNotNull('grade')->orWhereNotNull('score')->orWhereNotNull('position');
                });
            if ($schoolId) {
                $scoredQuery->whereHas('participant.registration', fn ($q) => $q->where('school_id', $schoolId));
            }
            $scored = $scoredQuery->distinct('participant_id')->count('participant_id');

            $judges = FestJudgeAssignment::where('event_id', $this->event->id)
                ->where('item_id', $item->id)
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
        return \App\Models\FestStage::where('event_id', $this->event->id)
            ->with('venue:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'venue_id']);
    }

    public function schoolRankingRows(): Collection
    {
        $ctx = EventContext::for($this->event);
        $board = collect($ctx->scoreboardBySchool());

        $marks = FestMark::where('event_id', $this->event->id)
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

    private function renderPdf(string $view, array $data, string $filename, bool $landscape = false): \Symfony\Component\HttpFoundation\Response
    {
        $html = view($view, $data)->render();

        return PdfGenerator::download($html, $filename, $this->preview, $landscape);
    }

    private function reportAudience(Request $request): string
    {
        return $request->input('audience', 'staff') === 'public' ? 'public' : 'staff';
    }

    /** @return list<array{reference: string, name: ?string, school: ?string, order: ?int, item: ?string, _student_id: ?int}> */
    private function participantReportRows($participants, string $audience): array
    {
        $visibility = app(FestPublicVisibilityService::class);

        $participantIds = collect($participants)->pluck('id')->all();
        $schedules = FestSchedule::whereIn('participant_id', $participantIds)
            ->get()
            ->keyBy('participant_id');

        return collect($participants)->map(function (FestParticipant $p) use ($visibility, $audience, $schedules) {
            $schedule = $schedules->get($p->id);

            return array_merge(
                $visibility->formatReportRow($this->event, $p, $audience, $schedule),
                ['_student_id' => $p->student_id],
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

        $marks = FestMark::where('event_id', $this->event->id)
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
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

        $schedules = FestSchedule::where('event_id', $this->event->id)
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
            // Unfilled standby slots and rows with no student/teacher attached aren't
            // real attendees — exclude them so the printed sheet has no blank rows.
            ->filter(fn ($p) => $p->participant_role !== 'standby' && ($p->student_id || $p->teacher_id))
            ->values();

        $audience = $this->reportAudience($request);
        $isPreview = $this->preview;

        // Group participants by item, then collapse teams into a single row per team
        // (matching the Vue Attendance.vue displayRows logic) so the PDF shows one
        // entry per team, not every individual member.
        $GROUP_PARTICIPANT_TYPES = ['team', 'group', 'pair', 'trio'];

        // Pre-built photo URL map — uses photoUrl() which returns a public HTTP/S3
        // URL (browsers and headless Chrome handle these natively).  DomPDF fallback
        // will skip photos (the blade uses @if check).
        $photoMap = [];
        $participantMap = []; // participant_id -> participant for attendance lookups
        foreach ($participants as $p) {
            $participantMap[$p->id] = $p;
            $sid = $p->student_id;
            if ($sid && ! isset($photoMap[$sid]) && method_exists($p->student ?? null, 'photoUrl')) {
                $photoMap[$sid] = $p->student->photoUrl();
            }
        }

        // Build item-wise rows, collapsing teams
        $rowsByItem = collect();
        $items = $participants->pluck('registration.item')->unique('id');

        foreach ($items as $item) {
            $itemParticipants = $participants->filter(fn ($p) => $p->registration?->item_id === $item->id)->values();
            $isGroupItem = $item && in_array($item->participant_type, $GROUP_PARTICIPANT_TYPES, true);
            $itemRows = [];

            if ($isGroupItem) {
                // Collapse team members into a single row per team
                $seenGroups = [];
                foreach ($itemParticipants as $p) {
                    if (! $p->group_id) {
                        continue;
                    }
                    $key = (string) $p->group_id;
                    if (isset($seenGroups[$key])) {
                        continue;
                    }
                    $seenGroups[$key] = true;

                    $members = $itemParticipants->filter(fn ($m) => $m->group_id === $p->group_id);
                    $firstMember = $members->first();

                    $itemRows[] = [
                        'reference'    => $firstMember?->group?->chest_no ?? $p->chest_no ?? '—',
                        'name'         => $firstMember?->group?->team_name ?? 'Team',
                        'school'       => $p->registration?->school?->name ?? '',
                        'item'         => $item->title,
                        'team_name'    => $firstMember?->group?->team_name ?? null,
                        'group_id'     => $p->group_id,
                        'member_count' => $members->count(),
                        'photo_url'    => $photoMap[$members->first()?->student_id] ?? null,
                        'dob'          => $this->event->event_type === 'sports'
                            ? ($members->first()?->student?->dob?->format('d M Y') ?? null) : null,
                        '_student_id'  => $members->first()?->student_id,
                        '_participant_ids' => $members->pluck('id')->all(),
                    ];
                }
            } else {
                // Individual participants — one row per person
                foreach ($itemParticipants as $p) {
                    $itemRows[] = [
                        'reference'    => $p->chest_no ?? '—',
                        'name'         => $p->student?->name ?? $p->teacher?->name ?? 'Participant',
                        'school'       => $p->registration?->school?->name ?? '',
                        'item'         => $item->title,
                        'team_name'    => null,
                        'group_id'     => null,
                        'member_count' => null,
                        'photo_url'    => $photoMap[$p->student_id] ?? null,
                        'dob'          => $this->event->event_type === 'sports'
                            ? ($p->student?->dob?->format('d M Y') ?? null) : null,
                        '_student_id'  => $p->student_id,
                        '_participant_ids' => [$p->id],
                    ];
                }
            }

            // Sort rows by chest number (numeric), then school name
            usort($itemRows, function ($a, $b) {
                $aNum = (int) preg_replace('/[^0-9]/', '', $a['reference'] ?? '0');
                $bNum = (int) preg_replace('/[^0-9]/', '', $b['reference'] ?? '0');
                return [$aNum <=> $bNum, $a['school'] <=> $b['school']];
            });

            if ($itemRows) {
                $rowsByItem[$item->title] = $itemRows;
            }
        }

        $sahodaya = Tenant::find($this->event->tenant_id);

        // Preview mode: return raw HTML for in-browser viewing (handles S3
        // images via photoUrl, proper page headers via browser print).
        if ($isPreview) {
            $html = view('fest.reports.attendance-sheet', [
                'event'      => $this->event,
                'sahodaya'   => $sahodaya,
                'logo'       => $sahodaya ? \App\Support\TenantBranding::logoEmbedSrc($sahodaya) : null,
                'rowsByItem' => $rowsByItem,
                'audience'   => $audience,
                'isPreview'  => true,
            ])->render();

            return response($html)->header('Content-Type', 'text/html');
        }

        return $this->renderPdf('fest.reports.attendance-sheet', [
            'event'      => $this->event,
            'sahodaya'   => $sahodaya,
            'logo'       => $sahodaya ? \App\Support\TenantBranding::logoEmbedSrc($sahodaya) : null,
            'rowsByItem' => $rowsByItem,
            'audience'   => $audience,
        ], $this->slug().'-attendance.pdf');
    }

    private function attendanceSheetSchoolPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
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

        // DOB enrichment for sports events only (photos skipped — DomPDF memory limit).
        if ($this->event->event_type === 'sports') {
            foreach ($studentRows as $id => $row) {
                $studentRows[$id]['dob'] = $row['student']->dob?->format('d M Y');
            }
        }

        return $this->renderPdf('fest.reports.attendance-sheet-school', [
            'event'       => $this->event,
            'school'      => $school,
            'studentRows' => $studentRows,
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

        $schedule = FestSchedule::where('event_id', $this->event->id)
            ->where('item_id', $itemId)
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
        $schedules = FestSchedule::where('event_id', $this->event->id)
            ->where('item_id', $itemId)
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
            ->where('event_id', $this->event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->when($itemId, fn ($q2) => $q2->where('item_id', $itemId)))
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
        $quals = FestQualification::where('event_id', $this->event->id)
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
        $quals = FestQualification::where('event_id', $this->event->id)
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
                ->where('event_id', $this->event->id)
                ->where('school_id', $schoolId))->pluck('id');

            $certs = Certificate::where('entity_type', FestParticipant::class)
                ->whereIn('entity_id', $partIds)
                ->count();

            $marks = FestMark::where('event_id', $this->event->id)
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
