<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Now that counts key off the school's own class list (school_class_id, see
        // submission_school_class_unique), class_category_id is only kept in sync for
        // backward-compatible reporting and is no longer unique per submission — several
        // classes commonly share the same category (e.g. Class 1 and Class 2 both "LP"),
        // which violated this legacy constraint as soon as class_category_id was populated
        // for more than one row per submission.
        Schema::table('school_year_student_counts', function (Blueprint $table) {
            $table->dropUnique('submission_category_unique');
        });
    }

    public function down(): void
    {
        Schema::table('school_year_student_counts', function (Blueprint $table) {
            $table->unique(['school_year_submission_id', 'class_category_id'], 'submission_category_unique');
        });
    }
};
