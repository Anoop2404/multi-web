<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fee_receipts') && ! Schema::hasColumn('fee_receipts', 'rejection_history')) {
            Schema::table('fee_receipts', function (Blueprint $table) {
                $table->json('rejection_history')->nullable()->after('rejection_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fee_receipts') && Schema::hasColumn('fee_receipts', 'rejection_history')) {
            Schema::table('fee_receipts', function (Blueprint $table) {
                $table->dropColumn('rejection_history');
            });
        }
    }
};
