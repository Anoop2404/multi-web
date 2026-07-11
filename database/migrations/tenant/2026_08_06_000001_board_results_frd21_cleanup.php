<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // #138 — migrate any remaining subject_marks JSON into topper_subject_marks, then drop column.
        if (Schema::hasTable('toppers') && Schema::hasColumn('toppers', 'subject_marks')) {
            if (Schema::hasTable('topper_subject_marks')) {
                $toppers = DB::table('toppers')->whereNotNull('subject_marks')->get(['id', 'subject_marks']);
                foreach ($toppers as $topper) {
                    $raw = $topper->subject_marks;
                    $marks = is_string($raw) ? json_decode($raw, true) : (array) $raw;
                    if (! is_array($marks) || $marks === []) {
                        continue;
                    }

                    $hasRows = DB::table('topper_subject_marks')->where('topper_id', $topper->id)->exists();
                    if ($hasRows) {
                        continue;
                    }

                    foreach ($marks as $label => $value) {
                        $label = trim((string) $label);
                        if ($label === '' || $value === '' || $value === null || ! is_numeric($value)) {
                            continue;
                        }
                        $num = (float) $value;
                        if ($num < 0 || $num > 100) {
                            continue;
                        }
                        DB::table('topper_subject_marks')->insert([
                            'topper_id' => $topper->id,
                            'subject_id' => null,
                            'subject_label' => $label,
                            'marks' => $num,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            Schema::table('toppers', function (Blueprint $table) {
                $table->dropColumn('subject_marks');
            });
        }

        // #153 — DB-level guards (PostgreSQL CHECK; skipped silently on drivers that reject).
        if (Schema::hasTable('board_results')) {
            try {
                DB::statement('ALTER TABLE board_results DROP CONSTRAINT IF EXISTS board_results_pass_lte_appeared_chk');
                DB::statement('ALTER TABLE board_results ADD CONSTRAINT board_results_pass_lte_appeared_chk CHECK (pass_count <= total_appeared)');
            } catch (\Throwable) {
                // SQLite / MySQL variants may not support IF EXISTS the same way.
            }
        }

        if (Schema::hasTable('toppers')) {
            try {
                // Soft uniqueness: one rank per board_result when rank is not null.
                // Partial unique index (Postgres); ignore if unsupported.
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS toppers_board_result_rank_unique ON toppers (board_result_id, rank) WHERE rank IS NOT NULL');
            } catch (\Throwable) {
                // Driver without partial indexes — app-level validation remains.
            }
        }

        // #137 — ensure global exam_streams seed rows exist (idempotent).
        if (Schema::hasTable('exam_streams')) {
            $defaults = [
                ['code' => 'bio_science', 'label' => 'Bio Science (PCB)', 'sort_order' => 10, 'default_subjects' => ['English Core', 'Physics', 'Chemistry', 'Biology', 'Mathematics']],
                ['code' => 'computer_science', 'label' => 'Computer Science (PCM + CS)', 'sort_order' => 20, 'default_subjects' => ['English Core', 'Physics', 'Chemistry', 'Mathematics', 'Computer Science']],
                ['code' => 'commerce', 'label' => 'Commerce', 'sort_order' => 30, 'default_subjects' => ['English Core', 'Accountancy', 'Business Studies', 'Economics', 'Mathematics']],
                ['code' => 'humanities', 'label' => 'Humanities / Arts', 'sort_order' => 40, 'default_subjects' => ['English Core', 'History', 'Geography', 'Political Science', 'Economics']],
                ['code' => 'other', 'label' => 'Other / Mixed', 'sort_order' => 90, 'default_subjects' => ['English Core', 'Mathematics', 'Physics', 'Chemistry', 'Biology']],
            ];

            foreach ($defaults as $stream) {
                $exists = DB::table('exam_streams')
                    ->whereNull('sahodaya_id')
                    ->where('code', $stream['code'])
                    ->exists();
                if ($exists) {
                    DB::table('exam_streams')
                        ->whereNull('sahodaya_id')
                        ->where('code', $stream['code'])
                        ->update([
                            'label' => $stream['label'],
                            'default_subjects' => json_encode($stream['default_subjects']),
                            'sort_order' => $stream['sort_order'],
                            'is_active' => true,
                            'updated_at' => now(),
                        ]);
                    continue;
                }

                DB::table('exam_streams')->insert([
                    'sahodaya_id' => null,
                    'code' => $stream['code'],
                    'label' => $stream['label'],
                    'examination_type' => 'AISSCE',
                    'sort_order' => $stream['sort_order'],
                    'is_active' => true,
                    'default_subjects' => json_encode($stream['default_subjects']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('board_results')) {
            try {
                DB::statement('ALTER TABLE board_results DROP CONSTRAINT IF EXISTS board_results_pass_lte_appeared_chk');
            } catch (\Throwable) {
            }
        }

        if (Schema::hasTable('toppers')) {
            try {
                DB::statement('DROP INDEX IF EXISTS toppers_board_result_rank_unique');
            } catch (\Throwable) {
            }

            if (! Schema::hasColumn('toppers', 'subject_marks')) {
                Schema::table('toppers', function (Blueprint $table) {
                    $table->jsonb('subject_marks')->nullable();
                });
            }
        }
    }
};
