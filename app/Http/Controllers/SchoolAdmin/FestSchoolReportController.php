<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
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
use App\Services\Events\FestSchoolReportAnalyticsService;
use App\Support\SchoolFestProgram;
use Illuminate\Http\Request;

class FestSchoolReportController extends SchoolAdminController
{
    use BuildsFestIdCardResponses;

    public function reportsHub()
    {
        $programs = [
            ['slug' => 'kalotsav', 'label' => 'Kalotsav'],
            ['slug' => 'sports-meet', 'label' => 'Sports Meet'],
            ['slug' => 'kids-fest', 'label' => 'Kids Fest'],
            ['slug' => 'teacher-fest', 'label' => 'Teacher Fest'],
        ];

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
            ->get(['id', 'title', 'status', 'event_start']);

        return $this->inertia('School/Events/Reports', [
            'program'     => $program,
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'events'      => $events,
        ]);
    }

    public function participation(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $service = new FestParticipationLimitService($event);
        $usage = $service->usageForSchool($this->school->id);

        return $this->inertia('School/Events/ReportParticipation', [
            'program' => $program,
            'school'  => $this->school->only('id', 'name'),
            'event'   => $event->only('id', 'title'),
            'used'    => $usage['used'],
            'limits'  => $usage['limits'],
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
                    ->where('school_id', $this->school->id))
                ->pluck('id');

            $regs = FestRegistration::where('event_id', $event->id)
                ->where('school_id', $this->school->id)
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

        return $this->inertia('School/Events/ReportStudentWise', [
            'program'  => $program,
            'school'   => $this->school->only('id', 'name'),
            'event'    => $event->only('id', 'title'),
            'rows'     => $rows,
        ]);
    }

    public function itemWise(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $items = $event->items()->orderBy('display_order')->get();
        $itemId = $request->integer('item_id') ?: $items->first()?->id;

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->where('item_id', $itemId)
            ->where('status', 'approved'))
            ->with(['student', 'mark'])
            ->get();

        return $this->inertia('School/Events/ReportItemWise', [
            'program'      => $program,
            'school'       => $this->school->only('id', 'name'),
            'event'        => $event->only('id', 'title'),
            'items'        => $items->map->only(['id', 'title']),
            'itemId'       => $itemId,
            'participants' => $participants,
        ]);
    }

    public function admitCards(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        return redirect("/sahodaya-admin/{$this->school->parent_id}/events/{$event->id}/reports/export/admit-cards?school_id={$this->school->id}");
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
                    ->where('school_id', $this->school->id))
                ->pluck('id');

            $regs = FestRegistration::where('event_id', $event->id)
                ->where('school_id', $this->school->id)
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

    public function exportStudentWise(FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        return response()->streamDownload(function () use ($event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reg No', 'Name', 'Items', 'Total Score', 'Results']);
            $students = \App\Models\Student::where('tenant_id', $this->school->id)->active()->orderBy('name')->get();
            foreach ($students as $student) {
                $partIds = FestParticipant::where('student_id', $student->id)
                    ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id)->where('school_id', $this->school->id))
                    ->pluck('id');
                $items = FestRegistration::where('event_id', $event->id)->where('school_id', $this->school->id)
                    ->whereHas('participants', fn ($q) => $q->where('student_id', $student->id))
                    ->with('item')->get()->pluck('item.title')->filter()->implode('; ');
                $marks = FestMark::where('event_id', $event->id)->whereIn('participant_id', $partIds)->with('item')->get();
                $results = $marks->map(fn ($m) => ($m->item?->title ?? '').':'.($m->grade ?? $m->position ?? $m->score))->implode('; ');
                fputcsv($out, [$student->reg_no, $student->name, $items, $marks->sum('score'), $results]);
            }
            fclose($out);
        }, "{$event->id}-student-wise.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportTeacherWise(FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($event->event_type !== 'teacher_fest', 404);

