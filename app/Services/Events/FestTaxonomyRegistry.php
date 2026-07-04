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
            $merged[$dimension] = $this->labels($dimension);
        }

        if (empty($merged['participant_type'])) {
            $merged['participant_type'] = [
                'individual' => 'Individual',
                'group'      => 'Group',
                'team'       => 'Team',
            ];
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
            ])
            ->all();
    }

    /** @return array<string, string> */
    private function configLabels(string $dimension): array
    {
        if ($dimension === 'participant_type') {
            return [
                'individual' => 'Individual',
                'group'      => 'Group',
                'team'       => 'Team',
            ];
        }

        return config("fest_item_taxonomy.{$dimension}", []);
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
