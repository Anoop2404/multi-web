<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal proctoring: client-side tab-switch / window-blur / fullscreen-exit
 * detection during an online MCQ exam session, logged here for Sahodaya staff
 * to review after the fact. Detect-and-log only -- no auto-submit, no
 * penalty is applied automatically from this table.
 *
 * Modeled as a normalized child table (one row per event) to match this
 * codebase's existing event-log convention (see audit_logs / the
 * mcq_attendance_correction_requests child-table pattern) rather than an
 * ever-growing JSON blob on mcq_registrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcq_registrations') || Schema::hasTable('mcq_proctor_events')) {
            return;
        }

        Schema::create('mcq_proctor_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->foreign('registration_id')->references('id')->on('mcq_registrations')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['registration_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcq_proctor_events');
    }
};
