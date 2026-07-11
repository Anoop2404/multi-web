<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_programs')) {
            return;
        }

        Schema::table('training_programs', function (Blueprint $table) {
            if (! Schema::hasColumn('training_programs', 'min_attendance_percent')) {
                $table->unsignedTinyInteger('min_attendance_percent')->nullable()->after('eligibility_config');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_programs')) {
            return;
        }

        Schema::table('training_programs', function (Blueprint $table) {
            if (Schema::hasColumn('training_programs', 'min_attendance_percent')) {
                $table->dropColumn('min_attendance_percent');
            }
        });
    }
};
