<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changes the column default for `strict_item_payment_gating` from false to true —
 * this affects NEW rows only (a `->default()` change never rewrites values already
 * stored on existing events), per the explicit product decision (24 Jul 2026): new
 * item_catalog/per_item events should ship with strict per-item payment gating on by
 * default, existing events keep whatever value they already have, untouched.
 *
 * Safe for every OTHER fee model too, not just item_catalog/per_item: the flag is only
 * ever honored inside FestSchoolEventFeeService::isPaidForRegistration() behind an
 * explicit `in_array($feeModel, ['item_catalog', 'per_item'])` guard — for
 * cksc_tiered/flat_school/per_student/sports_composite/none events this column being
 * true is inert, no behavior changes. See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §9.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fest_events', 'strict_item_payment_gating')) {
            return;
        }

        Schema::table('fest_events', function (Blueprint $table) {
            $table->boolean('strict_item_payment_gating')->default(true)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('fest_events', 'strict_item_payment_gating')) {
            return;
        }

        Schema::table('fest_events', function (Blueprint $table) {
            $table->boolean('strict_item_payment_gating')->default(false)->change();
        });
    }
};
