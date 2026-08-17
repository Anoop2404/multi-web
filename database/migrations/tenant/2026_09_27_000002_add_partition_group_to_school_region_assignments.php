<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §7.3 item 1 (dated 2026-08-15).
 *
 * Today `school_region_assignments` is keyed `(school_id, academic_year)` — one
 * Sahodaya-wide region per school per year, shared across every partitioned hub
 * (see FestRegionPartitionService::schoolRegion()). §7.2 identified this as the gap:
 * there's no way to give a school a different region for, say, phase 2 (Off Stage)
 * vs phase 3 (Sargadhara) of the same event.
 *
 * This adds a nullable `partition_group` column (e.g. 'off_stage', 'sargadhara' —
 * matching a regional FestEventPhase's `region_partition_group`, see the companion
 * migration on fest_event_phases). Rows are NEVER backfilled or defaulted to
 * anything but NULL here — that is the load-bearing backward-compat guarantee:
 * every existing row, and every row any *unmodified* caller writes going forward,
 * keeps `partition_group = NULL`, which is exactly the legacy "one Sahodaya-wide
 * region" row FestRegionPartitionService::schoolRegion() already resolves when its
 * new `$partitionGroup` parameter is omitted (the default). Sahodayas that never
 * adopt multi-group regional phases see zero behavior change.
 *
 * The old unique constraint on (school_id, academic_year) is replaced with two
 * partial unique indexes rather than simply adding partition_group to one combined
 * unique constraint:
 *   - one scoped to `partition_group IS NULL`, preserving the existing "at most one
 *     Sahodaya-wide row per school per year" invariant exactly as before;
 *   - one scoped to `partition_group IS NOT NULL`, allowing a school to hold one row
 *     PER GROUP per year (e.g. one 'off_stage' row and one independent 'sargadhara'
 *     row for the same school/year) — the whole point of this column.
 * A single combined unique(school_id, academic_year, partition_group) index would not
 * do this: Postgres treats NULL as distinct from NULL in a unique index, so it would
 * silently stop enforcing "only one legacy row per school per year" the moment this
 * column existed, even though nothing had opted into partition groups yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_region_assignments')) {
            return;
        }

        if (! Schema::hasColumn('school_region_assignments', 'partition_group')) {
            Schema::table('school_region_assignments', function (Blueprint $table) {
                // Nullable, no default — see docblock above. Existing rows land as NULL.
                $table->string('partition_group')->nullable()->after('academic_year');
            });
        }

        try {
            DB::statement('DROP INDEX IF EXISTS school_region_assignments_school_id_academic_year_unique');

            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS school_region_assignments_legacy_unique '.
                'ON school_region_assignments (school_id, academic_year) WHERE partition_group IS NULL'
            );

            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS school_region_assignments_group_unique '.
                'ON school_region_assignments (school_id, academic_year, partition_group) WHERE partition_group IS NOT NULL'
            );
        } catch (\Throwable $e) {
            logger()->warning('add_partition_group_to_school_region_assignments: index swap skipped', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_region_assignments')) {
            return;
        }

        try {
            DB::statement('DROP INDEX IF EXISTS school_region_assignments_group_unique');
            DB::statement('DROP INDEX IF EXISTS school_region_assignments_legacy_unique');

            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS school_region_assignments_school_id_academic_year_unique '.
                'ON school_region_assignments (school_id, academic_year)'
            );
        } catch (\Throwable $e) {
            // Best-effort — if group-scoped rows exist for a school/year that also has
            // a legacy NULL row, restoring the old unqualified unique index isn't
            // possible until they're resolved manually.
            logger()->warning('add_partition_group_to_school_region_assignments: rollback index restore skipped', ['error' => $e->getMessage()]);
        }

        if (Schema::hasColumn('school_region_assignments', 'partition_group')) {
            Schema::table('school_region_assignments', function (Blueprint $table) {
                $table->dropColumn('partition_group');
            });
        }
    }
};
