<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Events\FestScoreboardUpdated;
use App\Models\FestEvent;
use App\Models\FestQualification;
use App\Models\FestRegistration;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\EventContext;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestCmsAutoPush;
use App\Services\Events\FestEventNotifier;
use App\Services\Events\FestQualificationService;
use App\Services\Events\FestRegistrationService;
use Illuminate\Http\Request;

class FestResultsController extends SahodayaAdminController
{
    public function show(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $ctx = EventContext::for($event);

        $qualifications = FestQualification::where('event_id', $event->id)
            ->with(['participant.student', 'participant.teacher', 'item', 'nextLevelEvent'])
            ->latest('promoted_at')
            ->get();

        $qualService = app(FestQualificationService::class);
        $suggestedNext = $qualService->resolveNextLevelEvent($event);

        $nextEvents = collect($qualService->candidateNextEvents($event))
            ->map(fn (FestEvent $e) => [
                'id'          => $e->id,
                'title'       => $e->title,
                'status'      => $e->status,
                'level_round' => $e->level_round,
                'suggested'   => $suggestedNext?->id === $e->id,
            ]);

        return $this->inertia('Sahodaya/Events/Results', $this->withEventActivity($event, FestPageActivity::RESULTS, [
            'event'             => $event,
            'scoreboard'        => $ctx->scoreboardBySchool(),
            'qualifications'    => $qualifications,
            'nextEvents'        => $nextEvents,
            'suggestedNextId'   => $suggestedNext?->id,
            'levelLabels'       => FestEvent::levelLabels(),
        ]));
    }

    public function publish(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        EventLifecycleGate::allowPublishResults($event);

        EventContext::for($event)->recalculateSchoolPoints();

        $event->update([
            'results_published' => true,
            'status'            => 'completed',
        ]);

        app(\App\Services\Events\FestCertificateService::class)->generateForEvent($event);
        app(\App\Services\Events\FestCertificateService::class)->generateParticipationForEvent($event);
        app(\App\Services\Events\FestCmsAutoPush::class)->pushScoreboard($event);
        app(\App\Services\Events\FestEventNotifier::class)->resultsPublished($event);

        FestScoreboardUpdated::dispatch($event->fresh());

        $audit->festEvent($event, FestPageActivity::RESULTS, 'fest.results.published', 'Results published on public portal');

        return back()->with('success', 'Results published.');
    }

    public function unpublish(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($event->results_published, 422, 'Results are not published.');

        $event->update([
            'results_published' => false,
            'status'            => 'ongoing',
        ]);

        $audit->festEvent($event, FestPageActivity::RESULTS, 'fest.results.unpublished', 'Results unpublished');

        return back()->with('success', 'Results unpublished.');
    }

    public function promote(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'next_event_id' => 'required|exists:fest_events,id',
        ]);

        $toEvent = FestEvent::findOrFail($data['next_event_id']);
        abort_if($toEvent->tenant_id !== $this->sahodaya->id, 403);

        $qualService = app(FestQualificationService::class);
        $result = $qualService->promoteWinners($event, $toEvent);

        if ($result['promoted'] > 0) {
            app(FestEventNotifier::class)->promotionCompleted($toEvent, $result['promoted'], $event);
            app(PlatformAuditLogger::class)->festPromotionCompleted($toEvent, $result['promoted'], [
                'page'          => FestPageActivity::RESULTS,
                'from_event_id' => $event->id,
            ]);
        }

        return back()->with('success', "{$result['promoted']} participant(s) promoted. {$result['skipped']} skipped.");
    }

    public function promoteAuto(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $qualService = app(FestQualificationService::class);
        $toEvent = $qualService->resolveNextLevelEvent($event);
        abort_unless($toEvent, 422, 'No next-level event found. Create a parent or state-round event first.');

        $result = $qualService->promoteWinners($event, $toEvent);

        if ($result['promoted'] > 0) {
            app(FestEventNotifier::class)->promotionCompleted($toEvent, $result['promoted'], $event);
            app(PlatformAuditLogger::class)->festPromotionCompleted($toEvent, $result['promoted'], [
                'page'          => FestPageActivity::RESULTS,
                'from_event_id' => $event->id,
            ]);
        }

        return back()->with('success', "{$result['promoted']} promoted to {$toEvent->title}. {$result['skipped']} skipped.");
    }

    public function revokePromotion(string $tenantId, FestEvent $event, FestQualification $qualification)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($qualification->event_id !== $event->id, 404);

        app(FestQualificationService::class)->revokeQualification($qualification);

        return back()->with('success', 'Promotion revoked and next-level registration cancelled.');
    }
}
