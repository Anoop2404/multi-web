<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable food-item template for an event — define "Idli" once (name, description,
 * default price), then assign it onto as many menu_date + meal_type slots as needed
 * (FestFoodMenuItem rows) via FestFoodMenuController::assignCatalogItems() instead of
 * re-entering the same item on every date/meal it's served. Purely a template: nothing
 * here propagates to menu items already created from it if this catalog item is later
 * edited or deleted — matches how FestFoodMenuItem itself never retroactively changes
 * already-placed orders.
 */
class FestFoodCatalogItem extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'name', 'description', 'default_price', 'is_active',
    ];

    protected $casts = [
        'default_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(FestFoodMenuItem::class, 'catalog_item_id');
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }
}
