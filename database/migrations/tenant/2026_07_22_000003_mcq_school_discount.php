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

        if (! Schema::hasColumn('mcq_exams', 'school_discount_amount')) {
            Schema::table('mcq_exams', function (Blueprint $table) {
                $table->decimal('school_discount_amount', 10, 2)->nullable()->after('fee_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mcq_exams') && Schema::hasColumn('mcq_exams', 'school_discount_amount')) {
            Schema::table('mcq_exams', function (Blueprint $table) {
                $table->dropColumn('school_discount_amount');
            });
        }
    }
};
