<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_events') && ! Schema::hasColumn('fest_events', 'food_coupon_layout')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->json('food_coupon_layout')->nullable()->after('food_coupon_bg_image');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_events') && Schema::hasColumn('fest_events', 'food_coupon_layout')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->dropColumn('food_coupon_layout');
            });
        }
    }
};
