<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * School admission numbers (admission_number) only need to be unique within
     * one school (tenant_id) for one academic year — the same number can be
     * reused in a later year, or coincidentally repeated at a different school
     * sharing this Sahodaya's database. This is a partial index (only rows
     * with a non-null admission_number, and excluding soft-deleted students)
     * so blank admission numbers and withdrawn students never collide.
     */
    public function up(): void
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            return;
        }

        if (! Schema::hasTable('students') || ! Schema::hasColumn('students', 'admission_number')) {
            return;
        }

        try {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS students_tenant_year_admission_unique '.
                'ON students (tenant_id, academic_year_id, admission_number) '.
                'WHERE admission_number IS NOT NULL AND deleted_at IS NULL'
            );
        } catch (\Throwable $e) {
            // Existing duplicate admission numbers for the same school+year would
            // block this index from being created. Don't fail the whole deploy —
            // log it so duplicates can be cleaned up, then re-run the migration.
            Log::warning('students_tenant_year_admission_unique index not created: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            return;
        }

        if (! Schema::hasTable('students')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS students_tenant_year_admission_unique');
    }
};
