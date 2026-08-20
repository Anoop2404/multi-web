<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FestPhaseAdvancementService creates registrations with mode='phase_advance' (a same-event
 * phase-to-phase advance, e.g. Off Stage/Sargadhara region winners -> District Kalotsav —
 * see fest_phase_advancements, added alongside this migration). The original enum/CHECK
 * constraint from 2026_06_22_000011_phase11_13_event_platform.php only allows
 * 'full'/'winner_only' (the latter used by FestQualificationService's separate
 * Sahodaya->State promotion cascade), so this new mode needs the same widening treatment as
 * 2026_10_06_000003_widen_fest_event_items_participant_type_constraint.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE fest_registrations DROP CONSTRAINT IF EXISTS fest_registrations_mode_check');
        DB::statement(<<<'SQL'
            ALTER TABLE fest_registrations ADD CONSTRAINT fest_registrations_mode_check CHECK (
                mode IN ('full', 'winner_only', 'phase_advance')
            )
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE fest_registrations DROP CONSTRAINT IF EXISTS fest_registrations_mode_check');
        DB::statement(<<<'SQL'
            ALTER TABLE fest_registrations ADD CONSTRAINT fest_registrations_mode_check CHECK (
                mode IN ('full', 'winner_only')
            )
        SQL);
    }
};
