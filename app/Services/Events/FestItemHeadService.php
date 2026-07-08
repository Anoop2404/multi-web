<?php

namespace App\Services\Events;

use App\Models\FestCatalogItem;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use Illuminate\Support\Str;

class FestItemHeadService
{
    /** @return list<array{key: string, name: string, sport_discipline: ?string, is_team_heading: bool}> */
    public static function catalogHeadDefinitions(): array
    {
        return require app_path('Support/data/cksc_sports_heads.php');
    }

    /** Resolve main item head (Chess, Athletics, …) for a catalog row. */
    public static function resolveCatalogHeadKey(array $row): ?string
    {
        $title = strtolower((string) ($row['title'] ?? ''));

        if (str_contains($title, 'chess')) {
            return 'chess';
        }

        if (str_contains($title, 'carrom')) {
            return 'carrom';
        }

        $discipline = $row['sport_discipline'] ?? null;
        if (! $discipline) {
            return null;
        }

        foreach (self::catalogHeadDefinitions() as $def) {
            if (($def['sport_discipline'] ?? null) !== $discipline) {
                continue;
            }

            // board_game is split by title above (chess vs carrom).
            if ($discipline === 'board_game') {
                continue;
            }

            return $def['key'];
        }

        return null;
    }

    public static function headLabel(?string $headKey): string
    {
        if (! $headKey) {
            return 'Other';
        }

        foreach (self::catalogHeadDefinitions() as $def) {
            if ($def['key'] === $headKey) {
                return $def['name'];
            }
        }

        return ucfirst(str_replace('_', ' ', $headKey));
    }

    public function ensureCatalogHeads(string $tenantId, string $eventType = 'sports'): int
    {
        if ($eventType !== 'sports') {
            return 0;
        }

        $created = 0;
        $order = (int) FestItemHead::forTenant($tenantId)->whereNull('event_id')->max('sort_order');

        foreach (self::catalogHeadDefinitions() as $def) {
            $slug = Str::slug($def['key']);
            $existing = FestItemHead::forTenant($tenantId)
                ->whereNull('event_id')
                ->where('catalog_key', $def['key'])
                ->first();

            if ($existing) {
                $existing->update([
                    'name' => $def['name'],
                    'sport_discipline' => $def['sport_discipline'] ?? null,
                    'is_team_heading' => (bool) ($def['is_team_heading'] ?? true),
                ]);

                continue;
            }

            $order++;
            FestItemHead::create([
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'catalog_key' => $def['key'],
                'name' => $def['name'],
                'slug' => $slug,
                'sport_discipline' => $def['sport_discipline'] ?? null,
                'is_team_heading' => (bool) ($def['is_team_heading'] ?? true),
                'sort_order' => $order,
            ]);
            $created++;
        }

        return $created;
    }

    /** Assign head_key on all sports master catalog rows (Chess items → chess head, etc.). */
    public function syncCatalogItemHeadKeys(string $tenantId, string $eventType = 'sports'): int
    {
        if ($eventType !== 'sports') {
            return 0;
        }

        $updated = 0;

        FestCatalogItem::forProgram($tenantId, $eventType)->each(function (FestCatalogItem $item) use (&$updated) {
            $headKey = self::resolveCatalogHeadKey([
                'title' => $item->title,
                'sport_discipline' => $item->sport_discipline,
            ]);

            if ($headKey === $item->head_key) {
                return;
            }

            $item->update(['head_key' => $headKey]);
            $updated++;
        });

        return $updated;
    }

