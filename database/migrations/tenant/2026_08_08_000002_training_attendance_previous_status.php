<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_attendance') && ! Schema::hasColumn('training_attendance', 'previous_status')) {
            Schema::table('training_attendance', function (Blueprint $table) {
                $table->string('previous_status', 32)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('training_attendance') && Schema::hasColumn('training_attendance', 'previous_status')) {
            Schema::table('training_attendance', function (Blueprint $table) {
                $table->dropColumn('previous_status');
            });
        }
    }
};
