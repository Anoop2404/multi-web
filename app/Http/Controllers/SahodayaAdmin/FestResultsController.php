<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestQualification;
use App\Models\FestRegistration;
use App\Services\Events\EventContext;
use App\Services\Events\FestQualificationService;
use Illuminate\Http\Request;

class FestResultsController extends SahodayaAdminController
{
    public function show(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $ctx = EventContext::for($event);

        $qualifications = FestQualification::where('event_id', $event->id)
            ->with(['participant.student', 'item', 'nextLevelEvent'])
            ->latest('promoted_at')
            ->get();

        $nextEvents = FestEvent::where('tenant_id', $this->sahodaya->id)
            ->where('id', '!=', $event->id)
            ->whereIn('status', ['draft', 'published', 'registration_open'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'status']);

        return $this->inertia('Sahodaya/Events/Results', [
            'event'          => $event,
            'scoreboard'     => $ctx->scoreboardBySchool(),
            'qualifications' => $qualifications,
            'nextEvents'     => $nextEvents,
        ]);
    }

    public function publish(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        EventContext::for($event)->recalculateSchoolPoints();

        $event->update([
            'results_published' => true,
            'status'            => 'completed',
        ]);

        app(\App\Services\Events\FestCertificateService::class)->generateForEvent($event);
        app(\App\Services\Events\FestEventNotifier::class)->resultsPublished($event);

        return back()->with('success', 'Results published.');
    }

    public function promote(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'next_event_id' => 'required|exists:fest_events,id',
        ]);

        $toEvent = FestEvent::findOrFail($data['next_event_id']);
        abort_if($toEvent->tenant_id !== $this->sahodaya->id, 403);

        $result = app(FestQualificationService::class)->promoteWinners($event, $toEvent);

        return back()->with('success', "{$result['promoted']} participant(s) promoted. {$result['skipped']} skipped.");
    }
}
