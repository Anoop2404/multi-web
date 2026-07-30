<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class X (AISSE) has no streams, so it never got a subject list managed the
 * way Class XII's Science/Commerce/Humanities streams are (#161 follow-up).
 * This seeds one global "class_10" row into exam_streams — reusing the exact
 * same default_subjects JSON mechanism — so a Sahodaya admin can add/edit
 * Class X's subject list on the existing Masters page (BoardResultMastersController)
 * without any new UI. BoardExamSubjects::subjectsForClass10() reads it the same
 * way subjectsForStream() reads a real stream.
 *
 * Follows the same insert-if-absent, global-row (sahodaya_id null) pattern as
 * 2026_08_06_000002_board_results_cbse_stream_consolidation.php.
 */
return new class extends Migration
{
    private const CLASS_10_SUBJECTS = [
        'English', 'Hindi Course A', 'Hindi Course B', 'Malayalam', 'Sanskrit',
        'Mathematics Standard', 'Mathematics Basic', 'Science', 'Social Science',
        'Information Technology',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('exam_streams')) {
            return;
        }

        $existingId = DB::table('exam_streams')
            ->where('code', 'class_10')
            ->whereNull('sahodaya_id')
            ->value('id');

        $now = now();

        if ($existingId === null) {
            DB::table('exam_streams')->insert([
                'sahodaya_id' => null,
                'code' => 'class_10',
                'label' => 'Class X (AISSE)',
                'examination_type' => 'AISSE',
                'sort_order' => 5,
                'is_active' => true,
                'default_subjects' => json_encode(self::CLASS_10_SUBJECTS),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            // Row already exists (e.g. re-run in a fresh env) — leave label/subjects as
            // whatever an admin may have already customized, just make sure it's active.
            DB::table('exam_streams')->where('id', $existingId)->update([
                'is_active' => true,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('exam_streams')) {
            return;
        }

        DB::table('exam_streams')->where('code', 'class_10')->whereNull('sahodaya_id')->delete();
    }
};
