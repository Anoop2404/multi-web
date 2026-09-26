<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            $table->json('food_order_day_windows')->nullable()->after('food_order_closes_at');
        });
    }

    public function down(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            $table->dropColumn('food_order_day_windows');
        });
    }
};
