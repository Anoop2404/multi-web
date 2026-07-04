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

        $requests = FestSubstitutionRequest::where('event_id', $event->id)
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
        abort_if($substitutionRequest->event_id !== $event->id, 403);
        abort_unless($substitutionRequest->status === 'pending', 422);

        $data = $request->validate(['resolution_note' => 'nullable|string|max:2000']);

        $original = $substitutionRequest->originalParticipant;
        abort_unless($original, 422, 'Original participant not found.');

        if ($substitutionRequest->replacement_participant_id) {
            $standby = FestParticipant::findOrFail($substitutionRequest->replacement_participant_id);
            app(FestRegistrationService::class)->substitutePerformer($original, $standby);
        } elseif ($substitutionRequest->replacement_student_id) {
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
        abort_if($substitutionRequest->event_id !== $event->id, 403);
        abort_unless($substitutionRequest->status === 'pending', 422);

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
