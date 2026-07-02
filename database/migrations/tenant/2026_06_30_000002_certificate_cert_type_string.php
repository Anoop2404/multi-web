<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('certificates', 'cert_type')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE certificates ALTER COLUMN cert_type TYPE VARCHAR(30)');
        }
    }

    public function down(): void
    {
        // Non-reversible without data loss on custom cert types.
    }
};
