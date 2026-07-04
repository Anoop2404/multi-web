<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestClashRequest;
use App\Models\FestEvent;
use Illuminate\Http\Request;

class FestClashReviewController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $requests = FestClashRequest::where('event_id', $event->id)
            ->with([
                'school:id,name',
                'participant.student',
                'scheduleA.item',
                'scheduleB.item',
            ])
            ->latest()
            ->paginate(30);

        return $this->inertia('Sahodaya/Events/ClashReview', [
            'event'    => $event->only('id', 'title'),
            'requests' => $requests,
        ]);
    }

    public function approve(Request $request, string $tenantId, FestEvent $event, FestClashRequest $clashRequest)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($clashRequest->event_id !== $event->id, 403);
        abort_unless($clashRequest->status === 'pending', 422);

        $data = $request->validate(['resolution_note' => 'nullable|string|max:2000']);

        $clashRequest->update([
            'status'               => 'approved',
            'resolution_note'        => $data['resolution_note'] ?? null,
            'reviewed_by_user_id'    => $request->user()?->id,
            'reviewed_at'            => now(),
        ]);

        return back()->with('success', 'Clash request marked resolved.');
    }

    public function reject(Request $request, string $tenantId, FestEvent $event, FestClashRequest $clashRequest)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($clashRequest->event_id !== $event->id, 403);
        abort_unless($clashRequest->status === 'pending', 422);

        $data = $request->validate(['resolution_note' => 'nullable|string|max:2000']);

        $clashRequest->update([
            'status'               => 'rejected',
            'resolution_note'        => $data['resolution_note'] ?? null,
            'reviewed_by_user_id'    => $request->user()?->id,
            'reviewed_at'            => now(),
        ]);

        return back()->with('success', 'Clash request rejected.');
    }
}
