<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A trophy at a State event: a named group of items that crowns a winner.
 *
 * Distinct from an item's descriptive `category` tag — see the migration.
 */
class StatePrizeCategory extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_prize_categories';

    /** The State competes Sahodaya against Sahodaya, but a trophy can still name a school or a person. */
    public const AWARD_INDIVIDUAL = 'individual';

    public const AWARD_SCHOOL = 'school';

    public const AWARD_SAHODAYA = 'sahodaya';

    public const AWARDS = [
        self::AWARD_INDIVIDUAL => 'Individual champion',
        self::AWARD_SCHOOL => 'School champion',
        self::AWARD_SAHODAYA => 'Sahodaya champion',
    ];

    protected $fillable = [
        'state_event_id', 'state_id', 'code', 'name', 'description',
        'awards', 'is_overall', 'honour_count', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'awards' => 'array',
            'is_overall' => 'boolean',
            'is_active' => 'boolean',
            'honour_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StatePrizeCategoryItem::class, 'prize_category_id');
    }

    public function awards(string $award): bool
    {
        return in_array($award, $this->awards ?? [], true);
    }

    /** @return list<string> */
    public function awardLabels(): array
    {
        return array_values(array_map(
            fn (string $a) => self::AWARDS[$a] ?? $a,
            $this->awards ?? [],
        ));
    }
}
