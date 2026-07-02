<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('sahodaya_profiles', 'receipt_template_json')) {
                $table->json('receipt_template_json')->nullable()->after('application_form_config');
            }
            if (! Schema::hasColumn('sahodaya_profiles', 'receipt_next_number')) {
                $table->unsignedInteger('receipt_next_number')->default(1)->after('receipt_template_json');
            }
        });

        Schema::table('fee_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('fee_receipts', 'receipt_number')) {
                $table->string('receipt_number', 30)->nullable()->after('feeable_id');
            }
            if (! Schema::hasColumn('fee_receipts', 'generated_receipt_path')) {
                $table->string('generated_receipt_path')->nullable()->after('file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fee_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('fee_receipts', 'generated_receipt_path')) {
                $table->dropColumn('generated_receipt_path');
            }
            if (Schema::hasColumn('fee_receipts', 'receipt_number')) {
                $table->dropColumn('receipt_number');
            }
        });

        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('sahodaya_profiles', 'receipt_next_number')) {
                $table->dropColumn('receipt_next_number');
            }
            if (Schema::hasColumn('sahodaya_profiles', 'receipt_template_json')) {
                $table->dropColumn('receipt_template_json');
            }
        });
    }
};
