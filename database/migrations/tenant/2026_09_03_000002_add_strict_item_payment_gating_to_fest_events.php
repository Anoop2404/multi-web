<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opt-in switch: when true (and only for 'item_catalog'/'per_item' billing — see
     * FestSchoolEventFeeService::itemPaymentAllocation()), registration approval checks
     * whether THIS SPECIFIC item's fee is covered by payment, instead of the school's
     * aggregate balance. Defaults false for every existing and new event, so
     * isPaidForRegistration() — and therefore every approval flow — behaves exactly as it
     * does today unless a Sahodaya admin explicitly turns this on for one event, after
     * reviewing the read-only allocation report/checklist first.
     * See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §9.3.
     */
    public function up(): void
    {
        if (Schema::hasColumn('fest_events', 'strict_item_payment_gating')) {
            return;
        }

        Schema::table('fest_events', function (Blueprint $table) {
            $table->boolean('strict_item_payment_gating')->default(false)->after('aggregation_config');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('fest_events', 'strict_item_payment_gating')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->dropColumn('strict_item_payment_gating');
            });
        }
    }
};
