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
                DB::statement('DROP INDEX IF EXISTS toppers_board_result_rank_unique');
            } catch (\Throwable $e) {
                logger()->warning('drop_toppers_board_result_rank_unique_index: drop skipped', ['error' => $e->getMessage()]);
            }
        }
    }

    public function down(): void
    {
        // Do not recreate toppers_board_result_rank_unique index on rollback,
        // because overall toppers can legitimately tie (share identical rank values).
    }
};
