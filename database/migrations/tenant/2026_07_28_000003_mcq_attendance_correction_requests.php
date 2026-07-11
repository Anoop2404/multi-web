<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcq_attendance_correction_requests')) {
            return;
        }

        Schema::create('mcq_attendance_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('exam_id');
            $table->foreign('exam_id')->references('id')->on('mcq_exams')->cascadeOnDelete();
            $table->unsignedBigInteger('registration_id');
            $table->foreign('registration_id')->references('id')->on('mcq_registrations')->cascadeOnDelete();

            $table->string('previous_status')->nullable();
            $table->text('previous_note')->nullable();
            $table->string('requested_status');
            $table->text('requested_note')->nullable();

            $table->unsignedBigInteger('requested_by');
            $table->string('requested_by_role')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['exam_id', 'status']);
            $table->index('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcq_attendance_correction_requests');
    }
};
