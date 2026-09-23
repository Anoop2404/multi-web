<?php

namespace App\Services\State\Fest;

use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Phase 6 of the State Kalotsav module — importing a panel's marks from a spreadsheet.
 *
 * Judges at State level often score on paper and a clerk types the sheet up afterwards, so the import
 * is by chest number and item code: the two things actually written on a paper sheet. Names are not
 * accepted as an identifier — two participants from different Sahodayas share a name often enough
 * that matching on it would silently award the wrong person.
 *
 * Nothing is written until the whole file validates. A half-applied mark import is worse than a
 * rejected one, because the operator cannot tell which rows landed without re-reading every mark.
 */
class StateMarkImportService
{
    public const HEADERS = ['item_code', 'chest_number', 'score', 'grade', 'notes'];

    public function __construct(private StateConductService $conduct) {}

    /** A blank sheet to fill in, pre-filled with the entries that exist. */
    public function template(StateFestEvent $event, ?string $itemId = null): array
    {
        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->where('status', 'approved')
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
            ->with('participants')->get();

        return $registrations
            ->map(function (StateFestRegistration $r) {
                $anchor = $r->participants->firstWhere('is_leader', true)
                    ?? $r->participants->first(fn ($p) => $p->isCompeting());

                return $anchor?->chest_number
                    ? [$r->item_code, $anchor->chest_number, '', '', '']
                    : null;
            })
            ->filter()
            ->sortBy(fn (array $row) => [$row[0], (int) $row[1]])
            ->values()->all();
    }

    /**
     * Parse, validate and (unless previewing) apply.
     *
     * @param  list<array<int, string>>  $rows  raw rows, header row included
     * @return array{applied: int, rows: list<array<string, mixed>>, errors: list<string>}
     */
    public function import(StateFestEvent $event, int $judgeUserId, array $rows, bool $dryRun = false): array
    {
        $parsed = $this->parse($rows);

        if ($parsed['errors']) {
            // Reported all at once: a clerk fixing a 200-row sheet one error per upload is how a
            // whole afternoon disappears.
            throw ValidationException::withMessages(['file' => $parsed['errors']]);
        }

        $resolved = $this->resolve($event, $parsed['rows'], $judgeUserId);

        if ($resolved['errors']) {
            throw ValidationException::withMessages(['file' => $resolved['errors']]);
        }

        if ($dryRun) {
            return ['applied' => 0, 'rows' => $resolved['rows'], 'errors' => []];
        }

        // One transaction per score is what enterJudgeScore already does; wrapping the loop in an
        // outer transaction keeps a mid-file failure (a locked event, say) from leaving half a sheet.
        return \Illuminate\Support\Facades\DB::connection('state')->transaction(function () use ($event, $judgeUserId, $resolved) {
            foreach ($resolved['rows'] as $row) {
                $this->conduct->enterJudgeScore(
                    $event,
                    $row['registration'],
                    $row['participant_id'],
                    $judgeUserId,
                    [
                        'score' => $row['score'],
                        'grade' => $row['grade'],
                        'notes' => $row['notes'],
                        'reason' => 'Bulk import',
                    ],
                );
            }

            return [
                'applied' => count($resolved['rows']),
                'rows' => array_map(fn (array $r) => array_diff_key($r, ['registration' => null]), $resolved['rows']),
                'errors' => [],
            ];
        });
    }

    /**
     * @param  list<array<int, string>>  $rows
     * @return array{rows: list<array<string, mixed>>, errors: list<string>}
     */
    private function parse(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn ($row) => is_array($row) && implode('', array_map('strval', $row)) !== ''));

        if ($rows === []) {
            return ['rows' => [], 'errors' => ['The file is empty.']];
        }

        $header = array_map(fn ($h) => \Str::snake(trim(strtolower((string) $h))), $rows[0]);
        $missing = array_diff(['item_code', 'chest_number', 'score'], $header);

        if ($missing) {
            return ['rows' => [], 'errors' => [
                'The header row must include '.implode(', ', $missing).'. Expected columns: '.implode(', ', self::HEADERS).'.',
            ]];
        }

        $index = array_flip($header);
        $parsed = [];
        $errors = [];
        $seen = [];

        foreach (array_slice($rows, 1) as $i => $row) {
            $line = $i + 2;
            $get = fn (string $key) => isset($index[$key]) ? trim((string) ($row[$index[$key]] ?? '')) : '';

            $itemCode = $get('item_code');
            $chest = $get('chest_number');
            $score = $get('score');

            if ($itemCode === '' || $chest === '') {
                $errors[] = "Row {$line}: item code and chest number are both required.";

                continue;
            }

            if ($score === '') {
                // A blank score is a row the clerk has not typed yet, not an error — skipped so a
                // partially filled sheet can be uploaded as it goes.
                continue;
            }

            if (! is_numeric($score)) {
                $errors[] = "Row {$line}: \"{$score}\" is not a score.";

                continue;
            }

            $key = $itemCode.'|'.$chest;
            if (isset($seen[$key])) {
                $errors[] = "Row {$line}: chest {$chest} appears twice for {$itemCode} (also row {$seen[$key]}).";

                continue;
            }
            $seen[$key] = $line;

            $parsed[] = [
                'line' => $line,
                'item_code' => $itemCode,
                'chest_number' => $chest,
                'score' => (float) $score,
                'grade' => $get('grade') ?: null,
                'notes' => $get('notes') ?: null,
            ];
        }

        return ['rows' => $parsed, 'errors' => $errors];
    }

    /**
     * Match each row to a real entry.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: list<array<string, mixed>>, errors: list<string>}
     */
    private function resolve(StateFestEvent $event, array $rows, int $judgeUserId): array
    {
        if ($rows === []) {
            return ['rows' => [], 'errors' => ['Nothing in the file had a score to import.']];
        }

        $chests = array_unique(array_column($rows, 'chest_number'));

        $participants = StateFestParticipant::whereIn('chest_number', $chests)
            ->whereHas('registration', fn ($q) => $q->where('state_event_id', $event->id))
            ->with('registration')
            ->get();

        /** @var Collection<string, StateFestParticipant> $byKey */
        $byKey = $participants
            ->filter(fn (StateFestParticipant $p) => $p->registration !== null)
            ->keyBy(fn (StateFestParticipant $p) => $p->registration->item_code.'|'.$p->chest_number);

        // The named judge must actually be on the panel for every item in the file. Without this
        // check a mistyped judge produces a full set of marks attributed to someone who never saw
        // the performance, and aggregation treats them as real.
        $panelItems = \App\Models\State\StateJudgeAssignment::where('state_event_id', $event->id)
            ->where('user_id', $judgeUserId)->pluck('item_id')->all();

        $resolved = [];
        $errors = [];

        foreach ($rows as $row) {
            $participant = $byKey->get($row['item_code'].'|'.$row['chest_number']);

            if (! $participant) {
                // Named precisely: "chest 214 is not entered for MUS-07" is actionable, where
                // "no match" sends the clerk back through the whole sheet.
                $errors[] = "Row {$row['line']}: chest {$row['chest_number']} is not entered for {$row['item_code']}.";

                continue;
            }

            if (! in_array($participant->registration->item_id, $panelItems, true)) {
                $errors[] = "Row {$row['line']}: that judge is not on the panel for {$row['item_code']}.";

                continue;
            }

            $resolved[] = $row + [
                'registration' => $participant->registration,
                'participant_id' => $participant->id,
            ];
        }

        return ['rows' => $resolved, 'errors' => $errors];
    }
}
