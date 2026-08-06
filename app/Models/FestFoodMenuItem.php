<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A priced food item on an event's menu for a specific day + meal type. Schools order
 * quantities of these into their FestFoodBill via FestFoodOrderItem. Separate from the
 * older headcount-only FestCateringOrder / FestFoodCoupon flow.
 */
class FestFoodMenuItem extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'menu_date', 'meal_type', 'name', 'description',
        'price', 'is_available', 'max_per_school', 'sort_order',
    ];

    protected $casts = [
        'menu_date' => 'date',
        'price' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('fest_food_menu_items.tenant_id', $tenantId);
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }
}
