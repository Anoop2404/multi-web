<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes a latent inconsistency from 2026_08_06_000001_board_results_frd21_cleanup.php:
 * that migration added a partial unique index —
 *   CREATE UNIQUE INDEX toppers_board_result_rank_unique ON toppers (board_result_id, rank)
 *   WHERE rank IS NOT NULL
 * — whose whole point is to let non-ranked topper rows opt out by storing NULL. But the
 * original column (2026_05_24_000005_create_results_tables.php) is `unsignedInteger('rank')
 * ->default(1)` with no ->nullable(), so rank was never actually capable of being NULL —
 * every omitted rank silently fell back to the DB default of 1 instead.
 *
 * This went unnoticed until entry_type=full_a1 (Full A1 Achievers, #161) started inserting
 * toppers without a rank: rank isn't a meaningful concept for that entry type (it's a
 * qualifying list, not a leaderboard), but the DB default of 1 collided with whatever row
 * already held board_result_rank=1 (typically the Overall topper), throwing a 23505 unique
 * violation. Class X/XII entry_type=subject rows dodge this only because
 * storeSubjectToppersBatch() manually assigns max(rank)+1 — full_a1 has no such workaround
 * and shouldn't need one, since its rows were never meant to carry a rank at all.
 *
 * Making the column nullable and dropping its default is what the partial index already
 * assumed; existing rows keep whatever rank they have today (untouched).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('toppers') || ! Schema::hasColumn('toppers', 'rank')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE toppers ALTER COLUMN rank DROP NOT NULL');
            DB::statement('ALTER TABLE toppers ALTER COLUMN rank DROP DEFAULT');
        } catch (\Throwable $e) {
            // Non-Postgres drivers (e.g. SQLite in tests) don't support ALTER COLUMN the
            // same way; app-level code already avoids relying on the DB default for
            // entry_type=full_a1, so this is safe to skip there.
            logger()->warning('make_toppers_rank_nullable: ALTER COLUMN skipped', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('toppers') || ! Schema::hasColumn('toppers', 'rank')) {
            return;
        }

        try {
            // Backfill first — you can't add NOT NULL back while NULLs exist.
            DB::table('toppers')->whereNull('rank')->update(['rank' => 1]);
            DB::statement('ALTER TABLE toppers ALTER COLUMN rank SET DEFAULT 1');
            DB::statement('ALTER TABLE toppers ALTER COLUMN rank SET NOT NULL');
        } catch (\Throwable $e) {
            logger()->warning('make_toppers_rank_nullable: rollback skipped', ['error' => $e->getMessage()]);
        }
    }
};
