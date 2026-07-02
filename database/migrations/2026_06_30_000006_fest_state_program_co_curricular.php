<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_state_program_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_state_program_items', 'kids_band')) {
                $table->string('kids_band', 20)->nullable()->after('age_group');
            }
        });

        if (Schema::hasColumn('fest_state_program_items', 'age_group')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE fest_state_program_items MODIFY age_group VARCHAR(20) NULL');
            } elseif ($driver === 'pgsql') {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE fest_state_program_items DROP CONSTRAINT IF EXISTS fest_state_program_items_age_group_check');
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE fest_state_program_items ALTER COLUMN age_group TYPE VARCHAR(20) USING age_group::text');
            }
        }
    }

    public function down(): void
    {
        Schema::table('fest_state_program_items', function (Blueprint $table) {
            if (Schema::hasColumn('fest_state_program_items', 'kids_band')) {
                $table->dropColumn('kids_band');
            }
        });
    }
};
