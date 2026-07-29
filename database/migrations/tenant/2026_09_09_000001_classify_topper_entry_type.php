<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('toppers', 'entry_type')) {
            Schema::table('toppers', function (Blueprint $table) {
                $table->string('entry_type', 16)->default('overall')->after('tenant_id')->index();
            });
        }

        if (! Schema::hasTable('topper_subject_marks')) {
            return;
        }

        // The legacy subject-entry endpoint created Class XII subject rows as if they
        // were overall toppers: no stream, a 100-mark total, and the subject mark copied
        // into percentage/marks_obtained. Classify only that narrow shape so genuine
        // overall rows (including rows that also carry subject marks) remain untouched.
        $classTwelveResultIds = DB::table('board_results')
            ->where('class', 12)
            ->pluck('id');

        DB::table('toppers')
            ->whereIn('board_result_id', $classTwelveResultIds)
            ->whereNull('stream_id')
            ->where(function ($query) {
                $query->whereNull('stream')->orWhere('stream', '');
            })
            ->where('total_marks', 100)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('topper_subject_marks')
                    ->whereColumn('topper_subject_marks.topper_id', 'toppers.id');
            })
            ->update([
                'entry_type' => 'subject',
                'percentage' => null,
                'marks_obtained' => null,
                'total_marks' => null,
                'updated_at' => now(),
            ]);

        // Existing cached student rankings may still reference rows just reclassified
        // as subject-only. Clearing only these two derived scopes makes the next report
        // request rebuild them from the corrected source rows.
        if (Schema::hasTable('board_result_rankings')) {
            DB::table('board_result_rankings')
                ->whereIn('scope', ['student_overall', 'student_stream'])
                ->delete();
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('toppers', 'entry_type')) {
            Schema::table('toppers', function (Blueprint $table) {
                $table->dropColumn('entry_type');
            });
        }
    }
};
