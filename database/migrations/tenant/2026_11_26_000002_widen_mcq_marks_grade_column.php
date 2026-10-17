<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mcq_marks.grade was created as enum('A','B','C','D','F'), but McqGradeService's bands
     * (default and per-exam grade masters) produce labels like "A+", so saving any mark that
     * lands in such a band failed with "CHECK constraint failed: grade". Same fix and reasoning
     * as 2026_10_04_000002_widen_fest_grade_columns_for_custom_labels.php: on Postgres the
     * column is already VARCHAR with a named CHECK constraint, so drop the constraint; on SQLite
     * widen the column with Schema::change().
     */
    public function up(): void
    {
        if (! Schema::hasTable('mcq_marks')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE mcq_marks DROP CONSTRAINT IF EXISTS mcq_marks_grade_check');

            return;
        }

        Schema::table('mcq_marks', fn (Blueprint $table) => $table->string('grade', 40)->nullable()->change());
    }

    public function down(): void
    {
        // Irreversible — re-adding the old constraint would reject existing "A+"-style grades.
    }
};
