<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep unmarked attendance as pending (not present).
 *
 * Schools must explicitly mark Present before KG mark entry / scoring.
 * Previously unmarked rows must not count as present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcq_registrations') || ! Schema::hasColumn('mcq_registrations', 'attendance_status')) {
            return;
        }

        // Soft "present" only: never formally marked → treat as pending again.
        DB::table('mcq_registrations')
            ->whereNull('attendance_marked_at')
            ->where(function ($q) {
                $q->whereNull('attendance_status')
                    ->orWhere('attendance_status', 'present');
            })
            ->update(['attendance_status' => 'pending']);

        $states = "'pending','present','absent','malpractice','withheld'";
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE mcq_registrations MODIFY attendance_status ENUM({$states}) NOT NULL DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE mcq_registrations ALTER COLUMN attendance_status SET DEFAULT 'pending'");
        } else {
            Schema::table('mcq_registrations', function (Blueprint $table) {
                $table->enum('attendance_status', ['pending', 'present', 'absent', 'malpractice', 'withheld'])
                    ->default('pending')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        // No-op: pending remains the correct product default.
    }
};
