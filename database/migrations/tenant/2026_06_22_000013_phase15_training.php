<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('conductor_level', ['state', 'sahodaya', 'school'])->default('sahodaya');
            $table->date('registration_open')->nullable();
            $table->date('registration_close')->nullable();
            $table->unsignedSmallInteger('max_participants')->nullable();
            $table->enum('status', ['draft', 'published', 'ongoing', 'completed', 'cancelled'])->default('draft');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('program_id');
            $table->foreign('program_id')->references('id')->on('training_programs')->cascadeOnDelete();
            $table->string('title');
            $table->dateTime('scheduled_at')->nullable();
            $table->string('venue')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('training_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('program_id');
            $table->foreign('program_id')->references('id')->on('training_programs')->cascadeOnDelete();
            $table->unsignedBigInteger('teacher_id');
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
            $table->string('school_id');
            $table->enum('status', ['registered', 'confirmed', 'cancelled', 'completed'])->default('registered');
            $table->unsignedBigInteger('fee_receipt_id')->nullable();
            $table->foreign('fee_receipt_id')->references('id')->on('fee_receipts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['program_id', 'teacher_id']);
        });

        Schema::create('training_attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->foreign('session_id')->references('id')->on('training_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('registration_id');
            $table->foreign('registration_id')->references('id')->on('training_registrations')->cascadeOnDelete();
            $table->enum('status', ['present', 'absent'])->default('present');
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_attendance');
        Schema::dropIfExists('training_registrations');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_programs');
    }
};
