<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('topper_subject_marks')) {
            return;
        }

        if (! Schema::hasColumn('topper_subject_marks', 'tenant_id')) {
            Schema::table('topper_subject_marks', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->after('topper_id')->index();
            });
        }

        if (Schema::hasTable('toppers') && Schema::hasColumn('topper_subject_marks', 'tenant_id')) {
            $driver = DB::getDriverName();

            if ($driver === 'pgsql') {
                DB::statement('
                    UPDATE topper_subject_marks AS tsm
                    SET tenant_id = t.tenant_id
                    FROM toppers AS t
                    WHERE t.id = tsm.topper_id AND tsm.tenant_id IS NULL
                ');
            } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('
                    UPDATE topper_subject_marks AS tsm
                    INNER JOIN toppers AS t ON t.id = tsm.topper_id
                    SET tsm.tenant_id = t.tenant_id
                    WHERE tsm.tenant_id IS NULL
                ');
            } else {
                // SQLite / others
                $rows = DB::table('topper_subject_marks as tsm')
                    ->join('toppers as t', 't.id', '=', 'tsm.topper_id')
                    ->whereNull('tsm.tenant_id')
                    ->select('tsm.id', 't.tenant_id')
                    ->get();

                foreach ($rows as $row) {
                    DB::table('topper_subject_marks')
                        ->where('id', $row->id)
                        ->update(['tenant_id' => $row->tenant_id]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('topper_subject_marks') && Schema::hasColumn('topper_subject_marks', 'tenant_id')) {
            Schema::table('topper_subject_marks', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
