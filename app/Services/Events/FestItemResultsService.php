<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;

class FestItemResultsService
{

    /** @return array{items: int, published: int, pending: int, marks_ready: int, marks_pending: int} */
    public function totals(FestEvent $event): array
    {
        $rows = $this->itemSummaries($event);

        return [
            'items'         => count($rows),
            'published'     => collect($rows)->where('results_published', true)->count(),
            'pending'       => collect($rows)->where('results_published', false)->count(),
            'marks_ready'   => collect($rows)->where('marks_ready', true)->count(),
            'marks_pending' => collect($rows)->where('marks_ready', false)->where('performers', '>', 0)->count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function itemSummaries(FestEvent $event): array
    {
        $completeness = collect(
            app(FestEventReportAnalyticsService::class, ['event' => $event])->assignmentCompletenessRows(),
        )->keyBy('item_id');

        // For partition child events (e.g. Region 2), restrict items to that event.
        // For Season Hubs, fetch items across all reportable child events.
        $targetEventIds = $event->parent_event_id ? [(int) $event->id] : $event->reportableEventIds();

        $query = FestEventItem::query()
            ->whereIn('event_id', $targetEventIds)
            ->where('is_enabled', true);

        if ($event->event_type === 'sports' && ! empty($event->sport_discipline)) {
            $query->where(fn ($q) => $q->whereNull('sport_discipline')->orWhere('sport_discipline', $event->sport_discipline));
        }

        $items = $query->with('head:id,name,reg_start,reg_end,competition_start,competition_end')
            ->orderBy('display_order')
            ->orderBy('title')
            ->get([
                'id', 'title', 'item_code', 'head_id', 'age_group', 'class_group', 'gender',
                'sport_discipline', 'stage_type', 'reg_start', 'reg_end', 'competition_start',
                'competition_end', 'results_published_at', 'inherited_from_item_id', 'event_id',
            ]);

        // Group items by canonical root item ID (inherited_from_item_id ?: id)
        $grouped = $items->groupBy(fn (FestEventItem $item) => (int) ($item->inherited_from_item_id ?: $item->id));

        $summaries = [];
        foreach ($grouped as $rootId => $itemGroup) {
            $primary = $itemGroup->firstWhere('event_id', $event->id) ?? $itemGroup->first();
            $groupItemIds = $itemGroup->pluck('id')->all();

            $performers = 0;
            $registrationCount = 0;
            $marksEntered = 0;
            $judgesAssigned = 0;
            $anyPublished = false;

            foreach ($groupItemIds as $id) {
                $row = $completeness->get($id);
                if ($row) {
                    $performers += (int) ($row['performers'] ?? 0);
                    $registrationCount += (int) ($row['registration_count'] ?? 0);
                    $marksEntered += (int) ($row['marks_entered'] ?? 0);
                    $judgesAssigned = max($judgesAssigned, (int) ($row['judges_assigned'] ?? 0));
                }
            }

            foreach ($itemGroup as $it) {
                if ($it->results_published_at !== null) {
                    $anyPublished = true;
                    break;
                }
            }

            $marksReady = $performers > 0 && $marksEntered >= $performers;

            $summaries[] = [
                'item_id'               => $primary->id,
                'head_id'               => $primary->head_id,
                'head_name'             => $primary->head?->name,
                'title'                 => $primary->title,
                'item_code'             => $primary->item_code,
                'age_group'             => $primary->age_group,
                'class_group'           => $primary->class_group,
                'gender'                => $primary->gender,
                'sport_discipline'      => $primary->sport_discipline,
                'stage_type'            => $primary->stage_type,
                'performers'            => $performers,
                'registration_count'    => $registrationCount,
                'marks_entered'         => $marksEntered,
                'marks_pending'         => max(0, $performers - $marksEntered),
                'marks_ready'           => $marksReady,
                'judges_assigned'       => $judgesAssigned,
                'results_published'     => $anyPublished,
                'results_published_at'  => $primary->results_published_at?->toIso8601String(),
                'reg_start'             => $primary->reg_start?->format('Y-m-d'),
                'reg_end'               => $primary->reg_end?->format('Y-m-d'),
                'item_competition_start'=> $primary->competition_start?->format('Y-m-d'),
                'item_competition_end'  => $primary->competition_end?->format('Y-m-d'),
                'head_reg_start'        => $primary->head?->reg_start?->format('Y-m-d'),
                'head_reg_end'          => $primary->head?->reg_end?->format('Y-m-d'),
                'head_competition_start'=> $primary->head?->competition_start?->format('Y-m-d'),
                'head_competition_end'  => $primary->head?->competition_end?->format('Y-m-d'),
                'competition_start'     => $primary->competition_start?->format('Y-m-d')
                    ?? $primary->head?->competition_start?->format('Y-m-d'),
                'competition_end'       => $primary->competition_end?->format('Y-m-d')
                    ?? $primary->head?->competition_end?->format('Y-m-d'),
            ];
        }

        return $summaries;
    }

    /** @return list<array<string, mixed>> */
    public function resultRowsForItem(FestEvent $event, int $itemId): array
    {
        $itemIds = $event->reportableItemIds([$itemId]);

        $participants = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $event->reportableEventIds())
                ->whereIn('item_id', $itemIds)
                ->where('status', 'approved'))
            ->with([
                'group',
                'student:id,name,reg_no',
                'teacher:id,name,reg_no',
                'registration.school:id,name',
                'mark' => fn ($q) => $q->whereIn('item_id', $itemIds),
            ])
            ->get();

        $rows = $participants->map(fn (FestParticipant $p) => [
            'participant_id' => $p->id,
            'school'         => $p->registration?->school?->name,
            'name'           => $p->student?->name ?? $p->teacher?->name,
            'reg_no'         => $p->student?->reg_no ?? $p->teacher?->reg_no,
            'chest_no'       => $p->group?->chest_no ?? $p->chest_no,
            'grade'          => $p->mark?->grade,
            'position'       => $p->mark?->position,
            'score'          => $p->mark?->score,
            'measurement'    => $p->mark?->measurement_value,
            'measurement_unit' => $p->mark?->measurement_unit,
        ]);

        return $rows->sortBy([
            fn ($a, $b) => ((int) preg_replace('/[^0-9]/', '', (string) ($a['chest_no'] ?? 999999)))
                <=> ((int) preg_replace('/[^0-9]/', '', (string) ($b['chest_no'] ?? 999999))),
            fn ($a, $b) => ($a['participant_id'] ?? 0) <=> ($b['participant_id'] ?? 0),
        ])->values()->all();
    }

