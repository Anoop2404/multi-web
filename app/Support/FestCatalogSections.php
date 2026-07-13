<?php

namespace App\Support;

use App\Services\Events\FestTaxonomyRegistry;

/**
 * Master catalog browse sections — one page per group (sports discipline, kalotsav category, etc.).
 * Backed by FestTaxonomyMaster (dimension=catalog_section) with config fallback (FRD-08 Phase 0).
 */
class FestCatalogSections
{
    /** @return list<array{slug: string, label: string, description: string, filter: array<string, string>}> */
    public static function forEventType(string $eventType, ?string $tenantId = null): array
    {
        if ($tenantId) {
            return app(FestTaxonomyRegistry::class)
                ->forTenant($tenantId)
                ->catalogSectionsForEventType($eventType);
        }

        return config("fest_catalog_sections.{$eventType}", []);
    }

    /** @return array{slug: string, label: string, description: string, filter: array<string, string>}|null */
    public static function find(string $eventType, string $slug, ?string $tenantId = null): ?array
    {
        foreach (self::forEventType($eventType, $tenantId) as $section) {
            if ($section['slug'] === $slug) {
                return $section;
            }
        }

        return null;
    }

    /** @param  array<string, string>  $filter */
    public static function applyFilter($query, array $filter): void
    {
        foreach ($filter as $column => $value) {
            if ($column === 'participant_type' && $value === 'individual') {
                $query->where(function ($q) {
                    $q->where('participant_type', 'individual')->orWhereNull('participant_type');
                });

                continue;
            }

            $query->where($column, $value);
        }
    }

    /**
     * @return list<array{slug: string, label: string, description: string, total: int, enabled: int}>
     */
    public static function summaries(string $tenantId, string $eventType): array
    {
        $sections = [];
        $base = \App\Models\FestCatalogItem::forProgram($tenantId, $eventType);

        foreach (self::forEventType($eventType, $tenantId) as $section) {
            $q = clone $base;
            self::applyFilter($q, $section['filter']);
            $sections[] = [
                'slug'        => $section['slug'],
                'label'       => $section['label'],
                'description' => $section['description'],
                'total'       => (clone $q)->count(),
                'enabled'     => (clone $q)->where('is_enabled', true)->count(),
            ];
        }

        return $sections;
    }
}
