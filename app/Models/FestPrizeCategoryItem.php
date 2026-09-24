<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One item counting towards one prize category. An item may appear in several. */
class FestPrizeCategoryItem extends Model
{
    protected $table = 'fest_prize_category_items';

    protected $fillable = ['prize_category_id', 'item_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FestPrizeCategory::class, 'prize_category_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }
}
