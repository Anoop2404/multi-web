<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSubstitutionRequest;
use App\Support\SchoolFestProgram;
use App\Support\ProgramRouteMap;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FestSubstitutionRequestController extends SchoolAdminController
{
    public function index(string $tenantId, FestEvent $event, string $program)
    {
        $meta = SchoolFestProgram::meta($program);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $requests = FestSubstitutionRequest::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->with([
                'registration.item',
                'originalParticipant.student',
                'replacementParticipant.student',
                'replacementStudent:id,name,reg_no',
            ])
            ->latest()
            ->get();

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->where('status', 'approved')
            ->with(['item', 'participants.student'])
            ->get()
            ->map(fn (FestRegistration $r) => [
                'id'           => $r->id,
                'item_title'   => $r->item?->title,
                'participants' => $r->participants->map(fn (FestParticipant $p) => [
                    'id'   => $p->id,
                    'name' => $p->student?->name ?? $p->teacher?->name,
                    'role' => $p->participant_role,
                ])->values(),
            ]);

        return $this->inertia('School/Events/SubstitutionRequests', [
            'event'         => $event->only('id', 'title', 'status'),
            'program'       => $meta['slug'],
            'programMeta'   => $meta,
            'requests'      => $requests,
            'registrations' => $registrations,
        ]);
    }

    public function store(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        $meta = SchoolFestProgram::meta($program);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $data = $request->validate([
            'registration_id'            => 'required|exists:fest_registrations,id',
            'original_participant_id'    => 'required|exists:fest_participants,id',
            'replacement_participant_id' => 'nullable|exists:fest_participants,id',
            'replacement_student_id'     => 'nullable|exists:students,id',
            'reason'                     => 'required|string|max:2000',
        ]);

        if (empty($data['replacement_participant_id']) && empty($data['replacement_student_id'])) {
            throw ValidationException::withMessages([
                'replacement_participant_id' => 'Select a standby participant or replacement student.',
            ]);
        }

        $registration = FestRegistration::where('id', $data['registration_id'])
            ->where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->where('status', 'approved')
            ->firstOrFail();

        $original = FestParticipant::where('id', $data['original_participant_id'])
            ->where('registration_id', $registration->id)
            ->firstOrFail();

        if (! empty($data['replacement_participant_id'])) {
            FestParticipant::where('id', $data['replacement_participant_id'])
                ->where('registration_id', $registration->id)
                ->where('participant_role', 'standby')
                ->firstOrFail();
        }

        FestSubstitutionRequest::create([
            'event_id'                   => $event->id,
            'school_id'                  => $this->school->id,
            'registration_id'            => $registration->id,
            'original_participant_id'    => $original->id,
            'replacement_participant_id' => $data['replacement_participant_id'] ?? null,
            'replacement_student_id'     => $data['replacement_student_id'] ?? null,
            'reason'                     => $data['reason'],
            'status'                     => 'pending',
            'requested_by_user_id'       => $request->user()?->id,
        ]);

        return redirect('/school-admin/'.$this->school->id.'/'.ProgramRouteMap::prefixFromSlug($meta['slug'])."/events/{$event->id}/substitution-requests")
            ->with('success', 'Substitution request submitted.');
    }
}
