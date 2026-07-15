<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_year_student_counts', function (Blueprint $table) {
            // Counts now key off the school's own class list (school_classes) instead of the
            // shared class-category master, so schools can enter counts per actual class
            // (e.g. Class 1, Class 2 ...) rather than a coarse category grouping.
            // class_category_id is kept (nullable) and auto-populated from the class's own
            // category for backward-compatible category-level reporting.
            $table->unsignedBigInteger('class_category_id')->nullable()->change();
            $table->unsignedBigInteger('school_class_id')->nullable()->after('class_category_id');
            $table->foreign('school_class_id')->references('id')->on('school_classes')->nullOnDelete();
            $table->unique(['school_year_submission_id', 'school_class_id'], 'submission_school_class_unique');
        });
    }

    public function down(): void
    {
        Schema::table('school_year_student_counts', function (Blueprint $table) {
            $table->dropUnique('submission_school_class_unique');
            $table->dropForeign(['school_class_id']);
            $table->dropColumn('school_class_id');
        });
    }
};
