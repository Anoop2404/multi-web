<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_state_program_items') || ! Schema::hasColumn('fest_state_program_items', 'age_group')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE fest_state_program_items DROP CONSTRAINT IF EXISTS fest_state_program_items_age_group_check');
            DB::statement('ALTER TABLE fest_state_program_items ALTER COLUMN age_group TYPE VARCHAR(60) USING age_group::text');
            DB::statement(<<<SQL
                ALTER TABLE fest_state_program_items ADD CONSTRAINT fest_state_program_items_age_group_check CHECK (
                    age_group IS NULL
                    OR age_group ~ '^[A-Za-z0-9_-]{1,60}$'
                )
            SQL);
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE fest_state_program_items MODIFY age_group VARCHAR(60) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasTable('fest_state_program_items') && Schema::hasColumn('fest_state_program_items', 'age_group')) {
            DB::statement('ALTER TABLE fest_state_program_items DROP CONSTRAINT IF EXISTS fest_state_program_items_age_group_check');
            DB::statement('ALTER TABLE fest_state_program_items ALTER COLUMN age_group TYPE VARCHAR(20) USING age_group::text');
            $legacy = implode("', '", ['u8', 'u10', 'u11', 'u12', 'u14', 'u16', 'u17', 'u18', 'u19', 'open']);
            DB::statement("ALTER TABLE fest_state_program_items ADD CONSTRAINT fest_state_program_items_age_group_check CHECK (age_group IS NULL OR age_group IN ('{$legacy}'))");
        }
    }
};
