<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_state_programs') || ! Schema::hasColumn('fest_state_programs', 'event_type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE `fest_state_programs` MODIFY `event_type` VARCHAR(40) NOT NULL');
        }
    }

    public function down(): void
    {
        // Irreversible safely.
    }
};
