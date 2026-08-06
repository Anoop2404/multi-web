<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companion to 2026_09_13_000001 (see that file for the full rationale). Same pattern
 * applied to board_result_marks_configs: total_marks per Sahodaya+class+stream was
 * global-only; this adds a nullable academic_year override tier. Existing rows keep
 * academic_year = null and continue to resolve as the fallback for any year without its
 * own row — additive, no backfill required.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('board_result_marks_configs')) {
            return;
        }

        Schema::table('board_result_marks_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('board_result_marks_configs', 'academic_year')) {
                $table->string('academic_year', 10)->nullable()->after('sahodaya_id');
            }
        });

        Schema::table('board_result_marks_configs', function (Blueprint $table) {
            $table->dropUnique('board_result_marks_configs_unique');
            $table->unique(
                ['sahodaya_id', 'academic_year', 'class', 'stream_id'],
                'board_result_marks_configs_unique'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('board_result_marks_configs')) {
            return;
        }

        Schema::table('board_result_marks_configs', function (Blueprint $table) {
            $table->dropUnique('board_result_marks_configs_unique');
            $table->unique(
                ['sahodaya_id', 'class', 'stream_id'],
                'board_result_marks_configs_unique'
            );
        });

        Schema::table('board_result_marks_configs', function (Blueprint $table) {
            if (Schema::hasColumn('board_result_marks_configs', 'academic_year')) {
                $table->dropColumn('academic_year');
            }
        });
    }
};
