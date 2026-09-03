<?php

namespace App\Services\Events;

use App\Models\FestSportsAgeGroupConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class FestSportsAgeGroupRegistry
{
    private ?string $tenantId = null;

    /** @var Collection<int, FestSportsAgeGroupConfig>|null */
    private ?Collection $rows = null;

    public function forTenant(string $tenantId): self
    {
        $clone = clone $this;
        $clone->tenantId = $tenantId;
        $clone->rows = null;

        return $clone;
    }

    public function ensureDefaults(): void
    {
        if (! $this->tableExists() || ! $this->tenantId) {
            return;
        }

        if ($this->query()->exists()) {
            return;
        }

        $sort = 0;
        foreach (config('fest_co_curricular.sports.age_groups', []) as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            FestSportsAgeGroupConfig::create([
                'tenant_id'   => $this->tenantId,
                'group_key'   => $key,
                'label'       => $row['label'] ?? strtoupper($key),
                'under_age'   => $row['under_age'],
                'sort_order'  => $sort++,
                'default_fee' => config("fest_sports_age_groups.default_fees.{$key}"),
                'is_active'   => true,
            ]);
        }

        $this->rows = null;
    }

    /** @return Collection<int, FestSportsAgeGroupConfig> */
    public function all(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        if (! $this->tableExists() || ! $this->tenantId) {
            return $this->rows = collect($this->configRows());
        }

        $this->ensureDefaults();

        $this->rows = $this->query()->orderBy('sort_order')->orderBy('group_key')->get();

        if ($this->rows->isEmpty()) {
            $this->rows = collect($this->configRows());
        }

        return $this->rows;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return $this->all()->pluck('group_key')->all();
    }

    /** @return list<string> */
    public function activeKeys(): array
    {
        return $this->all()->where('is_active', true)->pluck('group_key')->values()->all();
    }

    /** @return list<string> Ordered active bands used for eligibility (excludes open). */
    public function orderedAgeGroups(): array
    {
        return $this->all()
            ->where('is_active', true)
            ->where('group_key', '!=', 'open')
            ->sortBy('sort_order')
            ->pluck('group_key')
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    public function labels(): array
    {
        return $this->all()->mapWithKeys(fn ($row) => [
            $row->group_key => $row->label,
        ])->all();
    }

    /** @return array<string, float> */
    public function defaultFees(): array
    {
        return $this->all()->mapWithKeys(function ($row) {
            $fee = $row->default_fee ?? config("fest_sports_age_groups.default_fees.{$row->group_key}", 0);

            return [$row->group_key => (float) $fee];
        })->all();
    }

    public function isValid(?string $ageGroup): bool
    {
        if (! filled($ageGroup)) {
            return false;
        }

        return in_array($ageGroup, $this->activeKeys(), true);
    }

    public function underAge(string $ageGroup): ?int
    {
        if ($ageGroup === 'open') {
            return null;
        }

        $row = $this->all()->firstWhere('group_key', $ageGroup);

        return $row?->under_age;
    }

    /** @return list<array<string, mixed>> */
    private function configRows(): array
    {
        $rows = [];
        $sort = 0;

        foreach (config('fest_co_curricular.sports.age_groups', []) as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = (object) [
                'group_key'   => $key,
                'label'       => $row['label'] ?? strtoupper($key),
                'under_age'   => $row['under_age'],
                'sort_order'  => $sort++,
                'default_fee' => config("fest_sports_age_groups.default_fees.{$key}"),
                'is_active'   => true,
            ];
        }

        return $rows;
    }

    private function query()
    {
        return FestSportsAgeGroupConfig::where('tenant_id', $this->tenantId);
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('fest_sports_age_group_configs');
        } catch (\Throwable) {
            return false;
        }
    }
}
