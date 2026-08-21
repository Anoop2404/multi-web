<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PortalEventHeadNavService
{
    public function __construct(
        private FestHeadItemNavigationService $navigation,
    ) {}

    /**
     * Head/item data for portal mark/registration pages, any event type. Items are
     * always included (unlike the old sports-only, head-tabs-without-items shape this
     * returned) so callers can build a single flat item picker instead of a two-step
     * "pick a head, then an item" UI.
     *
     * @return array{
     *     headItemGroups: list<array<string, mixed>>,
     *     headsForFilter: list<array{id: int, name: string}>,
     *     hasItemHeads: bool,
     *     selectedHeadId: int|string|null,
     *     selectedItemId: int|null
     * }
     */
    public function context(FestEvent $event, Request $request, ?string $schoolId = null): array
    {
        $nav = $this->navigation->navigationForEvent($event, $schoolId);
        $slim = $this->navigation->slimNavigation($nav, includeItems: true);

        return array_merge($slim, [
            'selectedHeadId' => $this->parseHeadId($request),
            'selectedItemId' => $request->integer('item_id') ?: null,
        ]);
    }

    /** Head tabs limited to heads that contain at least one assigned item (judges). */
    public function contextForAssignedItems(FestEvent $event, Request $request, array $itemIds): array
    {
        $ctx = $this->context($event, $request);

        if ($itemIds === [] || ! ($ctx['hasItemHeads'] ?? false)) {
            return $ctx;
        }

        $full = $this->navigation->navigationForEvent($event);
        $allowed = array_flip(array_map('intval', $itemIds));

        $filtered = collect($full['headItemGroups'] ?? [])
            ->map(function (array $group) use ($allowed) {
                $group['items'] = collect($group['items'] ?? [])
                    ->filter(fn (array $item) => isset($allowed[(int) $item['id']]))
                    ->values()
                    ->all();

                return $group;
            })
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()
            ->all();

        if ($filtered === []) {
            return $ctx;
        }

        return array_merge($ctx, $this->navigation->slimNavigation([
            'headItemGroups'  => $filtered,
            'headsForFilter'  => $full['headsForFilter'] ?? [],
            'hasItemHeads'    => true,
            'unassignedItems' => [],
        ], includeItems: true));
    }

    /** @param  Collection<int, FestRegistration>  $registrations */
    public function filterRegistrations(Collection $registrations, int|string|null $headId, ?int $itemId = null): Collection
    {
        if ($itemId) {
            return $registrations->filter(fn (FestRegistration $r) => (int) $r->item_id === $itemId)->values();
        }

        if ($headId === null || $headId === '') {
            return $registrations;
        }

        if ($headId === 'other') {
            return $registrations->filter(fn (FestRegistration $r) => $r->item?->head_id === null)->values();
        }

        $headId = (int) $headId;

        return $registrations->filter(fn (FestRegistration $r) => (int) ($r->item?->head_id ?? 0) === $headId)->values();
    }

    private function parseHeadId(Request $request): int|string|null
    {
        if (! $request->has('head_id')) {
            return null;
        }

        $raw = $request->input('head_id');
        if ($raw === 'other' || $raw === '' || $raw === null) {
            return $raw === 'other' ? 'other' : null;
        }

        return (int) $raw;
    }
}
