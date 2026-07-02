<?php

namespace App\Services\Events;

use App\Models\FestCatalogItem;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Support\FestItemCatalog;
use App\Support\FestSportsAgeGroup;
use Illuminate\Support\Str;

class FestCatalogService
{
    /** Seed CKSC master rows for a Sahodaya program (idempotent). */
    public function ensureSeeded(string $tenantId, string $eventType): int
    {
        $catalog = FestItemCatalog::forEventType($eventType);
        $order = (int) FestCatalogItem::forProgram($tenantId, $eventType)->max('display_order');
        $created = 0;

        foreach ($catalog as $row) {
            $key = $this->catalogKey($row);
            $normalized = $this->normalizeRow($row);

            $existing = FestCatalogItem::forProgram($tenantId, $eventType)
                ->where('catalog_key', $key)
                ->first();

            if ($existing) {
                if ($existing->source === 'cksc') {
                    $existing->update($this->syncableCatalogAttributes($normalized));
                }

                continue;
            }

            $order++;
            FestCatalogItem::create(array_merge($normalized, [
                'tenant_id'     => $tenantId,
                'event_type'    => $eventType,
                'catalog_key'   => $key,
                'source'        => 'cksc',
                'is_enabled'    => true,
                'fee_enabled'   => false,
                'display_order' => $order,
            ]));
            $created++;
        }

        if ($eventType === 'sports') {
            $this->retireObsoleteSportsCatalogItems($tenantId, $catalog);
        }

        return $created;
    }

    /** @param  array<string, mixed>  $normalized */
    private function syncableCatalogAttributes(array $normalized): array
    {
        return collect($normalized)->only([
            'title', 'item_code', 'category', 'stage_type', 'venue_type', 'competition_format',
            'sport_discipline', 'duration_minutes', 'criteria_json', 'participant_type', 'gender',
            'class_group', 'age_group', 'kids_band', 'max_per_school', 'min_group_size',
            'max_group_size', 'qualify_count',
        ])->all();
    }

    /** Disable superseded open-age CKSC sports templates after U14/U17/U19 expansion. */
    private function retireObsoleteSportsCatalogItems(string $tenantId, array $catalog): void
    {
        $validKeys = collect($catalog)->map(fn (array $row) => $this->catalogKey($row))->all();

        FestCatalogItem::forProgram($tenantId, 'sports')
            ->where('source', 'cksc')
            ->where('age_group', 'open')
            ->whereNotIn('catalog_key', $validKeys)
            ->update(['is_enabled' => false]);
    }

    /**
     * Copy enabled master items into a fest event (skips duplicates by item_code or title).
     *
     * @param  list<string>|null  $classGroups
     * @param  list<int>|null  $catalogItemIds
     */
    public function importEnabledToEvent(FestEvent $event, ?array $classGroups = null, ?array $catalogItemIds = null): int
    {
        $this->ensureSeeded($event->tenant_id, $event->event_type);

        $query = FestCatalogItem::forProgram($event->tenant_id, $event->event_type)
            ->where('is_enabled', true)
            ->orderBy('display_order');

        if ($catalogItemIds !== null && $catalogItemIds !== []) {
            $query->whereIn('id', $catalogItemIds);
        }

        $items = $query->get();

        if ($classGroups) {
            $items = $items->filter(function (FestCatalogItem $item) use ($classGroups, $event) {
                if (($item->age_group ?? '') === 'open' || ($item->class_group ?? '') === 'open') {
                    return true;
                }

                if ($event->event_type === 'sports' && $item->age_group) {
                    $allowedAgeGroups = array_values(array_filter(array_map(
                        fn ($cg) => FestSportsAgeGroup::fromClassGroup($cg),
                        $classGroups
                    )));

                    return in_array($item->age_group, $allowedAgeGroups, true);
                }

                return in_array($item->class_group ?? 'open', $classGroups, true);
            });
        }

        $existingCodes = FestEventItem::where('event_id', $event->id)
            ->whereNotNull('item_code')
            ->pluck('item_code')
            ->all();
        $existingTitles = FestEventItem::where('event_id', $event->id)
            ->pluck('title')
            ->map(fn ($t) => strtolower(trim($t)))
            ->all();

        $order = (int) FestEventItem::where('event_id', $event->id)->max('display_order');
        $created = 0;

        foreach ($items as $catalogItem) {
            if ($catalogItem->item_code && in_array($catalogItem->item_code, $existingCodes, true)) {
                continue;
            }
            if (in_array(strtolower(trim($catalogItem->title)), $existingTitles, true)) {
                continue;
            }

            $order++;
            FestEventItem::create(array_merge($catalogItem->toEventAttributes(), [
                'event_id'      => $event->id,
                'display_order' => $order,
            ]));
            $created++;
        }

        return $created;
    }

    /** @return array<string, mixed> */
    public function summary(string $tenantId, string $eventType): array
    {
        $base = FestCatalogItem::forProgram($tenantId, $eventType);

        return [
            'total'   => (clone $base)->count(),
            'enabled' => (clone $base)->where('is_enabled', true)->count(),
            'cksc'    => (clone $base)->where('source', 'cksc')->count(),
            'custom'  => (clone $base)->where('source', 'custom')->count(),
        ];
    }

    /** @param  array<string, mixed>  $row */
    public function catalogKey(array $row): string
    {
        if (! empty($row['item_code'])) {
            return 'code:'.$row['item_code'];
        }

        return 'title:'.Str::slug($row['title'] ?? 'item');
    }

    /** @param  array<string, mixed>  $row */
    public function normalizeRow(array $row): array
    {
        $artsCategory = $row['category'] ?? 'general';
        $row['category'] = match ($artsCategory) {
            'fine_arts', 'technology' => 'general',
            'traditional'             => 'dance',
            default                   => in_array($artsCategory, ['music', 'dance', 'drama', 'literary', 'sports', 'general'], true)
                ? $artsCategory : 'general',
        };

        if ($artsCategory !== $row['category']) {
            $criteria = $row['criteria_json'] ?? [];
            $criteria['arts_category'] = $artsCategory;
            $row['criteria_json'] = $criteria;
        }

        unset($row['owner_level']);

        return $row;
    }
}
