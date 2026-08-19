<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A named, Sahodaya-wide (not per-event) reusable mark-entry rubric — e.g. "Standard
        // On-Stage Solo (Content/Presentation)" — so items that share an identical scoring
        // structure don't each need it re-typed by hand. Applying a template to an item
        // copies its criteria rows into that item's own FestMarkCriterion set (same
        // replace-and-recreate shape as FestMarkCriteriaService::copyCriteriaFromItem());
        // the copy is independent afterwards, so deleting a template never affects items
        // that already had it applied. Mirrors fest_class_category_schemes' shape.
        if (! Schema::hasTable('fest_scoring_rubric_templates')) {
            Schema::create('fest_scoring_rubric_templates', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'name']);
            });
        }

        if (! Schema::hasTable('fest_scoring_rubric_template_criteria')) {
            Schema::create('fest_scoring_rubric_template_criteria', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('template_id');
                $table->string('label');
                $table->decimal('max_score', 8, 2)->default(10);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('template_id')->references('id')->on('fest_scoring_rubric_templates')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_scoring_rubric_template_criteria');
        Schema::dropIfExists('fest_scoring_rubric_templates');
    }
};
