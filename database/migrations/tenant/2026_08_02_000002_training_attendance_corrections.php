<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_attendance')) {
            return;
        }

        $this->allowStatus(['present', 'absent', 'late', 'with_permission'], 'present');

        Schema::table('training_attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('training_attendance', 'correction_reason')) {
                $table->text('correction_reason')->nullable()->after('marked_at');
            }
            if (! Schema::hasColumn('training_attendance', 'corrected_by')) {
                $table->unsignedBigInteger('corrected_by')->nullable()->after('correction_reason');
            }
            if (! Schema::hasColumn('training_attendance', 'approval_status')) {
                $table->string('approval_status', 20)->nullable()->after('corrected_by');
            }
        });

        // marked_by already exists on phase15; ensure it is nullable FK-friendly.
        if (Schema::hasColumn('training_attendance', 'marked_by')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('ALTER TABLE training_attendance MODIFY marked_by BIGINT UNSIGNED NULL');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_attendance')) {
            return;
        }

        DB::table('training_attendance')
            ->whereIn('status', ['late', 'with_permission'])
            ->update(['status' => 'present']);

        $this->allowStatus(['present', 'absent'], 'present');

        Schema::table('training_attendance', function (Blueprint $table) {
            foreach (['correction_reason', 'corrected_by', 'approval_status'] as $col) {
                if (Schema::hasColumn('training_attendance', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /** @param  list<string>  $states */
    private function allowStatus(array $states, string $default): void
    {
        $driver = DB::getDriverName();
        $list = "'".implode("','", $states)."'";

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE training_attendance MODIFY status ENUM({$list}) NOT NULL DEFAULT '{$default}'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE training_attendance DROP CONSTRAINT IF EXISTS training_attendance_status_check');
            DB::statement("ALTER TABLE training_attendance ADD CONSTRAINT training_attendance_status_check CHECK (status IN ({$list}))");
        } else {
            Schema::table('training_attendance', function (Blueprint $table) use ($states, $default) {
                $table->enum('status', $states)->default($default)->change();
            });
        }
    }
};
