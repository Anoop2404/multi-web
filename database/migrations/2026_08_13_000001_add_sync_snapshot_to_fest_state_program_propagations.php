<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rule-boundary fix (STATE_SAHODAYA_RULE_BOUNDARY_FIX_PLAN_2026_08_13.md, Set 1 item 3).
 *
 * FestStateProgramService::syncTenantEvent() no longer overwrites a Sahodaya's own event
 * settings/participation policy on every state re-publish (Set 1 items 1-2) — those fields
 * are seeded once, at creation, and belong to the Sahodaya from then on. That means there's
 * no longer any signal telling either side when a Sahodaya's event has fallen behind a
 * State program that was edited afterward. This column is that signal: a snapshot of
 * fest_state_programs.updated_at taken at the moment this propagation's tenant event was
 * created. Comparing it against the program's current updated_at (see
 * FestStateProgramPropagation::isDivergedFromState()) tells us "State has changed this
 * program since this Sahodaya's event was created" without re-introducing any automatic
 * overwrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_state_program_propagations') && ! Schema::hasColumn('fest_state_program_propagations', 'program_updated_at_when_synced')) {
            Schema::table('fest_state_program_propagations', function (Blueprint $table) {
                $table->timestamp('program_updated_at_when_synced')->nullable()->after('is_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_state_program_propagations') && Schema::hasColumn('fest_state_program_propagations', 'program_updated_at_when_synced')) {
            Schema::table('fest_state_program_propagations', function (Blueprint $table) {
                $table->dropColumn('program_updated_at_when_synced');
            });
        }
    }
};
