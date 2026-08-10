<?php

namespace App\Services\State;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestStateProgram;
use App\Models\User;

class FestStateNominationService
{
    /**
     * Build the eligible candidate pool from combined Region/Finale certified results.
     *
     * @return array<int, array<string, mixed>>
     */
    public function candidatePool(FestStateProgram $program, FestEvent $hubEvent): array
    {
        $candidates = [];

        $items = FestEventItem::where('event_id', $hubEvent->id)->get();

        foreach ($items as $item) {
            if (! $item->state_program_item_id) {
                continue;
            }

            $marks = FestMark::where('event_id', $hubEvent->id)
                ->where('item_id', $item->id)
                ->whereNotNull('position')
                ->with(['participant.registration', 'participant.student'])
                ->orderBy('position')
                ->get();

            foreach ($marks as $mark) {
                $participant = $mark->participant;
                if (! $participant || ! $participant->registration) {
                    continue;
                }

                $candidates[] = [
                    'mark_id'                => $mark->id,
                    'item_id'                => $item->state_program_item_id,
                    'item_code'              => $item->item_code,
                    'item_title'             => $item->title,
                    'school_id'              => $participant->registration->school_id,
                    'student_name'           => $participant->student?->name ?? 'Participant',
                    'source_position'        => $mark->position,
                    'grade'                  => $mark->grade,
                    'score'                  => $mark->score,
                    'is_eligible'            => true,
                ];
            }
        }

        return $candidates;
    }

    /**
     * Submit maker nomination for checker review.
     *
     * @param array<int, array{mark_id: int, nomination_type: string, priority_order: int}> $selections
     */
    public function createMakerNomination(FestStateProgram $program, FestEvent $hubEvent, User $maker, array $selections): array
    {
        return [
            'state_program_id' => $program->id,
            'hub_event_id'     => $hubEvent->id,
            'maker_id'         => $maker->id,
            'status'           => 'ready_for_check',
            'selections'       => $selections,
            'created_at'       => now()->toIso8601String(),
        ];
    }

    /**
     * Certify nomination by checker (must be a different authorized user than maker).
     */
    public function certifyCheckerNomination(array $nominationBatch, User $checker): array
    {
        if ($nominationBatch['maker_id'] === $checker->id) {
            throw new \InvalidArgumentException('Checker cannot be the same user as the nomination maker.');
        }

        $nominationBatch['checker_id'] = $checker->id;
        $nominationBatch['certified_at'] = now()->toIso8601String();
        $nominationBatch['status'] = 'certified';

        return $nominationBatch;
    }
}
