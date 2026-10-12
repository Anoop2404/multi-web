<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['fest_event_items', 'fest_catalog_items'];

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'age_group')) {
                continue;
            }

            if ($driver === 'pgsql') {
                $constraint = "{$table}_age_group_check";
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
                DB::statement("ALTER TABLE {$table} ALTER COLUMN age_group TYPE VARCHAR(60) USING age_group::text");

                if ($table === 'fest_event_items') {
                    DB::statement(<<<SQL
                        ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (
                            age_group IS NULL
                            OR age_group ~ '^[A-Za-z0-9_-]{1,60}$'
                        )
                    SQL);
                }
            } elseif ($driver === 'mysql') {
                DB::statement("ALTER TABLE {$table} MODIFY age_group VARCHAR(60) NULL");
            }
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasTable('fest_event_items') && Schema::hasColumn('fest_event_items', 'age_group')) {
            DB::statement('ALTER TABLE fest_event_items DROP CONSTRAINT IF EXISTS fest_event_items_age_group_check');
            DB::statement('ALTER TABLE fest_event_items ALTER COLUMN age_group TYPE VARCHAR(20) USING age_group::text');
            $legacy = implode("', '", ['u8', 'u10', 'u11', 'u12', 'u14', 'u16', 'u17', 'u18', 'u19', 'open']);
            DB::statement("ALTER TABLE fest_event_items ADD CONSTRAINT fest_event_items_age_group_check CHECK (age_group IS NULL OR age_group IN ('{$legacy}'))");
        }
    }
};