    /**
     * @param  ?list<array<string, mixed>>  $summaries  Pre-fetched itemSummaries() output for
     *                                                    the item's event, to avoid recomputing
     *                                                    the full-event aggregate on every call
     *                                                    when checking many items in a loop (see
     *                                                    FestResultsController::bulkPublishItems()).
     *                                                    Defaults to null, which recomputes
     *                                                    exactly as before — existing single-item
     *                                                    callers are unaffected.
     */
    public function assertCanPublish(FestEventItem $item, ?array $summaries = null): void
    {
        $event = $item->event ?? FestEvent::findOrFail($item->event_id);
        $summaries ??= $this->itemSummaries($event);
        $summary = collect($summaries)->firstWhere('item_id', $item->id);

        abort_unless($summary, 404, 'Item not found.');
        abort_if($summary['results_published'] ?? false, 422, 'Results for this item are already published.');

        $performers = (int) ($summary['performers'] ?? 0);
        abort_if($performers === 0, 422, 'No approved participants for this item.');

        $marksEntered = (int) ($summary['marks_entered'] ?? 0);
        abort_if(
            $marksEntered < $performers,
            422,
            "Enter marks for all participants before publishing ({$marksEntered}/{$performers} marked).",
        );
    }

    /**
     * @param  ?list<array<string, mixed>>  $summaries  See assertCanPublish() — pass a
     *                                                    precomputed itemSummaries() result when
     *                                                    publishing many items from the same event
     *                                                    in one request to avoid an O(items) blowup
     *                                                    of full-event aggregate queries. Note: the
     *                                                    snapshot is taken once before the loop, so
     *                                                    if two items being bulk-published share the
     *                                                    same inherited/root item group, the second
     *                                                    won't see the first's just-published state
     *                                                    in this same request — it will re-run an
     *                                                    idempotent UPDATE instead of hitting the
     *                                                    "already published" guard. No data
     *                                                    inconsistency results, just a skipped
     *                                                    validation message in that rare case.
     */
    public function publishItem(FestEventItem $item, ?array $summaries = null): void
    {
        $this->assertCanPublish($item, $summaries);
        $event = $item->event ?? FestEvent::findOrFail($item->event_id);
        $itemIds = $event->reportableItemIds([$item->id]);

        FestEventItem::whereIn('id', $itemIds)->update(['results_published_at' => now()]);
    }

    public function unpublishItem(FestEventItem $item): void
    {
        $event = $item->event ?? FestEvent::findOrFail($item->event_id);
        $itemIds = $event->reportableItemIds([$item->id]);

        FestEventItem::whereIn('id', $itemIds)->update(['results_published_at' => null]);
    }

    public function isItemVisible(FestEventItem $item, FestEvent $event): bool
    {
        if ($item->results_published_at) {
            return true;
        }

        return (bool) $event->results_published;
    }

}
