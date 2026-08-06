<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line item on a FestFoodBill — a quantity of a menu item ordered for a specific
 * day/meal. Name and unit price are snapshotted at order time so later menu edits don't
 * retroactively change an already-placed order.
 */
class FestFoodOrderItem extends Model
{
    protected $fillable = [
        'bill_id', 'menu_item_id', 'menu_date', 'meal_type', 'item_name',
        'unit_price', 'quantity', 'line_total', 'ordered_by_user_id',
    ];

    protected $casts = [
        'menu_date' => 'date',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(FestFoodBill::class, 'bill_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(FestFoodMenuItem::class, 'menu_item_id');
    }

    public static function fromMenuItem(FestFoodMenuItem $item, int $quantity, ?int $orderedByUserId = null): array
    {
        return [
            'menu_item_id' => $item->id,
            'menu_date' => $item->menu_date,
            'meal_type' => $item->meal_type,
            'item_name' => $item->name,
            'unit_price' => $item->price,
            'quantity' => $quantity,
            'line_total' => round((float) $item->price * $quantity, 2),
            'ordered_by_user_id' => $orderedByUserId,
        ];
    }
}
