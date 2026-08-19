<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removes the closed-set CHECK constraints (A_plus/A/B/C-only, and a pre-existing
     * mismatched A/B/C-only on fest_marks that didn't even allow 'A+') generated from the
     * original Laravel enum() column definitions on these four grade columns, so an event
     * can define its own grade vocabulary (see FestGradePointService::validGradesForEvent())
     * instead of being locked to the fixed four.
     *
     * Postgres: these columns are already VARCHAR (Laravel's enum() compiles to varchar +
     * a named CHECK constraint there, not a native enum type — confirmed against the live
     * schema; same shape already fixed for fest_events.event_type by
     * 2026_09_02_000001_fix_postgres_fest_check_constraints.php), so only the named
     * constraints need dropping — no column type change.
     *
     * SQLite: confirmed empirically (a test inserting a non-legacy grade failed with
     * "CHECK constraint failed: grade") that enum() enforces the same restriction here too
     * — unlike fest_events.event_type/fest_event_items.category, which apparently never hit
     * this in practice. DROP CONSTRAINT isn't supported at all on SQLite; Schema::change()
     * is the correct driver-native way to widen the column (Laravel rebuilds the table under
     * the hood), so that path is used here instead of raw DB::statement().
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE fest_grade_configs DROP CONSTRAINT IF EXISTS fest_grade_configs_grade_check');
            DB::statement('ALTER TABLE fest_point_rules DROP CONSTRAINT IF EXISTS fest_point_rules_grade_check');
            DB::statement('ALTER TABLE fest_marks DROP CONSTRAINT IF EXISTS fest_marks_grade_check');
            DB::statement('ALTER TABLE fest_judge_scores DROP CONSTRAINT IF EXISTS fest_judge_scores_grade_check');

            return;
        }

        Schema::table('fest_grade_configs', fn (Blueprint $table) => $table->string('grade', 40)->nullable(false)->change());
        Schema::table('fest_point_rules', fn (Blueprint $table) => $table->string('grade', 40)->nullable()->change());
        Schema::table('fest_marks', fn (Blueprint $table) => $table->string('grade', 40)->nullable()->change());
        Schema::table('fest_judge_scores', fn (Blueprint $table) => $table->string('grade', 40)->nullable()->change());
    }

    public function down(): void
    {
        // Irreversible — re-adding the old constraints would risk rejecting any custom
        // grade rows created while this migration was active.
    }
};
