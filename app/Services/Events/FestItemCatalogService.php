<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;

class FestItemCatalogService
{
    /** Import enabled master-catalog items into an event (skips duplicates by item_code or title). */
    public function import(FestEvent $event, ?array $classGroups = null): int
    {
        return app(FestCatalogService::class)->importEnabledToEvent($event, $classGroups);
    }

    /**
     * Group items for admin display using event-type sort order.
     *
     * @param  \Illuminate\Support\Collection<int, FestEventItem>  $items
     * @return array<string, array<string, mixed>>
     */
    public function groupForDisplay($items, string $eventType): array
    {
        $taxonomy = config('fest_item_taxonomy');
        $sortKeys = $taxonomy['sort_order'][$eventType] ?? ['title'];

        $grouped = [];
        foreach ($items as $item) {
            $path = [];
            foreach ($sortKeys as $key) {
                if ($key === 'title') {
                    continue;
                }
                $val = $item->{$key} ?? 'other';
                $label = $this->labelFor($key, $val, $taxonomy);
                $path[] = $label;
            }
            $bucket = implode(' › ', $path) ?: 'All Items';
            $grouped[$bucket] ??= [];
            $grouped[$bucket][] = $item;
        }

        ksort($grouped);

        return $grouped;
    }

    /** Map extended arts categories to DB enum + preserve detail in criteria_json. */
    private function normalizeRow(array $row): array
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

        return $row;
    }

    private function labelFor(string $key, mixed $val, array $taxonomy): string
    {
        $val = $val ?: 'other';

        return match ($key) {
            'stage_type'           => $taxonomy['stage_type'][$val] ?? ucfirst(str_replace('_', ' ', (string) $val)),
            'venue_type'           => $taxonomy['venue_type'][$val] ?? ucfirst((string) $val),
            'competition_format'   => $taxonomy['competition_format'][$val] ?? ucfirst(str_replace('_', ' ', (string) $val)),
            'sport_discipline'     => $taxonomy['sport_discipline'][$val] ?? ucfirst(str_replace('_', ' ', (string) $val)),
            'category'             => $taxonomy['arts_category'][$val] ?? ucfirst((string) $val),
            'class_group'          => $taxonomy['class_group'][$val] ?? strtoupper((string) $val),
            'age_group'            => $taxonomy['age_group'][$val] ?? strtoupper((string) $val),
            'kids_band'            => $taxonomy['kids_band'][$val] ?? strtoupper(str_replace('_', ' ', (string) $val)),
            'gender'               => $taxonomy['gender'][$val] ?? ucfirst((string) $val),
            default                => (string) $val,
        };
    }
}
