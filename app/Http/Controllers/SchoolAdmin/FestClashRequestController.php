<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestClashRequest;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use App\Models\Student;
use App\Services\Events\FestRegistrationRouterService;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use App\Support\PdfGenerator;
use App\Support\SchoolFestProgram;
use App\Support\ProgramRouteMap;
use App\Support\TenantBranding;
use Illuminate\Http\Request;

class FestClashRequestController extends SchoolAdminController
{
    public function index(string $tenantId, FestEvent $event, string $program)
    {
        $meta = SchoolFestProgram::meta($program);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        // A school hitting this page against the hub id directly (instead of its assigned
        // region/finale child) would read/write FestClashRequest rows keyed to the wrong
        // event_id — inconsistent with the participant/schedule data on the same page, which
        // already reads via reportableEventIds(). Same sibling-region gap class as Phase 1's
        // food-ordering fix (Phase 9 audit).
        app(FestRegistrationRouterService::class)->assertSchoolCanAccess($event, $this->school->id);

        $requestRows = FestClashRequest::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->with(['participant.student'])
            ->latest()
            ->get();

        // Batched instead of one schedules() query per row.
        $requestScheduleIds = $requestRows
            ->flatMap(fn (FestClashRequest $r) => $r->schedule_ids ?: array_filter([$r->schedule_id_a, $r->schedule_id_b]))
            ->unique()
            ->values();
        $requestSchedulesById = FestSchedule::with('item:id,title')->whereIn('id', $requestScheduleIds)->get()->keyBy('id');

        $requests = $requestRows->map(fn (FestClashRequest $r) => $r->toArray() + [
            'schedules' => collect($r->schedule_ids ?: array_filter([$r->schedule_id_a, $r->schedule_id_b]))
                ->map(fn ($id) => $requestSchedulesById->get($id))
                ->filter()
                ->map(fn ($s) => ['id' => $s->id, 'item_title' => $s->item?->title])
                ->values()
                ->all(),
        ]);

        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $this->school->id)
            ->where('status', 'approved'))
            ->with(['student', 'registration.item'])
            ->get()
            ->map(function (FestParticipant $p) use ($event, $classGroupLabels, $artsCategoryLabels) {
                $schedules = FestSchedule::whereIn('event_id', $event->reportableEventIds())
                    ->where('participant_id', $p->id)
                    ->with('item')
                    ->orderBy('scheduled_at')
                    ->get()
                    ->map(fn (FestSchedule $s) => [
                        'id'             => $s->id,
                        'item_title'     => $s->item?->title,
                        'category_label' => FestItemCategoryLabel::resolve($s->item, $classGroupLabels, $artsCategoryLabels),
                        'scheduled_at'   => $s->scheduled_at?->toIso8601String(),
                        'stage'          => $s->stage,
                    ]);

                return [
                    'id'             => $p->id,
                    'name'           => $p->student?->name ?? $p->teacher?->name,
                    'item'           => $p->registration?->item?->title,
                    'category_label' => FestItemCategoryLabel::resolve($p->registration?->item, $classGroupLabels, $artsCategoryLabels),
                    'schedules'      => $schedules,
                ];
            });

        return $this->inertia('School/Events/ClashRequests', [
            'event'        => $event->only('id', 'title', 'status', 'schedule_published'),
            'program'      => $meta['slug'],
            'programMeta'  => $meta,
            'requests'     => $requests,
            'participants' => $participants,
        ]);
    }

    public function store(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        $meta = SchoolFestProgram::meta($program);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        app(FestRegistrationRouterService::class)->assertSchoolCanAccess($event, $this->school->id);

        $data = $request->validate([
            'participant_id'       => 'required|exists:fest_participants,id',
            // A clash is two or more overlapping slots — the detected-clashes report
            // already flags every overlapping pair for a student with 3+ items, so this
            // form must be able to report all of them in one go, not just two.
            'schedule_ids'         => 'required|array|min:2',
            'schedule_ids.*'       => 'required|exists:fest_schedules,id',
            'description'          => 'required|string|max:2000',
            'requested_resolution' => 'nullable|string|max:2000',
        ]);

        $participant = FestParticipant::where('id', $data['participant_id'])
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $event->reportableEventIds())
                ->where('school_id', $this->school->id))
            ->firstOrFail();

        $scheduleIds = array_values(array_unique(array_map('intval', $data['schedule_ids'])));
        $ownedCount = FestSchedule::whereIn('id', $scheduleIds)
            ->where('participant_id', $participant->id)
            ->whereIn('event_id', $event->reportableEventIds())
            ->count();
        abort_unless($ownedCount === count($scheduleIds), 422, 'One of the selected slots does not belong to this participant.');

        FestClashRequest::create([
            'event_id'               => $event->id,
            'school_id'              => $this->school->id,
            'participant_id'         => $data['participant_id'],
            // Kept for older code/reports that still read the pair columns directly.
            'schedule_id_a'          => $scheduleIds[0] ?? null,
            'schedule_id_b'          => $scheduleIds[1] ?? null,
            'schedule_ids'           => $scheduleIds,
            'description'            => $data['description'],
            'requested_resolution'   => $data['requested_resolution'] ?? null,
            'status'                 => 'pending',
            'requested_by_user_id'   => $request->user()?->id,
        ]);

        return redirect('/school-admin/'.$this->school->id.'/'.ProgramRouteMap::prefixFromSlug($meta['slug'])."/events/{$event->id}/clash-requests")
            ->with('success', 'Clash report submitted.');
    }

    /**
     * Printable "Off Stage/Stage Events — Clash Form" (two copies per page, one for the
     * team manager to keep and one for the Sahodaya desk), branded with THIS Sahodaya's
     * own name/logo — every Sahodaya gets its own header instead of one hardcoded copy.
     * With ?clash_request=ID it prints that already-filed report pre-filled, with exactly
     * as many item boxes as that report has clashing slots (two or more, not capped at
     * two like the old paper form); without it, a blank form with ?items= boxes (default
     * 3) for filling in by hand on the spot.
     */
    public function printForm(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        $meta = SchoolFestProgram::meta($program);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        app(FestRegistrationRouterService::class)->assertSchoolCanAccess($event, $this->school->id);

        $data = ['schoolName' => $this->school->name];

        if ($clashRequestId = $request->query('clash_request')) {
            $clashRequest = FestClashRequest::where('event_id', $event->id)
                ->where('school_id', $this->school->id)
                ->with('participant.student')
                ->findOrFail($clashRequestId);

            $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
            $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);
            $schedules = $clashRequest->schedules();

            $data['date'] = $clashRequest->created_at?->format('d M Y');
            $data['studentName'] = $clashRequest->participant?->student?->name;
            $data['rollNo'] = $clashRequest->participant?->chest_no ?? $clashRequest->participant?->level_registration_number;
            $data['category'] = FestItemCategoryLabel::resolve($schedules->first()?->item, $classGroupLabels, $artsCategoryLabels);
            $data['items'] = $schedules->map(fn (FestSchedule $s) => [
                'title' => $s->item?->title,
                'stage' => $s->festStage?->name ?? $s->stage,
                'time'  => $s->scheduled_at?->format('h:i A'),
            ])->all();
        } elseif ($studentId = $request->query('student_id')) {
            $student = Student::where('tenant_id', $this->school->id)->findOrFail($studentId);
            $participant = FestParticipant::whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('school_id', $this->school->id)
            )->where('student_id', $student->id)->with(['student', 'group', 'registration.item'])->first();

            $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
            $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

            $scheduleIds = array_filter(array_map('intval', explode(',', (string) $request->query('schedule_ids'))));

            if (!empty($scheduleIds)) {
                $baseSchedules = FestSchedule::where('event_id', $event->id)
                    ->whereIn('id', $scheduleIds)
                    ->with(['item', 'festStage.venue'])
                    ->orderBy('scheduled_at')
                    ->get();

                // If this student has additional overlapping schedules around that same time window,
                // include them too so all clashing items (2, 3, or more) appear on the clash form.
                $windowStart = $baseSchedules->min('scheduled_at');
                $windowEnd = $baseSchedules->map(fn ($s) => $s->scheduled_at?->copy()->addMinutes($s->item?->estimatedDurationMinutes() ?? 60))->max();

                $allStudentSchedules = FestSchedule::where('event_id', $event->id)
                    ->whereNotNull('scheduled_at')
                    ->whereHas('participant', fn ($q) => $q->where('student_id', $student->id))
                    ->with(['item', 'festStage.venue'])
                    ->orderBy('scheduled_at')
                    ->get();

                $overlapping = $allStudentSchedules->filter(function (FestSchedule $s) use ($windowStart, $windowEnd, $scheduleIds) {
                    if (in_array($s->id, $scheduleIds, true)) {
                        return true;
                    }
                    if (!$windowStart || !$windowEnd || !$s->scheduled_at) {
                        return false;
                    }
                    $sEnd = $s->scheduled_at->copy()->addMinutes($s->item?->estimatedDurationMinutes() ?? 60);
                    return $s->scheduled_at->lessThan($windowEnd) && $sEnd->greaterThan($windowStart);
                })->values();

                $schedules = $overlapping->isNotEmpty() ? $overlapping : $baseSchedules;
            } else {
                $schedules = FestSchedule::where('event_id', $event->id)
                    ->whereNotNull('scheduled_at')
                    ->whereHas('participant', fn ($q) => $q->where('student_id', $student->id))
                    ->with(['item', 'festStage.venue'])
                    ->orderBy('scheduled_at')
                    ->get();
            }

            $data['date'] = now()->format('d M Y');
            $data['studentName'] = $student->name;
            $data['rollNo'] = $participant?->chest_no ?? $participant?->group?->chest_no ?? $participant?->level_registration_number ?? $student->reg_no ?? $student->admission_number;
            $data['category'] = FestItemCategoryLabel::resolve($schedules->first()?->item ?? $participant?->registration?->item, $classGroupLabels, $artsCategoryLabels);
            $data['items'] = $schedules->map(fn (FestSchedule $s) => [
                'title' => $s->item?->title,
                'stage' => $s->festStage?->name ?? $s->stage,
                'time'  => $s->scheduled_at?->format('h:i A'),
            ])->all();

            while (count($data['items']) < 2) {
                $data['items'][] = [];
            }
        } else {
            $boxCount = max(2, min(6, (int) $request->query('items', 3)));
            $data['items'] = array_fill(0, $boxCount, []);
        }

        $sahodaya = \App\Models\Tenant::find($this->school->parent_id);
        $profile = \App\Models\SahodayaProfile::where('tenant_id', $this->school->parent_id)->first();

        $html = view('fest.reports.clash-form', $data + [
            'orgName'     => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'     => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'orgSubtitle' => $profile?->address,
            'orgContact'  => trim(implode('   ', array_filter([$profile?->contact_email, $profile?->contact_phone]))),
            'year'        => now()->format('Y'),
        ])->render();

        if ($request->boolean('raw_html')) {
            return response($html)->header('Content-Type', 'text/html');
        }

        return PdfGenerator::download($html, 'clash-form.pdf', $request->boolean('inline') || $request->boolean('preview') || ! $request->has('download'));
    }
}
