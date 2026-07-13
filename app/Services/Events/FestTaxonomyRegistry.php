<?php

namespace App\Services\Events;

use App\Models\FestTaxonomyMaster;
use App\Support\FestSportsAgeGroup;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class FestTaxonomyRegistry
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $configCache = null;

    public function forTenant(string $tenantId): self
    {
        $clone = clone $this;
        $clone->tenantId = $tenantId;

        return $clone;
    }

    private ?string $tenantId = null;

    public function ensureDefaults(?string $dimension = null): void
    {
        if (! $this->tableExists() || ! $this->tenantId) {
            return;
        }

        $dimensions = $dimension ? [$dimension] : array_keys(FestTaxonomyMaster::DIMENSIONS);

        foreach ($dimensions as $dim) {
            if ($dim === 'catalog_section') {
                $this->ensureCatalogSectionDefaults();
                continue;
            }

            if ($this->query($dim)->exists()) {
                continue;
            }

            $sort = 0;
            foreach ($this->configLabels($dim) as $key => $label) {
                FestTaxonomyMaster::create([
                    'tenant_id'  => $this->tenantId,
                    'dimension'  => $dim,
                    'entry_key'  => $key,
                    'label'      => $label,
                    'sort_order' => $sort++,
                    'is_active'  => true,
                ]);
            }
        }
    }

    /**
     * Seed browse sections for every competition type (keyed as {event_type}.{slug}).
     */
    public function ensureCatalogSectionDefaults(): void
    {
        if (! $this->tableExists() || ! $this->tenantId) {
            return;
        }

        if ($this->query('catalog_section')->exists()) {
            return;
        }

        $sort = 0;
        foreach (config('fest_catalog_sections', []) as $eventType => $sections) {
            foreach ($sections as $section) {
                FestTaxonomyMaster::create([
                    'tenant_id'  => $this->tenantId,
                    'dimension'  => 'catalog_section',
                    'entry_key'  => $eventType.'.'.$section['slug'],
                    'label'      => $section['label'],
                    'sort_order' => $sort++,
                    'is_active'  => true,
                    'meta'       => [
                        'event_type'  => $eventType,
                        'slug'        => $section['slug'],
                        'description' => $section['description'] ?? '',
                        'filter'      => $section['filter'] ?? [],
                    ],
                ]);
            }
        }
    }

    /**
     * @return list<array{slug: string, label: string, description: string, filter: array<string, string>}>
     */
    public function catalogSectionsForEventType(string $eventType): array
    {
        if (! $this->tenantId || ! $this->tableExists()) {
            return $this->configCatalogSections($eventType);
        }

        $this->ensureCatalogSectionDefaults();

        $rows = FestTaxonomyMaster::where('tenant_id', $this->tenantId)
            ->where('dimension', 'catalog_section')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->filter(function (FestTaxonomyMaster $row) use ($eventType) {
                $metaType = $row->meta['event_type'] ?? null;
                if ($metaType) {
                    return $metaType === $eventType;
                }

                return str_starts_with($row->entry_key, $eventType.'.');
            })
            ->values();

        if ($rows->isEmpty()) {
            return $this->configCatalogSections($eventType);
        }

        return $rows->map(function (FestTaxonomyMaster $row) use ($eventType) {
            $meta = $row->meta ?? [];
            $slug = $meta['slug'] ?? (str_contains($row->entry_key, '.')
                ? substr($row->entry_key, strlen($eventType) + 1)
                : $row->entry_key);

            return [
                'slug'        => $slug,
                'label'       => $row->label,
                'description' => (string) ($meta['description'] ?? ''),
                'filter'      => is_array($meta['filter'] ?? null) ? $meta['filter'] : [],
            ];
        })->all();
    }

    /** @return array<string, string> */
    public function labels(?string $dimension = null): array
    {
        if (! $this->tenantId) {
            return $dimension ? $this->configLabels($dimension) : [];
        }

        if (! $this->tableExists()) {
            return $dimension ? $this->configLabels($dimension) : [];
        }

        $this->ensureDefaults($dimension);

        $query = FestTaxonomyMaster::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label');

        if ($dimension) {
            $query->where('dimension', $dimension);
        }

        $fromDb = $query->pluck('label', 'entry_key')->all();

        if ($fromDb !== []) {
            return $fromDb;
        }

        return $dimension ? $this->configLabels($dimension) : [];
    }

    /** @return array<string, array<string, string>> */
    public function allLabels(): array
    {
        $merged = config('fest_item_taxonomy', []);
        $merged['class_group'] = $merged['class_group'] ?? [];
        $merged['age_group'] = FestSportsAgeGroup::labels($this->tenantId);
        $merged['kids_band'] = \App\Support\FestKidsFestBand::labels();

        foreach (array_keys(FestTaxonomyMaster::DIMENSIONS) as $dimension) {
            if ($dimension === 'catalog_section') {
                continue;
            }
            $merged[$dimension] = $this->labels($dimension);
        }

        if (empty($merged['participant_type'])) {
            $merged['participant_type'] = [
                'individual' => 'Individual',
                'pair'       => 'Pair',
                'trio'       => 'Trio',
                'group'      => 'Group',
                'team'       => 'Team',
            ];
        }

        if (empty($merged['result_method'])) {
            $merged['result_method'] = $this->configLabels('result_method');
        }

        return $merged;
    }

    /** @return list<string> */
    public function activeKeys(string $dimension): array
    {
        return array_keys($this->labels($dimension));
    }

    public function validationRule(string $dimension): \Illuminate\Validation\Rules\In
    {
        return Rule::in($this->activeKeys($dimension));
    }

    /** @return list<array<string, mixed>> */
    public function rowsForDimension(string $dimension): array
    {
        if (! $this->tableExists() || ! $this->tenantId) {
            return collect($this->configLabels($dimension))
                ->map(fn ($label, $key) => [
                    'id'         => null,
                    'entry_key'  => $key,
                    'label'      => $label,
                    'sort_order' => 0,
                    'is_active'  => true,
                    'from_config'=> true,
                    'meta'       => null,
                ])
                ->values()
                ->all();
        }

        $this->ensureDefaults($dimension);

        return FestTaxonomyMaster::where('tenant_id', $this->tenantId)
            ->where('dimension', $dimension)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (FestTaxonomyMaster $row) => [
                'id'         => $row->id,
                'entry_key'  => $row->entry_key,
                'label'      => $row->label,
                'sort_order' => $row->sort_order,
                'is_active'  => $row->is_active,
                'from_config'=> false,
                'meta'       => $row->meta,
            ])
            ->all();
    }

    /** @return array<string, string> */
    private function configLabels(string $dimension): array
    {
        if ($dimension === 'participant_type') {
            return [
                'individual' => 'Individual',
                'pair'       => 'Pair',
                'trio'       => 'Trio',
                'group'      => 'Group',
                'team'       => 'Team',
            ];
        }

        if ($dimension === 'result_method') {
            return [
                'marks'     => 'Marks / score',
                'time'      => 'Time (faster wins)',
                'distance'  => 'Distance / measurement',
                'rank'      => 'Rank only',
                'pass_fail' => 'Pass / fail',
                'points'    => 'Points',
            ];
        }

        if ($dimension === 'catalog_section') {
            $labels = [];
            foreach (config('fest_catalog_sections', []) as $eventType => $sections) {
                foreach ($sections as $section) {
                    $labels[$eventType.'.'.$section['slug']] = $section['label'].' ('.$eventType.')';
                }
            }

            return $labels;
        }

        return config("fest_item_taxonomy.{$dimension}", []);
    }

    /**
     * @return list<array{slug: string, label: string, description: string, filter: array<string, string>}>
     */
    private function configCatalogSections(string $eventType): array
    {
        return config("fest_catalog_sections.{$eventType}", []);
    }

    private function query(string $dimension)
    {
        return FestTaxonomyMaster::where('tenant_id', $this->tenantId)
            ->where('dimension', $dimension);
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('fest_taxonomy_masters');
        } catch (\Throwable) {
            return false;
        }
    }
}
