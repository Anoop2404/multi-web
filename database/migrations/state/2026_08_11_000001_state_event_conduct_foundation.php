<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * State Event Conduct, Phase 1 + 2 (docs/STATE_EVENT_CONDUCT_PLAN.md) — lifecycle-gate schema
 * foundation on state_fest_events, plus the first vertical slice (attendance). Judging, marks,
 * results-publish, appeals, and certificates are later phases, not built yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('state_fest_events', function (Blueprint $table) {
            $table->boolean('results_published')->default(false)->after('status');
            $table->boolean('scoring_locked')->default(false)->after('results_published');
            // Reused directly by App\Services\Events\FestGradePointService, which is stateless
            // scoring logic keyed off $event->scoring_preset + a score — not tenant-specific.
            $table->string('scoring_preset', 40)->nullable()->after('scoring_locked');
        });

        Schema::create('state_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('state_event_id');
            $table->foreign('state_event_id')->references('id')->on('state_fest_events')->cascadeOnDelete();

            // The State catalog item (FestStateProgramItem UUID) this attendance record is
            // for — attendance is always item-scoped, same as the tenant-level system.
            $table->uuid('item_id')->nullable();
            $table->string('item_code', 64)->nullable();

            $table->unsignedBigInteger('registration_id');
            $table->foreign('registration_id')->references('id')->on('state_fest_registrations')->cascadeOnDelete();
            $table->unsignedBigInteger('participant_id');
            $table->foreign('participant_id')->references('id')->on('state_fest_participants')->cascadeOnDelete();

            $table->string('status', 12)->default('absent'); // present | absent
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();

            // One attendance record per participant per item — re-marking updates it in place
            // rather than creating a duplicate (mirrors FestAttendance's updateOrCreate usage).
            $table->unique(['item_id', 'participant_id'], 'state_attendance_item_participant_unique');
            $table->index(['state_event_id', 'item_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_attendances');

        Schema::table('state_fest_events', function (Blueprint $table) {
            $table->dropColumn(['results_published', 'scoring_locked', 'scoring_preset']);
        });
    }
};
