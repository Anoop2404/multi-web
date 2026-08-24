<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FestCatalogSections and item creation forms allow fine_arts, it_fest, science, work_experience,
 * cultural, arts, sports, and custom category values, but the original enum/CHECK constraint
 * fest_event_items_category_check from 2026_06_22_000011_phase11_13_event_platform.php only allowed
 * ('music', 'dance', 'drama', 'literary', 'sports', 'general'). Creating an item in fine_arts
 * (e.g. Cartoon, Drawing, Painting) or other genres hit "violates check constraint fest_event_items_category_check".
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE fest_event_items DROP CONSTRAINT IF EXISTS fest_event_items_category_check');
            DB::statement("ALTER TABLE fest_event_items ALTER COLUMN category TYPE VARCHAR(60) USING category::text");
            DB::statement("ALTER TABLE fest_event_items ALTER COLUMN category SET DEFAULT 'general'");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE fest_event_items MODIFY category VARCHAR(60) NOT NULL DEFAULT 'general'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE fest_event_items DROP CONSTRAINT IF EXISTS fest_event_items_category_check');
            DB::statement("ALTER TABLE fest_event_items ALTER COLUMN category TYPE VARCHAR(20) USING category::text");
            DB::statement("ALTER TABLE fest_event_items ALTER COLUMN category SET DEFAULT 'general'");
            DB::statement("
                ALTER TABLE fest_event_items ADD CONSTRAINT fest_event_items_category_check CHECK (
                    category IN ('music', 'dance', 'drama', 'literary', 'sports', 'general', 'fine_arts', 'cultural', 'it_fest', 'science', 'work_experience')
                )
            ");
        }
    }
};
