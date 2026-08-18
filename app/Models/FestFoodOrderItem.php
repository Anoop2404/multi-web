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

    /**
     * Day x meal-type x item breakdown for an event: total quantity, revenue, and number
     * of distinct schools ordering, per item — grouped by menu_date then meal type in the
     * canonical (chronological) order, matching how the menu/order pages already group.
     * Cancelled bills are excluded (their items don't represent real demand); item_name is
     * used rather than menu_item_id so the report still reflects historical orders even if
     * the underlying menu item was later edited or deleted.
     *
     * @return list<array{
     *     date: string, day_total_quantity: int, day_total_revenue: float,
     *     meals: list<array{
     *         meal_type: string, subtotal_quantity: int, subtotal_revenue: float,
     *         items: list<array{item_name: string, quantity: int, revenue: float, schools_count: int}>,
     *     }>,
     * }>
     */
    public static function dayMealReport(int $eventId, ?string $hostSchoolId = null): array
    {
        $query = static::query()
            ->join('fest_food_bills', 'fest_food_bills.id', '=', 'fest_food_order_items.bill_id')
            ->where('fest_food_bills.event_id', $eventId)
            ->where('fest_food_bills.status', '!=', FestFoodBill::STATUS_CANCELLED);

        if ($hostSchoolId !== null) {
            $query->where('fest_food_bills.payee_type', 'host_school')
                ->where('fest_food_bills.host_school_id', $hostSchoolId);
        }

        $rows = $query->selectRaw(implode(', ', [
            'fest_food_order_items.menu_date as menu_date',
            'fest_food_order_items.meal_type as meal_type',
            'fest_food_order_items.item_name as item_name',
            'SUM(fest_food_order_items.quantity) as total_quantity',
            'SUM(fest_food_order_items.line_total) as total_revenue',
            'COUNT(DISTINCT fest_food_bills.school_id) as schools_count',
        ]))
            ->groupBy('fest_food_order_items.menu_date', 'fest_food_order_items.meal_type', 'fest_food_order_items.item_name')
            ->get();

        $byDate = [];
        foreach ($rows as $row) {
            $date = $row->menu_date->format('Y-m-d');
            $byDate[$date][$row->meal_type][] = [
                'item_name' => $row->item_name,
                'quantity' => (int) $row->total_quantity,
                'revenue' => round((float) $row->total_revenue, 2),
                'schools_count' => (int) $row->schools_count,
            ];
        }

        $report = [];
        foreach (array_keys($byDate) as $date) {
            $meals = [];
            $dayQuantity = 0;
            $dayRevenue = 0.0;

            foreach (FestFoodMenuItem::MEAL_TYPES as $mealType => $label) {
                if (empty($byDate[$date][$mealType])) {
                    continue;
                }

                $items = $byDate[$date][$mealType];
                usort($items, fn ($a, $b) => strcmp($a['item_name'], $b['item_name']));

                $subtotalQuantity = array_sum(array_column($items, 'quantity'));
                $subtotalRevenue = round(array_sum(array_column($items, 'revenue')), 2);

                $meals[] = [
                    'meal_type' => $mealType,
                    'subtotal_quantity' => $subtotalQuantity,
                    'subtotal_revenue' => $subtotalRevenue,
                    'items' => $items,
                ];

                $dayQuantity += $subtotalQuantity;
                $dayRevenue += $subtotalRevenue;
            }

            $report[] = [
                'date' => $date,
                'day_total_quantity' => $dayQuantity,
                'day_total_revenue' => round($dayRevenue, 2),
                'meals' => $meals,
            ];
        }

        usort($report, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $report;
    }
}
