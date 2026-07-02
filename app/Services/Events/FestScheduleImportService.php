<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use App\Models\FestStage;

class FestScheduleImportService
{
    public function __construct(private FestParticipantLookupService $lookup) {}

    /** @return array{imported: int, errors: list<string>} */
    public function importFromCsv(FestEvent $event, string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ['imported' => 0, 'errors' => ['Could not read CSV file.']];
        }

        $headerRow = fgetcsv($handle);
        if ($headerRow === false) {
            fclose($handle);

            return ['imported' => 0, 'errors' => ['CSV file is empty.']];
        }

        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);
        $imported = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $data = [];
            foreach ($headers as $i => $key) {
                $data[$key] = trim((string) ($row[$i] ?? ''));
            }

            if (($data['item_id'] ?? '') === '' && ($data['item_title'] ?? '') === '') {
                continue;
            }

            $item = ! empty($data['item_id'])
                ? FestEventItem::where('event_id', $event->id)->find($data['item_id'])
                : FestEventItem::where('event_id', $event->id)->where('title', $data['item_title'])->first();

            if (! $item) {
                $errors[] = 'Unknown item: '.($data['item_title'] ?? $data['item_id']);

                continue;
            }

            $data['item_id'] = (string) $item->id;

            $participant = null;
            if (! empty($data['participant_id']) || ! empty($data['reg_no'])) {
                $participant = $this->lookup->resolveForEvent($event, $data);
                if (! $participant) {
                    $errors[] = 'Participant not found: '.($data['reg_no'] ?? $data['participant_id']);

                    continue;
                }
            }

            $stageName = trim((string) ($data['stage'] ?? ''));
            $stageId = null;
            if ($stageName !== '') {
                $stage = FestStage::where('event_id', $event->id)->where('name', $stageName)->first();
                $stageId = $stage?->id;
            }

            FestSchedule::updateOrCreate(
                [
                    'item_id'        => $item->id,
                    'participant_id' => $participant?->id,
                ],
                [
                    'event_id'     => $event->id,
                    'scheduled_at' => ! empty($data['scheduled_at']) ? $data['scheduled_at'] : null,
                    'stage_id'     => $stageId,
                    'stage'        => $stageName !== '' ? $stageName : null,
                    'sort_order'   => ! empty($data['sort_order']) ? (int) $data['sort_order'] : ($imported + 1),
                ]
            );

            $imported++;
        }

        fclose($handle);

        return compact('imported', 'errors');
    }
}
