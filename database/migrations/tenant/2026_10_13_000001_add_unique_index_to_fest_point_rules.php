<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FestEventSettingsController::storePointRule()/seedConfedKalotsavPoints()/
 * syncPointRulesToRegions() all use updateOrCreate() keyed on
 * (event_id, grade, position, is_group) to stop the same rule being saved twice — but
 * updateOrCreate() is a firstOrNew()+save(), not atomic, so two concurrent requests
 * (e.g. a double form-submit) can still both pass the "does this exist" check before
 * either has saved, producing two rows with different points and no error. Same class
 * of race condition already fixed for fest_school_event_fees this session
 * (FestRegistrationBatchFeeService::syncRollup()). No duplicates exist in current data
 * (checked directly) — this is a real DB-level guard against it happening going forward.
 *
 * grade/position are both nullable (a rule can be grade-only, position-only, or both),
 * and Postgres/SQLite unique indexes treat NULL as distinct from every other NULL, so a
 * plain composite unique() wouldn't actually catch a duplicate where either is null —
 * COALESCE to a sentinel that can't otherwise occur (grade is never '', position is
 * never 0) so the index treats "no grade"/"no position" as one comparable value.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_point_rules')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('
                CREATE UNIQUE INDEX IF NOT EXISTS fest_point_rules_identity_unique
                ON fest_point_rules (event_id, COALESCE(grade, \'\'), COALESCE(position, 0), is_group)
            ');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_point_rules')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS fest_point_rules_identity_unique');
        }
    }
};
