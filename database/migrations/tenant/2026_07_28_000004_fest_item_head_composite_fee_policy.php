<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-head fee/quota/policy columns for the sports_composite billing model.
     *
     * These are deliberately new, distinctly-named columns rather than a repurposing of the
     * existing `default_item_fee`/`extra_item_fee` pair on this table: those two are already
     * wired end-to-end for the (separate, older) `item_catalog` fee model and are left untouched
     * here to avoid disturbing that tested path. `sports_composite` billing gets its own fields:
     *
     *  - school_registration_fee : flat, charged once per school per head.
     *  - student_registration_fee: flat, charged once per student per head, AND used as the
     *                              fallback per-item rate when a registered item has no fee_amount
     *                              override of its own.
     *  - team_registration_fee   : flat, charged once per team per head (replaces per-student item
     *                              billing for that team item entirely).
     *  - included_items_per_student: individual free-item quota per student per head. Only items
     *                              flagged fest_event_items.quota_eligible can be waived by it.
     *  - included_teams          : free team-registration quota per head (waives team_registration_fee).
     *  - verification_policy     : 'verified_only' | 'all_students'.
     *  - approval_policy         : 'auto' | 'manual'.
     */
    public function up(): void
    {
        if (Schema::hasTable('fest_item_heads')) {
            Schema::table('fest_item_heads', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_item_heads', 'school_registration_fee')) {
                    $table->decimal('school_registration_fee', 10, 2)->nullable()->after('extra_item_fee');
                }
                if (! Schema::hasColumn('fest_item_heads', 'student_registration_fee')) {
                    $table->decimal('student_registration_fee', 10, 2)->nullable()->after('school_registration_fee');
                }
                if (! Schema::hasColumn('fest_item_heads', 'team_registration_fee')) {
                    $table->decimal('team_registration_fee', 10, 2)->nullable()->after('student_registration_fee');
                }
                if (! Schema::hasColumn('fest_item_heads', 'included_items_per_student')) {
                    $table->unsignedSmallInteger('included_items_per_student')->default(0)->after('team_registration_fee');
                }
                if (! Schema::hasColumn('fest_item_heads', 'included_teams')) {
                    $table->unsignedSmallInteger('included_teams')->default(0)->after('included_items_per_student');
                }
                if (! Schema::hasColumn('fest_item_heads', 'verification_policy')) {
                    $table->string('verification_policy', 20)->default('all_students')->after('included_teams');
                }
                if (! Schema::hasColumn('fest_item_heads', 'approval_policy')) {
                    $table->string('approval_policy', 20)->default('auto')->after('verification_policy');
                }
                if (! Schema::hasColumn('fest_item_heads', 'max_participants')) {
                    $table->unsignedInteger('max_participants')->nullable()->after('approval_policy');
                }
                if (! Schema::hasColumn('fest_item_heads', 'max_teams')) {
                    $table->unsignedInteger('max_teams')->nullable()->after('max_participants');
                }
            });
        }

        if (Schema::hasTable('fest_event_items')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                // Whether this item can be covered by the head's free individual-item quota.
                // Independent of whether the item has its own fee_amount override — a
                // quota-eligible item is fully waived (0) whenever quota remains, override or not.
                if (! Schema::hasColumn('fest_event_items', 'quota_eligible')) {
                    $table->boolean('quota_eligible')->default(false)->after('fee_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_item_heads')) {
            Schema::table('fest_item_heads', function (Blueprint $table) {
                foreach ([
                    'school_registration_fee', 'student_registration_fee', 'team_registration_fee',
                    'included_items_per_student', 'included_teams',
                    'verification_policy', 'approval_policy',
                    'max_participants', 'max_teams',
                ] as $col) {
                    if (Schema::hasColumn('fest_item_heads', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('fest_event_items')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                if (Schema::hasColumn('fest_event_items', 'quota_eligible')) {
                    $table->dropColumn('quota_eligible');
                }
            });
        }
    }
};
