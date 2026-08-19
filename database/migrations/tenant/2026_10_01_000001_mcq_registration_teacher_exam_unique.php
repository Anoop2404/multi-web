<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcq_registrations')) {
            return;
        }

        $duplicateGroups = DB::table('mcq_registrations')
            ->select('exam_id', 'teacher_id')
            ->whereNotNull('teacher_id')
            ->groupBy('exam_id', 'teacher_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $rows = DB::table('mcq_registrations')
                ->where('exam_id', $group->exam_id)
                ->where('teacher_id', $group->teacher_id)
                ->orderBy('id')
                ->pluck('id');

            $markedId = Schema::hasTable('mcq_marks')
                ? DB::table('mcq_marks')->whereIn('registration_id', $rows)->orderBy('registration_id')->value('registration_id')
                : null;

            $keepId = $markedId ?? $rows->first();
            $dropIds = $rows->reject(fn ($id) => $id === $keepId);

            if ($dropIds->isNotEmpty()) {
                DB::table('mcq_registrations')->whereIn('id', $dropIds)->delete();
            }
        }

        if (! Schema::hasIndex('mcq_registrations', 'mcq_registrations_exam_id_teacher_id_unique')) {
            Schema::table('mcq_registrations', function (Blueprint $table) {
                $table->unique(['exam_id', 'teacher_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mcq_registrations') && Schema::hasIndex('mcq_registrations', 'mcq_registrations_exam_id_teacher_id_unique')) {
            Schema::table('mcq_registrations', function (Blueprint $table) {
                $table->dropUnique(['exam_id', 'teacher_id']);
            });
        }
    }
};
