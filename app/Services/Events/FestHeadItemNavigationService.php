<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use Illuminate\Support\Facades\DB;

class FestHeadItemNavigationService
{
    public function __construct(
        private FestItemHeadService $headService,
    ) {}

    /**
     * Lightweight head tabs for school item registration. Item payloads (title,
     * category_label, etc.) are only fetched/computed when $withItems is true —
     * callers that just need tab counts skip the extra columns and label lookups.
     *
     * @return array{
     *     headItemGroups: list<array<string, mixed>>,
     *     headsForFilter: list<array{id: int, name: string}>,
     *     hasItemHeads: bool
     * }
     */
    public function headSummariesForEvent(FestEvent $event, ?string $schoolId = null, bool $withItems = false): array
    {
        // Sports (Head = Event): tabs are sport events, never FestItemHead rows —
        // leftover head rows relinked to sport events must not render as tabs.
        if ($event->event_type === 'sports') {
            $nav = $this->sportsNavigation($event, $schoolId, withItems: $withItems);

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

        $itemColumns = ['id', 'head_id'];
        $classGroupLabels = [];
        $artsCategoryLabels = [];
        $defaultChestStart = 1;

        if ($withItems) {
            $itemColumns = [
                'id', 'title', 'item_code', 'head_id', 'chest_no_start', 'item_reg_id_start',
                'stage_type', 'reg_start', 'reg_end', 'competition_start', 'competition_end',
                'competition_time', 'results_published_at', 'participant_type', 'gender',
                'age_group', 'class_group', 'category', 'min_group_size', 'max_group_size', 'criteria_json',
            ];
            $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
            $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);
            $numbering = app(FestNumberingService::class)->settings($event);
            $defaultChestStart = (int) ($numbering['chest_no_start'] ?? 1);
        }

        $items = FestEventItem::query()
            ->where('event_id', $event->id)
            ->where('is_enabled', true)
            ->get($itemColumns);

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

            $group = [
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

            if ($withItems) {
                $group['items'] = $headItems->map(
                    fn (FestEventItem $item) => $this->itemNavPayload($item, $stats, $defaultChestStart, $head->name, $classGroupLabels, $artsCategoryLabels),
                )->values()->all();
            }

            $groups[] = $group;

            $headsForFilter[] = ['id' => $head->id, 'name' => $head->name];
        }

        $unassigned = $itemsByHead->get(0) ?? collect();
        if ($unassigned->isNotEmpty()) {
            $unassignedGroup = [
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

            if ($withItems) {
                $unassignedGroup['items'] = $unassigned->map(
                    fn (FestEventItem $item) => $this->itemNavPayload($item, $stats, $defaultChestStart, null, $classGroupLabels, $artsCategoryLabels),
                )->values()->all();
            }

            $groups[] = $unassignedGroup;
        }

        return [
            'headItemGroups'  => $groups,
            'headsForFilter'    => $headsForFilter,
            'hasItemHeads'      => $heads->isNotEmpty() || $groups !== [],
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

        $targetEvent = $this->catalogScopeEvent($event);
        $stats = $this->participantStatsByItem($event, $schoolId);

        $heads = FestItemHead::query()
            ->where('event_id', $targetEvent->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order', 'reg_start', 'reg_end', 'competition_start', 'competition_end', 'schedule_mode', 'competition_time', 'status']);

        $items = self::filterToOwnPhase(
            FestEventItem::query()
                ->where('event_id', $targetEvent->id)
                ->where('is_enabled', true)
                ->with('phase:id,source_phase_id')
                ->orderBy('display_order')
                ->orderBy('title')
                ->get(['id', 'title', 'item_code', 'head_id', 'chest_no_start', 'item_reg_id_start', 'stage_type', 'reg_start', 'reg_end', 'competition_start', 'competition_end', 'competition_time', 'results_published_at', 'class_group', 'category', 'age_group', 'phase_id']),
            $event,
        );

        $numbering = app(FestNumberingService::class)->settings($targetEvent);
        $defaultChestStart = (int) ($numbering['chest_no_start'] ?? 1);
        $classGroupLabels = FestClassGroupScheme::labels(null, $targetEvent);
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

        $itemsByHead = $items->groupBy(fn ($i) => $i->head_id ?? 0);
        $groups = [];
        $headsForFilter = [];

        foreach ($heads as $head) {
            $headItems = ($itemsByHead->get($head->id) ?? collect())->map(
                fn (FestEventItem $item) => $this->itemNavPayload($item, $stats, $defaultChestStart, $head->name, $classGroupLabels, $artsCategoryLabels),
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
            fn (FestEventItem $item) => $this->itemNavPayload($item, $stats, $defaultChestStart, null, $classGroupLabels, $artsCategoryLabels),
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
                ->map(fn (FestEventItem $item) => $this->itemNavPayload($item, $stats, $defaultChestStart, null, $classGroupLabels, $artsCategoryLabels))
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
                ->map(fn (FestEventItem $item) => $this->itemNavPayload($item, $stats, $defaultChestStart, null, $classGroupLabels, $artsCategoryLabels))
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
            'hasItemHeads'     => $heads->isNotEmpty() || $groups !== [],
            'unassignedItems'  => $unassigned,
        ];
    }

    /** Head tab metadata only — no nested item lists (keeps Inertia payloads small). */
    public function tabSummaries(array $headItemGroups, bool $includeItems = false): array
    {
        return array_map(fn (array $group) => array_filter([
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
            'items'              => $includeItems ? ($group['items'] ?? []) : null,
        ], fn ($v) => $v !== null), $headItemGroups);
    }

    /** @param array<string, mixed> $nav */
    public function slimNavigation(array $nav, bool $includeItems = false): array
    {
        return [
            'headItemGroups'  => $this->tabSummaries($nav['headItemGroups'] ?? [], $includeItems),
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

    /**
     * Every item id that should be counted together with $itemId — same item, its
     * inherited copies, or anything sharing its item_code — mirroring
     * FestEvent::reportableItemIds()'s matching rules exactly, but resolved against a
     * pre-fetched $byRoot/$byCode map instead of a fresh query per item. Keeps
     * participantStatsByItem() (called once per item, potentially 100+ times per
     * event) from re-querying fest_events/fest_event_items on every iteration.
     *
     * @param  array<int, true>  $inScopeIds
     * @param  array<int, list<int>>  $byRoot
     * @param  array<string, list<int>>  $byCode
     * @return list<int>
     */
    private function equivalentItemIdsFromMap(int $itemId, ?int $inheritedFromItemId, ?string $itemCode, array $inScopeIds, array $byRoot, array $byCode): array
    {
        // reportableItemIds() first re-fetches the item itself scoped to $eventIds —
        // if that lookup comes back empty (item's own event_id isn't reportable from
        // here, e.g. $event is a leaf whose scope doesn't reach the root event the item
        // list is drawn from) it returns [] immediately, before ever looking at what
        // other in-scope items point back to it via inherited_from_item_id/item_code.
        // Must gate on that here too, or an in-scope inherited copy would pull an
        // otherwise-unreachable root item's count back in.
        if (! isset($inScopeIds[$itemId])) {
            return [];
        }

        $rootId = $inheritedFromItemId ?: $itemId;
        $ids = $byRoot[$rootId] ?? [];

        if (filled($itemCode) && isset($byCode[$itemCode])) {
            $ids = array_merge($ids, $byCode[$itemCode]);
        }

        return array_values(array_unique($ids));
    }

    /**
     * The event whose own item/head catalog $event should read from. A phase/region leaf
     * gets its own copied catalog rows (event_id = leaf.id) via
     * FestItemSyncService::copyItemsToPartition(), so prefer those over unconditionally
     * jumping to the root's whole-event catalog — that jump used to be unconditional here,
     * which is why every phase's items showed up in every other phase's item
     * pickers/reports (Chest Numbers, Mark Entry, Results, Reports) once partition-scoped
     * item copies existed. Falls back to the root only when this leaf genuinely has no
     * item rows of its own yet (pre-sync/legacy topologies).
     */
    private function catalogScopeEvent(FestEvent $event): FestEvent
    {
        if (! $event->parent_event_id) {
            return $event;
        }

        return FestEventItem::where('event_id', $event->id)->exists() ? $event : $event->rootEvent();
    }

    /**
     * Narrows an item collection down to items whose canonical phase matches $event's own
     * source_phase_id — a leaf's own item table can still hold a handful of items copied
     * under the wrong phase. Same fix/precedent as
     * FestRegistrationController::eventRegistration() (first found live on Wayanad
     * Sahodaya: event 5 "PHASE 1" listing all 141 hub items instead of its own 74). No-op
     * when $event isn't phase-scoped, or an item has no phase_id of its own.
     *
     * Public (and static) so every controller loading $event->items directly for an item
     * picker/filter — not just this service's own navigationForEvent()/
     * participantStatsByItem() — can reuse the exact same rule instead of re-deriving it.
     * Callers must eager-load item.phase (e.g. ->with('phase:id,source_phase_id')) first;
     * this never queries on its own.
     *
     * @param  \Illuminate\Support\Collection<int, FestEventItem>  $items
     * @return \Illuminate\Support\Collection<int, FestEventItem>
     */
    public static function filterToOwnPhase($items, FestEvent $event)
    {
        if (! $event->source_phase_id) {
            return $items;
        }

        return $items->filter(function (FestEventItem $item) use ($event) {
            if (! $item->phase_id) {
                return true;
            }
            $phase = $item->phase;
            if (! $phase) {
                return true;
            }
            $canonicalPhaseId = $phase->source_phase_id ?: $phase->id;

            return $canonicalPhaseId === $event->source_phase_id;
        })->values();
    }

    /** @return array<int, array{participant_count: int, chest_assigned: int, item_reg_assigned: int}> */
    private function participantStatsByItem(FestEvent $event, ?string $schoolId): array
    {
        $eventIds = $event->reportableEventIds();
        $targetEvent = $this->catalogScopeEvent($event);

        $items = self::filterToOwnPhase(
            FestEventItem::query()
                ->where('event_id', $targetEvent->id)
                ->where('is_enabled', true)
                ->with('phase:id,source_phase_id')
                ->get(['id', 'item_code', 'participant_type', 'inherited_from_item_id', 'phase_id']),
            $event,
        );

        if ($items->isEmpty()) {
            return [];
        }

        // Same universe reportableItemIds() would search within — every item across the
        // reportable event scope, not just $targetEvent — so partition/cluster children
        // sharing an item_code or inherited_from_item_id still get folded in below.
        $inScopeIds = [];
        $byRoot = [];
        $byCode = [];
        foreach (FestEventItem::whereIn('event_id', $eventIds)->get(['id', 'item_code', 'inherited_from_item_id']) as $row) {
            $inScopeIds[(int) $row->id] = true;
            $rootId = (int) ($row->inherited_from_item_id ?: $row->id);
            $byRoot[$rootId][] = (int) $row->id;
            if (filled($row->item_code)) {
                $byCode[$row->item_code][] = (int) $row->id;
            }
        }

        $baseGroupQuery = fn () => \App\Models\FestGroup::query()
            ->join('fest_registrations', 'fest_groups.registration_id', '=', 'fest_registrations.id')
            ->whereIn('fest_registrations.event_id', $eventIds)
            ->whereNotIn('fest_registrations.status', ['rejected', 'withdrawn'])
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId));

        $groupRows = $baseGroupQuery()
            ->selectRaw('fest_registrations.item_id as item_id, count(*) as team_count, sum(case when fest_groups.chest_no is not null then 1 else 0 end) as chest_assigned')
            ->groupBy('fest_registrations.item_id')
            ->get()
            ->keyBy('item_id');

        $baseParticipantQuery = fn () => FestParticipant::query()
            ->join('fest_registrations', 'fest_participants.registration_id', '=', 'fest_registrations.id')
            ->leftJoin('fest_groups', 'fest_participants.group_id', '=', 'fest_groups.id')
            ->whereIn('fest_registrations.event_id', $eventIds)
            ->whereNotIn('fest_registrations.status', ['rejected', 'withdrawn'])
            ->where('fest_participants.participant_role', '!=', 'standby')
            ->where(fn ($q) => $q->whereNotNull('fest_participants.student_id')->orWhereNotNull('fest_participants.teacher_id'))
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId));

        $participantRows = $baseParticipantQuery()
            ->selectRaw('fest_registrations.item_id as item_id, count(*) as indiv_count, sum(case when fest_participants.chest_no is not null or fest_groups.chest_no is not null then 1 else 0 end) as chest_assigned')
            ->groupBy('fest_registrations.item_id')
            ->get()
            ->keyBy('item_id');

        $map = [];

        foreach ($items as $item) {
            $itemIds = $this->equivalentItemIdsFromMap((int) $item->id, $item->inherited_from_item_id, $item->item_code, $inScopeIds, $byRoot, $byCode);
            $isMultiPerson = \App\Support\FestTeamSquadRules::isMultiPerson($item->participant_type);
            $rows = $isMultiPerson ? $groupRows : $participantRows;
            $countKey = $isMultiPerson ? 'team_count' : 'indiv_count';

            $count = 0;
            $chestAssigned = 0;
            foreach ($itemIds as $iid) {
                $row = $rows->get($iid);
                if ($row) {
                    $count += (int) $row->{$countKey};
                    $chestAssigned += (int) $row->chest_assigned;
                }
            }

            $map[$item->id] = [
                'participant_count' => $count,
                'chest_assigned'    => $chestAssigned,
                'item_reg_assigned' => $chestAssigned,
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
                ->get([
                    'id', 'title', 'item_code', 'head_id', 'chest_no_start', 'item_reg_id_start',
                    'stage_type', 'reg_start', 'reg_end', 'competition_start', 'competition_end',
                    'competition_time', 'results_published_at',
                    'participant_type', 'gender', 'age_group', 'min_group_size', 'max_group_size', 'criteria_json',
                    'class_group', 'category',
                ]);

            if ($items->isEmpty() && $schoolId) {
                continue;
            }

            $numbering = app(FestNumberingService::class)->settings($sport);
            $defaultChestStart = (int) ($numbering['chest_no_start'] ?? 1);
            $classGroupLabels = FestClassGroupScheme::labels(null, $sport->parent_event_id ? $sport->rootEvent() : $sport);
            $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

            $itemPayloads = $items->map(
                fn (FestEventItem $item) => $this->itemNavPayload($item, $stats, $defaultChestStart, $sport->title, $classGroupLabels, $artsCategoryLabels),
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
        array $classGroupLabels = [],
        array $artsCategoryLabels = [],
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
            'participant_type'  => $item->participant_type,
            'gender'            => $item->gender,
            'age_group'         => $item->age_group,
            'category_label'    => FestItemCategoryLabel::resolve($item, $classGroupLabels, $artsCategoryLabels),
            'squad_summary'     => $item->squadSummary(),
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