    public function syncEventHeads(FestEvent $event): int
    {
        $tenantId = $event->tenant_id;
        $catalogHeads = FestItemHead::forTenant($tenantId)
            ->whereNull('event_id')
            ->where('event_type', $event->event_type)
            ->orderBy('sort_order')
            ->get();

        $map = [];
        $created = 0;

        foreach ($catalogHeads as $catalogHead) {
            $eventHead = FestItemHead::forTenant($tenantId)
                ->forEvent($event->id)
                ->where('catalog_key', $catalogHead->catalog_key)
                ->first();

            if ($eventHead) {
                $eventHead->update([
                    'name' => $catalogHead->name,
                    'slug' => $catalogHead->slug,
                    'sport_discipline' => $catalogHead->sport_discipline,
                    'is_team_heading' => $catalogHead->is_team_heading,
                    'sort_order' => $catalogHead->sort_order,
                ]);
            } else {
                $eventHead = FestItemHead::create([
                    'tenant_id' => $tenantId,
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                    'catalog_key' => $catalogHead->catalog_key,
                    'name' => $catalogHead->name,
                    'slug' => $catalogHead->slug,
                    'sport_discipline' => $catalogHead->sport_discipline,
                    'is_team_heading' => $catalogHead->is_team_heading,
                    'sort_order' => $catalogHead->sort_order,
                ]);
                $created++;
            }

            $map[$catalogHead->catalog_key] = $eventHead->id;
        }

        FestEventItem::where('event_id', $event->id)
            ->each(function (FestEventItem $item) use ($map) {
                $headKey = self::resolveCatalogHeadKey([
                    'title' => $item->title,
                    'sport_discipline' => $item->sport_discipline,
                ]);

                if (! $headKey || ! isset($map[$headKey])) {
                    return;
                }

                $headId = (int) $map[$headKey];
                if ((int) $item->head_id === $headId) {
                    return;
                }

                $item->update(['head_id' => $headId]);
            });

        return $created;
    }

    /** Re-seed master catalogs and relink sports item heads for an existing tenant DB. */
    public function backfillTenant(string $tenantId, array $eventTypes, bool $linkEventHeads = true): array
    {
        $totals = [
            'created' => 0,
            'updated' => 0,
            'heads_created' => 0,
            'head_links' => 0,
            'events_synced' => 0,
        ];

        foreach ($eventTypes as $eventType) {
            $sync = app(FestCatalogService::class)->ensureSeeded($tenantId, $eventType);
            foreach (['created', 'updated', 'heads_created', 'head_links'] as $key) {
                $totals[$key] += (int) ($sync[$key] ?? 0);
            }
        }

        if ($linkEventHeads) {
            FestEvent::forTenant($tenantId)
                ->ofType('sports')
                ->each(function (FestEvent $event) use (&$totals) {
                    app(self::class)->syncEventHeads($event);
                    $totals['events_synced']++;
                });
        }

        return $totals;
    }

    /** @return list<FestItemHead> */
    public function headsForEvent(FestEvent $event): array
    {
        return FestItemHead::forTenant($event->tenant_id)
            ->forEvent($event->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['items' => fn ($q) => $q->where('event_id', $event->id)->enabled()])
            ->get()
            ->all();
    }

    public function userCanAccessHead(?int $userId, FestEvent $event, ?int $headId): bool
    {
        if (! $userId || ! $headId) {
            return true;
        }

        $assigned = \App\Models\FestEventStaff::where('event_id', $event->id)
            ->where('user_id', $userId)
            ->get();

        if ($assigned->isEmpty()) {
            return true;
        }

        return $assigned->contains(fn ($row) => $row->head_id === null || (int) $row->head_id === (int) $headId);
    }

    /** @param  array<string, mixed>  $dates */
    public function applyWindowToItems(FestItemHead $head, FestEvent $event, array $dates, bool $overwriteItemDates = true): int
    {
        $payload = array_filter([
            'reg_start'          => $dates['reg_start'] ?? null,
            'reg_end'            => $dates['reg_end'] ?? null,
            'competition_start'  => $dates['competition_start'] ?? null,
            'competition_end'    => $dates['competition_end'] ?? null,
            'competition_time'   => $dates['competition_time'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($payload === []) {
            return 0;
        }

        $query = FestEventItem::query()
            ->where('event_id', $event->id)
            ->where('head_id', $head->id);

        if (! $overwriteItemDates) {
            foreach (array_keys($payload) as $column) {
                $query->whereNull($column);
            }
        }

        return $query->update($payload);
    }
}
