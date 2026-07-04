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

        FestCatalogItem::forProgram($tenantId, $eventType)
            ->whereNotNull('head_key')
            ->each(function (FestCatalogItem $item) use ($tenantId) {
                if (! $item->head_key) {
                    return;
                }
                FestItemHead::forTenant($tenantId)
                    ->whereNull('event_id')
                    ->where('catalog_key', $item->head_key)
                    ->exists();
            });

        return $created;
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

            if (! $eventHead) {
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
            if ($catalogHead->sport_discipline) {
                $map[$catalogHead->sport_discipline] = $eventHead->id;
            }
        }

        FestEventItem::where('event_id', $event->id)
            ->whereNull('head_id')
            ->each(function (FestEventItem $item) use ($map, $tenantId) {
                $headKey = $this->resolveHeadKeyForItem($item, $tenantId);
                if ($headKey && isset($map[$headKey])) {
                    $item->update(['head_id' => $map[$headKey]]);

                    return;
                }
                if ($item->sport_discipline && isset($map[$item->sport_discipline])) {
                    $item->update(['head_id' => $map[$item->sport_discipline]]);
                }
            });

        return $created;
    }

    private function resolveHeadKeyForItem(FestEventItem $item, string $tenantId): ?string
    {
        if ($item->sport_discipline) {
            $head = FestItemHead::forTenant($tenantId)
                ->whereNull('event_id')
                ->where('sport_discipline', $item->sport_discipline)
                ->first();
            if ($head?->catalog_key) {
                return $head->catalog_key;
            }
        }

        $title = strtolower($item->title ?? '');
        if (str_contains($title, 'chess')) {
            return 'chess';
        }
        if (str_contains($title, 'carrom')) {
            return 'carrom';
        }

        return null;
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

        return $assigned->contains(fn ($row) => $row->duty === 'discipline' && (int) $row->head_id === (int) $headId)
            || $assigned->contains(fn ($row) => $row->duty !== 'discipline' && $row->head_id === null);
    }
}
