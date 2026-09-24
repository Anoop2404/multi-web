<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One item counting towards one prize category. An item may appear in several. */
class StatePrizeCategoryItem extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_prize_category_items';

    protected $fillable = ['prize_category_id', 'item_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(StatePrizeCategory::class, 'prize_category_id');
    }
}
