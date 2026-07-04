<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestClashRequest;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use App\Support\SchoolFestProgram;
use App\Support\ProgramRouteMap;
use Illuminate\Http\Request;

class FestClashRequestController extends SchoolAdminController
{
    public function index(FestEvent $event, string $program)
    {
        $meta = SchoolFestProgram::meta($program);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $requests = FestClashRequest::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->with(['participant.student', 'scheduleA.item', 'scheduleB.item'])
            ->latest()
            ->get();

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->where('status', 'approved'))
            ->with(['student', 'registration.item'])
            ->get()
            ->map(function (FestParticipant $p) use ($event) {
                $schedules = FestSchedule::where('event_id', $event->id)
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

        $data = $request->validate([
            'participant_id'       => 'required|exists:fest_participants,id',
            'schedule_id_a'        => 'nullable|exists:fest_schedules,id',
            'schedule_id_b'        => 'nullable|exists:fest_schedules,id',
            'description'          => 'required|string|max:2000',
            'requested_resolution' => 'nullable|string|max:2000',
        ]);

        FestParticipant::where('id', $data['participant_id'])
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
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
