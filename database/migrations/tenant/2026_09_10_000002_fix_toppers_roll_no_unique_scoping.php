<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('toppers')) {
            try {
                // Drop global (board_result_id, roll_no) unique index if present
                DB::statement('DROP INDEX IF EXISTS toppers_board_result_roll_no_unique');

                // Re-create as (board_result_id, roll_no, entry_type) so a student can exist
                // as an overall topper, subject topper, and full A1 achiever within the same result
                DB::statement(
                    'CREATE UNIQUE INDEX IF NOT EXISTS toppers_board_result_roll_no_entry_type_unique ' .
                    'ON toppers (board_result_id, roll_no, entry_type) WHERE roll_no IS NOT NULL AND roll_no != \'\''
                );
            } catch (\Throwable $e) {
                logger()->warning('fix_toppers_roll_no_unique_scoping: index update skipped', ['error' => $e->getMessage()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('toppers')) {
            try {
                DB::statement('DROP INDEX IF EXISTS toppers_board_result_roll_no_entry_type_unique');
                DB::statement(
                    'CREATE UNIQUE INDEX IF NOT EXISTS toppers_board_result_roll_no_unique ' .
                    'ON toppers (board_result_id, roll_no) WHERE roll_no IS NOT NULL AND roll_no != \'\''
                );
            } catch (\Throwable $e) {
                logger()->warning('fix_toppers_roll_no_unique_scoping: rollback skipped', ['error' => $e->getMessage()]);
            }
        }
    }
};
