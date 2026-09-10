<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestStage;
use App\Models\FestVenue;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use Carbon\Carbon;

class FestItemScheduleService
{
    /** @return list<array<string, mixed>> */
    public function rowsForEvent(FestEvent $event): array
    {
        $schedules = FestSchedule::where('event_id', $event->id)
            ->whereNull('participant_id')
            ->with(['festStage.venue', 'venue'])
            ->get()
            ->keyBy('item_id');

        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());

        return FestEventItem::query()
            ->where('event_id', $event->id)
            ->where('is_enabled', true)
            ->with('head:id,name')
            ->withCount(['registrations' => fn ($q) => $q->whereIn('status', FestRegistration::ACTIVE_STATUSES)])
            ->orderBy('display_order')
            ->orderBy('title')
            ->get()
            ->map(fn (FestEventItem $item) => $this->rowFromItem($item, $schedules->get($item->id), $classGroupLabels))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $classGroupLabels
     * @return array<string, mixed>
     */
    public function rowFromItem(FestEventItem $item, ?FestSchedule $schedule = null, array $classGroupLabels = []): array
    {
        $at = $schedule?->scheduled_at;
        $participantCount = $item->registrations_count ?? null;

        return [
            'item_id'         => $item->id,
            'title'           => $item->title,
            'head_id'         => $item->head_id,
            'head_name'       => $item->head?->name,
            'age_group'       => $item->age_group,
            'category_label'  => FestItemCategoryLabel::resolve($item, $classGroupLabels),
            'gender'          => $item->gender,
            'participant_type' => $item->participant_type,
            'phase_id'        => $item->phase_id,
            'timing_mode'            => $item->timing_mode ?? 'per_participant',
            'duration_minutes'       => $item->duration_minutes,
            'calling_buffer_minutes' => $item->calling_buffer_minutes,
            'registrations_count'    => $participantCount,
            'estimated_minutes'      => $item->estimatedDurationMinutes($participantCount),
            'schedule_id'    => $schedule?->id,
            'scheduled_at'   => $at?->format('Y-m-d\TH:i'),
            'scheduled_date' => $at?->format('Y-m-d'),
            'scheduled_time' => $at?->format('H:i'),
            'stage_id'       => $schedule?->stage_id,
            'stage'          => $schedule?->stage,
            'venue_id'       => $schedule?->venue_id,
            'venue'          => $schedule?->venue?->name ?? $schedule?->festStage?->venue?->name,
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
        // Was previously 4-6+ individual queries per row (item lookup, stage lookup,
        // venue lookup, updateOrCreate's own select + write, and a sort_order max()
        // re-run on every single row) — for the 100-140 item events this page is built
        // for, that's 500-800+ synchronous round-trips in one request, which is exactly
        // why saving took "too much time" for a full list. Batch-fetch everything these
        // rows could reference once, up front, then do a single read-or-write per row
        // instead of several.
        $itemIds = collect($rows)->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $items = FestEventItem::where('event_id', $event->id)->whereIn('id', $itemIds)->get()->keyBy('id');

        $stageIds = collect($rows)->pluck('stage_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $stages = $stageIds->isEmpty()
            ? collect()
            : FestStage::where('event_id', $event->id)->whereIn('id', $stageIds)->get()->keyBy('id');

        $venueIds = collect($rows)->pluck('venue_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $validVenueIds = $venueIds->isEmpty()
            ? collect()
            : FestVenue::where('event_id', $event->id)->whereIn('id', $venueIds)->pluck('id')->flip();

        $existingSchedules = FestSchedule::where('event_id', $event->id)
            ->whereIn('item_id', $itemIds)
            ->whereNull('participant_id')
            ->get()
            ->keyBy('item_id');

        $nextSortOrder = (FestSchedule::where('event_id', $event->id)->max('sort_order') ?? 0) + 1;

        $saved = 0;

        foreach ($rows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $item = $items->get($itemId);
            abort_unless($item, 422, "Item {$itemId} does not belong to this event.");

            $this->applyTimingFields($item, $row);

            $scheduledAt = $this->resolveDateTime($row);
            $stageId = ! empty($row['stage_id']) ? (int) $row['stage_id'] : null;
            $stageName = trim((string) ($row['stage'] ?? ''));
            $venueId = ! empty($row['venue_id']) ? (int) $row['venue_id'] : null;

            if ($stageId) {
                $stage = $stages->get($stageId);
                abort_unless($stage, 404, "Stage {$stageId} does not belong to this event.");
                $stageName = $stage->name;
            }

            if ($venueId) {
                abort_unless($validVenueIds->has($venueId), 404, "Venue {$venueId} does not belong to this event.");
            }

            $hasData = $scheduledAt !== null || $stageId || $stageName !== '' || $venueId;
            $existing = $existingSchedules->get($itemId);

            if (! $hasData) {
                $existing?->delete();

                continue;
            }

            $attributes = [
                'event_id'     => $event->id,
                'scheduled_at' => $scheduledAt,
                'stage_id'     => $stageId,
                'stage'        => $stageName !== '' ? $stageName : null,
                'venue_id'     => $venueId,
                'sort_order'   => isset($row['sort_order']) ? (int) $row['sort_order'] : $nextSortOrder++,
            ];

            if ($existing) {
                $existing->update($attributes);
            } else {
                FestSchedule::create($attributes + ['item_id' => $itemId, 'participant_id' => null]);
            }

            $saved++;
        }

        return $saved;
    }

    /** @param array<string, mixed> $row */
    private function applyTimingFields(FestEventItem $item, array $row): void
    {
        $dirty = false;

        if (! empty($row['timing_mode'])) {
            $item->timing_mode = $row['timing_mode'];
            $dirty = true;
        }
        if (array_key_exists('duration_minutes', $row)) {
            $item->duration_minutes = $row['duration_minutes'] !== null && $row['duration_minutes'] !== ''
                ? (int) $row['duration_minutes']
                : null;
            $dirty = true;
        }
        if (array_key_exists('calling_buffer_minutes', $row)) {
            $item->calling_buffer_minutes = $row['calling_buffer_minutes'] !== null && $row['calling_buffer_minutes'] !== ''
                ? (int) $row['calling_buffer_minutes']
                : null;
            $dirty = true;
        }

        if ($dirty) {
            $item->save();
        }
    }

    /**
     * Cascades a start time across an ordered list of items on one stage: item 1 gets
     * $startAt, item 2 starts when item 1's estimated duration ends, and so on.
     *
     * @param  list<int>  $orderedItemIds
     * @return array{count: int, ends_at: Carbon}
     */
    public function autoSequence(FestEvent $event, array $orderedItemIds, Carbon $startAt, ?int $stageId = null, ?string $stageName = null, ?int $venueId = null): array
    {
        $items = FestEventItem::where('event_id', $event->id)
            ->whereIn('id', $orderedItemIds)
            ->withCount(['registrations' => fn ($q) => $q->whereIn('status', FestRegistration::ACTIVE_STATUSES)])
            ->get()
            ->keyBy('id');

        $stage = $stageId ? FestStage::where('event_id', $event->id)->findOrFail($stageId) : null;
        $resolvedStageName = $stage?->name ?? $stageName;
        $venue = $venueId ? FestVenue::where('event_id', $event->id)->findOrFail($venueId) : null;

        $cursor = $startAt->copy();
        $sortOrder = FestSchedule::where('event_id', $event->id)->max('sort_order') ?? 0;
        $count = 0;

        foreach ($orderedItemIds as $itemId) {
            $item = $items->get((int) $itemId);
            if (! $item) {
                continue;
            }

            FestSchedule::updateOrCreate(
                ['item_id' => $item->id, 'participant_id' => null],
                [
                    'event_id'     => $event->id,
                    'scheduled_at' => $cursor->copy(),
                    'stage_id'     => $stage?->id,
                    'stage'        => $resolvedStageName,
                    'venue_id'     => $venue?->id,
                    'sort_order'   => ++$sortOrder,
                ]
            );

            $cursor = $cursor->copy()->addMinutes($item->estimatedDurationMinutes($item->registrations_count));
            $count++;
        }

        return ['count' => $count, 'ends_at' => $cursor];
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
