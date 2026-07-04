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

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE sahodaya_profiles MODIFY membership_fee_type ENUM('fixed', 'variable_by_student_count', 'none') NOT NULL DEFAULT 'fixed'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sahodaya_profiles') || ! Schema::hasColumn('sahodaya_profiles', 'membership_fee_type')) {
            return;
        }

        DB::table('sahodaya_profiles')
            ->where('membership_fee_type', 'none')
            ->update(['membership_fee_type' => 'fixed', 'fixed_membership_fee_amount' => 0]);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE sahodaya_profiles MODIFY membership_fee_type ENUM('fixed', 'variable_by_student_count') NOT NULL DEFAULT 'fixed'");
        }
    }
};
