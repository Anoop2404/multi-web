<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clean up any existing duplicate roll_no values BEFORE creating the unique
        // index. The earlier duplication bug may have created multiple toppers with
        // the same roll_no within the same board_result. Keep only the most recently
        // updated row for each duplicate pair; older duplicates are removed.
        //
        // Uses HAVING COUNT(*) > 1 (not the alias "cnt") because PostgreSQL rejects
        // column aliases in HAVING clauses.
        $duplicates = DB::table('toppers')
            ->select('board_result_id', 'roll_no')
            ->selectRaw('COUNT(*) as cnt')
            ->whereNotNull('roll_no')
            ->where('roll_no', '!=', '')
            ->groupBy('board_result_id', 'roll_no')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            // Keep the row with the highest id (most recently created), delete the rest.
            $keepId = DB::table('toppers')
                ->where('board_result_id', $dup->board_result_id)
                ->where('roll_no', $dup->roll_no)
                ->max('id');

            DB::table('toppers')
                ->where('board_result_id', $dup->board_result_id)
                ->where('roll_no', $dup->roll_no)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('toppers', function (Blueprint $table) {
            // Partial unique index — only non-null roll_no values are enforced, so
            // multiple toppers without a roll_no string are still allowed. MySQL 8.0.13+
            // and SQLite 3.25+ both support WHERE in unique indexes; PostgreSQL does too.
            $table->unique(['board_result_id', 'roll_no'], 'toppers_board_result_roll_no_unique')
                ->where('roll_no IS NOT NULL');
        });
    }

    public function down(): void
    {
        Schema::table('toppers', function (Blueprint $table) {
            $table->dropIndex('toppers_board_result_roll_no_unique');
        });
    }
};
