<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcq_question_banks', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id');
            $table->string('school_id');
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->string('subject', 120);
            $table->string('class_group', 20)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])->default('active');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['sahodaya_id', 'school_id']);
            $table->index(['teacher_id', 'status']);
        });

        Schema::create('mcq_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_id');
            $table->foreign('bank_id')->references('id')->on('mcq_question_banks')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('body_text')->nullable();
            $table->string('document_path')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('mcq_exam_question_banks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id');
            $table->foreign('exam_id')->references('id')->on('mcq_exams')->cascadeOnDelete();
            $table->unsignedBigInteger('bank_id');
            $table->foreign('bank_id')->references('id')->on('mcq_question_banks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'bank_id']);
        });

        Schema::create('mcq_exam_staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id');
            $table->foreign('exam_id')->references('id')->on('mcq_exams')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['controller', 'staff'])->default('staff');
            $table->timestamps();

            $table->unique(['exam_id', 'user_id']);
        });

        Schema::table('mcq_exams', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_exams', 'venue')) {
                $table->string('venue')->nullable()->after('scheduled_at');
            }
            if (! Schema::hasColumn('mcq_exams', 'hall_instructions')) {
                $table->text('hall_instructions')->nullable()->after('venue');
            }
            if (! Schema::hasColumn('mcq_exams', 'next_hall_ticket_no')) {
                $table->unsignedInteger('next_hall_ticket_no')->default(1)->after('hall_instructions');
            }
        });

        Schema::table('mcq_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_registrations', 'hall_ticket_no')) {
                $table->string('hall_ticket_no', 30)->nullable()->after('school_id');
            }
            if (! Schema::hasColumn('mcq_registrations', 'hall_room')) {
                $table->string('hall_room', 80)->nullable()->after('hall_ticket_no');
            }
            if (! Schema::hasColumn('mcq_registrations', 'seat_no')) {
                $table->string('seat_no', 20)->nullable()->after('hall_room');
            }
            if (! Schema::hasColumn('mcq_registrations', 'attendance_status')) {
                $table->enum('attendance_status', ['pending', 'present', 'absent'])->default('pending')->after('status');
            }
            if (! Schema::hasColumn('mcq_registrations', 'attendance_marked_at')) {
                $table->timestamp('attendance_marked_at')->nullable()->after('attendance_status');
            }
            if (! Schema::hasColumn('mcq_registrations', 'attendance_marked_by')) {
                $table->unsignedBigInteger('attendance_marked_by')->nullable()->after('attendance_marked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mcq_registrations', function (Blueprint $table) {
            foreach (['attendance_marked_by', 'attendance_marked_at', 'attendance_status', 'seat_no', 'hall_room', 'hall_ticket_no'] as $col) {
                if (Schema::hasColumn('mcq_registrations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('mcq_exams', function (Blueprint $table) {
            foreach (['next_hall_ticket_no', 'hall_instructions', 'venue'] as $col) {
                if (Schema::hasColumn('mcq_exams', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('mcq_exam_staff');
        Schema::dropIfExists('mcq_exam_question_banks');
        Schema::dropIfExists('mcq_questions');
        Schema::dropIfExists('mcq_question_banks');
    }
};
