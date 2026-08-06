<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Board Results settings redesign (docs/BOARD_RESULTS_UX_REDESIGN_PLAN.md §3.3): topper
 * caps/tie-mode/rank-style were global-per-Sahodaya only, applying retroactively to every
 * academic year at once. Adds a nullable academic_year so a year can carry its own explicit
 * override; existing rows are left with academic_year = null, which TopperCountService's
 * resolution now treats as "applies to any year without its own override" — additive,
 * no backfill, nothing existing changes behavior. NULL is distinct per row in both MySQL's
 * and Postgres's unique-index semantics, so the widened unique constraint below allows one
 * global (null-year) row *and* any number of per-year rows for the same
 * class/scope/stream/subject combination without colliding.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('topper_count_configs')) {
            return;
        }

        Schema::table('topper_count_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('topper_count_configs', 'academic_year')) {
                $table->string('academic_year', 10)->nullable()->after('sahodaya_id');
            }
        });

        Schema::table('topper_count_configs', function (Blueprint $table) {
            $table->dropUnique('topper_count_configs_unique');
            $table->unique(
                ['sahodaya_id', 'academic_year', 'class', 'scope', 'stream_id', 'subject_id'],
                'topper_count_configs_unique'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('topper_count_configs')) {
            return;
        }

        Schema::table('topper_count_configs', function (Blueprint $table) {
            $table->dropUnique('topper_count_configs_unique');
            $table->unique(
                ['sahodaya_id', 'class', 'scope', 'stream_id', 'subject_id'],
                'topper_count_configs_unique'
            );
        });

        Schema::table('topper_count_configs', function (Blueprint $table) {
            if (Schema::hasColumn('topper_count_configs', 'academic_year')) {
                $table->dropColumn('academic_year');
            }
        });
    }
};
