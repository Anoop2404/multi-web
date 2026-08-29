<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A school's self-selected student-count-strength band for the 'kalolsavam_composite'/
 * 'sports_composite' billing models' optional school_fee_mode='student_count_slab' -- see
 * FestSchoolFeeSlabSelectionService. Mirrors fest_school_phase_region_selections' shape
 * (lock-on-first-pick, audited admin override), but scoped to the whole event rather than
 * one phase, since the school registration fee is billed once per event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_school_fee_slab_selections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('school_id');
            $table->unsignedInteger('min_count');
            $table->unsignedInteger('max_count')->nullable();
            $table->decimal('amount', 10, 2);
            $table->timestamp('selected_at')->nullable();
            $table->unsignedBigInteger('selected_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->text('change_reason')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'school_id']);
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_school_fee_slab_selections');
    }
};
