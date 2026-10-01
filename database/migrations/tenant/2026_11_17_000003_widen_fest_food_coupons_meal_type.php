<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * fest_food_coupons.meal_type and fest_catering_orders.meal_type were originally
 * created as enums ('breakfast', 'lunch', 'dinner', 'snacks'). On PostgreSQL, this
 * generates CHECK constraints (e.g. fest_food_coupons_meal_type_check) that reject 'tea',
 * 'other', and any additional meal types configured in fest_food_menu_items.
 *
 * This migration drops the check constraints and widens meal_type to VARCHAR(32).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->widenMealType('fest_food_coupons');
        $this->widenMealType('fest_catering_orders');
    }

    public function down(): void
    {
        // Non-destructive: maintain widened column
    }

    private function widenMealType(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'meal_type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // Drop Postgres check constraint generated from legacy Laravel enum definitions
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_meal_type_check");
            // Widen column to VARCHAR(32) so tea, other, and any custom meal types are allowed
            DB::statement("ALTER TABLE {$table} ALTER COLUMN meal_type TYPE VARCHAR(32)");
            // Ensure default value remains 'lunch'
            DB::statement("ALTER TABLE {$table} ALTER COLUMN meal_type SET DEFAULT 'lunch'");
            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `meal_type` VARCHAR(32) NOT NULL DEFAULT 'lunch'");
            return;
        }

        // SQLite or other drivers in tests
        try {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('meal_type', 32)->default('lunch')->change();
            });
        } catch (\Throwable $e) {
            // Ignore if change() is not supported on this driver
        }
    }
};
