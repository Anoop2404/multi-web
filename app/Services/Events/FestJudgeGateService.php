<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestJudgeAssignment;
use App\Models\FestJudgeScore;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FestJudgeGateService
{
    public function assertCanPublish(FestEvent $event): void
    {
        if ($event->event_type === 'sports' || ! $event->require_judge_scores_before_publish) {
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

            $participantIds = FestParticipant::whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('item_id', $itemId)
                ->where('status', 'approved'))
                ->pluck('id');

            if ($participantIds->isEmpty()) {
                continue;
            }

            $requiredScores = $participantIds->count() * $judges->count();
            $entered = FestJudgeScore::where('event_id', $event->id)
                ->where('item_id', $itemId)
                ->whereIn('participant_id', $participantIds)
                ->whereIn('judge_user_id', $judges->pluck('user_id'))
                ->where(function ($q) {
                    $q->whereNotNull('score')->orWhereNotNull('grade');
                })
                ->count();

            if ($entered < $requiredScores) {
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
        if ($event->event_type === 'sports') {
            return $this->sportsMarkStatus($event);
        }

        $rows = [];
        $complete = 0;
        $total = 0;

        foreach (FestEventItem::where('event_id', $event->id)->get() as $item) {
            $judges = FestJudgeAssignment::where('event_id', $event->id)
                ->where('item_id', $item->id)
                ->pluck('user_id');

            $participants = FestRegistration::where('event_id', $event->id)
                ->where('item_id', $item->id)
                ->where('status', 'approved')
                ->count();

            if ($participants === 0) {
                continue;
            }

            if ($judges->isEmpty()) {
                $marked = FestMark::where('event_id', $event->id)
                    ->where('item_id', $item->id)
                    ->where(function ($q) {
                        $q->whereNotNull('score')->orWhereNotNull('grade');
                    })
                    ->count();
                $itemTotal = $participants;
                $itemComplete = min($marked, $participants);
            } else {
                $participantIds = FestParticipant::whereHas('registration', fn ($q) => $q
                    ->where('event_id', $event->id)
                    ->where('item_id', $item->id)
                    ->where('status', 'approved'))
                    ->pluck('id');

                $itemTotal = $participantIds->count() * $judges->count();
                $itemComplete = FestJudgeScore::where('event_id', $event->id)
                    ->where('item_id', $item->id)
                    ->whereIn('participant_id', $participantIds)
                    ->whereIn('judge_user_id', $judges)
                    ->where(function ($q) {
                        $q->whereNotNull('score')->orWhereNotNull('grade');
                    })
                    ->count();
            }

            $total += $itemTotal;
            $complete += $itemComplete;

            $rows[] = [
                'item'   => $item->title,
                'marked' => $itemComplete,
                'total'  => $itemTotal,
            ];
        }

        return [
            'complete' => $complete,
            'total'    => $total,
            'items'    => $rows,
        ];
    }

    /** @return array{complete: int, total: int, items: list<array{item: string, marked: int, total: int}>} */
    private function sportsMarkStatus(FestEvent $event): array
    {
        $rows = [];
        $complete = 0;
        $total = 0;

        foreach (FestEventItem::where('event_id', $event->id)->where('is_enabled', true)->get() as $item) {
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
                    $q->whereNotNull('score')->orWhereNotNull('grade')->orWhereNotNull('position');
                })
                ->count();

            $itemComplete = min($marked, $participants);
            $total += $participants;
            $complete += $itemComplete;

            $rows[] = [
                'item'   => $item->title,
                'marked' => $itemComplete,
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
