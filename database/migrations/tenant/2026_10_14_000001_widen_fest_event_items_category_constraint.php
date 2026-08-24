<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * fest_event_items.category was created (2026_06_22_000011_phase11_13_event_platform.php)
 * as enum('music','dance','drama','literary','sports','general') — a fixed 6-value list
 * from before the item taxonomy system existed. Every sibling enum on this table that
 * outgrew its original list (participant_type, class_group, event_type on fest_events)
 * already got a widening migration; category never did. Confirmed live: creating an item
 * tagged "fine_arts" (e.g. a Cartoon/Drawing item) 500s with "violates check constraint
 * fest_event_items_category_check" — the modern taxonomy (App\Support\FestTaxonomyRegistry
 * / category masters) offers category values the original 2026-06 list never anticipated,
 * and nothing in the app validates against that stale 6-value set, so any category the
 * admin-configured taxonomy allows can 500 at save time.
 *
 * Widened fully open (VARCHAR, no replacement constraint) rather than a new fixed
 * allow-list — matching how fest_events.event_type/status/fee_type were fixed in
 * 2026_09_02_000001_fix_postgres_fest_check_constraints.php — since category is
 * admin-configurable taxonomy, not a fixed enum, and a new allow-list would just repeat
 * this same bug the next time a Sahodaya adds a category.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_event_items') || ! Schema::hasColumn('fest_event_items', 'category')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE fest_event_items DROP CONSTRAINT IF EXISTS fest_event_items_category_check');
            DB::statement("ALTER TABLE fest_event_items ALTER COLUMN category TYPE VARCHAR(60) USING category::text");
            DB::statement("ALTER TABLE fest_event_items ALTER COLUMN category SET DEFAULT 'general'");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `fest_event_items` MODIFY `category` VARCHAR(60) NOT NULL DEFAULT 'general'");
        } elseif ($driver === 'sqlite') {
            Schema::table('fest_event_items', fn ($table) => $table->string('category', 60)->default('general')->change());
        }
    }

    public function down(): void
    {
        // Irreversible — re-adding the old 6-value constraint would risk rejecting any
        // category row created while this migration was active.
    }
};