        return response()->streamDownload(function () use ($event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reg No', 'Name', 'Designation', 'Items', 'Total Score', 'Results']);
            $teachers = \App\Models\Teacher::where('tenant_id', $this->school->id)->active()->orderBy('name')->get();
            foreach ($teachers as $teacher) {
                $partIds = FestParticipant::where('teacher_id', $teacher->id)
                    ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id)->where('school_id', $this->school->id))
                    ->pluck('id');
                $items = FestRegistration::where('event_id', $event->id)->where('school_id', $this->school->id)
                    ->whereHas('participants', fn ($q) => $q->where('teacher_id', $teacher->id))
                    ->with('item')->get()->pluck('item.title')->filter()->implode('; ');
                $marks = FestMark::where('event_id', $event->id)->whereIn('participant_id', $partIds)->with('item')->get();
                $results = $marks->map(fn ($m) => ($m->item?->title ?? '').':'.($m->grade ?? $m->position ?? $m->score))->implode('; ');
                fputcsv($out, [$teacher->reg_no, $teacher->name, $teacher->designation, $items, $marks->sum('score'), $results]);
            }
            fclose($out);
        }, "{$event->id}-teacher-wise.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportItemWise(FestEvent $event, string $program, Request $request)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $itemId = $request->integer('item_id') ?: $event->items()->first()?->id;
        $item = $event->items()->findOrFail($itemId);

        return response()->streamDownload(function () use ($event, $itemId, $item) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Item', 'Participant', 'Reg No', 'Grade', 'Position', 'Score']);
            $participants = FestParticipant::whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('school_id', $this->school->id)
                ->where('item_id', $itemId)
                ->where('status', 'approved'))
                ->with(['student', 'teacher', 'mark'])
                ->get();
            foreach ($participants as $p) {
                fputcsv($out, [
                    $item->title,
                    $p->student?->name ?? $p->teacher?->name,
                    $p->student?->reg_no ?? $p->teacher?->reg_no,
                    $p->mark?->grade,
                    $p->mark?->position,
                    $p->mark?->score,
                ]);
            }
            fclose($out);
        }, "{$event->id}-item-{$itemId}.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportParticipation(FestEvent $event, string $program)
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

    public function exportQualifiers(string $program)
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

    public function registrationRegister(FestEvent $event, string $program, FestRegistrationRegisterService $register)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $data = $register->build($event, $this->school->id);

        return $this->inertia('School/Events/ReportRegistrationRegister', [
            'program'         => $meta['slug'],
            'programMeta'     => $meta,
            'school'          => $this->school->only('id', 'name'),
            'event'           => $event->only('id', 'title', 'status', 'level_round'),
            'rows'            => $data['rows'],
            'schoolSummary'   => $data['school_summaries'][0] ?? null,
            'totals'          => $data['totals'],
            'paymentsUrl'     => "/school-admin/{$this->school->id}/payments",
        ]);
    }

    public function exportRegistrationRegister(FestEvent $event, string $program, FestRegistrationRegisterService $register)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        return $register->exportCsv($event, $this->school->id);
    }

    public function idCards(FestEvent $event, string $program, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $event->load(['items' => fn ($q) => $q->where('is_enabled', true)->orderBy('title')]);

        $itemCounts = $service->itemParticipantCounts($event, $this->school->id);
        $registrationCounts = $service->itemRegistrationCounts($event, $this->school->id);

        $cluster = Tenant::find($this->school->parent_id);

        return $this->inertia('School/Events/ReportIdCards', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'clusterName' => $cluster?->name ?? 'Sahodaya',
            'event'       => $event->only('id', 'title', 'status'),
            'items'       => $event->items->map(fn ($item) => [
                'id'                 => $item->id,
                'title'              => $item->title,
                'participant_type'   => $item->participant_type,
                'count'              => $itemCounts[$item->id] ?? 0,
                'registration_count' => $registrationCounts[$item->id] ?? 0,
            ]),
            'meta'        => $service->indexMeta($event, $this->school->id),
        ]);
    }

    public function idCardsJson(FestEvent $event, string $program, FestIdCardService $service, Request $request)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $filters = array_merge($this->idCardFilters($request), [
            'school_id' => $this->school->id,
        ]);

        if (($filters['scope'] ?? 'item') === 'item' && empty($filters['item_id'])) {
            return response()->json(['cards' => [], 'message' => 'Select an item to preview cards.']);
        }

        return response()->json([
            'cards' => $service->cards($event, 'student', $filters),
        ]);
    }

    public function idCardsPdf(FestEvent $event, string $program, FestIdCardService $service, Request $request)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $cluster = Tenant::findOrFail($this->school->parent_id);
        $filters = array_merge($this->idCardFilters($request), [
            'school_id' => $this->school->id,
        ]);

        $service->requireStudentItem('student', $filters);

        $cards = $service->cards($event, 'student', $filters);
        $slug = str($event->title)->slug('-');
        $scopeSuffix = ($filters['scope'] ?? 'item') === 'event' ? 'event-pass' : 'student';

        return \Barryvdh\DomPDF\Facade\Pdf::loadView($this->idCardSheetView($request), $this->idCardViewData(
            $event,
            $cluster->name,
            $cards,
            'student',
            false,
        ))->download("{$slug}-{$scopeSuffix}-id-cards.pdf");
    }

    public function feeSummary(FestEvent $event, string $program)
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
        ]);
    }

    public function disciplineParticipation(FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);

        return $this->inertia('School/Events/ReportDisciplineParticipation', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'event'       => $event->only('id', 'title'),
            'rows'        => $analytics->disciplineParticipationRows(),
        ]);
    }

    public function scheduleClashes(FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $analytics = new FestSchoolReportAnalyticsService($event, $this->school->id);
        $clashes = $analytics->scheduleClashes();

        return $this->inertia('School/Events/ReportScheduleClashes', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'event'       => $event->only('id', 'title'),
            'participant' => $clashes['participant'],
            'stage'       => $clashes['stage'],
            'csvUrl'      => "/sahodaya-admin/{$this->school->parent_id}/events/{$event->id}/reports/export/clashes?school_id={$this->school->id}",
        ]);
    }

    public function markEntryStatus(FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $data = (new FestSchoolReportAnalyticsService($event, $this->school->id))->markEntryStatus();

        return $this->inertia('School/Events/ReportMarkEntryStatus', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'event'       => $event->only('id', 'title'),
            'summary'     => $data['summary'],
            'rows'        => $data['rows'],
            'csvUrl'      => "/sahodaya-admin/{$this->school->parent_id}/events/{$event->id}/reports/export/mark-entry-status",
        ]);
    }

    public function resultsSummary(FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $meta = SchoolFestProgram::meta($program);
        $results = (new FestSchoolReportAnalyticsService($event, $this->school->id))->resultsSummary();

        return $this->inertia('School/Events/ReportResultsSummary', [
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'school'      => $this->school->only('id', 'name'),
            'event'       => $event->only('id', 'title'),
            'results'     => $results,
        ]);
    }

    public function groupRoster(FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        return redirect("/sahodaya-admin/{$this->school->parent_id}/events/{$event->id}/reports/export/team-squad-sheets?school_id={$this->school->id}");
    }

    public function attendanceSheet(FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        return redirect("/sahodaya-admin/{$this->school->parent_id}/events/{$event->id}/reports/export/attendance-sheet-school?school_id={$this->school->id}");
    }
}
