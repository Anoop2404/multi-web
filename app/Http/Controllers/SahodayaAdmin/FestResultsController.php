<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsItemHeadReportContext;
use App\Models\FestEventItem;
use App\Support\FestPageActivity;
use App\Events\FestScoreboardUpdated;
use App\Models\FestEvent;
use App\Models\FestQualification;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\EventContext;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestCmsAutoPush;
use App\Services\Events\FestEventNotifier;
use App\Services\Events\FestItemHeadService;
use App\Services\Events\FestItemResultsService;
use App\Services\Events\FestQualificationService;
use Illuminate\Http\Request;

class FestResultsController extends SahodayaAdminController
{
    use BuildsItemHeadReportContext;

    public function show(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($event->event_type === 'sports') {
            app(FestItemHeadService::class)->syncEventHeads($event);
        }

        $headId = $this->resolveHeadQueryParam($request->input('head_id'));
        $itemId = $request->integer('item_id') ?: null;

        $resultsService = app(FestItemResultsService::class);
        $itemSummaries = collect($resultsService->itemSummaries($event));
        $summaryByItem = $itemSummaries->keyBy('item_id');

        $reportCtx = $this->itemHeadReportContext($event);
        $ctx = array_merge($reportCtx, [
            'headItemGroups' => $this->enrichHeadGroupsWithPublishStatus(
                $reportCtx['headItemGroups'] ?? [],
                $summaryByItem,
            ),
        ]);

        $selectedItem = $itemId ? $itemSummaries->firstWhere('item_id', $itemId) : null;
        $itemResultRows = $selectedItem
            ? $resultsService->resultRowsForItem($event, $itemId)
            : [];

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

        return $this->inertia('Sahodaya/Events/Results', $this->withEventActivity($event, FestPageActivity::RESULTS, array_merge($ctx, [
            'event'             => $event,
            'scoreboard'        => EventContext::for($event)->scoreboardBySchool(),
            'qualifications'    => $qualifications,
            'nextEvents'        => $nextEvents,
            'suggestedNextId'   => $suggestedNext?->id,
            'levelLabels'       => FestEvent::levelLabels(),
            'itemSummaries'     => $itemSummaries->values()->all(),
            'publishTotals'     => $resultsService->totals($event),
            'filterHeadId'      => $headId === 0 ? 'other' : $headId,
            'selectedHeadId'    => $headId === 0 ? 'other' : $headId,
            'filterItemId'      => $itemId,
            'selectedItemId'    => $itemId,
            'selectedItem'      => $selectedItem,
            'itemResultRows'    => $itemResultRows,
            'resultsBaseUrl'    => "/sahodaya-admin/{$tenantId}/events/{$event->id}/results",
            'marksBaseUrl'      => "/sahodaya-admin/{$tenantId}/events/{$event->id}/marks",
        ])));
    }

    /** @param list<array<string, mixed>> $groups */
    private function enrichHeadGroupsWithPublishStatus(array $groups, \Illuminate\Support\Collection $summaryByItem): array
    {
        return array_map(function (array $group) use ($summaryByItem) {
            $items = array_map(function (array $item) use ($summaryByItem) {
                $summary = $summaryByItem->get($item['id']);

                return array_merge($item, $summary ? [
                    'age_group'             => $summary['age_group'] ?? null,
                    'class_group'           => $summary['class_group'] ?? null,
                    'gender'                => $summary['gender'] ?? null,
                    'sport_discipline'      => $summary['sport_discipline'] ?? null,
                    'stage_type'            => $summary['stage_type'] ?? null,
                    'performers'            => (int) ($summary['performers'] ?? 0),
                    'registration_count'    => (int) ($summary['registration_count'] ?? 0),
                    'marks_entered'         => (int) ($summary['marks_entered'] ?? 0),
                    'marks_pending'         => (int) ($summary['marks_pending'] ?? 0),
                    'marks_ready'           => (bool) ($summary['marks_ready'] ?? false),
                    'judges_assigned'       => (int) ($summary['judges_assigned'] ?? 0),
                    'results_published'     => (bool) ($summary['results_published'] ?? $item['results_published'] ?? false),
                    'results_published_at'  => $summary['results_published_at'] ?? $item['results_published_at'] ?? null,
                    'reg_start'             => $summary['reg_start'] ?? null,
                    'reg_end'               => $summary['reg_end'] ?? null,
                    'item_competition_start'=> $summary['item_competition_start'] ?? null,
                    'item_competition_end'  => $summary['item_competition_end'] ?? null,
                    'competition_start'     => $summary['competition_start'] ?? null,
                    'competition_end'       => $summary['competition_end'] ?? null,
                ] : []);
            }, $group['items'] ?? []);

            $group['items'] = $items;
            $group['published_count'] = count(array_filter($items, fn ($i) => $i['results_published'] ?? false));
            $group['pending_count'] = count($items) - $group['published_count'];

            return $group;
        }, $groups);
    }

    public function publishItem(string $tenantId, FestEvent $event, FestEventItem $item, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        app(FestItemResultsService::class)->publishItem($item);
        EventContext::for($event)->recalculateSchoolPoints();

        $audit->festEvent($event, FestPageActivity::RESULTS, 'fest.results.item_published', "Results published for {$item->title}", [
            'item_id' => $item->id,
        ]);

        return back()->with('success', "Results published for {$item->title}.");
    }

    public function unpublishItem(string $tenantId, FestEvent $event, FestEventItem $item, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        app(FestItemResultsService::class)->unpublishItem($item);

        $audit->festEvent($event, FestPageActivity::RESULTS, 'fest.results.item_unpublished', "Results unpublished for {$item->title}", [
            'item_id' => $item->id,
        ]);

        return back()->with('success', "Results unpublished for {$item->title}.");
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
