<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens the billing unit for fest_school_event_fees to also carry phase_id, so a
 * Kalotsavam event with phase_mode_enabled can bill each FestEventPhase independently
 * (one fee record per event+school+phase), mirroring how head_id already does this for
 * sports_composite billing (see 2026_07_28_000005_fest_school_event_fee_head_id.php).
 * phase_id is nullable so pre-migration, event-level-only fee records (and any event not
 * using per-phase billing) keep working unchanged — they simply have phase_id = null.
 *
 * See docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §3 for the design this implements.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_school_event_fees')) {
            return;
        }

        if (! Schema::hasColumn('fest_school_event_fees', 'phase_id')) {
            Schema::table('fest_school_event_fees', function (Blueprint $table) {
                $table->unsignedBigInteger('phase_id')->nullable()->after('head_id');
                $table->foreign('phase_id')->references('id')->on('fest_event_phases')->nullOnDelete();
            });
        }

        Schema::table('fest_school_event_fees', function (Blueprint $table) {
            $table->dropUnique('fest_school_event_fees_event_school_head_unique');
            $table->unique(
                ['event_id', 'school_id', 'head_id', 'phase_id'],
                'fest_school_event_fees_event_school_head_phase_unique'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_school_event_fees')) {
            return;
        }

        Schema::table('fest_school_event_fees', function (Blueprint $table) {
            $table->dropUnique('fest_school_event_fees_event_school_head_phase_unique');
        });

        // Same defensive pattern as the head_id migration this widens: only restore the
        // narrower (event_id, school_id, head_id) constraint if no (event_id, school_id,
        // head_id) group would end up with more than one row once phase_id is dropped —
        // otherwise leave the wider constraint in place rather than losing data on rollback.
        $hasMultiPhaseRows = DB::table('fest_school_event_fees')
            ->select('event_id', 'school_id', 'head_id')
            ->whereNotNull('phase_id')
            ->groupBy('event_id', 'school_id', 'head_id')
            ->havingRaw('count(*) > 1')
            ->exists();

        if (! $hasMultiPhaseRows) {
            Schema::table('fest_school_event_fees', function (Blueprint $table) {
                $table->unique(
                    ['event_id', 'school_id', 'head_id'],
                    'fest_school_event_fees_event_school_head_unique'
                );
            });
        }

        if (Schema::hasColumn('fest_school_event_fees', 'phase_id')) {
            Schema::table('fest_school_event_fees', function (Blueprint $table) {
                $table->dropForeign(['phase_id']);
                $table->dropColumn('phase_id');
            });
        }
    }
};
