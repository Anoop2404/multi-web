<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            $table->string('payment_bank_name')->nullable()->after('payment_instructions');
            $table->string('payment_account_no', 50)->nullable()->after('payment_bank_name');
            $table->string('payment_ifsc', 20)->nullable()->after('payment_account_no');
            $table->string('payment_upi', 100)->nullable()->after('payment_ifsc');
        });
    }

    public function down(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            $table->dropColumn(['payment_bank_name', 'payment_account_no', 'payment_ifsc', 'payment_upi']);
        });
    }
};
