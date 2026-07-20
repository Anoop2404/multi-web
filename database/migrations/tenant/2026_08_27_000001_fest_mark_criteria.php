<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable multi-criteria / judge-column mark entry.
 *
 * Deliberately a NEW pair of tables rather than reusing
 * `fest_event_items.criteria_json` — that column is already overloaded by
 * FestTeamSquadRules for team/squad composition rules (min/max playing,
 * subs, squad size), so repurposing it for judge scoring criteria would
 * silently corrupt squad-rule data for team items. See
 * FestTeamSquadRules::mergeIntoItem().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_mark_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('fest_events')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('fest_event_items')->cascadeOnDelete();
            $table->string('label');
            $table->decimal('max_score', 8, 2)->default(10);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['item_id', 'sort_order']);
        });

        Schema::create('fest_mark_criterion_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterion_id')->constrained('fest_mark_criteria')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('fest_event_items')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('fest_participants')->cascadeOnDelete();
            $table->decimal('score', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['criterion_id', 'participant_id'], 'fest_mark_criterion_scores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_mark_criterion_scores');
        Schema::dropIfExists('fest_mark_criteria');
    }
};
