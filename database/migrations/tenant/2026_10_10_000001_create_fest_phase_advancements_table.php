<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_phase_advancements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('root_event_id')->constrained('fest_events')->cascadeOnDelete();
            $table->foreignId('from_phase_id')->constrained('fest_event_phases')->cascadeOnDelete();
            $table->foreignId('to_phase_id')->constrained('fest_event_phases')->cascadeOnDelete();
            $table->foreignId('from_item_id')->constrained('fest_event_items')->cascadeOnDelete();
            $table->foreignId('to_item_id')->constrained('fest_event_items')->cascadeOnDelete();
            $table->foreignId('from_registration_id')->constrained('fest_registrations')->cascadeOnDelete();
            $table->foreignId('target_registration_id')->nullable()->constrained('fest_registrations')->nullOnDelete();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('advanced_by')->nullable();
            $table->timestamp('advanced_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->unsignedBigInteger('withdrawn_by')->nullable();
            $table->timestamps();

            // One live (non-withdrawn) advancement per source registration per target phase --
            // re-advancing after a withdraw is allowed (new row), re-advancing while still
            // live is a no-op the service treats as idempotent, not a duplicate.
            $table->unique(['from_registration_id', 'to_phase_id', 'withdrawn_at'], 'fest_phase_advancement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_phase_advancements');
    }
};
