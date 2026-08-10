<?php

use App\Models\FestEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md §7.1 (Phase 7): root_event_id
 * resolves a program tree's season/root efficiently without walking parent_event_id at
 * query time. parent_event_id remains the *immediate* parent; root_event_id is the
 * season/program root (same value as the event's own id for a root event).
 *
 * Backfilled here via a bounded PHP loop rather than a single recursive SQL statement —
 * this app supports both MySQL and SQLite (tests run on SQLite, per phpunit.xml), and a
 * portable recursive CTE across both engines' supported versions isn't guaranteed, while
 * the topology here is only ever 2-3 levels deep (season -> sport -> region at most), so
 * FestEvent::rootEvent() (added in Phase 2) resolves each row in a handful of queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_events', 'root_event_id')) {
                $table->unsignedBigInteger('root_event_id')->nullable()->after('parent_event_id')->index();
            }
        });

        FestEvent::query()->select(['id', 'parent_event_id'])->chunkById(500, function ($events) {
            foreach ($events as $event) {
                if ($event->parent_event_id === null) {
                    $event->newQuery()->whereKey($event->id)->update(['root_event_id' => $event->id]);

                    continue;
                }

                $root = $event->rootEvent();
                $event->newQuery()->whereKey($event->id)->update(['root_event_id' => $root->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            if (Schema::hasColumn('fest_events', 'root_event_id')) {
                $table->dropColumn('root_event_id');
            }
        });
    }
};
