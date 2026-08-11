<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * State Event Conduct, Phase 3 + 4 (docs/STATE_EVENT_CONDUCT_PLAN.md) — judge assignment and
 * per-judge scoring. Mirrors FestJudgeAssignment/FestJudgeScore: each assigned judge submits
 * independently; once every assigned judge for an item has scored a participant, the average
 * is written into state_fest_marks as the canonical mark (StateJudgeScoreService::
 * syncAggregatedMark()) — this *is* the "double verification" step, not a separate
 * reconciliation pass, same as the tenant-level system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_judge_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('state_event_id');
            $table->foreign('state_event_id')->references('id')->on('state_fest_events')->cascadeOnDelete();
            $table->uuid('item_id'); // FestStateProgramItem catalog UUID, matches state_fest_registrations.item_id
            $table->string('item_code', 64)->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['state_event_id', 'item_id', 'user_id'], 'state_judge_assignment_unique');
            $table->index(['state_event_id', 'item_id']);
        });

        Schema::create('state_judge_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('state_event_id');
            $table->foreign('state_event_id')->references('id')->on('state_fest_events')->cascadeOnDelete();
            $table->uuid('item_id');
            $table->string('item_code', 64)->nullable();
            $table->unsignedBigInteger('participant_id');
            $table->foreign('participant_id')->references('id')->on('state_fest_participants')->cascadeOnDelete();
            $table->unsignedBigInteger('judge_user_id');
            $table->decimal('score', 8, 2)->nullable();
            $table->string('grade', 8)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'participant_id', 'judge_user_id'], 'state_judge_score_unique');
            $table->index(['state_event_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_judge_scores');
        Schema::dropIfExists('state_judge_assignments');
    }
};
