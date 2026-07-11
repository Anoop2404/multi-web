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
            if (! Schema::hasColumn('mcq_exams', 'payment_deadline')) {
                $table->date('payment_deadline')->nullable()->after('school_discount_amount');
            }
            if (! Schema::hasColumn('mcq_exams', 'late_fee_amount')) {
                $table->decimal('late_fee_amount', 10, 2)->nullable()->after('payment_deadline');
            }
            if (! Schema::hasColumn('mcq_exams', 'penalty_amount')) {
                $table->decimal('penalty_amount', 10, 2)->nullable()->after('late_fee_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mcq_exams')) {
            return;
        }

        Schema::table('mcq_exams', function (Blueprint $table) {
            foreach (['penalty_amount', 'late_fee_amount', 'payment_deadline'] as $column) {
                if (Schema::hasColumn('mcq_exams', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
