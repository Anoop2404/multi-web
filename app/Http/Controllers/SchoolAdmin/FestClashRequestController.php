<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestClashRequest;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use App\Models\Student;
use App\Services\Events\FestRegistrationRouterService;
use App\Services\Events\FestScheduleConflictService;
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
        app(FestRegistrationRouterService::class)->assertSchoolCanAccess($event, $this->school->id);

        // 1. Auto-sync detected schedule conflicts as clash requests for this school.
        // Schools shouldn't have to manually re-type conflicts that the algorithm already detects.
        $conflictService = new FestScheduleConflictService($event);
        $detectedClashes = $conflictService->detectAll($this->school->id);

        if (! empty($detectedClashes)) {
            $clashesByStudent = collect($detectedClashes)->groupBy('student_id');

            foreach ($clashesByStudent as $studentId => $studentClashes) {
                $scheduleIds = $studentClashes->flatMap(fn ($c) => [
                    $c['schedule1_id'] ?? null,
                    $c['schedule2_id'] ?? null,
                ])->filter()->unique()->values()->all();

                if (count($scheduleIds) < 2) {
                    continue;
                }

                // Check if a clash request already exists for this student & overlapping schedules
                $existing = FestClashRequest::where('event_id', $event->id)
                    ->where('school_id', $this->school->id)
                    ->where(function ($q) use ($studentId, $scheduleIds) {
                        $q->whereHas('participant', fn ($p) => $p->where('student_id', $studentId))
                            ->where(function ($sq) use ($scheduleIds) {
                                foreach ($scheduleIds as $sId) {
                                    $sq->orWhereJsonContains('schedule_ids', $sId)
                                        ->orWhere('schedule_id_a', $sId)
                                        ->orWhere('schedule_id_b', $sId);
                                }
                            });
                    })
                    ->first();

                if (! $existing) {
                    $participant = FestParticipant::whereHas('registration', fn ($q) => $q
                        ->whereIn('event_id', $event->reportableEventIds())
                        ->where('school_id', $this->school->id))
                        ->where('student_id', $studentId)
                        ->first();

                    if ($participant) {
                        $itemSummaries = [];
                        foreach ($studentClashes as $sc) {
                            $summary = "{$sc['event1']} ({$sc['item1_time']}) overlaps with {$sc['event2']} ({$sc['item2_time']})";
                            if (! in_array($summary, $itemSummaries, true)) {
                                $itemSummaries[] = $summary;
                            }
                        }

                        FestClashRequest::create([
                            'event_id'             => $event->id,
                            'school_id'            => $this->school->id,
                            'participant_id'       => $participant->id,
                            'schedule_id_a'        => $scheduleIds[0] ?? null,
                            'schedule_id_b'        => $scheduleIds[1] ?? null,
                            'schedule_ids'         => $scheduleIds,
                            'description'          => 'Detected schedule clash: ' . implode('; ', $itemSummaries),
                            'status'               => 'pending',
                            'requested_by_user_id' => auth()->id(),
                        ]);
                    }
                }
            }
        }

        $requestRows = FestClashRequest::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->with(['participant.student', 'participant.group'])
            ->latest()
            ->get();

        $requestScheduleIds = $requestRows
            ->flatMap(fn (FestClashRequest $r) => $r->schedule_ids ?: array_filter([$r->schedule_id_a, $r->schedule_id_b]))
            ->unique()
            ->values();

        $requestSchedulesById = FestSchedule::with(['item:id,title,duration_minutes,calling_buffer_minutes,timing_mode', 'festStage.venue', 'venue'])
            ->whereIn('id', $requestScheduleIds)
            ->get()
            ->keyBy('id');

        $requests = $requestRows->map(fn (FestClashRequest $r) => $r->toArray() + [
            'student_name' => $r->participant?->student?->name,
            'roll_no'      => $r->participant?->level_registration_number ?? $r->participant?->chest_no ?? $r->participant?->student?->reg_no,
            'schedules'    => collect($r->schedule_ids ?: array_filter([$r->schedule_id_a, $r->schedule_id_b]))
                ->map(fn ($id) => $requestSchedulesById->get($id))
                ->filter()
                ->map(fn ($s) => [
                    'id'         => $s->id,
                    'item_title' => $s->item?->title,
                    'stage'      => $this->formatScheduleStage($s),
                    'time'       => $this->formatScheduleSlotTime($s),
                    'date'       => $s->scheduled_at?->format('d M Y'),
                ])
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
                $studentId = $p->student_id;
                $schedules = FestSchedule::whereIn('event_id', $event->reportableEventIds())
                    ->where(function ($q) use ($p, $studentId) {
                        $q->where('participant_id', $p->id);
                        if ($studentId) {
                            $q->orWhereHas('participant', fn ($sq) => $sq->where('student_id', $studentId));
                        }
                    })
                    ->with(['item', 'festStage.venue', 'venue'])
                    ->orderBy('scheduled_at')
                    ->get()
                    ->unique('id')
                    ->values()
                    ->map(fn (FestSchedule $s) => [
                        'id'             => $s->id,
                        'item_title'     => $s->item?->title,
                        'category_label' => FestItemCategoryLabel::resolve($s->item, $classGroupLabels, $artsCategoryLabels),
                        'scheduled_at'   => $s->scheduled_at?->toIso8601String(),
                        'stage'          => $this->formatScheduleStage($s),
                        'time'           => $this->formatScheduleSlotTime($s),
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
            'detectedCount'=> count($detectedClashes),
        ]);
    }

    public function store(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        $meta = SchoolFestProgram::meta($program);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        app(FestRegistrationRouterService::class)->assertSchoolCanAccess($event, $this->school->id);

        $data = $request->validate([
            'participant_id'       => 'required|exists:fest_participants,id',
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
        $studentId = $participant->student_id;
        $schedules = FestSchedule::whereIn('id', $scheduleIds)
            ->whereIn('event_id', $event->reportableEventIds())
            ->with('participant')
            ->get();
        $allBelong = $schedules->count() === count($scheduleIds) && $schedules->every(function ($s) use ($participant, $studentId) {
            return $s->participant_id === $participant->id || ($studentId && $s->participant?->student_id === $studentId);
        });
        abort_unless($allBelong, 422, 'One of the selected slots does not belong to this participant.');

        FestClashRequest::create([
            'event_id'               => $event->id,
            'school_id'              => $this->school->id,
            'participant_id'         => $data['participant_id'],
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

    public function update(Request $request, string $tenantId, FestEvent $event, FestClashRequest $clashRequest, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        app(FestRegistrationRouterService::class)->assertSchoolCanAccess($event, $this->school->id);
        abort_unless($clashRequest->school_id === $this->school->id, 403);

        $data = $request->validate([
            'requested_resolution' => 'nullable|string|max:2000',
        ]);

        $clashRequest->update([
            'requested_resolution' => $data['requested_resolution'] ?? null,
        ]);

        return back()->with('success', 'Resolution suggestion updated.');
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

            $data['date'] = $schedules->first(fn ($s) => $s->scheduled_at !== null)?->scheduled_at?->format('d M Y')
                ?? $clashRequest->created_at?->format('d M Y');
            $data['studentName'] = $clashRequest->participant?->student?->name;
            $data['rollNo'] = $clashRequest->participant?->level_registration_number ?? $clashRequest->participant?->chest_no;
            $data['category'] = FestItemCategoryLabel::resolve($schedules->first()?->item, $classGroupLabels, $artsCategoryLabels);
            $data['items'] = $schedules->map(fn (FestSchedule $s) => [
                'title' => $s->item?->title,
                'stage' => $this->formatScheduleStage($s),
                'time'  => $this->formatScheduleSlotTime($s, $data['date'] ?? null),
            ])->all();

            while (count($data['items']) < 2) {
                $data['items'][] = [];
            }
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

            $data['date'] = $schedules->first(fn ($s) => $s->scheduled_at !== null)?->scheduled_at?->format('d M Y')
                ?? $event->event_start?->format('d M Y')
                ?? now()->format('d M Y');
            $data['studentName'] = $student->name;
            $data['rollNo'] = $participant?->level_registration_number ?? $participant?->chest_no ?? $student->reg_no ?? $participant?->group?->chest_no ?? $student->admission_number;
            $data['category'] = FestItemCategoryLabel::resolve($schedules->first()?->item ?? $participant?->registration?->item, $classGroupLabels, $artsCategoryLabels);
            $data['items'] = $schedules->map(fn (FestSchedule $s) => [
                'title' => $s->item?->title,
                'stage' => $this->formatScheduleStage($s),
                'time'  => $this->formatScheduleSlotTime($s, $data['date'] ?? null),
            ])->all();

            while (count($data['items']) < 2) {
                $data['items'][] = [];
            }
        } else {
            $boxCount = max(2, min(6, (int) $request->query('items', 3)));
            $data['items'] = array_fill(0, $boxCount, []);
        }

        $sahodaya = \App\Models\Tenant::find($this->school->parent_id);

        $html = view('fest.reports.clash-form', $data + [
            'orgName'     => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'     => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'year'        => now()->format('Y'),
        ])->render();

        if ($request->boolean('raw_html')) {
            return response($html)->header('Content-Type', 'text/html');
        }

        return PdfGenerator::download(
            $html,
            'clash-form.pdf',
            $request->boolean('inline') || $request->boolean('preview') || ! $request->has('download'),
            isLandscape: true,
        );
    }

    private function formatScheduleSlotTime(?FestSchedule $schedule, ?string $reportDate = null): ?string
    {
        if (! $schedule || ! $schedule->scheduled_at) {
            return null;
        }

        $startAt = $schedule->scheduled_at;
        $durationMinutes = $schedule->item?->estimatedDurationMinutes()
            ?? $schedule->item?->duration_minutes
            ?? 60;
        $endAt = $startAt->copy()->addMinutes($durationMinutes);

        $datePrefix = ($reportDate && $startAt->format('d M Y') !== $reportDate)
            ? $startAt->format('d M, ')
            : '';

        return "{$datePrefix}{$startAt->format('h:i A')} – {$endAt->format('h:i A')}";
    }

    private function formatScheduleStage(?FestSchedule $schedule): ?string
    {
        if (! $schedule) {
            return null;
        }

        $stageName = $schedule->festStage?->name ?? $schedule->stage;
        $venueName = $schedule->festStage?->venue?->name ?? $schedule->venue?->name;

        return ($stageName && $venueName && ! str_contains(strtolower((string) $stageName), strtolower((string) $venueName)))
            ? "{$stageName} · {$venueName}"
            : ($stageName ?? $venueName);
    }
}
