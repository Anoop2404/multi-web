<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A trophy at a Sahodaya fest: a named group of items that crowns a winner.
 *
 * The Sahodaya counterpart of State\StatePrizeCategory. A Sahodaya event competes School against
 * School, so there is no Sahodaya title to award here.
 */
class FestPrizeCategory extends Model
{
    protected $table = 'fest_prize_categories';

    public const AWARD_INDIVIDUAL = 'individual';

    public const AWARD_SCHOOL = 'school';

    public const AWARDS = [
        self::AWARD_INDIVIDUAL => 'Individual champion',
        self::AWARD_SCHOOL => 'School champion',
    ];

    protected $fillable = [
        'event_id', 'code', 'name', 'description', 'awards',
        'is_overall', 'honour_count', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'awards' => 'array',
        'is_overall' => 'boolean',
        'is_active' => 'boolean',
        'honour_count' => 'integer',
        'sort_order' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FestPrizeCategoryItem::class, 'prize_category_id');
    }

    public function awards(string $award): bool
    {
        return in_array($award, $this->awards ?? [], true);
    }

    /** @return list<string> */
    public function awardLabels(): array
    {
        return array_values(array_map(fn (string $a) => self::AWARDS[$a] ?? $a, $this->awards ?? []));
    }
}
