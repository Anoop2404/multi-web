<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// WF-04 fix (functional audit, 2026-08-11/12): mcq_registrations previously
// had only a non-unique index on (exam_id, school_id) — nothing stopped the
// same student from ending up with two registration rows for the same exam
// (McqRegistrationController::store() did a plain check-then-create with no
// transaction/lock). A unique index on (exam_id, student_id) makes that
// impossible at the database level going forward, matching the pattern
// training_registrations already uses correctly
// (unique(['program_id','teacher_id']), see 2026_06_22_000013_phase15_training.php).
// NULL student_id rows (teacher-only registrations, if any) are exempt from a
// standard SQL unique index since NULLs are not considered equal to each
// other, so this only constrains genuine student double-registrations.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcq_registrations')) {
            return;
        }

        // Best-effort de-duplication before the constraint is added, in case
        // any duplicate (exam_id, student_id) rows already exist. For each
        // duplicate group, keep the row that already has a recorded mark (the
        // "richest" record — losing a graded row would be worse than losing
        // an empty duplicate), falling back to the earliest row by id.
        $duplicateGroups = DB::table('mcq_registrations')
            ->select('exam_id', 'student_id')
            ->whereNotNull('student_id')
            ->groupBy('exam_id', 'student_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $rows = DB::table('mcq_registrations')
                ->where('exam_id', $group->exam_id)
                ->where('student_id', $group->student_id)
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

        if (! Schema::hasIndex('mcq_registrations', 'mcq_registrations_exam_id_student_id_unique')) {
            Schema::table('mcq_registrations', function (Blueprint $table) {
                $table->unique(['exam_id', 'student_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mcq_registrations') && Schema::hasIndex('mcq_registrations', 'mcq_registrations_exam_id_student_id_unique')) {
            Schema::table('mcq_registrations', function (Blueprint $table) {
                $table->dropUnique(['exam_id', 'student_id']);
            });
        }
    }
};
