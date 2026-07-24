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
        );

        $items = FestEventItem::query()
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('is_enabled', true)
            ->with('head:id,name,reg_start,reg_end,competition_start,competition_end')
            ->orderBy('display_order')
            ->orderBy('title')
            ->get([
                'id', 'title', 'item_code', 'head_id', 'age_group', 'class_group', 'gender',
                'sport_discipline', 'stage_type', 'reg_start', 'reg_end', 'competition_start',
                'competition_end', 'results_published_at',
            ]);

        return $items->map(function (FestEventItem $item) use ($completeness) {
            $row = $completeness->firstWhere('item_id', $item->id) ?? [];
            $performers = (int) ($row['performers'] ?? 0);
            $marksEntered = (int) ($row['marks_entered'] ?? 0);
            $marksReady = $performers > 0 && $marksEntered >= $performers;

            return [
                'item_id'               => $item->id,
                'head_id'               => $item->head_id,
                'head_name'             => $item->head?->name,
                'title'                 => $item->title,
                'item_code'             => $item->item_code,
                'age_group'             => $item->age_group,
                'class_group'           => $item->class_group,
                'gender'                => $item->gender,
                'sport_discipline'      => $item->sport_discipline,
                'stage_type'            => $item->stage_type,
                'performers'            => $performers,
                'registration_count'    => (int) ($row['registration_count'] ?? 0),
                'marks_entered'         => $marksEntered,
                'marks_pending'         => max(0, $performers - $marksEntered),
                'marks_ready'           => $marksReady,
                'judges_assigned'       => (int) ($row['judges_assigned'] ?? 0),
                'results_published'     => $item->results_published_at !== null,
                'results_published_at'  => $item->results_published_at?->toIso8601String(),
                'reg_start'             => $item->reg_start?->format('Y-m-d'),
                'reg_end'               => $item->reg_end?->format('Y-m-d'),
                'item_competition_start'=> $item->competition_start?->format('Y-m-d'),
                'item_competition_end'  => $item->competition_end?->format('Y-m-d'),
                'head_reg_start'        => $item->head?->reg_start?->format('Y-m-d'),
                'head_reg_end'          => $item->head?->reg_end?->format('Y-m-d'),
                'head_competition_start'=> $item->head?->competition_start?->format('Y-m-d'),
                'head_competition_end'  => $item->head?->competition_end?->format('Y-m-d'),
                'competition_start'     => $item->competition_start?->format('Y-m-d')
                    ?? $item->head?->competition_start?->format('Y-m-d'),
                'competition_end'       => $item->competition_end?->format('Y-m-d')
                    ?? $item->head?->competition_end?->format('Y-m-d'),
            ];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function resultRowsForItem(FestEvent $event, int $itemId): array
    {
        $participants = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $event->reportableEventIds())
                ->where('item_id', $itemId)
                ->where('status', 'approved'))
            ->with([
                'student:id,name,reg_no',
                'teacher:id,name,reg_no',
                'registration.school:id,name',
                'mark' => fn ($q) => $q->where('item_id', $itemId),
            ])
            ->orderBy('id')
            ->get();

        return $participants->map(fn (FestParticipant $p) => [
            'participant_id' => $p->id,
            'school'         => $p->registration?->school?->name,
            'name'           => $p->student?->name ?? $p->teacher?->name,
            'reg_no'         => $p->student?->reg_no ?? $p->teacher?->reg_no,
            'chest_no'       => $p->chest_no,
            'grade'          => $p->mark?->grade,
            'position'       => $p->mark?->position,
            'score'          => $p->mark?->score,
            'measurement'    => $p->mark?->measurement_value,
            'measurement_unit' => $p->mark?->measurement_unit,
        ])->values()->all();
    }

    public function assertCanPublish(FestEventItem $item): void
    {
        $event = $item->event ?? FestEvent::findOrFail($item->event_id);
        $summary = collect($this->itemSummaries($event))->firstWhere('item_id', $item->id);

        abort_unless($summary, 404, 'Item not found.');
        abort_if($item->results_published_at, 422, 'Results for this item are already published.');

        $performers = (int) ($summary['performers'] ?? 0);
        abort_if($performers === 0, 422, 'No approved participants for this item.');

        $marksEntered = (int) ($summary['marks_entered'] ?? 0);
        abort_if(
            $marksEntered < $performers,
            422,
            "Enter marks for all participants before publishing ({$marksEntered}/{$performers} marked).",
        );
    }

    public function publishItem(FestEventItem $item): void
    {
        $this->assertCanPublish($item);
        $item->update(['results_published_at' => now()]);
    }

    public function unpublishItem(FestEventItem $item): void
    {
        abort_unless($item->results_published_at, 422, 'This item\'s results are not published.');
        $item->update(['results_published_at' => null]);
    }

    public function isItemVisible(FestEventItem $item, FestEvent $event): bool
    {
        if ($item->results_published_at) {
            return true;
        }

        return (bool) $event->results_published;
    }

}
