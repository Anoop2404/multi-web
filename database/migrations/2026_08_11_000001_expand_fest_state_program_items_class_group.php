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
            DB::statement("ALTER TABLE fest_state_program_items DROP CONSTRAINT IF EXISTS fest_state_program_items_class_group_check");
            DB::statement("ALTER TABLE fest_state_program_items ALTER COLUMN class_group TYPE VARCHAR(30) USING class_group::text");
            DB::statement("ALTER TABLE fest_state_program_items ALTER COLUMN class_group SET DEFAULT 'category_5'");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE fest_state_program_items MODIFY class_group VARCHAR(30) NOT NULL DEFAULT 'category_5'");
        }
    }

    public function down(): void
    {
        // No-op revert
    }
};
