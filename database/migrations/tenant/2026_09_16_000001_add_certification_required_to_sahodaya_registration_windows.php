<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Board Results — Principal Verification (docs/BOARD_RESULTS_PRINCIPAL_VERIFICATION_PLAN.md
 * §13 Phase 5): mandatory certification must be enabled per academic year, not flipped on
 * globally for every school/year at once. Same fail-safe nullable-boolean pattern as
 * board_entry_enabled (see 2026_09_13_000003_add_board_entry_enabled_to_sahodaya_registration_windows.php):
 *
 *   - null  = untouched (default for every existing row) — direct BoardResult submission
 *             stays allowed unless/until a certification package already exists for that
 *             result (the soft, package-presence-based gate already enforced in code).
 *   - true  = Sahodaya has explicitly opted this academic year into mandatory Principal
 *             Verification — direct submission is blocked outright, certification required.
 *   - false = explicitly opted out / exempted (e.g. historical legacy year).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sahodaya_registration_windows')) {
            return;
        }

        Schema::table('sahodaya_registration_windows', function (Blueprint $table) {
            if (! Schema::hasColumn('sahodaya_registration_windows', 'certification_required')) {
                $table->boolean('certification_required')->nullable()->default(null)->after('board_entry_enabled');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sahodaya_registration_windows')) {
            return;
        }

        Schema::table('sahodaya_registration_windows', function (Blueprint $table) {
            if (Schema::hasColumn('sahodaya_registration_windows', 'certification_required')) {
                $table->dropColumn('certification_required');
            }
        });
    }
};
