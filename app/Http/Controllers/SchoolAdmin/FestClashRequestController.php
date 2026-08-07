<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestClashRequest;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use App\Services\Events\FestRegistrationRouterService;
use App\Support\SchoolFestProgram;
use App\Support\ProgramRouteMap;
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

        $requests = FestClashRequest::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->with(['participant.student', 'scheduleA.item', 'scheduleB.item'])
            ->latest()
            ->get();

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $this->school->id)
            ->where('status', 'approved'))
            ->with(['student', 'registration.item'])
            ->get()
            ->map(function (FestParticipant $p) use ($event) {
                $schedules = FestSchedule::whereIn('event_id', $event->reportableEventIds())
                    ->where('participant_id', $p->id)
                    ->with('item')
                    ->orderBy('scheduled_at')
                    ->get()
                    ->map(fn (FestSchedule $s) => [
                        'id'           => $s->id,
                        'item_title'   => $s->item?->title,
                        'scheduled_at' => $s->scheduled_at?->toIso8601String(),
                        'stage'        => $s->stage,
                    ]);

                return [
                    'id'        => $p->id,
                    'name'      => $p->student?->name ?? $p->teacher?->name,
                    'item'      => $p->registration?->item?->title,
                    'schedules' => $schedules,
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
            'schedule_id_a'        => 'nullable|exists:fest_schedules,id',
            'schedule_id_b'        => 'nullable|exists:fest_schedules,id',
            'description'          => 'required|string|max:2000',
            'requested_resolution' => 'nullable|string|max:2000',
        ]);

        FestParticipant::where('id', $data['participant_id'])
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $event->reportableEventIds())
                ->where('school_id', $this->school->id))
            ->firstOrFail();

        FestClashRequest::create([
            'event_id'               => $event->id,
            'school_id'              => $this->school->id,
            'participant_id'         => $data['participant_id'],
            'schedule_id_a'          => $data['schedule_id_a'] ?? null,
            'schedule_id_b'          => $data['schedule_id_b'] ?? null,
            'description'            => $data['description'],
            'requested_resolution'   => $data['requested_resolution'] ?? null,
            'status'                 => 'pending',
            'requested_by_user_id'   => $request->user()?->id,
        ]);

        return redirect('/school-admin/'.$this->school->id.'/'.ProgramRouteMap::prefixFromSlug($meta['slug'])."/events/{$event->id}/clash-requests")
            ->with('success', 'Clash report submitted.');
    }
}
