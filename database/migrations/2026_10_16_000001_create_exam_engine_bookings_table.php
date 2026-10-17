<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide capacity bookings for online exams on the exam engine
 * (docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md §6, §9): each Sahodaya schedules its own exams,
 * so overlapping live windows are summed here to refuse overbooking and to drive scale-out.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exam_engine_bookings')) {
            return;
        }

        Schema::create('exam_engine_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('exam_id');
            $table->string('title');
            $table->timestamp('live_starts_at');   // waiting room opens
            $table->timestamp('live_ends_at');     // last possible deadline + grace
            $table->unsignedInteger('expected_count')->default(0);
            $table->string('entry_mode', 20)->default('either');
            $table->string('status', 20)->default('booked'); // booked | armed | closed | cancelled
            $table->timestamps();

            $table->unique(['tenant_id', 'exam_id']);
            $table->index(['status', 'live_starts_at', 'live_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_engine_bookings');
    }
};
