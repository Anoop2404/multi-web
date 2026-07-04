<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class FestCatalogItem extends Model
{
    protected $fillable = [
        'tenant_id', 'event_type', 'catalog_key', 'source', 'is_enabled', 'is_mandatory', 'fee_enabled',
        'title', 'item_code', 'category', 'stage_type', 'venue_type', 'competition_format',
        'sport_discipline', 'duration_minutes', 'criteria_json', 'participant_type', 'gender',
        'class_group', 'age_group', 'kids_band', 'max_per_school', 'min_group_size',
        'max_group_size', 'qualify_count', 'fee_amount', 'display_order',
    ];

    protected $casts = [
        'criteria_json' => 'array',
        'is_enabled' => 'boolean',
        'is_mandatory' => 'boolean',
        'fee_enabled' => 'boolean',
        'fee_amount' => 'decimal:2',
    ];

    public function scopeForProgram(Builder $query, string $tenantId, string $eventType): Builder
    {
        return $query->where('tenant_id', $tenantId)->where('event_type', $eventType);
    }

    public function isCustom(): bool
    {
        return $this->source === 'custom';
    }

    /** @return array<string, mixed> */
    public function toEventAttributes(): array
    {
        $attrs = collect($this->only([
            'title', 'item_code', 'category', 'stage_type', 'venue_type', 'competition_format',
            'sport_discipline', 'duration_minutes', 'criteria_json', 'participant_type', 'gender',
            'class_group', 'age_group', 'kids_band', 'max_per_school', 'min_group_size',
            'max_group_size', 'qualify_count', 'display_order', 'is_mandatory',
        ]))->filter(fn ($v) => $v !== null)->all();

        if ($this->fee_enabled && $this->fee_amount !== null) {
            $attrs['fee_amount'] = $this->fee_amount;
        }

        $attrs['owner_level'] = 'sahodaya';

        return $attrs;
    }
}
