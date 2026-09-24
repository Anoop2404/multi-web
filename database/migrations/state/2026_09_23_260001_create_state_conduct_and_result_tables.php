<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phases 6 and 7 of the State Kalotsav module — conduct corrections and item-level results.
 *
 * Two gaps this closes.
 *
 * Publication was event-wide: state_fest_events.results_published is a single flag, so the State
 * could publish everything or nothing. A Kalotsavam publishes item by item over three days, and an
 * item whose result is under appeal has to be held back without freezing the other hundred and
 * thirty-nine. state_item_results carries that per item, with provisional and published as distinct
 * states — a provisional result is visible to the office and not to the public.
 *
 * Corrections had no record at all. Attendance changed from absent to present, or a mark edited
 * after entry, is exactly what a disputed result turns on, so both are appended here with who, what
 * it was, what it became and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_item_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();
            $table->uuid('item_id');
            $table->string('item_code', 64)->nullable();

            // draft | provisional | published | locked
            // provisional is computed and visible internally; published is visible publicly; locked
            // is final and refuses recomputation.
            $table->string('status', 20)->default('draft');
            $table->timestamp('computed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            // Recorded when positions are computed: how many were ranked, and whether any tie had to
            // be resolved, so a later query does not have to re-derive it.
            $table->unsignedSmallInteger('ranked_count')->default(0);
            $table->unsignedSmallInteger('tie_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['state_event_id', 'item_id']);
            $table->index(['state_event_id', 'status']);
        });

        Schema::create('state_conduct_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();
            // attendance | mark | result
            $table->string('kind', 20);
            $table->unsignedBigInteger('registration_id')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->uuid('item_id')->nullable();
            $table->string('item_code', 64)->nullable();

            $table->string('value_from')->nullable();
            $table->string('value_to')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['state_event_id', 'kind']);
            $table->index('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_conduct_audits');
        Schema::dropIfExists('state_item_results');
    }
};
