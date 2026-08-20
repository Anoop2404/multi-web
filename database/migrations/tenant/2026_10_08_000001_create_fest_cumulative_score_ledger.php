<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_score_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('root_event_id')->constrained('fest_events')->cascadeOnDelete();
            $table->foreignId('phase_id')->constrained('fest_event_phases')->cascadeOnDelete();
            $table->foreignId('source_event_id')->constrained('fest_events')->cascadeOnDelete();
            $table->string('school_id');
            $table->string('source_category_key', 100)->default('overall');
            $table->string('championship_category_key', 100)->default('overall');
            $table->unsignedInteger('version');
            $table->decimal('points', 12, 2)->default(0);
            $table->timestamp('invalidated_at')->nullable();
            $table->unsignedBigInteger('invalidated_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['phase_id', 'source_event_id', 'school_id', 'championship_category_key', 'version'],
                'fest_score_contribution_unique'
            );
            $table->index(['root_event_id', 'phase_id', 'version'], 'fest_score_contribution_lookup');
        });

        Schema::create('fest_phase_score_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('root_event_id')->constrained('fest_events')->cascadeOnDelete();
            $table->foreignId('phase_id')->constrained('fest_event_phases')->cascadeOnDelete();
            $table->string('school_id');
            $table->string('championship_category_key', 100)->default('overall');
            $table->unsignedInteger('version');
            $table->decimal('opening_points', 12, 2)->default(0);
            $table->decimal('current_points', 12, 2)->default(0);
            $table->decimal('closing_points', 12, 2)->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->timestamp('locked_at');
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->text('correction_reason')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->unsignedBigInteger('invalidated_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['phase_id', 'school_id', 'championship_category_key', 'version'],
                'fest_phase_score_snapshot_unique'
            );
            $table->index(
                ['root_event_id', 'phase_id', 'championship_category_key', 'version'],
                'fest_phase_score_snapshot_public_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_phase_score_snapshots');
        Schema::dropIfExists('fest_score_contributions');
    }
};
