<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidates the two "Science" sub-streams (bio_science / computer_science) seeded by
 * 2026_08_03_000003_board_results_frd21_high.php / 2026_08_06_000001_board_results_frd21_cleanup.php
 * into a single canonical `science` exam stream, matching official CBSE stream naming — CBSE
 * only recognises three Class XII streams (Science / Commerce / Humanities); "Bio Science" and
 * "Computer Science" aren't separate streams, they're just elective combinations a student
 * picks within Science. Also corrects the default_subjects list for all three streams to match
 * the real CBSE Class XII subject spread (the original seed only listed 5 subjects each).
 *
 * This is a forward-fixing migration, not an edit of the original seed migrations (which are
 * left alone as historical record — editing an already-applied migration's seed data in place
 * would silently diverge already-migrated environments from fresh ones).
 *
 * Existing toppers recorded against bio_science/computer_science are remapped to the new
 * `science` stream (same for topper_count_configs). The old stream rows are deactivated rather
 * than deleted, preserving their ids/history for audit purposes and for anything not traced
 * here that might still hold a reference. board_result_rankings is a derived/computed cache
 * (rebuilt by RankingEngine on every publish run) and does not need migrating — it will pick
 * up the new stream automatically next time rankings are recomputed.
 */
return new class extends Migration
{
    // Exact CBSE Class XII stream subject lists — Physics/Chemistry/Mathematics/Biology/
    // Computer Science/English Core for Science; Accountancy/Economics/Business Studies/
    // Mathematics/English Core for Commerce; History/Political Science/Geography/Economics/
    // Sociology/Psychology/Philosophy/Mathematics/English Core for Humanities. No additions
    // beyond the official CBSE list (e.g. no Informatics Practices bolted on).
    private const SCIENCE_SUBJECTS = [
        'English Core', 'Physics', 'Chemistry', 'Mathematics', 'Biology', 'Computer Science',
    ];

    private const COMMERCE_SUBJECTS = [
        'English Core', 'Accountancy', 'Economics', 'Business Studies', 'Mathematics',
    ];

    private const HUMANITIES_SUBJECTS = [
        'English Core', 'History', 'Political Science', 'Geography', 'Economics',
        'Sociology', 'Psychology', 'Philosophy', 'Mathematics',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('exam_streams')) {
            return;
        }

        // Every distinct sahodaya_id scope (including the global null scope) that currently
        // has a bio_science or computer_science row needs its own consolidated `science` row.
        $scopes = DB::table('exam_streams')
            ->whereIn('code', ['bio_science', 'computer_science'])
            ->pluck('sahodaya_id')
            ->unique()
            ->values();

        if (! $scopes->contains(null)) {
            $scopes->push(null);
        }

        $now = now();

        foreach ($scopes as $sahodayaId) {
            $scienceId = DB::table('exam_streams')
                ->where('code', 'science')
                ->where(function ($q) use ($sahodayaId) {
                    $sahodayaId === null ? $q->whereNull('sahodaya_id') : $q->where('sahodaya_id', $sahodayaId);
                })
                ->value('id');

            if ($scienceId === null) {
                $scienceId = DB::table('exam_streams')->insertGetId([
                    'sahodaya_id' => $sahodayaId,
                    'code' => 'science',
                    'label' => 'Science',
                    'examination_type' => 'AISSCE',
                    'sort_order' => 10,
                    'is_active' => true,
                    'default_subjects' => json_encode(self::SCIENCE_SUBJECTS),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('exam_streams')->where('id', $scienceId)->update([
                    'label' => 'Science',
                    'default_subjects' => json_encode(self::SCIENCE_SUBJECTS),
                    'is_active' => true,
                    'sort_order' => 10,
                    'updated_at' => $now,
                ]);
            }

            $oldIds = DB::table('exam_streams')
                ->whereIn('code', ['bio_science', 'computer_science'])
                ->where(function ($q) use ($sahodayaId) {
                    $sahodayaId === null ? $q->whereNull('sahodaya_id') : $q->where('sahodaya_id', $sahodayaId);
                })
                ->pluck('id');

            if ($oldIds->isNotEmpty()) {
                if (Schema::hasTable('toppers')) {
                    DB::table('toppers')->whereIn('stream_id', $oldIds)->update(['stream_id' => $scienceId]);
                }

                if (Schema::hasTable('topper_count_configs')) {
                    DB::table('topper_count_configs')->whereIn('stream_id', $oldIds)->update(['stream_id' => $scienceId]);
                }

                // Deactivate rather than delete — keeps the row (and its id) around for
                // historical audit trails / anything not traced here that might still point
                // at it, without it showing up as a selectable stream any more.
                DB::table('exam_streams')->whereIn('id', $oldIds)->update([
                    'is_active' => false,
                    'updated_at' => $now,
                ]);
            }
        }

        // Correct Commerce / Humanities subject lists to the real CBSE Class XII spread —
        // the original seed only had 5 subjects each and omitted common electives.
        DB::table('exam_streams')->where('code', 'commerce')->update([
            'default_subjects' => json_encode(self::COMMERCE_SUBJECTS),
            'updated_at' => $now,
        ]);
        DB::table('exam_streams')->where('code', 'humanities')->update([
            'default_subjects' => json_encode(self::HUMANITIES_SUBJECTS),
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Deliberately not reversible — we don't record each topper's pre-merge stream_id,
        // so splitting Science back into bio_science/computer_science can't be done losslessly.
    }
};
