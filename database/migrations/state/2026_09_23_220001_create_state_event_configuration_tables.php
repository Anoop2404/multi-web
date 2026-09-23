<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 of the State Kalotsav module — venues, stages and event staff.
 *
 * Neither existed on the State side at all: the thin pre-module workspace had judges and marks but
 * nowhere to say where an item is held or who is running it. Phase 5 cannot schedule anything
 * without venues, and Phase 6 cannot route attendance or mark entry to an operator without staff.
 *
 * A stage belongs to a venue (parent_id self-reference) rather than living in its own table: a
 * venue, a stage inside it, a green room and a reporting desk are all "a place with a capacity and
 * someone responsible", and the schedule cares only about which place an item is at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_venues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // state_fest_events.id is an auto-increment integer, not a uuid — matching it matters,
            // or Postgres refuses the comparison outright rather than just failing to join.
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();
            $table->uuid('parent_id')->nullable();

            $table->string('name');
            $table->string('code', 40)->nullable();
            // venue | stage | room | green_room | reporting — what the place is for, which decides
            // where it can appear (an item is scheduled at a stage, participants report to a desk).
            $table->string('kind', 20)->default('venue');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('address')->nullable();
            $table->text('directions')->nullable();
            $table->string('officer_name')->nullable();
            $table->string('officer_phone', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['state_event_id', 'kind']);
            $table->index('parent_id');
        });

        Schema::create('state_event_staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();

            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            // The State role they are doing here, which is not necessarily a login: many event staff
            // are volunteers for three days and never get an account.
            $table->string('role', 40);
            $table->uuid('venue_id')->nullable();
            // Set only when this person also has a platform account, so a stage manager who does
            // have a login can be tied to it without forcing one on everybody else.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['state_event_id', 'role']);
            $table->index('venue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_event_staff');
        Schema::dropIfExists('state_venues');
    }
};
