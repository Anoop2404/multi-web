<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes the same class of bug as 2026_08_20_000003_make_toppers_rank_nullable.php,
 * for roll_no this time.
 *
 * 2026_09_08_000001_add_roll_no_unique_to_toppers.php added
 *   UNIQUE (board_result_id, roll_no) WHERE roll_no IS NOT NULL
 * with no entry_type scoping. But it's completely normal for the same real student to
 * appear in more than one list for the same board_result — e.g. the class topper
 * (entry_type=overall) is very plausibly also a Full A1 Achiever (entry_type=full_a1)
 * and/or a subject-wise topper (entry_type=subject) — and all three rows legitimately
 * share that student's one CBSE roll_no. The old index rejects that with a raw 23505
 * unique-violation the moment a second entry_type row is saved for the same roll_no,
 * exactly like the rank collision fixed by 000003.
 *
 * This re-scopes the constraint to (board_result_id, roll_no, entry_type) — still
 * blocks two DIFFERENT students from sharing a roll_no within the same list (the
 * actual data-integrity guarantee that migration was written for), but no longer
 * blocks the same student from legitimately appearing in more than one list.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('toppers')) {
            return;
        }

        // Clean up any duplicate (board_result_id, roll_no) pairs WITHIN the same
        // entry_type before creating the new index — same "keep the newest row"
        // policy the original migration used, just scoped so it never touches two
        // rows that legitimately belong to different entry_types.
        $duplicates = DB::table('toppers')
            ->select('board_result_id', 'roll_no', 'entry_type')
            ->selectRaw('COUNT(*) as cnt')
            ->whereNotNull('roll_no')
            ->where('roll_no', '!=', '')
            ->groupBy('board_result_id', 'roll_no', 'entry_type')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $keepId = DB::table('toppers')
                ->where('board_result_id', $dup->board_result_id)
                ->where('roll_no', $dup->roll_no)
                ->where('entry_type', $dup->entry_type)
                ->max('id');

            DB::table('toppers')
                ->where('board_result_id', $dup->board_result_id)
                ->where('roll_no', $dup->roll_no)
                ->where('entry_type', $dup->entry_type)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        try {
            DB::statement('DROP INDEX IF EXISTS toppers_board_result_roll_no_unique');
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS toppers_board_result_roll_no_entry_type_unique '.
                'ON toppers (board_result_id, roll_no, entry_type) WHERE roll_no IS NOT NULL'
            );
        } catch (\Throwable $e) {
            logger()->warning('scope_toppers_roll_no_unique_by_entry_type: index swap skipped', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('toppers')) {
            return;
        }

        try {
            DB::statement('DROP INDEX IF EXISTS toppers_board_result_roll_no_entry_type_unique');
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS toppers_board_result_roll_no_unique '.
                'ON toppers (board_result_id, roll_no) WHERE roll_no IS NOT NULL'
            );
        } catch (\Throwable $e) {
            // Rolling back is best-effort — if cross-entry_type duplicates now exist
            // (created while the scoped index was active), the old unscoped index
            // can't be recreated until they're resolved manually.
            logger()->warning('scope_toppers_roll_no_unique_by_entry_type: rollback skipped', ['error' => $e->getMessage()]);
        }
    }
};
