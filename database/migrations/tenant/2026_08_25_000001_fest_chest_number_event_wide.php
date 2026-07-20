<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reverts chest numbers to event-wide uniqueness (one chest number per
 * student/teacher per event, across every item they compete in).
 *
 * Chest numbers were briefly scoped per item-head (see
 * 2026_07_21_000001_fest_chest_per_item_head.php) back when one sports
 * FestEvent (the "season") held many disciplines, each its own head — heads
 * needed independent chest ranges. Since the Head = Event rebuild, every
 * discipline is its own standalone FestEvent, so per-head and per-event are
 * now the same scope. Any participant who still carries a distinct chest
 * number under an old head scope needs consolidating before the tighter
 * unique index can be restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_participants') || ! Schema::hasColumn('fest_participants', 'chest_head_id')) {
            return;
        }

        $this->consolidateDuplicateChestNumbers();
        $this->resolveCrossStudentCollisions();

        DB::table('fest_participants')->where('chest_head_id', '!=', 0)->update(['chest_head_id' => 0]);

        if (Schema::hasIndex('fest_participants', 'fest_participants_event_head_chest_unique')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                $table->dropUnique('fest_participants_event_head_chest_unique');
            });
        }

        if (! Schema::hasIndex('fest_participants', 'fest_participants_event_chest_unique')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                $table->unique(['event_id', 'chest_no'], 'fest_participants_event_chest_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_participants')) {
            return;
        }

        if (Schema::hasIndex('fest_participants', 'fest_participants_event_chest_unique')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                $table->dropUnique('fest_participants_event_chest_unique');
            });
        }

        if (Schema::hasColumn('fest_participants', 'chest_head_id')
            && ! Schema::hasIndex('fest_participants', 'fest_participants_event_head_chest_unique')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                $table->unique(['event_id', 'chest_head_id', 'chest_no'], 'fest_participants_event_head_chest_unique');
            });
        }
    }

    /**
     * Only one participant row per (event, student/teacher, old head scope)
     * ever had chest_no physically written — siblings resolved it via lookup
     * (see FestNumberingService::effectiveChestNumber()). A student who
     * competed across different head scopes within one sports event can
     * therefore hold more than one persisted chest_no. Keep the lowest
     * number, null out the rest so exactly one row stores it going forward.
     */
    private function consolidateDuplicateChestNumbers(): void
    {
        $rows = DB::table('fest_participants')
            ->whereNotNull('chest_no')
            ->whereNotNull('event_id')
            ->select('id', 'event_id', 'student_id', 'teacher_id', 'chest_no')
            ->orderBy('id')
            ->get();

        $groups = $rows->groupBy(function ($r) {
            $who = $r->student_id ? 's'.$r->student_id : 't'.($r->teacher_id ?? $r->id);

            return $r->event_id.':'.$who;
        });

        foreach ($groups as $group) {
            $distinct = $group->pluck('chest_no')->unique();
            if ($distinct->count() < 2) {
                continue;
            }

            $canonical = $distinct->min();
            $idsToClear = $group->where('chest_no', '!=', $canonical)->pluck('id');

            if ($idsToClear->isNotEmpty()) {
                DB::table('fest_participants')->whereIn('id', $idsToClear)->update(['chest_no' => null]);
            }
        }
    }

    /**
     * After consolidation, two different students could legitimately now want
     * the same chest_no in the same event (previously kept apart by different
     * head scopes). Renumber the later-created one to the next free number
     * for that event.
     */
    private function resolveCrossStudentCollisions(): void
    {
        $rows = DB::table('fest_participants')
            ->whereNotNull('chest_no')
            ->select('id', 'event_id', 'chest_no')
            ->orderBy('event_id')
            ->orderBy('chest_no')
            ->orderBy('id')
            ->get()
            ->groupBy('event_id');

        foreach ($rows as $eventRows) {
            $seen = [];
            $max = (int) $eventRows->max('chest_no');

            foreach ($eventRows as $row) {
                if (isset($seen[$row->chest_no])) {
                    $max++;
                    DB::table('fest_participants')->where('id', $row->id)->update(['chest_no' => $max]);
                    $seen[$max] = true;
                } else {
                    $seen[$row->chest_no] = true;
                }
            }
        }
    }
};
