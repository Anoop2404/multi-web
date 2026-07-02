<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcq_exams')) {
            return;
        }

        Schema::table('mcq_exams', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_exams', 'delivery_mode')) {
                $table->string('delivery_mode', 20)->default('offline')->after('exam_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mcq_exams')) {
            return;
        }

        Schema::table('mcq_exams', function (Blueprint $table) {
            if (Schema::hasColumn('mcq_exams', 'delivery_mode')) {
                $table->dropColumn('delivery_mode');
            }
        });
    }
};
