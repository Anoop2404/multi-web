<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A priced food item on an event's menu for a specific day + meal type. Schools order
 * quantities of these into their FestFoodBill via FestFoodOrderItem. Separate from the
 * older headcount-only FestCateringOrder / FestFoodCoupon flow.
 */
class FestFoodMenuItem extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'catalog_item_id', 'menu_date', 'meal_type', 'name', 'description',
        'price', 'is_available', 'max_per_school', 'sort_order',
    ];

    protected $casts = [
        'menu_date' => 'date',
        'price' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    /**
     * Canonical chronological order for meal types — `meal_type` is a plain varchar
     * column (not a DB enum), so a raw `orderBy('meal_type')` sorts alphabetically
     * ('dinner' before 'lunch'), not by time of day. This is the single source of truth
     * both the label shown to users and the sort order used by sortForDisplay() below.
     */
    public const MEAL_TYPES = [
        'breakfast' => 'Breakfast',
        'lunch' => 'Lunch',
        'snacks' => 'Snacks',
        'tea' => 'Tea',
        'dinner' => 'Dinner',
        'other' => 'Other',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(FestFoodCatalogItem::class, 'catalog_item_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('fest_food_menu_items.tenant_id', $tenantId);
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    public static function mealTypeSortIndex(string $mealType): int
    {
        $index = array_search($mealType, array_keys(self::MEAL_TYPES), true);

        return $index === false ? count(self::MEAL_TYPES) : $index;
    }

    /**
     * Sort a collection of menu items by date, then meal type in the canonical
     * (chronological) order above, then display order/name — instead of the alphabetical
     * meal-type order a plain orderBy('meal_type') would produce.
     *
     * @param  Collection<int, self>  $items
     * @return Collection<int, self>
     */
    public static function sortForDisplay(Collection $items): Collection
    {
        return $items->sort(function (self $a, self $b) {
            $dateCmp = $a->menu_date->format('Y-m-d') <=> $b->menu_date->format('Y-m-d');
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            $mealCmp = self::mealTypeSortIndex($a->meal_type) <=> self::mealTypeSortIndex($b->meal_type);
            if ($mealCmp !== 0) {
                return $mealCmp;
            }

            $orderCmp = $a->sort_order <=> $b->sort_order;
            if ($orderCmp !== 0) {
                return $orderCmp;
            }

            return strcmp($a->name, $b->name);
        })->values();
    }
}
