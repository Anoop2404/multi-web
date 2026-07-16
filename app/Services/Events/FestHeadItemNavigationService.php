<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use Illuminate\Support\Facades\DB;

class FestHeadItemNavigationService
{
    public function __construct(
        private FestItemHeadService $headService,
    ) {}

    /**
     * Lightweight head tabs for school item registration (no per-item payloads).
     *
     * @return array{
     *     headItemGroups: list<array<string, mixed>>,
     *     headsForFilter: list<array{id: int, name: string}>,
     *     hasItemHeads: bool
     * }
     */
    public function headSummariesForEvent(FestEvent $event, ?string $schoolId = null): array
    {
        // Sports (Head = Event): tabs are sport events, never FestItemHead rows —
        // leftover head rows relinked to sport events must not render as tabs.
        if ($event->event_type === 'sports') {
            $nav = $this->sportsNavigation($event, $schoolId, withItems: false);

            return [
                'headItemGroups' => $nav['headItemGroups'],
                'headsForFilter' => $nav['headsForFilter'],
                'hasItemHeads'   => $nav['hasItemHeads'],
            ];
        }

        $stats = $this->participantStatsByItem($event, $schoolId);

        $heads = FestItemHead::query()
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order', 'reg_start', 'reg_end', 'competition_start', 'competition_end', 'schedule_mode', 'competition_time', 'status']);

        $items = FestEventItem::query()
            ->where('event_id', $event->id)
            ->where('is_enabled', true)
            ->get(['id', 'head_id']);

        $itemsByHead = $items->groupBy(fn ($i) => $i->head_id ?? 0);
        $groups = [];
        $headsForFilter = [];

        foreach ($heads as $head) {
            $headItems = $itemsByHead->get($head->id) ?? collect();
            if ($headItems->isEmpty() && $schoolId) {
                continue;
            }

            $participantCount = $headItems->sum(
                fn (FestEventItem $item) => $stats[$item->id]['participant_count'] ?? 0,
            );

            $groups[] = [
                'head_id'            => $head->id,
                'head_name'          => $head->name,
                'item_count'         => $headItems->count(),
                'participant_count'  => $participantCount,
                'status'             => $head->effectiveStatus(),
                'venue'              => $head->venue,
                'reg_start'          => $head->reg_start?->format('Y-m-d'),
                'reg_end'            => $head->reg_end?->format('Y-m-d'),
                'competition_start'  => $head->competition_start?->format('Y-m-d'),
                'competition_end'    => $head->competition_end?->format('Y-m-d'),
                'schedule_mode'      => $head->schedule_mode ?? 'different_days',
                'competition_time'   => $head->competitionTimeShort(),
                'registration_open'  => $this->headRegistrationOpen($head),
            ];

            $headsForFilter[] = ['id' => $head->id, 'name' => $head->name];
        }

        $unassigned = $itemsByHead->get(0) ?? collect();
        if ($unassigned->isNotEmpty()) {
            $groups[] = [
                'head_id'           => null,
                'head_name'         => 'Other items',
                'item_count'        => $unassigned->count(),
                'participant_count' => $unassigned->sum(
                    fn (FestEventItem $item) => $stats[$item->id]['participant_count'] ?? 0,
                ),
                'reg_start'         => null,
                'reg_end'           => null,
                'competition_start' => null,
                'competition_end'   => null,
                'schedule_mode'     => 'different_days',
                'competition_time'  => null,
                'registration_open' => true,
            ];
        }

        return [
            'headItemGroups'  => $groups,
            'headsForFilter'    => $headsForFilter,
            'hasItemHeads'      => $heads->isNotEmpty(),
        ];
    }

