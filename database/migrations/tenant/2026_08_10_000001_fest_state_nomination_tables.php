<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-04 (docs/STATE_KALOTSAV_MASTER_IMPLEMENTATION_PLAN.md §27) — persistence for the
 * manual Sahodaya-to-State nomination workflow. Lives on the Sahodaya tenant connection
 * (same as FestEvent/FestMark/FestQualification) because nomination happens at the
 * Sahodaya, before anything is submitted to State. One batch per (state_program_id,
 * hub_event_id) — the hub is the real 'sahodaya' FestEvent, or its finale if partitioned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_state_nomination_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('state_program_id');
            $table->unsignedBigInteger('hub_event_id');
            $table->unsignedBigInteger('maker_id')->nullable();
            $table->unsignedBigInteger('checker_id')->nullable();
            // candidate_pool_building -> selection_in_progress -> ready_for_certification
            // -> certified -> submitted_to_state (§27.4)
            $table->string('status', 30)->default('candidate_pool_building');
            $table->timestamp('certified_at')->nullable();
            $table->text('certification_notes')->nullable();
            $table->timestamps();

            $table->unique(['state_program_id', 'hub_event_id']);
            $table->index(['hub_event_id', 'status']);
        });

        Schema::create('fest_state_nomination_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('fest_state_nomination_batches')->cascadeOnDelete();

            // The State catalog item (FestStateProgramItem UUID via FestEventItem.state_program_item_id)
            // — same canonical id FestStateQualifierPayloadBuilder now sends as item_id. Nullable:
            // a selection can be created from a bare mark_id and backfilled from the candidate pool
            // (see FestStateNominationService::backfillFromCandidate()); required only by the time
            // a batch is certified (enforced in the service, not the schema).
            $table->uuid('item_id')->nullable();
            $table->string('item_code', 64)->nullable();
            $table->string('item_title')->nullable();

            // Source candidate — the certified mark this selection was built from.
            $table->unsignedBigInteger('source_event_id')->nullable();
            $table->unsignedBigInteger('mark_id')->nullable();
            $table->unsignedBigInteger('registration_id')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('partition_key', 64)->nullable();

            $table->string('school_id')->nullable();
            $table->string('school_name')->nullable();
            $table->string('student_name')->nullable();
            $table->string('class_name')->nullable();
            $table->unsignedTinyInteger('source_position')->nullable();
            $table->string('grade', 8)->nullable();
            $table->decimal('score', 8, 2)->nullable();

            // 'primary' enters the State payload; 'reserve' is kept for withdrawal replacement only.
            $table->string('nomination_type', 12);
            // Ordering among reserves (1 = first replacement); always 1 for primary.
            $table->unsignedTinyInteger('priority_order')->default(1);
            // Required when a higher-ranked eligible candidate in the same item was passed over.
            $table->text('skip_reason')->nullable();

            // selected -> withdrawn -> replaced (§10.1 entry states, reused here pre-submission)
            $table->string('status', 20)->default('selected');
            $table->unsignedBigInteger('selected_by')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'item_id', 'nomination_type']);
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_state_nomination_selections');
        Schema::dropIfExists('fest_state_nomination_batches');
    }
};
