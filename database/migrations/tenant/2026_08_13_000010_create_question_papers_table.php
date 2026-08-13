<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_papers', function (Blueprint $table) {
            $table->id();
            $table->string('school_id');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->string('class_name', 80);
            // Subjects are central master data, so this cannot be a tenant DB foreign key.
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name', 120);
            $table->string('academic_year', 20);
            $table->string('title');
            $table->string('exam_name', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('storage_disk', 40);
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
            $table->foreign('school_class_id')->references('id')->on('school_classes')->nullOnDelete();
            $table->index(['school_id', 'school_class_id', 'subject_id'], 'question_papers_school_class_subject_index');
            $table->index(['teacher_id', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_papers');
    }
};
