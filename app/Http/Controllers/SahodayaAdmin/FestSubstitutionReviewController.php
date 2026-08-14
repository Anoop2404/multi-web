<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestSubstitutionRequest;
use App\Services\Events\FestRegistrationService;
use Illuminate\Http\Request;

class FestSubstitutionReviewController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // Same hub-aggregation gap as FestClashReviewController — substitution requests are
        // stored against the school's actual region/finale child event (see
        // FestSubstitutionRequestController, which already reads via reportableEventIds()),
        // so a hub admin reviewing from the hub page needs every region's requests here too
        // (Phase 9 audit).
        $requests = FestSubstitutionRequest::whereIn('event_id', $event->reportableEventIds())
            ->with([
                'school:id,name',
                'registration.item',
                'originalParticipant.student',
                'replacementParticipant.student',
                'replacementStudent:id,name,reg_no',
            ])
            ->latest()
            ->paginate(30);

        return $this->inertia('Sahodaya/Events/SubstitutionReview', [
            'event'    => $event->only('id', 'title'),
            'requests' => $requests,
        ]);
    }

    public function approve(Request $request, string $tenantId, FestEvent $event, FestSubstitutionRequest $substitutionRequest)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless(in_array($substitutionRequest->event_id, $event->reportableEventIds(), true), 403);
        abort_unless($substitutionRequest->status === 'pending', 422, 'Only pending substitution requests can be reviewed.');

        $data = $request->validate(['resolution_note' => 'nullable|string|max:2000']);

        $original = $substitutionRequest->originalParticipant;
        abort_unless($original, 422, 'Original participant not found.');

        $registration = $substitutionRequest->registration;
        $schoolId = $registration?->school_id;

        if ($substitutionRequest->replacement_participant_id) {
            $standby = FestParticipant::findOrFail($substitutionRequest->replacement_participant_id);
            abort_if($standby->registration->school_id !== $schoolId, 422, 'The replacement standby participant belongs to a different school.');
            app(FestRegistrationService::class)->substitutePerformer($original, $standby);
        } elseif ($substitutionRequest->replacement_student_id) {
            // Verify the replacement student belongs to the same school as the original registration.
            $student = \App\Models\Student::findOrFail($substitutionRequest->replacement_student_id);
            abort_if($student->tenant_id !== $schoolId, 422, 'The replacement student belongs to a different school.');
            $original->update(['student_id' => $substitutionRequest->replacement_student_id]);
        }

        $substitutionRequest->update([
            'status'               => 'approved',
            'resolution_note'        => $data['resolution_note'] ?? null,
            'reviewed_by_user_id'    => $request->user()?->id,
            'reviewed_at'            => now(),
        ]);

        return back()->with('success', 'Substitution request approved.');
    }

    public function reject(Request $request, string $tenantId, FestEvent $event, FestSubstitutionRequest $substitutionRequest)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless(in_array($substitutionRequest->event_id, $event->reportableEventIds(), true), 403);
        abort_unless($substitutionRequest->status === 'pending', 422, 'Only pending substitution requests can be reviewed.');

        $data = $request->validate(['resolution_note' => 'nullable|string|max:2000']);

        $substitutionRequest->update([
            'status'               => 'rejected',
            'resolution_note'        => $data['resolution_note'] ?? null,
            'reviewed_by_user_id'    => $request->user()?->id,
            'reviewed_at'            => now(),
        ]);

        return back()->with('success', 'Substitution request rejected.');
    }
}
