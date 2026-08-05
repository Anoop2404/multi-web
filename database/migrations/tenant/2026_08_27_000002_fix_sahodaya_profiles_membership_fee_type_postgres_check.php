<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sahodaya_profiles') || ! Schema::hasColumn('sahodaya_profiles', 'membership_fee_type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE sahodaya_profiles DROP CONSTRAINT IF EXISTS sahodaya_profiles_membership_fee_type_check');
            DB::statement('ALTER TABLE sahodaya_profiles ALTER COLUMN membership_fee_type TYPE VARCHAR(40)');
            DB::statement("ALTER TABLE sahodaya_profiles ADD CONSTRAINT sahodaya_profiles_membership_fee_type_check CHECK (membership_fee_type::text = ANY (ARRAY['fixed'::text, 'variable_by_student_count'::text, 'none'::text]))");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE sahodaya_profiles MODIFY membership_fee_type ENUM('fixed', 'variable_by_student_count', 'none') NOT NULL DEFAULT 'fixed'");
        }
    }

    public function down(): void
    {
        // No-op
    }
};
