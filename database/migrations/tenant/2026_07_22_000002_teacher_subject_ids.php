<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teachers')) {
            return;
        }

        if (! Schema::hasColumn('teachers', 'subject_ids')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->json('subject_ids')->nullable()->after('subject');
            });
        }

        // Backfill from the legacy teacher_subject pivot (tenant DB) so existing
        // teacher-subject links survive the move to a JSON column.
        if (Schema::hasTable('teacher_subject')) {
            DB::table('teacher_subject')
                ->select('teacher_id', 'subject_id')
                ->orderBy('teacher_id')
                ->get()
                ->groupBy('teacher_id')
                ->each(function ($rows, $teacherId) {
                    $ids = $rows->pluck('subject_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

                    DB::table('teachers')
                        ->where('id', $teacherId)
                        ->update(['subject_ids' => json_encode($ids)]);
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('teachers') && Schema::hasColumn('teachers', 'subject_ids')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->dropColumn('subject_ids');
            });
        }
    }
};
