<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestSchedule;
use App\Models\FestStage;
use Carbon\Carbon;

class FestItemScheduleService
{
    /** @return list<array<string, mixed>> */
    public function rowsForEvent(FestEvent $event): array
    {
        $schedules = FestSchedule::where('event_id', $event->id)
            ->whereNull('participant_id')
            ->with(['festStage.venue'])
            ->get()
            ->keyBy('item_id');

        return FestEventItem::query()
            ->where('event_id', $event->id)
            ->where('is_enabled', true)
            ->orderBy('display_order')
            ->orderBy('title')
            ->get()
            ->map(fn (FestEventItem $item) => $this->rowFromItem($item, $schedules->get($item->id)))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function rowFromItem(FestEventItem $item, ?FestSchedule $schedule = null): array
    {
        $at = $schedule?->scheduled_at;

        return [
            'item_id'        => $item->id,
            'title'          => $item->title,
            'age_group'      => $item->age_group,
            'gender'         => $item->gender,
            'schedule_id'    => $schedule?->id,
            'scheduled_at'   => $at?->format('Y-m-d\TH:i'),
            'scheduled_date' => $at?->format('Y-m-d'),
            'scheduled_time' => $at?->format('H:i'),
            'stage_id'       => $schedule?->stage_id,
            'stage'          => $schedule?->stage,
            'venue'          => $schedule?->festStage?->venue?->name,
            'sort_order'     => $schedule?->sort_order,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function reportRows(FestEvent $event, ?string $date = null, ?int $stageId = null): array
    {
        $rows = collect($this->rowsForEvent($event));

        if ($date) {
            $rows = $rows->filter(fn ($r) => ($r['scheduled_date'] ?? '') === $date);
        }

        if ($stageId) {
            $rows = $rows->filter(fn ($r) => (int) ($r['stage_id'] ?? 0) === $stageId);
        }

        return $rows
            ->sortBy([
                fn ($r) => $r['scheduled_at'] ?? '9999',
                fn ($r) => $r['sort_order'] ?? 9999,
                fn ($r) => $r['title'],
            ])
            ->values()
            ->all();
    }

    /** @return array{scheduled: int, unscheduled: int, total: int} */
    public function summary(FestEvent $event): array
    {
        $rows = $this->rowsForEvent($event);
        $scheduled = collect($rows)->filter(fn ($r) => ! empty($r['scheduled_at']))->count();

        return [
            'total'       => count($rows),
            'scheduled'   => $scheduled,
            'unscheduled' => count($rows) - $scheduled,
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    public function bulkSave(FestEvent $event, array $rows): int
    {
        $saved = 0;

        foreach ($rows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            abort_unless(
                FestEventItem::where('event_id', $event->id)->where('id', $itemId)->exists(),
                422,
                "Item {$itemId} does not belong to this event."
            );

            $scheduledAt = $this->resolveDateTime($row);
            $stageId = ! empty($row['stage_id']) ? (int) $row['stage_id'] : null;
            $stageName = trim((string) ($row['stage'] ?? ''));

            if ($stageId) {
                $stage = FestStage::where('event_id', $event->id)->findOrFail($stageId);
                $stageName = $stage->name;
            }

            $hasData = $scheduledAt !== null || $stageId || $stageName !== '';

            if (! $hasData) {
                FestSchedule::where('event_id', $event->id)
                    ->where('item_id', $itemId)
                    ->whereNull('participant_id')
                    ->delete();

                continue;
            }

            FestSchedule::updateOrCreate(
                [
                    'item_id'        => $itemId,
                    'participant_id' => null,
                ],
                [
                    'event_id'     => $event->id,
                    'scheduled_at' => $scheduledAt,
                    'stage_id'     => $stageId,
                    'stage'        => $stageName !== '' ? $stageName : null,
                    'sort_order'   => isset($row['sort_order']) ? (int) $row['sort_order'] : (FestSchedule::where('event_id', $event->id)->max('sort_order') ?? 0) + 1,
                ]
            );

            $saved++;
        }

        return $saved;
    }

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

            $stageName = trim((string) ($data['stage'] ?? ''));
            $stageId = null;
            if ($stageName !== '') {
                $stage = FestStage::where('event_id', $event->id)->where('name', $stageName)->first();
                $stageId = $stage?->id;
            }

            $this->bulkSave($event, [[
                'item_id'      => $item->id,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'scheduled_date' => $data['scheduled_date'] ?? null,
                'scheduled_time' => $data['scheduled_time'] ?? null,
                'stage_id'     => $stageId,
                'stage'        => $stageName !== '' ? $stageName : null,
                'sort_order'   => ! empty($data['sort_order']) ? (int) $data['sort_order'] : null,
            ]]);

            $imported++;
        }

        fclose($handle);

        return compact('imported', 'errors');
    }

    /** @param array<string, mixed> $row */
    private function resolveDateTime(array $row): ?Carbon
    {
        if (! empty($row['scheduled_at'])) {
            return Carbon::parse($row['scheduled_at']);
        }

        $date = trim((string) ($row['scheduled_date'] ?? ''));
        $time = trim((string) ($row['scheduled_time'] ?? ''));

        if ($date === '' && $time === '') {
            return null;
        }

        if ($date === '') {
            return null;
        }

        return Carbon::parse($time !== '' ? "{$date} {$time}" : $date);
    }
}
