<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            // results_published_at alone can't represent "explicitly unpublished" as
            // distinct from "never individually published" -- both are just null, and
            // FestItemResultsService::isItemVisible() falls back to the event-wide
            // results_published flag whenever an item's own timestamp is null. That
            // made unpublishItem() a silent no-op on the public portal once the event
            // had already been published overall: nulling the timestamp put the item
            // right back into the "never touched, fall back to event-wide" bucket
            // instead of actually hiding it. This flag is the real, unconditional
            // "hide this item no matter what" signal.
            $table->boolean('results_hidden')->default(false)->after('results_published_at');
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            $table->dropColumn('results_hidden');
        });
    }
};
