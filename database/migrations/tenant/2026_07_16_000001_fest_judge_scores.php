<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_judge_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->foreign('item_id')->references('id')->on('fest_event_items')->cascadeOnDelete();
            $table->unsignedBigInteger('participant_id');
            $table->foreign('participant_id')->references('id')->on('fest_participants')->cascadeOnDelete();
            $table->unsignedBigInteger('judge_user_id');
            $table->enum('grade', ['A', 'A+', 'B', 'C'])->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->string('measurement_value', 50)->nullable();
            $table->string('measurement_unit', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'participant_id', 'judge_user_id'], 'fest_judge_scores_unique');
            $table->index(['event_id', 'judge_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_judge_scores');
    }
};
