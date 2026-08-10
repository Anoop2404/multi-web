<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_state_programs')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE fest_state_programs DROP CONSTRAINT IF EXISTS fest_state_programs_event_type_check');
            }
        }
    }

    public function down(): void
    {
    }
};
