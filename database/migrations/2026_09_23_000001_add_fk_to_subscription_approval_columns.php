<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Defensively null out any orphaned references before constraining them.
        DB::table('subscription_invoices')
            ->whereNotNull('approved_by')
            ->whereNotIn('approved_by', DB::table('users')->select('id'))
            ->update(['approved_by' => null]);

        DB::table('subscription_receipts')
            ->whereNotNull('reviewed_by')
            ->whereNotIn('reviewed_by', DB::table('users')->select('id'))
            ->update(['reviewed_by' => null]);

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('subscription_receipts', function (Blueprint $table) {
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
        });

        Schema::table('subscription_receipts', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
        });
    }
};
