<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_food_order_items')) {
            return;
        }

        // Find bills that have duplicate items for the same menu_item_id
        $duplicates = DB::table('fest_food_order_items')
            ->select('bill_id', 'menu_item_id', DB::raw('count(*) as count'), DB::raw('sum(quantity) as total_qty'), DB::raw('sum(line_total) as total_line'))
            ->whereNotNull('menu_item_id')
            ->groupBy('bill_id', 'menu_item_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $rows = DB::table('fest_food_order_items')
                ->where('bill_id', $dup->bill_id)
                ->where('menu_item_id', $dup->menu_item_id)
                ->orderBy('id')
                ->get();

            if ($rows->count() > 1) {
                $first = $rows->first();
                $restIds = $rows->slice(1)->pluck('id')->all();

                // Update first row with combined quantity and line total
                DB::table('fest_food_order_items')
                    ->where('id', $first->id)
                    ->update([
                        'quantity'   => $dup->total_qty,
                        'line_total' => $dup->total_line,
                    ]);

                // Delete duplicate rows
                DB::table('fest_food_order_items')->whereIn('id', $restIds)->delete();
            }
        }
    }

    public function down(): void
    {
        // Consolidated rows cannot and do not need to be split back into individual 1-qty rows
    }
};
