<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('data_change_logs') || ! Schema::hasColumn('data_change_logs', 'subject_id')) {
            return;
        }

        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE data_change_logs ALTER COLUMN subject_id TYPE VARCHAR(255) USING subject_id::VARCHAR'),
            'mysql' => DB::statement('ALTER TABLE data_change_logs MODIFY subject_id VARCHAR(255) NULL'),
            default => null,
        };
    }

    public function down(): void
    {
        // UUID tenant ids cannot be safely converted back to bigint.
    }
};
