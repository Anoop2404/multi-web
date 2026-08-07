<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 audit item 1: fest_event_phases had only name/code/sort_order/is_default — no
 * dates, registration window, food cutoff, status, locks, or publication flags, so a
 * "phase" could never actually gate anything, only group items for display. This adds the
 * columns; wiring each pipeline area (registration, food, scheduling, marks, results,
 * certificates, promotion, reports, public pages) to actually READ and enforce these is
 * separate, larger work — not done in this migration.
 *
 * Also closes audit item 3 (no unique constraint on (event_id, code), no enforced single
 * default per event) and adds the phase_mode_enabled flag audit item 4 needs (the column
 * only — validation that item/registration creation actually requires a phase when this is
 * on is, likewise, separate follow-up work; see FestEventPhaseService for where the
 * single-default enforcement for is_default DID get wired up as part of this pass).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_event_phases', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->after('sort_order');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->timestamp('registration_open')->nullable()->after('ends_at');
            $table->timestamp('registration_close')->nullable()->after('registration_open');
            $table->boolean('registration_locked')->default(false)->after('registration_close');
            $table->timestamp('food_cutoff_at')->nullable()->after('registration_locked');
            $table->string('status', 20)->default('draft')->after('food_cutoff_at');
            $table->boolean('scoring_locked')->default(false)->after('status');
            $table->boolean('schedule_published')->default(false)->after('scoring_locked');
            $table->boolean('results_published')->default(false)->after('schedule_published');
            $table->boolean('appeals_open')->default(false)->after('results_published');
            $table->timestamp('appeal_deadline_at')->nullable()->after('appeals_open');

            // MySQL/Postgres both treat multiple NULLs as distinct in a unique index, so
            // this doesn't block several phases on the same event from having a blank code
            // — only actual duplicate non-null codes.
            $table->unique(['event_id', 'code'], 'fest_event_phases_event_code_unique');
        });

        if (! Schema::hasColumn('fest_events', 'phase_mode_enabled')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->boolean('phase_mode_enabled')->default(false)->after('conduct_mode');
            });
        }
    }

    public function down(): void
    {
        Schema::table('fest_event_phases', function (Blueprint $table) {
            $table->dropUnique('fest_event_phases_event_code_unique');
            $table->dropColumn([
                'starts_at', 'ends_at', 'registration_open', 'registration_close',
                'registration_locked', 'food_cutoff_at', 'status', 'scoring_locked',
                'schedule_published', 'results_published', 'appeals_open', 'appeal_deadline_at',
            ]);
        });

        if (Schema::hasColumn('fest_events', 'phase_mode_enabled')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->dropColumn('phase_mode_enabled');
            });
        }
    }
};
