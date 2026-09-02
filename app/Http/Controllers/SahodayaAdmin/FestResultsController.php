<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Events\FestScoreboardUpdated;
use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsItemHeadReportContext;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestPhaseAdvancement;
use App\Models\FestQualification;
use App\Models\FestResult;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\EventContext;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestCmsAutoPush;
use App\Services\Events\FestEventNotifier;
use App\Services\Events\FestItemHeadService;
use App\Services\Events\FestItemResultsService;
use App\Services\Events\FestPhaseAdvancementService;
use App\Services\Events\FestQualificationService;
use App\Services\Events\FestRegionPartitionService;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FestResultsController extends SahodayaAdminController
{
    use BuildsItemHeadReportContext;
    use \App\Http\Controllers\SahodayaAdmin\Concerns\ResolvesRegionAwareReportEvent;

    public function show(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        $event = $this->regionAwareTargetEvent($request, $event);

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

        $qualifications = FestQualification::whereIn('event_id', $event->reportableEventIds())
            ->with(['participant.student', 'participant.teacher', 'item', 'nextLevelEvent'])
            ->latest('promoted_at')
            ->get();

        $qualService = app(FestQualificationService::class);
        $suggestedNext = $qualService->resolveNextLevelEvent($event);

        $nextEvents = collect($qualService->candidateNextEvents($event))
            ->map(fn (FestEvent $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'status' => $e->status,
                'level_round' => $e->level_round,
                'suggested' => $suggestedNext?->id === $e->id,
            ]);

        $childEvents = $this->scopedChildEventOptions($event);

        return $this->inertia('Sahodaya/Events/Results', $this->withEventActivity($event, FestPageActivity::RESULTS, array_merge($ctx, [
            'event' => $event,
            'scoreboard' => EventContext::for($event)->scoreboardBySchool(),
            'qualifications' => $qualifications,
            'nextEvents' => $nextEvents,
            'suggestedNextId' => $suggestedNext?->id,
            'levelLabels' => FestEvent::levelLabels(),
            'itemSummaries' => $itemSummaries->values()->all(),
            'publishTotals' => $resultsService->totals($event),
            'filterHeadId' => $headId === 0 ? 'other' : $headId,
            'selectedHeadId' => $headId === 0 ? 'other' : $headId,
            'filterItemId' => $itemId,
            'selectedItemId' => $itemId,
            'selectedItem' => $selectedItem,
            'itemResultRows' => $itemResultRows,
            'resultsBaseUrl' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/results",
            'marksBaseUrl' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/marks",
            'childEvents' => $childEvents,
        ])));
    }

    /**
     * Full ranked results for one item, as a PDF — deliberately separate from
     * FestReportService::export()'s 'item-wise' report, which is Top-N capped
     * and gated behind the event-wide results_published flag. This is scoped
     * to whatever item the admin already has open on the Results page, so it
     * should work as soon as that item's own marks are in, regardless of
     * whether the rest of the event has been published yet.
     */
    public function downloadItemResults(Request $request, string $tenantId, FestEvent $event, FestEventItem $item)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $event = $this->regionAwareTargetEvent($request, $event);

        $rows = app(FestItemResultsService::class)->resultRowsForItem($event, $item->id);
        usort($rows, fn ($a, $b) => ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX));

        $itemCategory = null;
        if ($item->class_group && $item->class_group !== 'open') {
            $itemCategory = \App\Support\FestClassGroupScheme::resolveItemLabel(
                \App\Support\FestClassGroupScheme::labels(null, $event->rootEvent()),
                $item->class_group,
            );
        }

        $html = view('fest.reports.item-results-ranked', [
            'event'        => $event,
            'item'         => $item,
            'itemCategory' => $itemCategory,
            'rows'         => $rows,
            'orgName'      => $this->sahodaya->name,
            'logoSrc'      => \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya),
        ])->render();

        $filename = str($event->title.'-'.$item->title)->slug()->limit(60)->toString().'-results.pdf';
        $preview = $request->boolean('preview') || $request->boolean('inline');

        return \App\Support\PdfGenerator::download($html, $filename, $preview);
    }

    /** @param list<array<string, mixed>> $groups */
    private function enrichHeadGroupsWithPublishStatus(array $groups, Collection $summaryByItem): array
    {
        return array_map(function (array $group) use ($summaryByItem) {
            $items = array_map(function (array $item) use ($summaryByItem) {
                $summary = $summaryByItem->get($item['id']);

                return array_merge($item, $summary ? [
                    'age_group' => $summary['age_group'] ?? null,
                    'class_group' => $summary['class_group'] ?? null,
                    'gender' => $summary['gender'] ?? null,
                    'sport_discipline' => $summary['sport_discipline'] ?? null,
                    'stage_type' => $summary['stage_type'] ?? null,
                    'performers' => (int) ($summary['performers'] ?? 0),
                    'registration_count' => (int) ($summary['registration_count'] ?? 0),
                    'marks_entered' => (int) ($summary['marks_entered'] ?? 0),
                    'marks_pending' => (int) ($summary['marks_pending'] ?? 0),
                    'marks_ready' => (bool) ($summary['marks_ready'] ?? false),
                    'judges_assigned' => (int) ($summary['judges_assigned'] ?? 0),
                    'results_published' => (bool) ($summary['results_published'] ?? $item['results_published'] ?? false),
                    'results_published_at' => $summary['results_published_at'] ?? $item['results_published_at'] ?? null,
                    'reg_start' => $summary['reg_start'] ?? null,
                    'reg_end' => $summary['reg_end'] ?? null,
                    'item_competition_start' => $summary['item_competition_start'] ?? null,
                    'item_competition_end' => $summary['item_competition_end'] ?? null,
                    'competition_start' => $summary['competition_start'] ?? null,
                    'competition_end' => $summary['competition_end'] ?? null,
                ] : []);
            }, $group['items'] ?? []);

            $group['items'] = $items;
            $group['published_count'] = count(array_filter($items, fn ($i) => $i['results_published'] ?? false));
            $group['pending_count'] = count($items) - $group['published_count'];

            return $group;
        }, $groups);
    }

    public function bulkPublishItems(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer|exists:fest_event_items,id',
        ]);

        $items = FestEventItem::where('event_id', $event->id)
            ->whereIn('id', $data['item_ids'])
            ->get();

        // Compute the full-event completeness aggregate once for this batch instead of once
        // per item — assertCanPublish()/publishItem() previously recomputed it on every
        // iteration, turning an N-item bulk publish into N full-event aggregate scans. See
        // FestItemResultsService::publishItem() docblock for the one known edge case this
        // introduces (grouped/inherited items published in the same batch).
        $resultsService = app(FestItemResultsService::class);
        $summaries = $resultsService->itemSummaries($event);

        $publishedCount = 0;
        foreach ($items as $item) {
            $resultsService->publishItem($item, $summaries);
            $publishedCount++;
        }

        if ($publishedCount > 0) {
            EventContext::for($event)->recalculateSchoolPoints();
            $audit->festEvent($event, FestPageActivity::RESULTS, 'fest.results.bulk_items_published', "Bulk published results for {$publishedCount} item(s)");
        }

        return back()->with('success', "Successfully published results for {$publishedCount} item(s).");
    }

    public function publishItem(string $tenantId, FestEvent $event, FestEventItem $item, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        app(FestItemResultsService::class)->publishItem($item);
        EventContext::for($event)->recalculateSchoolPoints();

        $codeLabel = $item->item_code ? " ({$item->item_code})" : '';
        $audit->festEvent($event, FestPageActivity::RESULTS, 'fest.results.item_published', "Results published for {$item->title}{$codeLabel}", [
            'item_id'   => $item->id,
            'item_code' => $item->item_code,
            'title'     => $item->title,
        ]);

        return back()->with('success', "Results published for {$item->title}.");
    }

    public function unpublishItem(string $tenantId, FestEvent $event, FestEventItem $item, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        app(FestItemResultsService::class)->unpublishItem($item);
        EventContext::for($event)->recalculateSchoolPoints();

        $codeLabel = $item->item_code ? " ({$item->item_code})" : '';
        $audit->festEvent($event, FestPageActivity::RESULTS, 'fest.results.item_unpublished', "Results unpublished for {$item->title}{$codeLabel}", [
            'item_id'   => $item->id,
            'item_code' => $item->item_code,
            'title'     => $item->title,
        ]);

        return back()->with('success', "Results unpublished for {$item->title}.");
    }

    public function publish(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($event->usesPhasedRegionalBilling()) {
            app(\App\Services\Events\FestPhasePublicationService::class)
                ->publishResults($event, $request->user()?->id);
            app(FestCertificateService::class)->generateForEvent($event);
            app(FestCertificateService::class)->generateParticipationForEvent($event);
            app(FestCmsAutoPush::class)->pushScoreboard($event->rootEvent());
            app(FestEventNotifier::class)->resultsPublished($event);
            FestScoreboardUpdated::dispatch($event->rootEvent()->fresh());
            $audit->festEvent($event, FestPageActivity::RESULTS, 'fest.results.phase_published', 'Operational phase results published');

            return back()->with('success', 'Results published for this phase/region.');
        }

        // Verifies (across every region/finale child too, not just the hub itself — see
        // EventLifecycleGate::assertAllParticipantsMarked() and FestJudgeGateService::
        // assertCanPublish()) that marking is actually complete before a hub-level publish
        // is allowed to proceed. Phase 3 audit item 1.
        EventLifecycleGate::allowPublishResults($event);

        // A partitioned hub's marks (and therefore its FestResult school-points rows)
        // live on its region/finale children, never the hub's own event_id — recalculating
        // only $event left every child's FestResult stale as of this publish. Recompute
        // each reportable event (a no-op loop of one for a non-hub event).
        foreach (FestEvent::whereIn('id', $event->reportableEventIds())->get() as $scopeEvent) {
            EventContext::for($scopeEvent)->recalculateSchoolPoints();
        }

        $event->update([
            'results_published' => true,
            'status' => 'completed',
        ]);

        // Cascade to region AND finale/cluster children — a hub-level "Publish Results"
        // represents the whole fest being final, so finale (which the region-only default
        // deliberately excludes for registration/lock fields) needs to move with it too.
        // See FestRegionPartitionService::cascadeLifecycleToChildren()'s $includeFinale doc.
        app(FestRegionPartitionService::class)->cascadeLifecycleToChildren($event, [
            'results_published' => true,
            'status' => 'completed',
        ], includeFinale: true);

        // The public portal uses the summary projection's timestamp as the official
        // publication time. Recalculation creates/updates these rows but does not itself
        // publish them, so stamp every reportable scope only after the lifecycle gate and
        // hub/child publication updates have succeeded.
        $publicationEventIds = $event->parent_event_id ? [$event->id] : $event->reportableEventIds();
        FestResult::whereIn('event_id', $publicationEventIds)
            ->whereNull('item_id')
            ->update([
                'published_at' => now(),
                'published_by' => auth()->id(),
            ]);

        app(FestCertificateService::class)->generateForEvent($event);
        app(FestCertificateService::class)->generateParticipationForEvent($event);
        app(FestCmsAutoPush::class)->pushScoreboard($event);
        app(FestEventNotifier::class)->resultsPublished($event);

        FestScoreboardUpdated::dispatch($event->fresh());

        $audit->festEvent($event, FestPageActivity::RESULTS, 'fest.results.published', 'Results published on public portal');

        return back()->with('success', 'Results published.');
    }

    public function unpublish(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($event->results_published, 422, 'Results are not published.');

        if ($event->usesPhasedRegionalBilling()) {
            app(\App\Services\Events\FestPhasePublicationService::class)->unpublishResults($event);
            app(FestEventNotifier::class)->resultsUnpublished($event);
            $audit->festEvent($event, FestPageActivity::RESULTS, 'fest.results.phase_unpublished', 'Operational phase results unpublished');

            return back()->with('success', 'Results unpublished for this phase/region.');
        }

        $event->update([
            'results_published' => false,
            'status' => 'ongoing',
        ]);

        // LIFE-08 fix (functional audit, 2026-08-11/12): publish() above cascades to
        // region/finale children, pushes the CMS scoreboard, and notifies schools —
        // unpublish() previously only flipped the two columns on the hub itself, leaving
        // every region/finale child (and the public homepage scoreboard) stuck showing
        // "results published" indefinitely. Mirrors publish()'s cascade with the inverse
        // fields. Certificates already generated are deliberately left alone: there is no
        // revoke/invalidate concept anywhere in FestCertificateService, and retroactively
        // clawing back a certificate a school may have already downloaded is a materially
        // different (and much larger) feature than "undo a publish click" — out of scope
        // for this symmetric-cascade fix.
        app(FestRegionPartitionService::class)->cascadeLifecycleToChildren($event, [
            'results_published' => false,
            'status' => 'ongoing',
        ], includeFinale: true);

        $publicationEventIds = $event->parent_event_id ? [$event->id] : $event->reportableEventIds();
        FestResult::whereIn('event_id', $publicationEventIds)
            ->whereNull('item_id')
            ->update([
                'published_at' => null,
                'published_by' => null,
            ]);

        app(FestCmsAutoPush::class)->pushScoreboard($event);
        app(FestEventNotifier::class)->resultsUnpublished($event);

        FestScoreboardUpdated::dispatch($event->fresh());

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
                'page' => FestPageActivity::RESULTS,
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
                'page' => FestPageActivity::RESULTS,
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

    /**
     * Same-event, phase-to-phase advancement (e.g. Off Stage/Sargadhara region winners ->
     * District Kalotsav) — distinct from promote()/promoteAuto() above, which drive the
     * Sahodaya->State qualification cascade. See FestPhaseAdvancementService's docblock.
     */
    public function advancement(Request $request, string $tenantId, FestEvent $event, FestPhaseAdvancementService $service)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        $root = $event->rootEvent();

        $phases = $root->phases()->orderBy('sort_order')->get();
        $items = FestEventItem::where('event_id', $root->id)
            ->where('is_enabled', true)
            ->whereNotNull('phase_id')
            ->orderBy('title')
            ->get(['id', 'event_id', 'title', 'item_code', 'phase_id', 'participant_type', 'class_group', 'category']);

        $classGroupLabels = \App\Support\FestClassGroupScheme::labels(null, $root);
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);
        $items->each(function (FestEventItem $item) use ($classGroupLabels, $artsCategoryLabels) {
            $item->category_label = \App\Support\FestItemCategoryLabel::resolve($item, $classGroupLabels, $artsCategoryLabels);
        });

        $fromItems = $items->filter(fn (FestEventItem $i) => $phases->firstWhere('id', $i->phase_id)?->isRegional());
        $toItems = $items->filter(fn (FestEventItem $i) => ! $phases->firstWhere('id', $i->phase_id)?->isRegional());

        $advancements = FestPhaseAdvancement::where('root_event_id', $root->id)
            ->with(['fromItem:id,title', 'toItem:id,title', 'fromRegistration.school:id,name', 'region:id,name'])
            ->latest('advanced_at')
            ->get();

        $selectedFromItemId = $request->integer('from_item_id') ?: null;
        $candidates = [];
        if ($selectedFromItemId) {
            $fromItem = $items->firstWhere('id', $selectedFromItemId);
            if ($fromItem) {
                $candidates = $service->eligibleCandidates($fromItem);
            }
        }

        return $this->inertia('Sahodaya/Events/PhaseAdvancement', [
            'event' => $event,
            'fromItems' => $fromItems->values(),
            'toItems' => $toItems->values(),
            'advancements' => $advancements,
            'selectedFromItemId' => $selectedFromItemId,
            'candidates' => $candidates,
        ]);
    }

    public function advanceToPhase(Request $request, string $tenantId, FestEvent $event, FestPhaseAdvancementService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $root = $event->rootEvent();
        $data = $request->validate([
            'from_item_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('fest_event_items', 'id')->where('event_id', $root->id)],
            'to_item_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('fest_event_items', 'id')->where('event_id', $root->id)],
            'registration_ids' => 'required|array|min:1',
            'registration_ids.*' => 'integer',
        ]);

        $fromItem = FestEventItem::findOrFail($data['from_item_id']);
        $toItem = FestEventItem::findOrFail($data['to_item_id']);

        $advanced = $service->advance($fromItem, $toItem, $data['registration_ids'], $request->user()?->id);

        $audit->festEvent($event, FestPageActivity::RESULTS, 'fest.results.phase_advancement', "Advanced {$advanced->count()} entr(ies) from {$fromItem->title} to {$toItem->title}", [
            'from_item_id' => $fromItem->id,
            'to_item_id' => $toItem->id,
            'registration_ids' => $data['registration_ids'],
        ]);

        return back()->with('success', "Advanced {$advanced->count()} entr(ies) to {$toItem->title}.");
    }

    public function withdrawAdvancement(string $tenantId, FestEvent $event, FestPhaseAdvancement $advancement, FestPhaseAdvancementService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($advancement->root_event_id !== $event->rootEvent()->id, 404);

        $service->withdraw($advancement, request()->user()?->id);

        $audit->festEvent($event, FestPageActivity::RESULTS, 'fest.results.phase_advancement_withdrawn', 'Phase advancement withdrawn', [
            'advancement_id' => $advancement->id,
        ]);

        return back()->with('success', 'Advancement withdrawn.');
    }

    /**
     * Human-readable class/age-bracket or arts-genre label for an item, for display
     * next to the item's title in the phase-advancement pickers — this page's whole
     * point is moving winners between items, so telling apart same-named items in
     * different categories matters more here than most pickers. Null when the item
     * is in the generic 'open' class group and 'general' arts category.
     *
     * @param  array<string, string>  $classGroupLabels
     */
}
