<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_year_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('school_id');
            $table->string('academic_year', 10);
            $table->enum('full_records_status', ['not_applicable', 'pending', 'submitted', 'approved', 'rejected'])->default('not_applicable');
            $table->text('full_records_rejection_reason')->nullable();
            $table->enum('counts_status', ['not_applicable', 'pending', 'submitted', 'approved', 'rejected'])->default('not_applicable');
            $table->text('counts_rejection_reason')->nullable();
            $table->enum('teacher_status', ['not_applicable', 'pending', 'submitted', 'approved', 'rejected'])->default('not_applicable');
            $table->text('teacher_rejection_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'academic_year']);
        });

        Schema::create('submission_students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_year_submission_id');
            $table->foreign('school_year_submission_id')->references('id')->on('school_year_submissions')->cascadeOnDelete();
            $table->string('name');
            $table->string('class', 20);
            $table->string('section', 10)->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('dob')->nullable();
            $table->string('image_path')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('school_year_student_counts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_year_submission_id');
            $table->foreign('school_year_submission_id')->references('id')->on('school_year_submissions')->cascadeOnDelete();
            $table->unsignedBigInteger('class_category_id');
            $table->unsignedInteger('male_count')->default(0);
            $table->unsignedInteger('female_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->timestamps();

            $table->unique(['school_year_submission_id', 'class_category_id'], 'submission_category_unique');
        });

        Schema::create('submission_teachers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_year_submission_id');
            $table->foreign('school_year_submission_id')->references('id')->on('school_year_submissions')->cascadeOnDelete();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->unsignedBigInteger('teaching_type_id')->nullable();
            $table->timestamps();
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->string('school_id');
            $table->string('academic_year', 10);
            $table->string('reg_no')->unique();
            $table->decimal('membership_fee_amount', 10, 2)->nullable();
            $table->enum('registration_status', [
                'data_pending', 'data_rejected', 'payment_pending',
                'payment_submitted', 'payment_rejected', 'completed',
            ])->default('data_pending');
            $table->unsignedBigInteger('school_year_submission_id')->nullable();
            $table->foreign('school_year_submission_id')->references('id')->on('school_year_submissions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'academic_year']);
        });

        Schema::create('membership_payments', function (Blueprint $table) {
            $table->id();
            $table->string('school_id');
            $table->string('academic_year', 10);
            $table->unsignedBigInteger('registration_id')->nullable();
            $table->foreign('registration_id')->references('id')->on('registrations')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_proof_path');
            $table->string('payment_method')->nullable();
            $table->string('transaction_ref')->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->enum('status', ['submitted', 'verified', 'rejected'])->default('submitted');
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('verified_by_user_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_payments');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('submission_teachers');
        Schema::dropIfExists('school_year_student_counts');
        Schema::dropIfExists('submission_students');
        Schema::dropIfExists('school_year_submissions');
    }
};
