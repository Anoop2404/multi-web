<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestJudgeAssignment;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FestJudgeGateService
{
    public function assertCanPublish(FestEvent $event): void
    {
        if (! $event->require_judge_scores_before_publish) {
            return;
        }

        $items = FestEventItem::where('event_id', $event->id)->pluck('id');
        $assignments = FestJudgeAssignment::where('event_id', $event->id)->get()->groupBy('item_id');

        $pending = [];

        foreach ($items as $itemId) {
            $judges = $assignments->get($itemId, collect());
            if ($judges->isEmpty()) {
                continue;
            }

            $participantCount = FestParticipant::whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('item_id', $itemId)
                ->where('status', 'approved'))
                ->count();

            if ($participantCount === 0) {
                continue;
            }

            $marked = FestMark::where('event_id', $event->id)
                ->where('item_id', $itemId)
                ->where(function ($q) {
                    $q->whereNotNull('score')->orWhereNotNull('grade');
                })
                ->count();

            if ($marked < $participantCount) {
                $item = FestEventItem::find($itemId);
                $pending[] = $item?->title ?? "Item #{$itemId}";
            }
        }

        if ($pending !== []) {
            throw new HttpException(422, 'Judge scores incomplete for: '.implode(', ', $pending));
        }
    }

    /** @return array{complete: int, total: int, items: list<array{item: string, marked: int, total: int}>} */
    public function status(FestEvent $event): array
    {
        $rows = [];
        $complete = 0;
        $total = 0;

        foreach (FestEventItem::where('event_id', $event->id)->get() as $item) {
            $participants = FestRegistration::where('event_id', $event->id)
                ->where('item_id', $item->id)
                ->where('status', 'approved')
                ->count();

            if ($participants === 0) {
                continue;
            }

            $marked = FestMark::where('event_id', $event->id)
                ->where('item_id', $item->id)
                ->where(function ($q) {
                    $q->whereNotNull('score')->orWhereNotNull('grade');
                })
                ->count();

            $total += $participants;
            $complete += min($marked, $participants);

            $rows[] = [
                'item'   => $item->title,
                'marked' => $marked,
                'total'  => $participants,
            ];
        }

        return [
            'complete' => $complete,
            'total'    => $total,
            'items'    => $rows,
        ];
    }
}
