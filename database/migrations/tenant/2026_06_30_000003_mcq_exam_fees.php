<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcq_exams', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_exams', 'fee_type')) {
                $table->string('fee_type', 16)->default('none')->after('status');
            }
            if (! Schema::hasColumn('mcq_exams', 'fee_amount')) {
                $table->decimal('fee_amount', 10, 2)->nullable()->after('fee_type');
            }
        });

        Schema::table('mcq_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_registrations', 'fee_receipt_id')) {
                $table->unsignedBigInteger('fee_receipt_id')->nullable()->after('status');
                $table->foreign('fee_receipt_id')->references('id')->on('fee_receipts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mcq_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('mcq_registrations', 'fee_receipt_id')) {
                $table->dropForeign(['fee_receipt_id']);
                $table->dropColumn('fee_receipt_id');
            }
        });

        Schema::table('mcq_exams', function (Blueprint $table) {
            if (Schema::hasColumn('mcq_exams', 'fee_amount')) {
                $table->dropColumn('fee_amount');
            }
            if (Schema::hasColumn('mcq_exams', 'fee_type')) {
                $table->dropColumn('fee_type');
            }
        });
    }
};
