<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proposed in docs/FOOD_MENU_BILLING_PREORDER_PLAN.md §3 item 5 / §4.6 but never shipped —
 * today FestFoodCouponController::issueFromCatering() issues a coupon for every 'confirmed'
 * catering order regardless of payment status. Default false so existing events keep issuing
 * coupons on confirm exactly as before; an admin opts in per event.
 *
 * See docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.7, Phase 7.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_events') && ! Schema::hasColumn('fest_events', 'require_payment_for_coupons')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->boolean('require_payment_for_coupons')->default(false)->after('food_host_school_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_events') && Schema::hasColumn('fest_events', 'require_payment_for_coupons')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->dropColumn('require_payment_for_coupons');
            });
        }
    }
};
