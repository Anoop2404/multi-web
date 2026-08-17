<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_region_assignments')) {
            return;
        }

        if (! Schema::hasColumn('school_region_assignments', 'partition_group')) {
            Schema::table('school_region_assignments', function (Blueprint $table) {
                $table->string('partition_group')->nullable()->after('academic_year');
            });
        }

        try {
            DB::statement('DROP INDEX IF EXISTS school_region_assignments_school_id_academic_year_unique');

            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS school_region_assignments_legacy_unique '.
                'ON school_region_assignments (school_id, academic_year) WHERE partition_group IS NULL'
            );

            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS school_region_assignments_group_unique '.
                'ON school_region_assignments (school_id, academic_year, partition_group) WHERE partition_group IS NOT NULL'
            );
        } catch (\Throwable $e) {
            logger()->warning('ensure_partition_group_on_school_region_assignments: index swap skipped', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        // No-op
    }
};
