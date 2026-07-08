<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'login_code')) {
                $table->string('login_code', 16)->nullable()->unique()->after('reg_no');
            }
        });

        Schema::table('teachers', function (Blueprint $table) {
            if (! Schema::hasColumn('teachers', 'login_code')) {
                $table->string('login_code', 16)->nullable()->unique()->after('reg_no');
            }
        });

        Schema::table('fee_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('fee_receipts', 'receipt_emailed_at')) {
                $table->timestamp('receipt_emailed_at')->nullable()->after('reviewed_at');
            }
            if (! Schema::hasColumn('fee_receipts', 'receipt_email_status')) {
                $table->string('receipt_email_status', 20)->nullable()->after('receipt_emailed_at');
            }
            if (! Schema::hasColumn('fee_receipts', 'receipt_email_error')) {
                $table->text('receipt_email_error')->nullable()->after('receipt_email_status');
            }
            if (! Schema::hasColumn('fee_receipts', 'receipt_email_resend_count')) {
                $table->unsignedSmallInteger('receipt_email_resend_count')->default(0)->after('receipt_email_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'login_code')) {
                $table->dropUnique(['login_code']);
                $table->dropColumn('login_code');
            }
        });

        Schema::table('teachers', function (Blueprint $table) {
            if (Schema::hasColumn('teachers', 'login_code')) {
                $table->dropUnique(['login_code']);
                $table->dropColumn('login_code');
            }
        });

        Schema::table('fee_receipts', function (Blueprint $table) {
            foreach (['receipt_emailed_at', 'receipt_email_status', 'receipt_email_error', 'receipt_email_resend_count'] as $col) {
                if (Schema::hasColumn('fee_receipts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
