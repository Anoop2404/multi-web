<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->foreign('item_id')->references('id')->on('fest_event_items')->cascadeOnDelete();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->foreign('participant_id')->references('id')->on('fest_participants')->nullOnDelete();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('stage')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'participant_id']);
            $table->index(['event_id', 'scheduled_at']);
        });

        Schema::create('fest_judge_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->foreign('item_id')->references('id')->on('fest_event_items')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['item_id', 'user_id']);
            $table->index(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_judge_assignments');
        Schema::dropIfExists('fest_schedules');
    }
};
