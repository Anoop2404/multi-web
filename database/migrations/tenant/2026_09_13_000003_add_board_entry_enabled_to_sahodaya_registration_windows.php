<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Board Results settings redesign §3.3a (mid-session addition, 3 Aug 2026): year-based
 * board-result data entry needs an explicit enable/disable toggle in addition to the
 * existing board_entry_starts_at/board_entry_ends_at date range. Today, a blank date range
 * on SahodayaRegistrationWindow is treated by BoardResultAcademicYearService as "entry is
 * open" (fail-open). The user wants the opposite for years an admin has actually opted into
 * the new toggle: without a configured range, entry should be BLOCKED (fail-closed).
 *
 * Critically, this column is NULLABLE with NO default (stays null), not boolean-default-false.
 * A default of false would have marked every existing Sahodaya's row — including ones with
 * board_entry dates already configured and in active use — as "explicitly disabled" the
 * instant this migration ran, which would have silently locked everyone out. Instead:
 *   - null  = toggle never touched (every existing row, forever, unless resaved) — old
 *             date-only fail-open behavior is preserved exactly.
 *   - true  = admin explicitly enabled entry for this year (settings UI requires dates too).
 *   - false = admin explicitly disabled entry for this year — always blocked regardless of dates.
 *
 * BoardResultAcademicYearService only changes behavior when this column is non-null, so
 * nothing changes for any Sahodaya until an admin visits the new settings page and saves
 * this toggle themselves. See docs/BOARD_RESULTS_UX_REDESIGN_PLAN.md §3.3a for the full
 * rollout-safety sequencing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sahodaya_registration_windows')) {
            return;
        }

        Schema::table('sahodaya_registration_windows', function (Blueprint $table) {
            if (! Schema::hasColumn('sahodaya_registration_windows', 'board_entry_enabled')) {
                $table->boolean('board_entry_enabled')->nullable()->default(null)->after('board_entry_ends_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sahodaya_registration_windows')) {
            return;
        }

        Schema::table('sahodaya_registration_windows', function (Blueprint $table) {
            if (Schema::hasColumn('sahodaya_registration_windows', 'board_entry_enabled')) {
                $table->dropColumn('board_entry_enabled');
            }
        });
    }
};
