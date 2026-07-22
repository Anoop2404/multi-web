<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestQualification;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsFestIdCardResponses;
use App\Services\Events\FestParticipationLimitService;
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
use App\Support\ProgramRouteMap;
use App\Support\SchoolFestProgram;
use App\Support\TenantBranding;
use App\Support\FestEventMeta;
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

    public function studentWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $students = \App\Models\Student::where('tenant_id', $this->school->id)->active()->orderBy('name')->get();

        $rows = $students->map(function ($student) use ($event) {
            $partIds = FestParticipant::where('student_id', $student->id)
                ->whereHas('registration', fn ($q) => $q
                    ->where('event_id', $event->id)
                    ->where('school_id', $this->school->id)
                    ->active())
                ->pluck('id');

            $regs = FestRegistration::where('event_id', $event->id)
                ->where('school_id', $this->school->id)
                ->active()
                ->whereHas('participants', fn ($q) => $q->where('student_id', $student->id))
                ->with('item')
                ->get();

            $marks = FestMark::where('event_id', $event->id)
                ->whereIn('participant_id', $partIds)
                ->with('item')
                ->get();

            return [
                'student'       => $student->only(['id', 'name', 'reg_no']),
                'registrations' => $regs->map(fn ($r) => $r->item?->title),
                'total_score'   => $marks->sum('score'),
                'results'       => $marks->map(fn ($m) => [
                    'item' => $m->item?->title,
                    'position' => $m->position,
                    'grade' => $m->grade,
                    'score' => $m->score,
                ]),
            ];
        });

        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportStudentWise', [
            'program'  => $program,
            'school'   => $this->school->only('id', 'name'),
            'event'    => $event->only('id', 'title'),
            'rows'     => $rows,
            'pdfUrl'   => "{$base}/export/school-wise",
            'csvUrl'   => "{$base}/student-wise/export",
        ]);
    }

    public function itemWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $itemId = $request->integer('item_id') ?: null;

        $participants = collect();
        if ($itemId) {
            $participants = FestParticipant::whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('school_id', $this->school->id)
                ->where('item_id', $itemId)
                ->active())
                ->with(['student', 'teacher', 'mark'])
                ->get();
        }

        $base = $this->schoolReportsBase($program, $event);

        return $this->inertia('School/Events/ReportItemWise', array_merge(
            $this->schoolItemHeadReportContext($event, $program),
            [
                'program'      => $meta['slug'],
                'programMeta'  => $meta,
                'school'       => $this->school->only('id', 'name'),
                'event'        => $event->only('id', 'title'),
                'itemId'       => $itemId,
                'participants' => $participants,
                'pdfUrl'       => $itemId ? "{$base}/item-wise/pdf?item_id={$itemId}" : null,
                'csvUrl'       => $itemId ? "{$base}/item-wise/export?item_id={$itemId}" : null,
                'resultsPdfUrl'=> ($itemId && $event->results_published)
                    ? "{$base}/export/item-wise?item_id={$itemId}"
                    : null,
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

        $teachers = \App\Models\Teacher::where('tenant_id', $this->school->id)->active()->orderBy('name')->get();

        $rows = $teachers->map(function ($teacher) use ($event) {
            $partIds = FestParticipant::where('teacher_id', $teacher->id)
                ->whereHas('registration', fn ($q) => $q
                    ->where('event_id', $event->id)
                    ->where('school_id', $this->school->id)
                    ->active())
                ->pluck('id');

            $regs = FestRegistration::where('event_id', $event->id)
                ->where('school_id', $this->school->id)
                ->active()
                ->whereHas('participants', fn ($q) => $q->where('teacher_id', $teacher->id))
                ->with('item')
                ->get();

            $marks = FestMark::where('event_id', $event->id)
                ->whereIn('participant_id', $partIds)
                ->with('item')
                ->get();

            return [
                'teacher'       => $teacher->only(['id', 'name', 'reg_no', 'designation']),
                'registrations' => $regs->map(fn ($r) => $r->item?->title),
                'total_score'   => $marks->sum('score'),
                'results'       => $marks->map(fn ($m) => [
                    'item'     => $m->item?->title,
                    'position' => $m->position,
                    'grade'    => $m->grade,
                    'score'    => $m->score,
                ]),
            ];
        });

        return $this->inertia('School/Events/ReportTeacherWise', [
            'program' => $program,
            'school'  => $this->school->only('id', 'name'),
            'event'   => $event->only('id', 'title'),
            'rows'    => $rows,
        ]);
    }

    public function exportStudentWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        return response()->streamDownload(function () use ($event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reg No', 'Name', 'Items', 'Total Score', 'Results']);
            $students = \App\Models\Student::where('tenant_id', $this->school->id)->active()->orderBy('name')->get();
            foreach ($students as $student) {
                $partIds = FestParticipant::where('student_id', $student->id)
                    ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id)->where('school_id', $this->school->id)->active())
                    ->pluck('id');
                $items = FestRegistration::where('event_id', $event->id)->where('school_id', $this->school->id)
                    ->active()
                    ->whereHas('participants', fn ($q) => $q->where('student_id', $student->id))
                    ->with('item')->get()->pluck('item.title')->filter()->implode('; ');
                $marks = FestMark::where('event_id', $event->id)->whereIn('participant_id', $partIds)->with('item')->get();
                $results = $marks->map(fn ($m) => ($m->item?->title ?? '').':'.($m->grade ?? $m->position ?? $m->score))->implode('; ');
                fputcsv($out, [$student->reg_no, $student->name, $items, $marks->sum('score'), $results]);
            }
            fclose($out);
        }, "{$event->id}-student-wise.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportTeacherWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($event->event_type !== 'teacher_fest', 404);

        return response()->streamDownload(function () use ($event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reg No', 'Name', 'Designation', 'Items', 'Total Score', 'Results']);
            $teachers = \App\Models\Teacher::where('tenant_id', $this->school->id)->active()->orderBy('name')->get();
            foreach ($teachers as $teacher) {
                $partIds = FestParticipant::where('teacher_id', $teacher->id)
                    ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id)->where('school_id', $this->school->id)->active())
                    ->pluck('id');
                $items = FestRegistration::where('event_id', $event->id)->where('school_id', $this->school->id)
                    ->active()
                    ->whereHas('participants', fn ($q) => $q->where('teacher_id', $teacher->id))
                    ->with('item')->get()->pluck('item.title')->filter()->implode('; ');
                $marks = FestMark::where('event_id', $event->id)->whereIn('participant_id', $partIds)->with('item')->get();
                $results = $marks->map(fn ($m) => ($m->item?->title ?? '').':'.($m->grade ?? $m->position ?? $m->score))->implode('; ');
                fputcsv($out, [$teacher->reg_no, $teacher->name, $teacher->designation, $items, $marks->sum('score'), $results]);
            }
            fclose($out);
        }, "{$event->id}-teacher-wise.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportItemWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $itemId = $request->integer('item_id') ?: $event->items()->first()?->id;
        $item = $event->items()->findOrFail($itemId);

        return response()->streamDownload(function () use ($event, $itemId, $item) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Item', 'Participant', 'Reg No', 'Class', 'Fest ID', 'Item reg', 'Chest', 'Grade', 'Position', 'Score']);
            $participants = FestParticipant::whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('school_id', $this->school->id)
                ->where('item_id', $itemId)
                ->active())
                ->with(['student.schoolClass', 'teacher', 'mark'])
                ->get();
            foreach ($participants as $p) {
                fputcsv($out, [
                    $item->title,
                    $p->student?->name ?? $p->teacher?->name,
                    $p->student?->reg_no ?? $p->teacher?->reg_no,
                    $p->student?->schoolClass?->name,
                    $p->level_registration_number,
                    $p->item_registration_number,
                    $p->chest_no,
                    $p->mark?->grade,
                    $p->mark?->position,
                    $p->mark?->score,
                ]);
            }
            fclose($out);
        }, "{$event->id}-item-{$itemId}.csv", ['Content-Type' => 'text/csv']);
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
        $item = $event->items()->findOrFail($itemId);

        $rows = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->where('item_id', $itemId)
            ->active())
            ->with(['student.schoolClass', 'teacher', 'mark'])
            ->get()
            ->map(fn (FestParticipant $p) => [
                'participant' => $p->student?->name ?? $p->teacher?->name,
                'reg_no'      => $p->student?->reg_no ?? $p->teacher?->reg_no,
                'class'       => $p->student?->schoolClass?->name,
                'fest_id'     => $p->level_registration_number,
                'item_reg'    => $p->item_registration_number,
                'chest_no'    => $p->chest_no,
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
            fputcsv($out, ['Event', 'Limit type', 'Used', 'Limit']);
            foreach ($usage['used'] as $type => $count) {
                fputcsv($out, [
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
            fputcsv($out, ['Participant', 'Reg No', 'Item', 'From event', 'From level', 'Next event', 'Next level', 'Promoted at']);
            foreach ($rows as $q) {
                fputcsv($out, [
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
        $data = $register->build($event, $this->school->id);

        return $this->inertia('School/Events/ReportRegistrationRegister', array_merge(
            $this->schoolItemHeadReportContext($event, $program),
            [
                'program'         => $meta['slug'],
                'programMeta'     => $meta,
                'school'          => $this->school->only('id', 'name'),
                'event'           => $event->only('id', 'title', 'status', 'level_round'),
                'rows'            => $data['rows'],
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

        return $register->exportCsv($event, $this->school->id);
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

        $meta = SchoolFestProgram::meta($program);
        $event->load(['items' => fn ($q) => $q->where('is_enabled', true)->orderBy('title')]);

        $itemCounts = $service->itemParticipantCounts($event, $this->school->id);
        $registrationCounts = $service->itemRegistrationCounts($event, $this->school->id);

        $cluster = Tenant::find($this->school->parent_id);
        $downloadGate = app(SchoolDocumentDownloadGateService::class)->payload($this->school, $event);

        return $this->inertia('School/Events/ReportIdCards', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'clusterName' => $cluster?->name ?? 'Sahodaya',
            'clusterLogoUrl' => $cluster ? TenantBranding::logoUrl($cluster) : null,
            'event'       => $event->only('id', 'title', 'status'),
            'items'       => $event->items->map(fn ($item) => [
                'id'                 => $item->id,
                'title'              => $item->title,
                'participant_type'   => $item->participant_type,
                'count'              => $itemCounts[$item->id] ?? 0,
                'registration_count' => $registrationCounts[$item->id] ?? 0,
            ]),
            'heads'       => $service->headOptions($event, $this->school->id),
            'meta'        => $service->indexMeta($event, $this->school->id),
            'downloadGate' => $downloadGate,
        ]);
    }

    public function idCardsJson(Request $request, string $tenantId, FestEvent $event, string $program, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        app(SchoolDocumentDownloadGateService::class)->assertFestEventFeeForDownloads(
            $event, $this->school, $request->integer('head_id') ?: null,
        );

        $filters = array_merge($this->idCardFilters($request), [
            'school_id'        => $this->school->id,
            'school_downloads' => true,
        ]);

        if (($filters['scope'] ?? 'item') === 'item' && $request->input('item_id') === 'all') {
            $sections = $service->cardsGroupedByItem($event, $filters);
            $cards = collect($sections)->flatMap(fn ($section) => $section['cards'])->values()->all();

            return response()->json(['cards' => $cards]);
        }

        if (($filters['scope'] ?? 'item') === 'item' && empty($filters['item_id'])) {
            return response()->json(['cards' => [], 'message' => 'Select an item to preview cards.']);
        }

        if (($filters['scope'] ?? 'item') === 'head' && empty($filters['head_id'])) {
            return response()->json(['cards' => [], 'message' => 'Select an item head to preview cards.']);
        }

        return response()->json([
            'cards' => $service->cards($event, 'student', $filters),
        ]);
    }

    public function idCardsPreview(Request $request, string $tenantId, FestEvent $event, string $program, FestIdCardService $service)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        app(SchoolDocumentDownloadGateService::class)->assertFestEventFeeForDownloads(
            $event, $this->school, $request->integer('head_id') ?: null,
        );

        $cluster = Tenant::findOrFail($this->school->parent_id);
        $filters = array_merge($this->idCardFilters($request), [
            'school_id'        => $this->school->id,
            'school_downloads' => true,
        ]);

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

        app(SchoolDocumentDownloadGateService::class)->assertFestEventFeeForDownloads(
            $event, $this->school, $request->integer('head_id') ?: null,
        );

        $cluster = Tenant::findOrFail($this->school->parent_id);
        $filters = array_merge($this->idCardFilters($request), [
            'school_id'        => $this->school->id,
            'school_downloads' => true,
            'include_data_uris' => true,
        ]);

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
        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $event,
            $cluster,
            [],
            'student',
            false,
            $sections,
            $customTemplate,
            $isDomPdf,
        ))->render();

        return \App\Support\PdfGenerator::download($html, "{$slug}-all-heads-id-cards.pdf");
    }

    public function idCardsPdfAllItems(Request $request, string $tenantId, FestEvent $event, string $program, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

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
        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $event,
            $cluster,
            [],
            'student',
            false,
            $sections,
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
                'rows'         => $analytics->headWiseParticipantRows($headId),
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
            'rows'        => $analytics->numberingRegisterRows(),
            'xlsUrl'      => "{$base}/numbering-register/export",
        ]);
    }

    public function exportNumberingRegister(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        return (new FestEventReportAnalyticsService($event))->exportNumberingRegister($this->school->id);
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
            'rows'        => $analytics->pendingApprovalRows(),
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
                'rows'         => $analytics->attendanceRows($headId, $itemId),
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
                'results'      => $analytics->publishedResultsRows($headId, $itemId),
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

        $catalog = collect(FestReportCatalog::exports($this->school->parent_id, $event->id))->firstWhere('id', $exportType);
        abort_unless(is_array($catalog), 404, "Unknown report export: {$exportType}.");

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
}
