<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prize categories for a Sahodaya fest — "Dance Champion", "Music Champion", an overall title.
 *
 * The Sahodaya counterpart of state_prize_categories, deliberately the same shape so a trophy means
 * the same thing at both levels and the two screens read alike. What differs is who can be crowned: a
 * Sahodaya event competes School against School, so it awards individual and school titles and has no
 * Sahodaya title to give.
 *
 * Separate from `fest_event_items.category`, which is a descriptive tag. A prize category is a trophy:
 * created by an admin, given items, and crowning a winner. Folding the two together would make every
 * tag a trophy and a new trophy require re-tagging items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_prize_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('fest_events')->cascadeOnDelete();

            $table->string('code', 40);
            $table->string('name');
            $table->string('description')->nullable();

            // individual and/or school. Stored as a set: a category that crowns nothing is a mistake.
            $table->json('awards');

            // Covers every item in the event, so it needs no assignments and cannot go stale.
            $table->boolean('is_overall')->default(false);
            $table->unsignedSmallInteger('honour_count')->default(3);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'code']);
        });

        Schema::create('fest_prize_category_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prize_category_id')->constrained('fest_prize_categories')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('fest_event_items')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['prize_category_id', 'item_id']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_prize_category_items');
        Schema::dropIfExists('fest_prize_categories');
    }
};
