<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (item, participant, judge) — the judge's own paper subtotal
 * for that participant, typed in online after the physical sheets are
 * collected. FestMark.score is the sum across judges for the item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_mark_judge_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('fest_event_items')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('fest_participants')->cascadeOnDelete();
            $table->unsignedTinyInteger('judge_number');
            $table->decimal('score', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'participant_id', 'judge_number'], 'fest_mark_judge_scores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_mark_judge_scores');
    }
};
