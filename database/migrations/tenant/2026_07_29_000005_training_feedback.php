<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_feedback')) {
            return;
        }

        Schema::create('training_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('program_id');
            $table->foreign('program_id')->references('id')->on('training_programs')->cascadeOnDelete();
            $table->unsignedBigInteger('registration_id');
            $table->foreign('registration_id')->references('id')->on('training_registrations')->cascadeOnDelete();
            $table->unique('registration_id');
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->foreign('teacher_id')->references('id')->on('teachers')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comments')->nullable();
            $table->unsignedTinyInteger('content_rating')->nullable();
            $table->unsignedTinyInteger('trainer_rating')->nullable();
            $table->unsignedTinyInteger('venue_rating')->nullable();
            $table->string('status')->default('submitted');
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['program_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_feedback');
    }
};
