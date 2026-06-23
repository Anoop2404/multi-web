<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcq_exams', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->string('title');
            $table->enum('exam_type', ['practice', 'assessment', 'competitive'])->default('assessment');
            $table->enum('conductor_level', ['state', 'sahodaya', 'school'])->default('sahodaya');
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedSmallInteger('total_questions')->default(0);
            $table->unsignedSmallInteger('pass_mark')->nullable();
            $table->enum('status', ['draft', 'published', 'ongoing', 'completed', 'cancelled'])->default('draft');
            $table->json('settings_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('mcq_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id');
            $table->foreign('exam_id')->references('id')->on('mcq_exams')->cascadeOnDelete();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->string('school_id');
            $table->enum('status', ['registered', 'started', 'submitted', 'absent'])->default('registered');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['exam_id', 'school_id']);
        });

        Schema::create('mcq_marks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->foreign('registration_id')->references('id')->on('mcq_registrations')->cascadeOnDelete();
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('wrong_count')->default(0);
            $table->unsignedSmallInteger('unanswered_count')->default(0);
            $table->decimal('score', 8, 2)->default(0);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->enum('grade', ['A', 'B', 'C', 'D', 'F'])->nullable();
            $table->json('answers_json')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcq_marks');
        Schema::dropIfExists('mcq_registrations');
        Schema::dropIfExists('mcq_exams');
    }
};
