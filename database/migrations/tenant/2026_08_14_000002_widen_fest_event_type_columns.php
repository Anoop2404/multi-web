<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * fest_events.event_type (and related) were MySQL enums that omitted english_fest /
 * science_fest. Widen to string so Competition Type masters can introduce new keys (FRD-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->widen('fest_events', 'event_type');
        $this->widen('fest_catalog_items', 'event_type');
    }

    public function down(): void
    {
        // Irreversible safely — leave as string.
    }

    private function widen(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_{$column}_check");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE VARCHAR(40)");
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            $blueprint->string($column, 40)->change();
        });
    }
};
