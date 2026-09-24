<?php

namespace App\Services\State\Fest;

use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 5 of the State Kalotsav module — chest numbers.
 *
 * A chest number identifies a competitor to judges and on every printed sheet, so the one property
 * that matters more than the numbering scheme is that a number, once issued, does not move. The
 * database enforces uniqueness per event; this service never reassigns an existing number, only
 * fills gaps — assigning "all" after a sheet has been printed must not renumber the people on it.
 *
 * Numbers are allocated per Sahodaya block by default, because that is how a contingent arrives,
 * is seated and is called: a Sahodaya with numbers 200–239 can be handed one sheet.
 */
class StateChestNumberService
{
    /**
     * Allocate numbers to everyone who does not have one.
     *
     * @param  array{start?: int, block_size?: int, sahodaya_id?: ?string}  $options
     * @return array{assigned: int, skipped: int, blocks: array<string, array{from: int, to: int}>}
     */
    public function assignMissing(StateFestEvent $event, array $options = []): array
    {
        $start = max((int) ($options['start'] ?? 1), 1);
        $blockSize = (int) ($options['block_size'] ?? 0);

        $taken = $this->takenNumbers($event);
        $assigned = 0;
        $skipped = 0;
        $blocks = [];

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->where('status', 'approved')
            ->when($options['sahodaya_id'] ?? null, fn ($q, $id) => $q->where('sahodaya_id', $id))
            ->with('participants')
            ->orderBy('sahodaya_name')->orderBy('school_name')->orderBy('item_code')
            ->get()
            ->groupBy('sahodaya_id');

        DB::connection('state')->transaction(function () use ($registrations, $start, $blockSize, &$taken, &$assigned, &$skipped, &$blocks) {
            $cursor = $start;

            foreach ($registrations as $sahodayaId => $group) {
                $sahodayaName = $group->first()->sahodaya_name ?: (string) $sahodayaId;
                $blockStart = $cursor;

                foreach ($group as $registration) {
                    foreach ($registration->participants as $participant) {
                        // Standbys are not on the stage, so they are not numbered until they are
                        // substituted in — at which point they inherit the number being vacated.
                        if (! $participant->isCompeting()) {
                            continue;
                        }

                        if (filled($participant->chest_number)) {
                            // Never reissued: the number is already on a printed sheet.
                            $skipped++;

                            continue;
                        }

                        while (isset($taken[(string) $cursor])) {
                            $cursor++;
                        }

                        $participant->forceFill(['chest_number' => (string) $cursor])->save();
                        $taken[(string) $cursor] = true;
                        $assigned++;
                        $cursor++;
                    }
                }

                if ($cursor > $blockStart) {
                    $blocks[$sahodayaName] = ['from' => $blockStart, 'to' => $cursor - 1];
                }

                // Round each Sahodaya up to the next block so a contingent's numbers stay contiguous
                // even when entries are added later.
                if ($blockSize > 0) {
                    $cursor = $blockStart + (int) (ceil(max($cursor - $blockStart, 1) / $blockSize) * $blockSize);
                }
            }
        });

        return ['assigned' => $assigned, 'skipped' => $skipped, 'blocks' => $blocks];
    }

    /** Correct one number by hand. Refuses a duplicate rather than silently moving someone else's. */
    public function setNumber(StateFestEvent $event, int $participantId, ?string $number): StateFestParticipant
    {
        $participant = StateFestParticipant::whereHas(
            'registration',
            fn ($q) => $q->where('state_event_id', $event->id),
        )->findOrFail($participantId);

        $number = $number === null ? null : trim($number);

        if ($number !== null && $number !== '') {
            $clash = StateFestParticipant::whereHas('registration', fn ($q) => $q->where('state_event_id', $event->id))
                ->where('chest_number', $number)
                ->where('id', '!=', $participant->id)
                ->first();

            if ($clash) {
                throw ValidationException::withMessages([
                    'chest_number' => "{$number} already belongs to {$clash->student_name}. Clear it there first.",
                ]);
            }
        }

        $participant->forceFill(['chest_number' => $number ?: null])->save();

        return $participant->fresh();
    }

    /**
     * The register: every numbered competitor, in number order, with the Sahodaya and School.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function register(StateFestEvent $event, ?string $sahodayaId = null)
    {
        return StateFestRegistration::where('state_event_id', $event->id)
            ->where('status', 'approved')
            ->when($sahodayaId, fn ($q) => $q->where('sahodaya_id', $sahodayaId))
            ->with('participants')
            ->get()
            ->flatMap(fn (StateFestRegistration $r) => $r->participants
                ->filter(fn (StateFestParticipant $p) => $p->isCompeting())
                ->map(fn (StateFestParticipant $p) => [
                    'participant_id' => $p->id,
                    'chest_number' => $p->chest_number,
                    'name' => $p->student_name,
                    'class_name' => $p->class_name,
                    'sahodaya' => $r->sahodaya_name ?: $r->sahodaya_id,
                    'school' => $r->school_name ?: $r->school_id,
                    'item_code' => $r->item_code,
                ]))
            // Unnumbered last: the register is used to find gaps, so they belong at the end where
            // they are obvious, not sorted in among the numbers as zero.
            ->sortBy(fn ($row) => [$row['chest_number'] === null ? 1 : 0, (int) $row['chest_number']])
            ->values();
    }

    /** @return array{numbered: int, unnumbered: int, duplicates: int} */
    public function summary(StateFestEvent $event): array
    {
        $rows = $this->register($event);
        $numbers = $rows->pluck('chest_number')->filter();

        return [
            'numbered' => $numbers->count(),
            'unnumbered' => $rows->whereNull('chest_number')->count(),
            // Should always be zero — the unique index prevents it — but reported so a data
            // problem is visible rather than assumed impossible.
            'duplicates' => $numbers->count() - $numbers->unique()->count(),
        ];
    }

    /** @return array<string, true> */
    private function takenNumbers(StateFestEvent $event): array
    {
        return StateFestParticipant::whereHas('registration', fn ($q) => $q->where('state_event_id', $event->id))
            ->whereNotNull('chest_number')
            ->pluck('chest_number')
            ->flip()
            ->map(fn () => true)
            ->all();
    }
}
