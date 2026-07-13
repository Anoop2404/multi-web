<?php

namespace App\Services\Events;

use App\Models\FestCompetitionType;
use App\Models\FestEvent;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class FestCompetitionTypeRegistry
{
    private ?string $tenantId = null;

    public function forTenant(string $tenantId): self
    {
        $clone = clone $this;
        $clone->tenantId = $tenantId;

        return $clone;
    }

    public function ensureDefaults(): void
    {
        if (! $this->tableExists() || ! $this->tenantId) {
            return;
        }

        foreach (config('fest_competition_types', []) as $typeKey => $meta) {
            FestCompetitionType::firstOrCreate(
                [
                    'tenant_id' => $this->tenantId,
                    'type_key' => $typeKey,
                ],
                [
                    'label' => $meta['label'],
                    'nav_slug' => $meta['nav_slug'] ?? null,
                    'route_prefix' => $meta['route_prefix'] ?? null,
                    'icon' => $meta['icon'] ?? null,
                    'description' => $meta['description'] ?? null,
                    'is_singleton' => (bool) ($meta['is_singleton'] ?? true),
                    'is_system' => true,
                    'sort_order' => (int) ($meta['sort_order'] ?? 100),
                    'is_active' => true,
                ]
            );
        }
    }

    /** @return list<array<string, mixed>> */
    public function rows(): array
    {
        if (! $this->tenantId) {
            return $this->configRows();
        }

        if (! $this->tableExists()) {
            return $this->configRows();
        }

        $this->ensureDefaults();

        return FestCompetitionType::forTenant($this->tenantId)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (FestCompetitionType $row) => [
                'id' => $row->id,
                'type_key' => $row->type_key,
                'label' => $row->label,
                'nav_slug' => $row->nav_slug,
                'route_prefix' => $row->route_prefix,
                'icon' => $row->icon,
                'description' => $row->description,
                'is_singleton' => $row->is_singleton,
                'is_system' => $row->is_system,
                'sort_order' => $row->sort_order,
                'is_active' => $row->is_active,
            ])
            ->all();
    }

    /** @return array<string, string> type_key => label */
    public function labels(bool $activeOnly = true): array
    {
        if (! $this->tenantId || ! $this->tableExists()) {
            return collect(config('fest_competition_types', []))
                ->mapWithKeys(fn ($meta, $key) => [$key => $meta['label']])
                ->all();
        }

        $this->ensureDefaults();

        $query = FestCompetitionType::forTenant($this->tenantId)->orderBy('sort_order');
        if ($activeOnly) {
            $query->active();
        }

        return $query->pluck('label', 'type_key')->all();
    }

    /** @return list<string> */
    public function activeKeys(): array
    {
        return array_keys($this->labels(true));
    }

    /** @return list<string> */
    public function singletonKeys(): array
    {
        if (! $this->tenantId || ! $this->tableExists()) {
            return collect(config('fest_competition_types', []))
                ->filter(fn ($meta) => (bool) ($meta['is_singleton'] ?? false))
                ->keys()
                ->values()
                ->all();
        }

        $this->ensureDefaults();

        return FestCompetitionType::forTenant($this->tenantId)
            ->active()
            ->where('is_singleton', true)
            ->orderBy('sort_order')
            ->pluck('type_key')
            ->all();
    }

    public function validationRule(): \Illuminate\Validation\Rules\In
    {
        $keys = $this->activeKeys();
        if ($keys === []) {
            $keys = array_keys(config('fest_competition_types', []));
        }

        return Rule::in($keys);
    }

    public function find(string $typeKey): ?FestCompetitionType
    {
        if (! $this->tenantId || ! $this->tableExists()) {
            return null;
        }

        $this->ensureDefaults();

        return FestCompetitionType::forTenant($this->tenantId)
            ->where('type_key', $typeKey)
            ->first();
    }

    public function findByNavSlug(string $navSlug): ?array
    {
        $programs = $this->programsForNav();

        return $programs[$navSlug] ?? null;
    }

    /**
     * Program hub / catalog meta keyed by nav slug (kalotsav, sports-meet, robotics, …).
     *
     * @return array<string, array{
     *   slug: string,
     *   eventType: string,
     *   label: string,
     *   icon: string,
     *   prefix: string,
     *   description: ?string,
     *   is_singleton: bool,
     *   is_system: bool
     * }>
     */
    public function programsForNav(bool $activeOnly = true): array
    {
        $out = [];

        foreach ($this->rows() as $row) {
            if ($activeOnly && empty($row['is_active'])) {
                continue;
            }

            $slug = $row['nav_slug'] ?: str_replace('_', '-', $row['type_key']);
            $isSystem = (bool) ($row['is_system'] ?? false);
            $routePrefix = $row['route_prefix'] ?: $slug;

            // System types keep short prefixes (/sports, /kalotsav). Custom types live under /programs/{slug}.
            $prefix = $isSystem && $row['type_key'] !== 'custom'
                ? $routePrefix
                : 'programs/'.$slug;

            $out[$slug] = [
                'slug' => $slug,
                'eventType' => $row['type_key'],
                'label' => $row['label'],
                'icon' => $row['icon'] ?: 'calendar',
                'prefix' => $prefix,
                'description' => $row['description'],
                'is_singleton' => (bool) ($row['is_singleton'] ?? false),
                'is_system' => $isSystem,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function programMeta(string $navSlug): ?array
    {
        return $this->findByNavSlug($navSlug);
    }

    public function slugForEventType(string $eventType): ?string
    {
        foreach ($this->programsForNav(false) as $slug => $meta) {
            if ($meta['eventType'] === $eventType) {
                return $slug;
            }
        }

        $config = config("fest_competition_types.{$eventType}");
        if (is_array($config)) {
            return $config['nav_slug'] ?? str_replace('_', '-', $eventType);
        }

        return null;
    }

    public function typeInUse(string $typeKey): bool
    {
        if (! $this->tenantId) {
            return false;
        }

        return FestEvent::where('tenant_id', $this->tenantId)
            ->where('event_type', $typeKey)
            ->exists();
    }

    /** Seed a default "General" catalog browse section for a newly created type. */
    public function ensureDefaultCatalogSection(string $typeKey): void
    {
        if (! $this->tenantId || ! Schema::hasTable('fest_taxonomy_masters')) {
            return;
        }

        $entryKey = $typeKey.'.general';
        if (\App\Models\FestTaxonomyMaster::where('tenant_id', $this->tenantId)
            ->where('dimension', 'catalog_section')
            ->where('entry_key', $entryKey)
            ->exists()) {
            return;
        }

        \App\Models\FestTaxonomyMaster::create([
            'tenant_id' => $this->tenantId,
            'dimension' => 'catalog_section',
            'entry_key' => $entryKey,
            'label' => 'General items',
            'sort_order' => 0,
            'is_active' => true,
            'meta' => [
                'event_type' => $typeKey,
                'slug' => 'general',
                'description' => 'Default catalog section',
                'filter' => [],
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function configRows(): array
    {
        $rows = [];
        foreach (config('fest_competition_types', []) as $typeKey => $meta) {
            $rows[] = [
                'id' => null,
                'type_key' => $typeKey,
                'label' => $meta['label'],
                'nav_slug' => $meta['nav_slug'] ?? null,
                'route_prefix' => $meta['route_prefix'] ?? null,
                'icon' => $meta['icon'] ?? null,
                'description' => $meta['description'] ?? null,
                'is_singleton' => (bool) ($meta['is_singleton'] ?? true),
                'is_system' => true,
                'sort_order' => (int) ($meta['sort_order'] ?? 100),
                'is_active' => true,
            ];
        }

        return $rows;
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('fest_competition_types');
    }
}
