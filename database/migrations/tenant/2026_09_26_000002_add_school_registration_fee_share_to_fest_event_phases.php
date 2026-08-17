<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How much of the event's school registration fee a given phase collects — an explicit
 * rupee amount, not a percentage, matching how the rest of this fee engine already works
 * (see FestSchoolEventFeeService::schoolRegistrationAmount()). Nullable/0-default so a
 * phase that shouldn't charge any of the school registration fee simply omits it, and
 * events not using per-phase billing are entirely unaffected.
 *
 * Supports both shapes a Sahodaya might want: the whole flat fee owned by one phase
 * (e.g. MCS: ₹4000 entirely on "Level 1", 0 on "Level 2"), or split across phases
 * (e.g. ₹2000 + ₹2000). See docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §3 item 4.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_event_phases')) {
            return;
        }

        if (! Schema::hasColumn('fest_event_phases', 'school_registration_fee_share')) {
            Schema::table('fest_event_phases', function (Blueprint $table) {
                $table->decimal('school_registration_fee_share', 10, 2)->nullable()->after('is_default');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_event_phases')) {
            return;
        }

        if (Schema::hasColumn('fest_event_phases', 'school_registration_fee_share')) {
            Schema::table('fest_event_phases', function (Blueprint $table) {
                $table->dropColumn('school_registration_fee_share');
            });
        }
    }
};
