<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 of the State Kalotsav module — catering and duty rosters.
 *
 * Counting meals by Sahodaya rather than by participant is deliberate and matches how a State
 * Kalotsav actually caters: the kitchen is told "Kottayam, 180 lunches" because the contingent eats
 * together and arrives together. Per-participant coupons exist in theory and are abandoned by the
 * second day everywhere.
 *
 * Entitlement and issue are separate numbers on purpose. Entitlement is computed from approved
 * entries, issue is what the counter actually handed over, and the gap between them is the only
 * figure anyone argues about afterwards — so it is stored, not derived.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'state';
    }

    public function up(): void
    {
        Schema::connection('state')->create('state_meal_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // state_fest_events.id is a bigint, not a uuid.
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();

            $table->date('served_on');
            // breakfast / lunch / dinner / refreshment — free text rather than an enum, because a
            // three-day fest invents sessions ("late dinner for the drama crew") that no enum has.
            $table->string('session', 40);
            $table->string('menu')->nullable();
            $table->uuid('venue_id')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['state_event_id', 'served_on', 'session']);
            $table->index('venue_id');
        });

        Schema::connection('state')->create('state_meal_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('meal_session_id');
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('sahodaya_id')->nullable()->index();
            $table->string('sahodaya_name')->nullable();

            $table->unsignedInteger('entitled_count')->default(0);
            $table->unsignedInteger('issued_count')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('issued_by_user_id')->nullable();
            $table->string('issued_by_name')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            // One row per Sahodaya per session: issuing twice should update the count, not add a
            // second row that nobody reconciles.
            $table->unique(['meal_session_id', 'sahodaya_id']);
            $table->index(['state_event_id', 'sahodaya_id']);
        });

        Schema::connection('state')->create('state_staff_duties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();
            $table->uuid('staff_id');

            $table->date('duty_on');
            // Named sessions rather than start/end times: a volunteer roster is planned as
            // "morning at Stage 2", and precise times would be fiction.
            $table->string('session', 40);
            $table->uuid('venue_id')->nullable();
            $table->string('duty', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'duty_on', 'session']);
            $table->index(['state_event_id', 'duty_on']);
            $table->index('venue_id');
        });
    }

    public function down(): void
    {
        Schema::connection('state')->dropIfExists('state_staff_duties');
        Schema::connection('state')->dropIfExists('state_meal_allocations');
        Schema::connection('state')->dropIfExists('state_meal_sessions');
    }
};
