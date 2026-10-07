<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-school chest number ranges for sports events.
 *
 * Each school participating in a FestEvent can be assigned a fixed
 * chest_no_start (and optional chest_no_end cap) so every student from
 * that school always gets a number from their school's reserved block.
 *
 * When auto-assigning, FestNumberingService picks the lowest free number
 * at or above chest_no_start (and at or below chest_no_end, if set).
 * If the school's block is exhausted the service falls back to the
 * event-wide pool and logs a warning.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_school_chest_ranges')) {
            Schema::create('fest_school_chest_ranges', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
                $table->string('school_id');
                $table->unsignedInteger('chest_no_start');
                // Optional upper bound; NULL means open-ended (only the start is fixed).
                $table->unsignedInteger('chest_no_end')->nullable();
                $table->timestamps();

                $table->unique(['event_id', 'school_id'], 'fest_school_chest_ranges_event_school_unique');
                $table->index('event_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_school_chest_ranges');
    }
};
