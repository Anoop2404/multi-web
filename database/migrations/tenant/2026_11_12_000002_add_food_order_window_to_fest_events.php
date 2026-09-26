<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            $table->timestamp('food_order_opens_at')->nullable()->after('require_payment_for_coupons');
            $table->timestamp('food_order_closes_at')->nullable()->after('food_order_opens_at');
        });
    }

    public function down(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            $table->dropColumn(['food_order_opens_at', 'food_order_closes_at']);
        });
    }
};
