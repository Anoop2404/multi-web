<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic fee credit table for MCQ and Training programs (mirrors fest_fee_credits
 * but uses a polymorphic "creditable" so a single table covers multiple program types).
 * See docs/FLOW_GAP_FIX_PLAN.md Phase 1.1 for rationale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_fee_credits', function (Blueprint $table) {
            $table->id();

            // The fee aggregate record the credit belongs to (McqSchoolFee | TrainingSchoolFee)
            $table->string('creditable_type');
            $table->unsignedBigInteger('creditable_id');
            $table->index(['creditable_type', 'creditable_id']);

            // The registration that was cancelled and triggered this credit
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->index(['source_type', 'source_id']);

            $table->decimal('amount', 10, 2);
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Set when a Sahodaya admin applies the credit toward a future fee
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_fee_credits');
    }
};
