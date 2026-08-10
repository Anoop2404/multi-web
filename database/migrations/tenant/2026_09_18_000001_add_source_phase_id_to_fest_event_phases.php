<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md §6.2 / Phase 5: stable phase
 * identity across partitions. Parent/root phases are sources (source_phase_id null);
 * regional child phases point back at their source via source_phase_id. Additive only —
 * existing (event_id, code) rows are untouched; backfill of source_phase_id for already
 * -copied region-child phases is a separate data step (plan §8.4), not done by this
 * schema migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_event_phases', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_event_phases', 'source_phase_id')) {
                $table->foreignId('source_phase_id')->nullable()->after('event_id')
                    ->constrained('fest_event_phases')->nullOnDelete();
            }
        });

        // (event_id, source_phase_id) uniqueness — plan §6.2: "Enforce uniqueness of
        // (event_id, source_phase_id)." A null source_phase_id (every source/parent
        // phase) is exempt from the unique constraint under standard SQL NULL semantics
        // (MySQL/Postgres/SQLite all treat NULL as distinct from NULL in a unique index),
        // so this only prevents a region child from acquiring the same source phase
        // twice — it does not limit how many source phases one event can define.
        Schema::table('fest_event_phases', function (Blueprint $table) {
            $table->unique(['event_id', 'source_phase_id'], 'fest_event_phases_event_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_phases', function (Blueprint $table) {
            $table->dropUnique('fest_event_phases_event_source_unique');
        });

        Schema::table('fest_event_phases', function (Blueprint $table) {
            if (Schema::hasColumn('fest_event_phases', 'source_phase_id')) {
                $table->dropConstrainedForeignId('source_phase_id');
            }
        });
    }
};
