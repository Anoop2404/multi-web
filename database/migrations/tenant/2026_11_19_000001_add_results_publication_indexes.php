<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These indexes cover the three hot-path filter shapes introduced by the
        // event-wide results_published flag, individual results_published_at, and
        // the "recent winners" + "results standings" queries described in the
        // production monitoring audit.

        // 1. fest_event_items(event_id, results_published_at, results_hidden) — the
        //    whereHas('item', fn($q) => $q->whereNotNull('results_published_at')
        //    ->where('results_hidden', false)) subquery runs on every anonymous
        //    scoreboard/results/TV/event-home request. Without event_id as the
        //    leftmost column, Postgres cannot use the index for the correlated
        //    subquery scan inside a WHERE IN (...) — it falls back to a sequential
        //    scan of the entire table for every item_id check.
        $this->addIndex('fest_event_items', ['event_id', 'results_published_at', 'results_hidden'], 'fest_event_items_publish_idx');

        // 2. fest_marks(event_id, position, updated_at) — the recent-winners queries
        //    on the event home and scoreboard filter by event_id + position IN (1,2,3)
        //    and order by updated_at DESC. A single composite index covers the filter
        //    and the sort, so Postgres never needs a separate sort step or a filter
        //    on the full (event_id, item_id) index.
        $this->addIndex('fest_marks', ['event_id', 'position', 'updated_at'], 'fest_marks_recent_winners_idx');
    }

    public function down(): void
    {
        $this->dropIndex('fest_event_items', 'fest_event_items_publish_idx');
        $this->dropIndex('fest_marks', 'fest_marks_recent_winners_idx');
    }

    /** @param  list<string>  $columns */
    private function addIndex(string $table, array $columns, string $name): void
    {
        if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                $blueprint->index($columns, $name);
            });
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropIndex($name);
            });
        }
    }
};
