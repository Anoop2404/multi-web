<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcq_registrations')) {
            return;
        }

        if (! Schema::hasColumn('mcq_registrations', 'attendance_note')) {
            Schema::table('mcq_registrations', function (Blueprint $table) {
                $table->text('attendance_note')->nullable()->after('attendance_status');
            });
        }

        $states = "'pending','present','absent','malpractice','withheld'";
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE mcq_registrations MODIFY attendance_status ENUM({$states}) NOT NULL DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE mcq_registrations DROP CONSTRAINT IF EXISTS mcq_registrations_attendance_status_check');
            DB::statement("ALTER TABLE mcq_registrations ADD CONSTRAINT mcq_registrations_attendance_status_check CHECK (attendance_status IN ({$states}))");
        } else {
            // SQLite: rebuild the column so the enum CHECK constraint accepts the new values.
            Schema::table('mcq_registrations', function (Blueprint $table) {
                $table->enum('attendance_status', ['pending', 'present', 'absent', 'malpractice', 'withheld'])
                    ->default('pending')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('mcq_registrations')) {
            return;
        }

        // Downgrade any malpractice/withheld rows to 'absent' before narrowing the constraint back.
        DB::table('mcq_registrations')
            ->whereIn('attendance_status', ['malpractice', 'withheld'])
            ->update(['attendance_status' => 'absent']);

        $states = "'pending','present','absent'";
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE mcq_registrations MODIFY attendance_status ENUM({$states}) NOT NULL DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE mcq_registrations DROP CONSTRAINT IF EXISTS mcq_registrations_attendance_status_check');
            DB::statement("ALTER TABLE mcq_registrations ADD CONSTRAINT mcq_registrations_attendance_status_check CHECK (attendance_status IN ({$states}))");
        } else {
            Schema::table('mcq_registrations', function (Blueprint $table) {
                $table->enum('attendance_status', ['pending', 'present', 'absent'])
                    ->default('pending')
                    ->change();
            });
        }

        if (Schema::hasColumn('mcq_registrations', 'attendance_note')) {
            Schema::table('mcq_registrations', function (Blueprint $table) {
                $table->dropColumn('attendance_note');
            });
        }
    }
};
