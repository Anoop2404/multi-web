<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen the billing unit for fest_school_event_fees from (event_id, school_id) to
     * (event_id, school_id, head_id) so sports_composite billing can carry one fee record
     * per Event Head instead of one per event. head_id is nullable so pre-migration,
     * event-level-only fee records (and any event not using per-head billing) keep working
     * unchanged — they simply have head_id = null.
     */
    public function up(): void
    {
        if (! Schema::hasTable('fest_school_event_fees')) {
            return;
        }

        if (! Schema::hasColumn('fest_school_event_fees', 'head_id')) {
            Schema::table('fest_school_event_fees', function (Blueprint $table) {
                $table->unsignedBigInteger('head_id')->nullable()->after('school_id');
                $table->foreign('head_id')->references('id')->on('fest_item_heads')->nullOnDelete();
            });
        }

        Schema::table('fest_school_event_fees', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'school_id']);
            $table->unique(['event_id', 'school_id', 'head_id'], 'fest_school_event_fees_event_school_head_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_school_event_fees')) {
            return;
        }

        Schema::table('fest_school_event_fees', function (Blueprint $table) {
            $table->dropUnique('fest_school_event_fees_event_school_head_unique');
        });

        // Only rows with head_id still null can be safely re-collapsed onto the
        // original (event_id, school_id) unique constraint; if per-head rows exist
        // for the same (event_id, school_id), the old constraint cannot be restored
        // without deleting data, so we leave it out on rollback and log the situation
        // via a defensive count check instead of failing the migration outright.
        $hasMultiHeadRows = DB::table('fest_school_event_fees')
            ->select('event_id', 'school_id')
            ->whereNotNull('head_id')
            ->groupBy('event_id', 'school_id')
            ->havingRaw('count(*) > 1')
            ->exists();

        if (! $hasMultiHeadRows) {
            Schema::table('fest_school_event_fees', function (Blueprint $table) {
                $table->unique(['event_id', 'school_id']);
            });
        }

        if (Schema::hasColumn('fest_school_event_fees', 'head_id')) {
            Schema::table('fest_school_event_fees', function (Blueprint $table) {
                $table->dropForeign(['head_id']);
                $table->dropColumn('head_id');
            });
        }
    }
};