    /**
     * Head → item groups for navigation (reports, chest numbers, filters).
     *
     * @return array{
     *     headItemGroups: list<array<string, mixed>>,
     *     headsForFilter: list<array{id: int, name: string}>,
     *     hasItemHeads: bool,
     *     unassignedItems: list<array<string, mixed>>
     * }
     */
    public function navigationForEvent(FestEvent $event, ?string $schoolId = null): array
    {
        // Sports (Head = Event): groups are sport events, not FestItemHead rows.
        if ($event->event_type === 'sports') {
            return $this->sportsNavigation($event, $schoolId, withItems: true);
        }

        $stats = $this->participantStatsByItem($event, $schoolId);

        $heads = FestItemHead::query()
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order', 'reg_start', 'reg_end', 'competition_start', 'competition_end', 'schedule_mode', 'competition_time', 'status']);

        $items = FestEventItem::query()
            ->where('event_id', $event->id)
            ->where('is_enabled', true)
            ->orderBy('display_order')
            ->orderBy('title')
            ->get(['id', 'title', 'item_code', 'head_id', 'chest_no_start', 'item_reg_id_start', 'stage_type', 'reg_start', 'reg_end', 'competition_start', 'competition_end', 'competition_time', 'results_published_at']);

        $numbering = app(FestNumberingService::class)->settings($event);
        $defaultChestStart = (int) ($numbering['chest_no_start'] ?? 1);

        $itemsByHead = $items->groupBy(fn ($i) => $i->head_id ?? 0);
        $groups = [];
        $headsForFilter = [];

        foreach ($heads as $head) {
            $headItems = ($itemsByHead->get($head->id) ?? collect())->map(
                fn (FestEventItem $item) => $this->itemNavPayload($item, $stats, $defaultChestStart, $head->name),
            )->values()->all();

            if ($headItems === [] && $schoolId) {
                continue;
            }

            $participantCount = array_sum(array_column($headItems, 'participant_count'));

            $groups[] = [
                'head_id'            => $head->id,
                'head_name'          => $head->name,
                'item_count'         => count($headItems),
                'participant_count'  => $participantCount,
                'status'             => $head->effectiveStatus(),
                'venue'              => $head->venue,
                'reg_start'          => $head->reg_start?->format('Y-m-d'),
                'reg_end'            => $head->reg_end?->format('Y-m-d'),
                'competition_start'  => $head->competition_start?->format('Y-m-d'),
                'competition_end'    => $head->competition_end?->format('Y-m-d'),
                'schedule_mode'      => $head->schedule_mode ?? 'different_days',
                'competition_time'   => $head->competitionTimeShort(),
                'registration_open'  => $this->headRegistrationOpen($head),
                'items'              => $headItems,
            ];

            $headsForFilter[] = ['id' => $head->id, 'name' => $head->name];
        }

        $unassigned = ($itemsByHead->get(0) ?? collect())->map(
            fn (FestEventItem $item) => $this->itemNavPayload($item, $stats, $defaultChestStart, null),
        )->values()->all();

        if ($unassigned !== []) {
            $groups[] = [
                'head_id'           => null,
                'head_name'         => 'Other items',
                'item_count'        => count($unassigned),
                'participant_count' => array_sum(array_column($unassigned, 'participant_count')),
                'reg_start'         => null,
                'reg_end'           => null,
                'competition_start' => null,
                'competition_end'   => null,
                'registration_open' => true,
                'items'             => $unassigned,
            ];
        }

        $assignedItemIds = collect($groups)
            ->flatMap(fn (array $g) => collect($g['items'] ?? [])->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $orphaned = $items->filter(fn (FestEventItem $item) => ! in_array($item->id, $assignedItemIds, true));
        if ($orphaned->isNotEmpty()) {
            $orphanPayloads = $orphaned
                ->map(fn (FestEventItem $item) => $this->itemNavPayload($item, $stats, $defaultChestStart, null))
                ->values()
                ->all();

            $otherIndex = collect($groups)->search(fn (array $g) => ($g['head_id'] ?? null) === null);
            if ($otherIndex !== false) {
                $merged = array_merge($groups[$otherIndex]['items'], $orphanPayloads);
                $groups[$otherIndex]['items'] = $merged;
                $groups[$otherIndex]['item_count'] = count($merged);
                $groups[$otherIndex]['participant_count'] = array_sum(array_column($merged, 'participant_count'));
            } else {
                $groups[] = [
                    'head_id'           => null,
                    'head_name'         => 'Other items',
                    'item_count'        => count($orphanPayloads),
                    'participant_count' => array_sum(array_column($orphanPayloads, 'participant_count')),
                    'items'             => $orphanPayloads,
                ];
            }
        }

        if ($groups === [] && $items->isNotEmpty()) {
            $allItems = $items
                ->map(fn (FestEventItem $item) => $this->itemNavPayload($item, $stats, $defaultChestStart, null))
                ->values()
                ->all();

            $groups[] = [
                'head_id'           => null,
                'head_name'         => 'All items',
                'item_count'        => count($allItems),
                'participant_count' => array_sum(array_column($allItems, 'participant_count')),
                'items'             => $allItems,
            ];
        }

        return [
            'headItemGroups'   => $groups,
            'headsForFilter'   => $headsForFilter,
            'hasItemHeads'     => $heads->isNotEmpty(),
            'unassignedItems'  => $unassigned,
        ];
    }

    /** Head tab metadata only — no nested item lists (keeps Inertia payloads small). */
    public function tabSummaries(array $headItemGroups): array
    {
        return array_map(fn (array $group) => [
            'head_id'            => $group['head_id'] ?? null,
            'head_name'          => $group['head_name'] ?? '',
            'item_count'         => (int) ($group['item_count'] ?? 0),
            'participant_count'  => (int) ($group['participant_count'] ?? 0),
            'reg_start'          => $group['reg_start'] ?? null,
            'reg_end'            => $group['reg_end'] ?? null,
            'competition_start'  => $group['competition_start'] ?? null,
            'competition_end'    => $group['competition_end'] ?? null,
            'schedule_mode'      => $group['schedule_mode'] ?? 'different_days',
            'competition_time'   => $group['competition_time'] ?? null,
            'registration_open'  => $group['registration_open'] ?? true,
        ], $headItemGroups);
    }

    /** @param array<string, mixed> $nav */
    public function slimNavigation(array $nav): array
    {
        return [
            'headItemGroups'  => $this->tabSummaries($nav['headItemGroups'] ?? []),
            'headsForFilter'  => $nav['headsForFilter'] ?? [],
            'hasItemHeads'    => (bool) ($nav['hasItemHeads'] ?? false),
            'unassignedItems' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function selectedHeadFromGroups(array $groups, ?int $headId): ?array
    {
        if (! $headId) {
            return null;
        }

        foreach ($groups as $group) {
            if ((int) ($group['head_id'] ?? 0) === $headId) {
                return $group;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function findItemInGroups(array $groups, int $itemId): ?array
    {
        foreach ($groups as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if ((int) $item['id'] === $itemId) {
                    return array_merge($item, [
                        'head_id'   => $group['head_id'],
                        'head_name' => $group['head_name'],
                    ]);
                }
            }
        }

        return null;
    }

    /** @return array<int, array{participant_count: int, chest_assigned: int, item_reg_assigned: int}> */
    private function participantStatsByItem(FestEvent $event, ?string $schoolId): array
    {
        $rows = FestParticipant::query()
            ->join('fest_registrations', 'fest_participants.registration_id', '=', 'fest_registrations.id')
            ->where('fest_registrations.event_id', $event->id)
            ->whereIn('fest_registrations.status', \App\Models\FestRegistration::ACTIVE_STATUSES)
            ->whereNotNull('fest_registrations.item_id')
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId))
            ->groupBy('fest_registrations.item_id')
            ->select([
                'fest_registrations.item_id',
                DB::raw('COUNT(*) as participant_count'),
                DB::raw('SUM(CASE WHEN fest_participants.chest_no IS NOT NULL THEN 1 ELSE 0 END) as chest_assigned'),
                DB::raw('SUM(CASE WHEN fest_participants.item_registration_number IS NOT NULL THEN 1 ELSE 0 END) as item_reg_assigned'),
            ])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->item_id] = [
                'participant_count'  => (int) $row->participant_count,
                'chest_assigned'     => (int) $row->chest_assigned,
                'item_reg_assigned'  => (int) $row->item_reg_assigned,
            ];
        }

        return $map;
    }

    /** @param array<int, array{participant_count: int, chest_assigned: int, item_reg_assigned: int}> $stats */
    /**
     * Sports navigation: one group per sport event. On the season hub the groups
     * are the child sport events; on a single sport event, one group of its own
     * items. head_id carries the sport event id.
     *
     * @return array{
     *     headItemGroups: list<array<string, mixed>>,
     *     headsForFilter: list<array{id: int, name: string}>,
     *     hasItemHeads: bool,
     *     unassignedItems: list<array<string, mixed>>
     * }
     */
    private function sportsNavigation(FestEvent $event, ?string $schoolId, bool $withItems): array
    {
        $isSeason = $event->isSportsSeasonEvent();

        $sports = $isSeason
            ? FestEvent::where('parent_event_id', $event->id)
                ->ofType('sports')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
            : collect([$event]);

        $groups = [];
        $headsForFilter = [];

        foreach ($sports as $sport) {
            $stats = $this->participantStatsByItem($sport, $schoolId);

            $items = FestEventItem::query()
                ->where('event_id', $sport->id)
                ->where('is_enabled', true)
                ->orderBy('display_order')
                ->orderBy('title')
                ->get(['id', 'title', 'item_code', 'head_id', 'chest_no_start', 'item_reg_id_start', 'stage_type', 'reg_start', 'reg_end', 'competition_start', 'competition_end', 'competition_time', 'results_published_at']);

            if ($items->isEmpty() && $schoolId) {
                continue;
            }

            $numbering = app(FestNumberingService::class)->settings($sport);
            $defaultChestStart = (int) ($numbering['chest_no_start'] ?? 1);

            $itemPayloads = $items->map(
                fn (FestEventItem $item) => $this->itemNavPayload($item, $stats, $defaultChestStart, $sport->title),
            )->values()->all();

            $groups[] = [
                'head_id'            => $sport->id,
                'head_name'          => $sport->title,
                'item_count'         => count($itemPayloads),
                'participant_count'  => array_sum(array_column($itemPayloads, 'participant_count')),
                'status'             => $sport->status,
                'venue'              => $sport->venue,
                'reg_start'          => $sport->reg_start?->format('Y-m-d'),
                'reg_end'            => $sport->reg_end?->format('Y-m-d'),
                'competition_start'  => $sport->competition_start?->format('Y-m-d'),
                'competition_end'    => $sport->competition_end?->format('Y-m-d'),
                'schedule_mode'      => $sport->schedule_mode ?? 'different_days',
                'competition_time'   => $sport->competition_time
                    ? substr((string) $sport->competition_time, 0, 5)
                    : null,
                'registration_open'  => $sport->isRegistrationOpen(),
                'items'              => $withItems ? $itemPayloads : [],
            ];

            $headsForFilter[] = ['id' => $sport->id, 'name' => $sport->title];
        }

        return [
            'headItemGroups'  => $groups,
            'headsForFilter'  => $headsForFilter,
            // Tabs only make sense on the season hub (multiple sports). A single
            // sport event renders its items flat — no head/tab chrome.
            'hasItemHeads'    => $isSeason && $groups !== [],
            'unassignedItems' => [],
        ];
    }

    private function itemNavPayload(
        FestEventItem $item,
        array $stats,
        int $defaultChestStart,
        ?string $headName,
    ): array {
        $stat = $stats[$item->id] ?? ['participant_count' => 0, 'chest_assigned' => 0, 'item_reg_assigned' => 0];
        $total = $stat['participant_count'];

        return [
            'id'                => $item->id,
            'title'             => $item->title,
            'item_code'         => $item->item_code,
            'head_id'           => $item->head_id,
            'head_name'         => $headName,
            'reg_start'         => $item->reg_start?->format('Y-m-d'),
            'reg_end'           => $item->reg_end?->format('Y-m-d'),
            'competition_start' => $item->competition_start?->format('Y-m-d'),
            'competition_end'   => $item->competition_end?->format('Y-m-d'),
            'competition_time'  => $item->competition_time ? substr((string) $item->competition_time, 0, 5) : null,
            'chest_no_start'    => $item->chest_no_start ?? $defaultChestStart,
            'item_reg_id_start' => $item->item_reg_id_start ?? 1,
            'stage_type'        => $item->stage_type,
            'participant_count' => $total,
            'chest_assigned'    => $stat['chest_assigned'],
            'chest_missing'     => max(0, $total - $stat['chest_assigned']),
            'item_reg_missing'  => max(0, $total - $stat['item_reg_assigned']),
            'results_published' => $item->results_published_at !== null,
            'results_published_at' => $item->results_published_at?->toIso8601String(),
        ];
    }

    private function headRegistrationOpen(FestItemHead $head): bool
    {
        if (! $head->isRegistrationOpenForSchools()) {
            return false;
        }

        $today = now()->startOfDay();

        if ($head->reg_start && $today->lt($head->reg_start->startOfDay())) {
            return false;
        }

        if ($head->reg_end && $today->gt($head->reg_end->startOfDay())) {
            return false;
        }

        return true;
    }
}
