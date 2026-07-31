<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('toppers')) {
            // In PostgreSQL, unique constraints created via $table->unique() must be dropped
            // using ALTER TABLE DROP CONSTRAINT before the underlying index can be removed.
            try {
                DB::statement('ALTER TABLE toppers DROP CONSTRAINT IF EXISTS toppers_board_result_roll_no_unique');
            } catch (\Throwable $e) {
                logger()->info('drop constraint toppers_board_result_roll_no_unique: ' . $e->getMessage());
            }

            try {
                DB::statement('ALTER TABLE toppers DROP CONSTRAINT IF EXISTS toppers_board_result_rank_unique');
            } catch (\Throwable $e) {
                logger()->info('drop constraint toppers_board_result_rank_unique: ' . $e->getMessage());
            }

            try {
                DB::statement('DROP INDEX IF EXISTS toppers_board_result_roll_no_unique');
            } catch (\Throwable $e) {
                logger()->info('drop index toppers_board_result_roll_no_unique: ' . $e->getMessage());
            }

            try {
                DB::statement('DROP INDEX IF EXISTS toppers_board_result_rank_unique');
            } catch (\Throwable $e) {
                logger()->info('drop index toppers_board_result_rank_unique: ' . $e->getMessage());
            }

            try {
                DB::statement('DROP INDEX IF EXISTS toppers_board_result_roll_no_entry_type_unique');
                DB::statement(
                    'CREATE UNIQUE INDEX IF NOT EXISTS toppers_board_result_roll_no_entry_type_unique ' .
                    'ON toppers (board_result_id, roll_no, entry_type) WHERE roll_no IS NOT NULL AND roll_no != \'\''
                );
            } catch (\Throwable $e) {
                logger()->warning('create toppers_board_result_roll_no_entry_type_unique: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // No rollback needed as legacy un-scoped constraints caused 500 errors in production.
    }
};
