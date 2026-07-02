<?php

namespace App\Services\Events;

use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestParticipant;

class FestAttendanceImportService
{
    /** @return array{imported: int, skipped: int, errors: list<string>} */
    public function importFromCsv(FestEvent $event, string $path, ?int $markedBy = null): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $lookup = app(FestParticipantLookupService::class);

        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Could not read CSV file.']];
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($handle) ?: []);
        $regNoIdx = array_search('reg_no', $header, true);
        $participantIdx = array_search('participant_id', $header, true);
        $itemIdx = array_search('item_id', $header, true);
        $itemTitleIdx = array_search('item_title', $header, true);
        $statusIdx = array_search('status', $header, true);

        if ($statusIdx === false) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV must include status column (present|absent).']];
        }

        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $status = strtolower(trim($row[$statusIdx] ?? ''));
            if (! in_array($status, ['present', 'absent'], true)) {
                $errors[] = "Row {$rowNum}: invalid status (use present or absent).";
                $skipped++;

                continue;
            }

            $participant = null;
            if ($participantIdx !== false && ! empty($row[$participantIdx])) {
                $participant = FestParticipant::where('id', (int) $row[$participantIdx])
                    ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id))
                    ->first();
            } elseif ($regNoIdx !== false && ! empty($row[$regNoIdx])) {
                $participant = $lookup->resolveForEvent($event, [
                    'reg_no'     => trim($row[$regNoIdx]),
                    'item_id'    => $itemIdx !== false ? ($row[$itemIdx] ?: null) : null,
                    'item_title' => $itemTitleIdx !== false ? ($row[$itemTitleIdx] ?: null) : null,
                ]);
            }

            if (! $participant) {
                $errors[] = "Row {$rowNum}: participant not found.";
                $skipped++;

                continue;
            }

            $itemId = $participant->registration?->item_id;
            if (! $itemId) {
                $errors[] = "Row {$rowNum}: registration has no item.";
                $skipped++;

                continue;
            }

            FestAttendance::updateOrCreate(
                ['item_id' => $itemId, 'participant_id' => $participant->id],
                [
                    'event_id'  => $event->id,
                    'status'    => $status,
                    'marked_by' => $markedBy,
                    'marked_at' => now(),
                ]
            );

            $imported++;
        }

        fclose($handle);

        return compact('imported', 'skipped', 'errors');
    }
}
