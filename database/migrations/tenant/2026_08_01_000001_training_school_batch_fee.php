<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_programs') && Schema::hasColumn('training_programs', 'fee_type')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement("ALTER TABLE training_programs MODIFY fee_type ENUM('none', 'flat', 'school') NOT NULL DEFAULT 'none'");
            } elseif ($driver === 'pgsql') {
                // Postgres may store fee_type as varchar/check; widen via text if needed.
                DB::statement('ALTER TABLE training_programs ALTER COLUMN fee_type TYPE VARCHAR(20)');
            }
            // sqlite / others: string columns already accept 'school'
        }

        if (Schema::hasTable('training_school_fees') && ! Schema::hasColumn('training_school_fees', 'amount_paid')) {
            Schema::table('training_school_fees', function (Blueprint $table) {
                $table->decimal('amount_paid', 10, 2)->default(0)->after('total_due');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('training_school_fees') && Schema::hasColumn('training_school_fees', 'amount_paid')) {
            Schema::table('training_school_fees', function (Blueprint $table) {
                $table->dropColumn('amount_paid');
            });
        }

        if (Schema::hasTable('training_programs') && Schema::hasColumn('training_programs', 'fee_type')) {
            DB::table('training_programs')->where('fee_type', 'school')->update(['fee_type' => 'flat']);

            $driver = DB::getDriverName();
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement("ALTER TABLE training_programs MODIFY fee_type ENUM('none', 'flat') NOT NULL DEFAULT 'none'");
            }
        }
    }
};
