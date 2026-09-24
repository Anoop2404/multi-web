<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prize categories for a State event — "Dance Champion", "Music Champion", and an overall title.
 *
 * Separate from `FestStateProgramItem.category`, which is a fixed descriptive tag on an item. A prize
 * category is a *trophy*: it is created by the State office, items are assigned to it, and it crowns
 * a winner. The two are not the same thing, and folding them together would mean a new trophy could
 * only be created by re-tagging items and every tag would silently become a trophy.
 *
 * Many-to-many on purpose: an item counts towards "Classical Music" and towards a combined "Music
 * Overall" without being duplicated or re-tagged.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'state';
    }

    public function up(): void
    {
        Schema::connection('state')->create('state_prize_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // state_fest_events.id is a bigint, not a uuid.
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();

            $table->string('code', 40);
            $table->string('name');
            $table->string('description')->nullable();

            // Which titles this category awards: individual, school and/or sahodaya. Stored as a set
            // rather than one column each, because a category that crowns nothing is a mistake and a
            // category that crowns all three is ordinary.
            $table->json('awards');

            // The "common" category: covers every item in the event, so it needs no assignments and
            // cannot go stale when items are added.
            $table->boolean('is_overall')->default(false);

            // How many are honoured — first three by default, because a category usually gives a
            // champion and two runners-up.
            $table->unsignedSmallInteger('honour_count')->default(3);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['state_event_id', 'code']);
        });

        Schema::connection('state')->create('state_prize_category_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('prize_category_id');
            $table->uuid('item_id');
            $table->timestamps();

            $table->unique(['prize_category_id', 'item_id']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::connection('state')->dropIfExists('state_prize_category_items');
        Schema::connection('state')->dropIfExists('state_prize_categories');
    }
};
