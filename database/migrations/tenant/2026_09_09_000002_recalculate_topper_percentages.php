<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('toppers')) {
            return;
        }

        DB::table('toppers')
            ->where('entry_type', 'overall')
            ->whereNotNull('marks_obtained')
            ->whereNotNull('total_marks')
            ->where('total_marks', '>', 0)
            ->orderBy('id')
            ->eachById(function ($topper) {
                $percentage = round(((float) $topper->marks_obtained / (float) $topper->total_marks) * 100, 2);
                if (abs((float) $topper->percentage - $percentage) < 0.001) {
                    return;
                }

                DB::table('toppers')->where('id', $topper->id)->update([
                    'percentage' => $percentage,
                    'updated_at' => now(),
                ]);
            });

        if (Schema::hasTable('board_result_rankings')) {
            DB::table('board_result_rankings')
                ->whereIn('scope', ['student_overall', 'student_stream'])
                ->delete();
        }
    }

    public function down(): void
    {
        // Derived percentages cannot be restored to their previously inconsistent values.
    }
};
