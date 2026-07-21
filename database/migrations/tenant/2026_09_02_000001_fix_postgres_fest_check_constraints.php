<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // Drop Postgres check constraints generated from legacy Laravel enum definitions
            DB::statement('ALTER TABLE fest_events DROP CONSTRAINT IF EXISTS fest_events_event_type_check');
            DB::statement('ALTER TABLE fest_catalog_items DROP CONSTRAINT IF EXISTS fest_catalog_items_event_type_check');
            DB::statement('ALTER TABLE fest_events DROP CONSTRAINT IF EXISTS fest_events_conductor_level_check');
            DB::statement('ALTER TABLE fest_events DROP CONSTRAINT IF EXISTS fest_events_fee_type_check');
            DB::statement('ALTER TABLE fest_events DROP CONSTRAINT IF EXISTS fest_events_status_check');

            // Alter column types to VARCHAR to allow new event types like english_fest, science_fest, etc.
            DB::statement('ALTER TABLE fest_events ALTER COLUMN event_type TYPE VARCHAR(40)');
            DB::statement('ALTER TABLE fest_catalog_items ALTER COLUMN event_type TYPE VARCHAR(40)');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `fest_events` MODIFY `event_type` VARCHAR(40) NOT NULL");
            DB::statement("ALTER TABLE `fest_catalog_items` MODIFY `event_type` VARCHAR(40) NOT NULL");
        }
    }

    public function down(): void
    {
        // Irreversible
    }
};
