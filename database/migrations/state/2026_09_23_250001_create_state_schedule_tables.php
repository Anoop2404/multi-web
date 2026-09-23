<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 of the State Kalotsav module — when and where each item happens.
 *
 * Nothing in the State stack has carried a time before now: the pre-module workspace could record
 * marks but not say when an item was held, so clashes could not be detected, performance order had
 * nothing to order against, and the attendance sheets that are printed in that order could not be
 * produced.
 *
 * Times are stored as a date plus three times rather than timestamps because that is how a schedule
 * is actually published and argued about — "Folk Dance, 21st, report 9:30, stage at 10:00" — and a
 * timezone-bearing timestamp invites the wrong question. The expected finish is derived from the
 * item duration and participant count when it is not set by hand, which is what makes overlap
 * detection possible at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_item_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();
            $table->uuid('item_id');
            $table->string('item_code', 64)->nullable();

            $table->date('scheduled_on')->nullable();
            $table->time('reporting_at')->nullable();
            $table->time('starts_at')->nullable();
            // Set by hand when an item runs long; otherwise computed from duration x participants.
            $table->time('ends_at')->nullable();

            $table->uuid('venue_id')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            // The State decides per item whether the public sees it, so a provisional slot can be
            // arranged without publishing it.
            $table->boolean('is_public')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['state_event_id', 'item_id']);
            $table->index(['state_event_id', 'scheduled_on']);
            $table->index('venue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_item_schedules');
    }
};
